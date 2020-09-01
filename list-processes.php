<?php
namespace PWH;
require "vendor/autoload.php";

foreach(Process::getProcessList() as $process_name)
{
	echo Process::getProcessId($process_name)."\t".$process_name."\n";
}
