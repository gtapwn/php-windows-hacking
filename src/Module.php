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

	private function callFunction(int $function_address, ?int $arg_1, ?int $arg_2, int $ret): ?int
	{
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
		$asm->endFarCall();
		if($ret != 0)
		{
			$ExitThread_fp = Kernel32::GetProcAddress(Kernel32::GetModuleHandleA("kernel32.dll"), "ExitThread");
			if ($ExitThread_fp == Pointer::nullptr)
			{
				throw new RuntimeException("Failed to find ExitThread");
			}
			if($ret == 1)
			{
				$asm->subtractFromReturnValue($this->base->address & 0xFFFFFFFF);
			}
			$asm->useReturnValueAsArgument1ToNextCall()
				->beginFarCall($ExitThread_fp)
				->endFarCall();
		}
		$asm->endFunction();
		$bytecode = $asm->getByteCode();
		$alloc = $this->allocate(strlen($bytecode));
		$alloc->writeString($bytecode);
		$thread = Kernel32::CreateRemoteThread($this->processHandle, $alloc->address);
		Kernel32::WaitForSingleObject($thread);
		if($ret != 0)
		{
			$exit_code = FFI::new($ret == 3 ? "int32_t" : "uint32_t");
			Kernel32::GetExitCodeThread($thread, $exit_code);
			/** @noinspection PhpUndefinedFieldInspection */
			return $exit_code->cdata;
		}
		return null;
	}

	/**
	 * Calls a function in the module that accepts 0 to 2 uint64_t-compatible parameters.
	 *
	 * @param int $function_address
	 * @param null|int $arg_1
	 * @param null|int $arg_2
	 * @return void
	 */
	function callVoidFunction(int $function_address, ?int $arg_1 = null, ?int $arg_2 = null) : void
	{
		$this->callFunction($function_address, $arg_1, $arg_2, 0);
	}

	/**
	 * Calls a function in the module that accepts 0 to 2 uint64_t-compatible parameters and returns a pointer.
	 * Note that the resulting pointer should point somewhere between the module base and (modBase + pow(2, 32)) as the method of inter process communication that is the thread exit code can only hold 32 bits.
	 *
	 * @param int $function_address
	 * @param int|null $arg_1
	 * @param int|null $arg_2
	 * @return Pointer
	 */
	function callPtrFunction(int $function_address, ?int $arg_1 = null, ?int $arg_2 = null) : Pointer
	{
		return $this->base->add($this->callFunction($function_address, $arg_1, $arg_2, 1));
	}

	/**
	 * Calls a function in the module that accepts 0 to 2 uint64_t-compatible parameters and returns a uint32_t.
	 *
	 * @param int $function_address
	 * @param null|int $arg_1
	 * @param null|int $arg_2
	 * @return int
	 */
	function callUint32Function(int $function_address, ?int $arg_1 = null, ?int $arg_2 = null) : int
	{
		return $this->callFunction($function_address, $arg_1, $arg_2, 2);
	}

	/**
	 * Calls a function in the module that accepts 0 to 2 uint64_t-compatible parameters and returns an int32_t.
	 *
	 * @param int $function_address
	 * @param null|int $arg_1
	 * @param null|int $arg_2
	 * @return int
	 */
	function callInt32Function(int $function_address, ?int $arg_1 = null, ?int $arg_2 = null) : int
	{
		return $this->callFunction($function_address, $arg_1, $arg_2, 3);
	}
}
