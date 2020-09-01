<?php
namespace PWH;
use FFI;
use GMP;
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

	/**
	 * @param int|string|GMP|Pointer $function $function
	 * @param callable|null $parse_return_func
	 * @param int|string|GMP|null $arg_1
	 * @param int|string|GMP|null $arg_2
	 * @return null|
	 */
	function callFunction($function, ?callable $parse_return_func = null, $arg_1 = null, $arg_2 = null)
	{
		$asm = (new AssemblyBuilder())->beginFunction();
		if($arg_2 !== null)
		{
			$asm->setArgument2($arg_2);
		}
		$asm->callFar($function);
		$trailer = (new AssemblyBuilder())->endFunction();
		if($parse_return_func !== null)
		{
			$asm->copyRaxToEipOffset($trailer->getByteCodeLength());
		}
		$asm->append($trailer);
		$bytecode = $asm->getByteCode();
		$alloc_size = strlen($bytecode);
		if($parse_return_func !== null)
		{
			$alloc_size += 8;
		}
		$alloc = $this->allocate($alloc_size);
		$alloc->writeString($bytecode);
		$thread = Kernel32::CreateRemoteThread($this->processHandle, $alloc->address, $arg_1 ?? 0);
		Kernel32::WaitForSingleObject($thread);
		//$exit_code = FFI::new("uint32_t");
		//Kernel32::GetExitCodeThread($thread, $exit_code);
		//echo "Thread exited with code ".$exit_code->cdata."\n";
		return $parse_return_func === null ? null : $parse_return_func($alloc->add(strlen($bytecode)));
	}

	/**
	 * Calls a function in the module that accepts 0 to 2 uint64_t-compatible parameters.
	 *
	 * @param int|string|GMP|Pointer $function
	 * @param int|string|GMP|null $arg_1
	 * @param int|string|GMP|null $arg_2
	 * @return void
	 */
	function callVoidFunction($function, $arg_1 = null, $arg_2 = null) : void
	{
		$this->callFunction($function, null, $arg_1, $arg_2);
	}

	/**
	 * Calls a function in the module that accepts 0 to 2 uint64_t-compatible parameters and returns an int32_t.
	 *
	 * @param int|string|GMP|Pointer $function
	 * @param int|string|GMP|null $arg_1
	 * @param int|string|GMP|null $arg_2
	 * @return int
	 */
	function callInt32Function($function, $arg_1 = null, $arg_2 = null) : int
	{
		return $this->callFunction($function, function(Pointer $pointer) : int
		{
			return $pointer->readInt32();
		}, $arg_1, $arg_2);
	}

	/**
	 * Calls a function in the module that accepts 0 to 2 uint64_t-compatible parameters and returns a uint32_t.
	 *
	 * @param int|string|GMP|Pointer $function
	 * @param int|string|GMP|null $arg_1
	 * @param int|string|GMP|null $arg_2
	 * @return int
	 */
	function callUint32Function($function, $arg_1 = null, $arg_2 = null) : int
	{
		return $this->callFunction($function, function(Pointer $pointer) : int
		{
			return $pointer->readUInt32();
		}, $arg_1, $arg_2);
	}

	/**
	 * Calls a function in the module that accepts 0 to 2 uint64_t-compatible parameters and returns an int64_t.
	 *
	 * @param int|string|GMP|Pointer $function
	 * @param int|string|GMP|null $arg_1
	 * @param int|string|GMP|null $arg_2
	 * @return int
	 */
	function callInt64Function($function, $arg_1 = null, $arg_2 = null): int
	{
		return $this->callFunction($function, function(Pointer $pointer) : int
		{
			return $pointer->readInt64();
		}, $arg_1, $arg_2);
	}

	/**
	 * Calls a function in the module that accepts 0 to 2 uint64_t-compatible parameters and returns a uint64_t.
	 *
	 * @param int|string|GMP|Pointer $function
	 * @param int|string|GMP|null $arg_1
	 * @param int|string|GMP|null $arg_2
	 * @return GMP
	 */
	function callUInt64Function($function, $arg_1 = null, $arg_2 = null): GMP
	{
		return $this->callFunction($function, function(Pointer $pointer) : GMP
		{
			return $pointer->readUInt64();
		}, $arg_1, $arg_2);
	}

	/**
	 * Calls a function in the module that accepts 0 to 2 uint64_t-compatible parameters and returns a pointer.
	 *
	 * @param int|string|GMP|Pointer $function
	 * @param int|string|GMP|null $arg_1
	 * @param int|string|GMP|null $arg_2
	 * @return Pointer
	 */
	function callPtrFunction($function, $arg_1 = null, $arg_2 = null) : Pointer
	{
		return new Pointer($this->processHandle, $this->callInt64Function($function, $arg_1, $arg_2));
	}
}
