<?php
namespace PWH;
use FFI;
use RuntimeException;
class Process
{
	public Module $module;

	/**
	 * @param string|int $process_name_or_id
	 * @param int $desired_access
	 * @throws RuntimeException if the process isn't open
	 */
	function __construct($process_name_or_id, int $desired_access = Kernel32::PROCESS_CREATE_THREAD | Kernel32::PROCESS_QUERY_LIMITED_INFORMATION | Kernel32::PROCESS_VM_OPERATION | Kernel32::PROCESS_VM_READ | Kernel32::PROCESS_VM_WRITE | Kernel32::SYNCHRONIZE)
	{
		$process_id = -1;
		$name = null;
		if(is_int($process_name_or_id))
		{
			$process_id = $process_name_or_id;
			foreach(self::getProcessList() as $process)
			{
				if($process["process_id"] == $process_id)
				{
					$name = $process["exe_file"];
					break;
				}
			}
			if($name == null)
			{
				throw new RuntimeException("Failed to find process with id $process_id");
			}
		}
		else
		{
			$name = $process_name_or_id;
			foreach(self::getProcessList() as $process)
			{
				if($process["exe_file"] == $name)
				{
					$process_id = $process["process_id"];
					break;
				}
			}
			if($process_id == -1)
			{
				throw new RuntimeException("Failed to find process with name $name");
			}
		}
		$this->module = new Module(Kernel32::OpenProcess($process_id, $desired_access), $name);
	}

	/**
	 * Returns an array of arrays containing:
	 * - process_id
	 * - threads
	 * - parent_process_id
	 * - priority_class_base
	 * - exe_file
	 *
	 * @return array
	 * @noinspection PhpUndefinedFieldInspection
	 */
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
			array_push($list, [
				"process_id" => $process_entry->th32ProcessID,
				"threads" => $process_entry->cntThreads,
				"parent_process_id" => $process_entry->th32ParentProcessID,
				"priority_class_base" => $process_entry->pcPriClassBase,
				"exe_file" => FFI::string($process_entry->szExeFile),
			]);
		}
		while(Kernel32::Process32Next($process_snapshot, $process_entry));
		return $list;
	}

	function getModule(string $module) : Module
	{
		return $module == $this->module->name ? $this->module : new Module($this->module->processHandle, $module);
	}
}
