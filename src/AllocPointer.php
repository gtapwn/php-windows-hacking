<?php
namespace PWH;
use LogicException;
use PWH\Handle\ProcessHandle;
class AllocPointer extends Pointer
{
	public int $alloc_size;

	function __construct(ProcessHandle $processHandle, int $address, int $alloc_size)
	{
		parent::__construct($processHandle, $address);
		$this->alloc_size = $alloc_size;
	}

	function __clone()
	{
		if(!$this->isNullptr())
		{
			throw new LogicException("Cannot clone AllocPointer due to destructor");
		}
	}

	function __destruct()
	{
		if(!$this->isNullptr())
		{
			try
			{
				Kernel32::VirtualFreeEx($this, $this->alloc_size, Kernel32::MEM_DECOMMIT);
			}
			catch(Kernel32Exception $ex)
			{
				echo $ex->getMessage()."\n".$ex->getTraceAsString()."\n";
			}
		}
	}
}
