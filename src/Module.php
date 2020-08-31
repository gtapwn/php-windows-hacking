<?php
namespace PWH;
use FFI;
use PWH\Handle\ProcessHandle;
use RuntimeException;
class Module
{
	public ProcessHandle $processHandle;
	public string $name;
	public Pointer $base;
	public int $size;
	public string $path;

	/** @noinspection PhpUndefinedFieldInspection */
	function __construct(ProcessHandle $processHandle, string $name)
	{
		$this->processHandle = $processHandle;
		$this->name = $name;
		$module_snapshot = Kernel32::CreateToolhelp32Snapshot(Kernel32::TH32CS_SNAPMODULE | Kernel32::TH32CS_SNAPMODULE32, $processHandle->process_id);
		if(!$module_snapshot->isValid())
		{
			throw new Kernel32Exception("Failed to create module snapshot");
		}
		$module_entry = Kernel32::$ffi->new("MODULEENTRY32");
		$module_entry->dwSize = FFI::sizeof($module_entry);
		if(!Kernel32::Module32First($module_snapshot, $module_entry))
		{
			throw new Kernel32Exception("Failed to get module list");
		}
		do
		{
			if(FFI::string($module_entry->szModule) == $name)
			{
				$this->base = new Pointer($processHandle, $module_entry->modBaseAddr);
				$this->size = $module_entry->modBaseSize;
				$this->path = FFI::string($module_entry->szExePath);
				break;
			}
		}
		while(Kernel32::Module32Next($module_snapshot, $module_entry));
	}

	function isValid() : bool
	{
		return $this->base instanceof Pointer;
	}

	function getOffsetTo(Pointer $pointer) : int
	{
		return $pointer->address - $this->base->address;
	}

	function allocate(int $bytes) : AllocPointer
	{
		return Kernel32::VirtualAllocEx($this->processHandle, $bytes);
	}

	/**
	 * Calls a function in the module that matches void*(*)(uint64_t, uint64_t)
	 * However, the resulting pointer should point somewhere between the module base and (modBase + pow(2, 32)) as the method of inter process communication that is the thread exit code can only hold 32 bits.
	 *
	 * @param int $function_address
	 * @param int|null $arg_1
	 * @param int|null $arg_2
	 * @return Pointer
	 */
	function callPtrFunction(int $function_address, ?int $arg_1 = null, ?int $arg_2 = null) : Pointer
	{
		$ExitThread_fp = Kernel32::GetProcAddress(Kernel32::GetModuleHandleA("kernel32.dll"), "ExitThread");
		if ($ExitThread_fp == Pointer::nullptr)
		{
			throw new RuntimeException("Failed to find ExitThread");
		}
		$dword_base = $this->base->address & 0xFFFFFFFF;
		$asm = (new AssemblyBuilder())->beginFunction()
									  ->beginFarCall($function_address);
		if(is_int($arg_1))
		{
			$asm->setArgument1($arg_1);
			if(is_int($arg_2))
			{
				$asm->setArgument2($arg_2);
			}
		}
		$bytecode = $asm->endFarCall()
						->subtractFromReturnValue($dword_base)
						->useReturnValueAsArgument1ToNextCall()
						->beginFarCall($ExitThread_fp)
						->endFarCall()
						->endFunction()
						->getByteCode();
		$alloc = $this->allocate(strlen($bytecode));
		$alloc->writeString($bytecode);
		$thread = Kernel32::CreateRemoteThread($this->processHandle, $alloc->address);
		Kernel32::WaitForSingleObject($thread);
		$exit_code = FFI::new("uint32_t");
		Kernel32::GetExitCodeThread($thread, $exit_code);
		/** @noinspection PhpUndefinedFieldInspection */
		return $this->base->add($exit_code->cdata);
	}
}
