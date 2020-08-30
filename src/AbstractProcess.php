<?php
namespace PWH;
abstract class AbstractProcess extends Process
{
	private array $pattern_scan_results_cache = [];
	private array $pattern_scan_results = [];

	abstract function getPatternResultsCacheJsonFilePath() : string;
	abstract function getUniqueVersionAndEditionName() : string;

	function initPatternScanResultsCache() : void
	{
		$version_and_edition_name = $this->getUniqueVersionAndEditionName();
		if(file_exists($this->getPatternResultsCacheJsonFilePath()))
		{
			$this->pattern_scan_results_cache = json_decode(file_get_contents($this->getPatternResultsCacheJsonFilePath()), true);
			if(@$this->pattern_scan_results_cache["__version_and_edition"] === $version_and_edition_name)
			{
				echo "Edition and Online Version match cache, so we're using cached offsets!\n";
				foreach($this->pattern_scan_results_cache as $pattern_name => $offset)
				{
					if(substr($pattern_name, 0, 2) != "__")
					{
						$this->pattern_scan_results[$pattern_name] = $this->module->base->add($this->pattern_scan_results_cache[$pattern_name]);
					}
				}
				return;
			}
		}
		$this->pattern_scan_results_cache = [
			"__version_and_edition" => $version_and_edition_name,
		];
	}

	function getPatternScanResult(string $pattern_name, callable $get_pattern_func, ?callable $process_pointer_func = null) : Pointer
	{
		if(!array_key_exists($pattern_name, $this->pattern_scan_results))
		{
			echo "Looking for {$pattern_name}... ";
			$pattern = $get_pattern_func();
			assert($pattern instanceof Pattern);
			$this->pattern_scan_results[$pattern_name] = $pattern->scan($this->module);
			if(!$this->pattern_scan_results[$pattern_name] instanceof Pointer)
			{
				die("Pattern not found. :(\n");
			}
			if(is_callable($process_pointer_func))
			{
				$this->pattern_scan_results[$pattern_name] = $process_pointer_func($this->pattern_scan_results[$pattern_name]);
			}
			$offset = $this->module->getOffsetTo($this->pattern_scan_results[$pattern_name]);
			echo "Found at ".$this->module->name."+".dechex($offset)." (".$this->pattern_scan_results[$pattern_name].")";
			if(count($this->pattern_scan_results_cache) > 0)
			{
				$this->pattern_scan_results_cache[$pattern_name] = $offset;
				file_put_contents($this->getPatternResultsCacheJsonFilePath(), json_encode($this->pattern_scan_results_cache));
			}
			echo "\n";
		}
		return $this->pattern_scan_results[$pattern_name];
	}
}
