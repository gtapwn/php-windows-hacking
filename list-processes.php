<?php
namespace PWH;
require "vendor/autoload.php";

foreach(Process::getProcessList() as $process)
{
	echo $process["process_id"]."\t".$process["exe_file"]."\n";
}
