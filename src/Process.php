<?php
namespace PWH;
use FFI;
use RuntimeException;
class Process
{
	public Module $module;

	/**
	 * @param string $name
	 * @param int $desired_access
	 * @throws RuntimeException if the process isn't open
	 */
	function __construct(string $name, int $desired_access = Kernel32::PROCESS_CREATE_THREAD | Kernel32::PROCESS_QUERY_LIMITED_INFORMATION | Kernel32::PROCESS_VM_OPERATION | Kernel32::PROCESS_VM_READ | Kernel32::PROCESS_VM_WRITE | Kernel32::SYNCHRONIZE)
	{
		$process_id = self::getProcessId($name);
		if($process_id == -1)
		{
			throw new RuntimeException("$name isn't open");
		}
		$this->module = new Module(Kernel32::OpenProcess($process_id, $desired_access), $name);
	}

	/** @noinspection PhpUndefinedFieldInspection */
	static function getProcessId(string $process_name) : int
	{
		$process_snapshot = Kernel32::CreateToolhelp32Snapshot(Kernel32::TH32CS_SNAPPROCESS, 0);
		if(!$process_snapshot->isValid())
		{
			throw new Kernel32Exception("Failed to get process snapshot");
		}
		$process_entry = Kernel32::$ffi->new("PROCESSENTRY32");
		$process_entry->dwSize = FFI::sizeof($process_entry);
		if(!Kernel32::Process32First($process_snapshot, $process_entry))
		{
			throw new Kernel32Exception("Failed to get process list");
		}
		do
		{
			if(FFI::string($process_entry->szExeFile) == $process_name)
			{
				return $process_entry->th32ProcessID;
			}
		}
		while(Kernel32::Process32Next($process_snapshot, $process_entry));
		return -1;
	}

	/** @noinspection PhpUndefinedFieldInspection */
	static function getProcessList() : array
	{
		$list = [];
		$process_snapshot = Kernel32::CreateToolhelp32Snapshot(Kernel32::TH32CS_SNAPPROCESS, 0);
		if(!$process_snapshot->isValid())
		{
			throw new Kernel32Exception("Failed to get process snapshot");
		}
		$process_entry = Kernel32::$ffi->new("PROCESSENTRY32");
		$process_entry->dwSize = FFI::sizeof($process_entry);
		if(!Kernel32::Process32First($process_snapshot, $process_entry))
		{
			throw new Kernel32Exception("Failed to get process list");
		}
		do
		{
			array_push($list, FFI::string($process_entry->szExeFile));
		}
		while(Kernel32::Process32Next($process_snapshot, $process_entry));
		return $list;
	}

	function getModule(string $module) : Module
	{
		return $module == $this->module->name ? $this->module : new Module($this->module->processHandle, $module);
	}
}
