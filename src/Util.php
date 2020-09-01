<?php
namespace PWH;
class Util
{
	static function binaryStringToHexString(string $bin)
	{
		$hex = "";
		foreach(str_split($bin) as $c)
		{
			$hex .= strtoupper(str_pad(dechex(ord($c)), 2, "0", STR_PAD_LEFT))." ";
		}
		return rtrim($hex, " ");
	}
}
