<?php
namespace PWH;
use GMP;
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
			if($hex != "")
			{
				$bin .= chr(hexdec($hex));
			}
		}
		return $bin;
	}

	function getByteCodeLength() : int
	{
		return strlen($this->hex) / 3;
	}

	function append(AssemblyBuilder $trailer) : AssemblyBuilder
	{
		$this->hex .= $trailer->hex;
		$this->asm .= $trailer->asm;
		return $this;
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

	/**
	 * @param int|string|GMP|Pointer $arg_1
	 * @return AssemblyBuilder
	 */
	function setArgument1($arg_1) : AssemblyBuilder
	{
		$arg_1 = Pointer::addr($arg_1);
		return $this->addInstruction(
			"48 B9 ".Util::binaryStringToHexString(gmp_export($arg_1, 8)),
			"mov     rcx, $arg_1 ; argument 1"
		);
	}

	/**
	 * @param int|string|GMP|Pointer $arg_2
	 * @return AssemblyBuilder
	 */
	function setArgument2($arg_2) : AssemblyBuilder
	{
		$arg_2 = Pointer::addr($arg_2);
		return $this->addInstruction(
			"48 BA ".Util::binaryStringToHexString(gmp_export($arg_2, 8)),
			"mov     rdx, $arg_2 ; argument 2"
		);
	}

	/**
	 * @param int|string|GMP|Pointer $function
	 * @return AssemblyBuilder
	 */
	function callFar($function) : AssemblyBuilder
	{
		$function_address = Pointer::addr($function);
		return $this->addInstruction(
			"48 B8 ".Util::binaryStringToHexString(gmp_export($function_address, 8)),
			"mov     rax, $function_address ; function address"
		)->addInstruction("FF D0", "call    rax");
	}

	function copyRaxToEipOffset(int $offset) : AssemblyBuilder
	{
		return $this->addInstruction("48 89 05 ".Util::binaryStringToHexString(pack("l", $offset)), "mov     eip+$offset, rax");
	}
}
