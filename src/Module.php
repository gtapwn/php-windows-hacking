<?php
namespace PWH;
use FFI;
use PWH\Handle\ProcessHandle;
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

	function callFunction(int $function_address, bool $return_value = false, ?int $arg_1 = null, ?int $arg_2 = null): ?int
	{
		$asm = (new AssemblyBuilder())->beginFunction();
		if(is_int($arg_1))
		{
			$asm->setArgument1($arg_1);
			if(is_int($arg_2))
			{
				$asm->setArgument2($arg_2);
			}
		}
		$asm->callFar($function_address);
		$trailer = (new AssemblyBuilder())->endFunction();
		if($return_value)
		{
			$asm->copyRaxToEipOffset(strlen($trailer->getByteCode()));
		}
		$asm->append($trailer);
		$bytecode = $asm->getByteCode();
		$alloc_size = strlen($bytecode);
		if($return_value)
		{
			$alloc_size += 8;
		}
		$alloc = $this->allocate($alloc_size);
		$alloc->writeString($bytecode);
		$thread = Kernel32::CreateRemoteThread($this->processHandle, $alloc->address);
		Kernel32::WaitForSingleObject($thread);
		//$exit_code = FFI::new("uint32_t");
		//Kernel32::GetExitCodeThread($thread, $exit_code);
		//echo "Thread exited with code ".$exit_code->cdata."\n";
		return $return_value ? $alloc->add(strlen($bytecode))->readUInt64() : null;
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
		$this->callFunction($function_address, false, $arg_1, $arg_2);
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
		return $this->callFunction($function_address, true, $arg_1, $arg_2) & 0xFFFFFFFF;
	}

	/**
	 * Calls a function in the module that accepts 0 to 2 uint64_t-compatible parameters and returns a uint64_t.
	 *
	 * @param int $function_address
	 * @param null|int $arg_1
	 * @param null|int $arg_2
	 * @return int
	 */
	function callUInt64Function(int $function_address, ?int $arg_1 = null, ?int $arg_2 = null): int
	{
		return $this->callFunction($function_address, true, $arg_1, $arg_2);
	}

	/**
	 * Calls a function in the module that accepts 0 to 2 uint64_t-compatible parameters and returns a pointer.
	 *
	 * @param int $function_address
	 * @param int|null $arg_1
	 * @param int|null $arg_2
	 * @return Pointer
	 */
	function callPtrFunction(int $function_address, ?int $arg_1 = null, ?int $arg_2 = null) : Pointer
	{
		return new Pointer($this->processHandle, $this->callUInt64Function($function_address, $arg_1, $arg_2));
	}
}
