<?php
namespace PWH\Handle;
use LogicException;
use PWH\Kernel32;
class ObjectHandle extends Handle
{
	function __construct(int $handle)
	{
		parent::__construct($handle);
	}

	function __clone()
	{
		if($this->isValid())
		{
			throw new LogicException("Cannot clone ObjectHandle due to destructor");
		}
	}

	function __destruct()
	{
		if($this->isValid())
		{
			Kernel32::CloseHandle($this);
		}
	}
}
