<?php
namespace PWH;
use FFI;
use FFI\CData;
use GMP;
use PWH\Handle\ProcessHandle;
class Pointer
{
	const nullptr = 0;

	public ProcessHandle $processHandle;
	public int $address;

	const BUFFER_SIZE = 0x10000;
	public static CData $buffer;
	private static int $buffer_address_start = 0;
	private static int $buffer_address_end = 0;

	function __construct(ProcessHandle $processHandle, int $address)
	{
		$this->processHandle = $processHandle;
		$this->address = $address;
	}

	function __toString() : string
	{
		return dechex($this->address);
	}

	function isNullptr() : bool
	{
		return $this->address == Pointer::nullptr;
	}

	function add(int $offset) : Pointer
	{
		return new Pointer($this->processHandle, $this->address + $offset);
	}

	function subtract(int $offset) : Pointer
	{
		return new Pointer($this->processHandle, $this->address - $offset);
	}

	function isBuffered(int $min_bytes = 1) : bool
	{
		return self::$buffer_address_start <= $this->address && $this->address + $min_bytes <= self::$buffer_address_end;
	}

	function buffer(int $bytes) : void
	{
		Kernel32::ReadProcessMemory($this->processHandle, $this->address, self::$buffer, $bytes);
		self::$buffer_address_start = $this->address;
		self::$buffer_address_end = $this->address + $bytes;
	}

	function ensureBuffer(int $bytes) : void
	{
		if(!$this->isBuffered($bytes))
		{
			$this->buffer($bytes);
		}
	}

	function readBinary(int $bytes) : string
	{
		$this->ensureBuffer($bytes);
		$bin_str = "";
		$i = $this->address - self::$buffer_address_start;
		$end = $i + $bytes;
		for(; $i < $end; $i++)
		{
			$bin_str .= chr(self::$buffer[$i]);
		}
		return $bin_str;
	}

	function readByte() : int
	{
		$this->ensureBuffer(1);
		return self::$buffer[$this->address - self::$buffer_address_start];
	}

	function readInt32() : int
	{
		return unpack("l", $this->readBinary(4))[1];
	}

	function readUInt32() : int
	{
		return unpack("L", $this->readBinary(4))[1];
	}

	function rip() : Pointer
	{
		return $this->add($this->readInt32())->add(4);
	}

	function readInt64() : int
	{
		return unpack("q", $this->readBinary(8))[1];
	}

	function readUInt64() : GMP
	{
		return gmp_import($this->readBinary(8), 8);
	}

	function dereference() : Pointer
	{
		return new Pointer($this->processHandle, $this->readInt64());
	}

	function readString() : string
	{
		$len = 0;
		while($this->add($len)->readByte() != 0)
		{
			$len++;
		}
		return $this->readBinary($len);
	}

	function readFloat() : float
	{
		return unpack("f", $this->readBinary(4))[1];
	}

	function writeByte(int $b) : void
	{
		self::$buffer[0] = $b;
		self::$buffer_address_start++;
		Kernel32::WriteProcessMemory($this->processHandle, $this->address, self::$buffer, 1);
	}

	function writeString(string $bin) : void
	{
		$i = 0;
		foreach(str_split($bin) as $c)
		{
			self::$buffer[$i++] = ord($c);
		}
		self::$buffer_address_start += $i;
		Kernel32::WriteProcessMemory($this->processHandle, $this->address, self::$buffer, $i);
	}

	function writeInt32(int $value) : void
	{
		$this->writeString(pack("l", $value));
	}

	function writeUInt32(int $value) : void
	{
		$this->writeString(pack("L", $value));
	}

	function writeInt64(int $value) : void
	{
		$this->writeString(pack("q", $value));
	}

	/**
	 * @param int|string|GMP $value
	 */
	function writeUInt64($value) : void
	{
		$this->writeString(gmp_export($value, 8));
	}

	function writeFloat(float $value) : void
	{
		$this->writeString(pack("f", $value));
	}
}

Pointer::$buffer = FFI::new(FFI::arrayType(FFI::type("uint8_t"), [Pointer::BUFFER_SIZE]));
