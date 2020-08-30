<?php
namespace PWH;
require "vendor/autoload.php";

$LoadLibraryA_fp = Kernel32::GetProcAddress(Kernel32::GetModuleHandleA("kernel32.dll"), "LoadLibraryA");
if ($LoadLibraryA_fp == Pointer::nullptr)
{
	die("Failed to find LoadLibraryA.\n");
}

if(empty($argv[2]))
{
	die(/** @lang text */ "Syntax: php inject.php <process name> <dll path>\n");
}

$process = new Process($argv[1], Kernel32::PROCESS_CREATE_THREAD | Kernel32::PROCESS_VM_OPERATION | Kernel32::PROCESS_VM_READ | Kernel32::PROCESS_VM_WRITE);
$parameter = $process->allocate(strlen($argv[2]));
$parameter->writeString($argv[2]);
Kernel32::CreateRemoteThread($process->module->processHandle, $LoadLibraryA_fp, $parameter);
echo "Successfully injected.\n";
