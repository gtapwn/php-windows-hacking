<?php
namespace PWH;
class AssemblyBuilder
{
	private string $hex = "";
	private string $asm = "";

	function addInstruction(string $hex, string $asm) : AssemblyBuilder
	{
		$this->hex .= $hex."\n";
		$this->asm .= $asm."\n";
		return $this;
	}

	function getByteCode() : string
	{
		$bin = "";
		foreach(explode(" ", str_replace("\n", " ", $this->hex)) as $hex)
		{
			$bin .= chr(hexdec($hex));
		}
		return $bin;
	}

	function __toString() : string
	{
		$hex_arr = explode("\n", $this->hex);
		$pad_len = 0;
		foreach($hex_arr as $line)
		{
			$len = strlen($line);
			if($len > $pad_len)
			{
				$pad_len = $len;
			}
		}
		$pad_len += 3;
		$asm_arr = explode("\n", $this->asm);
		assert(count($asm_arr) == count($hex_arr));
		$i = 0;
		$str = "";
		foreach($hex_arr as $line)
		{
			$str .= str_pad($line, $pad_len, " ", STR_PAD_RIGHT).$asm_arr[$i++]."\n";
		}
		return $str;
	}

	function beginFunction() : AssemblyBuilder
	{
		return $this->addInstruction("55",          "push    rbp")
		            ->addInstruction("48 89 E5",    "mov     rbp, rsp")
		            ->addInstruction("48 83 EC 20", "sub     rsp, 20h");
	}

	function endFunction() : AssemblyBuilder
	{
		return $this->addInstruction("48 83 C4 20", "add     rsp, 20h")
			        ->addInstruction("5D",          "pop     rsb")
			        ->addInstruction("C3",          "retn");
	}

	private static function pack($format, int $data) : string
	{
		$hex = "";
		foreach(str_split(pack($format, $data)) as $c)
		{
			$hex .= " ".str_pad(dechex(ord($c)), 2, "0", STR_PAD_LEFT);
		}
		return strtoupper($hex);
	}

	function beginFarCall(int $function_address) : AssemblyBuilder
	{
		return $this->addInstruction("48 B8".self::pack("Q", $function_address), "mov     rax, $function_address ; function address");
	}

	function setArgument1(int $arg_1) : AssemblyBuilder
	{
		return $this->addInstruction("48 B9".self::pack("Q", $arg_1), "mov     rcx, $arg_1 ; argument 1");
	}

	function setArgument2(int $arg_2) : AssemblyBuilder
	{
		return $this->addInstruction("48 BA".self::pack("Q", $arg_2), "mov     rdx, $arg_2 ; argument 2");
	}

	function endFarCall() : AssemblyBuilder
	{
		return $this->addInstruction("FF D0", "call    rax");
	}

	function useReturnValueAsArgument1ToNextCall() : AssemblyBuilder
	{
		return $this->addInstruction("89 C1", "mov     ecx, eax ; use return value as argument 1 to next call");
	}

	function subtractFromReturnValue(int $value) : AssemblyBuilder
	{
		return $this->addInstruction("2D".self::pack("l", $value), "sub     eax, $value");
	}
}
