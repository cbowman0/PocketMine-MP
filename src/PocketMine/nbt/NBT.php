<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____  
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \ 
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/ 
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_| 
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 * 
 *
*/

/**
 * Named Binary Tag handling classes
 */
namespace PocketMine\NBT;

use PocketMine\NBT\Tag\Byte;
use PocketMine\NBT\Tag\Byte_Array;
use PocketMine\NBT\Tag\Compound;
use PocketMine\NBT\Tag\Double;
use PocketMine\NBT\Tag\End;
use PocketMine\NBT\Tag\Enum;
use PocketMine\NBT\Tag\Float;
use PocketMine\NBT\Tag\Int;
use PocketMine\NBT\Tag\Int_Array;
use PocketMine\NBT\Tag\Long;
use PocketMine\NBT\Tag\NamedTAG;
use PocketMine\NBT\Tag\Short;
use PocketMine\NBT\Tag\String;
use PocketMine\NBT\Tag\Tag;
use PocketMine\Utils\Utils;

/**
 * Named Binary Tag encoder/decoder
 *
 * Class NBT
 * @package PocketMine\NBT
 */
class NBT{
	const LITTLE_ENDIAN = 0;
	const BIG_ENDIAN = 1;
	const TAG_End = 0;
	const TAG_Byte = 1;
	const TAG_Short = 2;
	const TAG_Int = 3;
	const TAG_Long = 4;
	const TAG_Float = 5;
	const TAG_Double = 6;
	const TAG_Byte_Array = 7;
	const TAG_String = 8;
	const TAG_Enum = 9;
	const TAG_Compound = 10;
	const TAG_Int_Array = 11;

	private $buffer;
	private $offset;
	private $endianness;
	private $data;

	public function get($len){
		if($len < 0){
			$this->offset = strlen($this->buffer) - 1;

			return "";
		}elseif($len === true){
			return substr($this->buffer, $this->offset);
		}

		$buffer = "";
		for(; $len > 0; --$len, ++$this->offset){
			$buffer .= @$this->buffer{$this->offset};
		}

		return $buffer;
	}

	public function put($v){
		$this->buffer .= $v;
	}

	public function feof(){
		return !isset($this->buffer{$this->offset});
	}

	public function __construct($endianness = self::LITTLE_ENDIAN){
		$this->offset = 0;
		$this->endianness = $endianness & 0x01;
	}

	public function read($buffer){
		$this->offset = 0;
		$this->buffer = $buffer;
		$this->data = $this->readTag();
		$this->buffer = "";
	}

	public function readCompressed($buffer){
		$this->read(\gzdecode($buffer));
	}

	public function write(){
		$this->offset = 0;
		if($this->data instanceof Compound){
			$this->writeTag($this->data);

			return $this->buffer;
		}else{
			return false;
		}
	}

	public function writeCompressed(){
		if(($write = $this->write()) !== false){
			return \gzencode($write, 9);
		}

		return false;
	}

	public function readTag(){
		switch($this->getByte()){
			case NBT::TAG_Byte:
				$tag = new Byte($this->getString());
				$tag->read($this);
				break;
			case NBT::TAG_Short:
				$tag = new Short($this->getString());
				$tag->read($this);
				break;
			case NBT::TAG_Int:
				$tag = new Int($this->getString());
				$tag->read($this);
				break;
			case NBT::TAG_Long:
				$tag = new Long($this->getString());
				$tag->read($this);
				break;
			case NBT::TAG_Float:
				$tag = new Float($this->getString());
				$tag->read($this);
				break;
			case NBT::TAG_Double:
				$tag = new Double($this->getString());
				$tag->read($this);
				break;
			case NBT::TAG_Byte_Array:
				$tag = new Byte_Array($this->getString());
				$tag->read($this);
				break;
			case NBT::TAG_String:
				$tag = new String($this->getString());
				$tag->read($this);
				break;
			case NBT::TAG_Enum:
				$tag = new Enum($this->getString());
				$tag->read($this);
				break;
			case NBT::TAG_Compound:
				$tag = new Compound($this->getString());
				$tag->read($this);
				break;
			case NBT::TAG_Int_Array:
				$tag = new Int_Array($this->getString());
				$tag->read($this);
				break;

			case NBT::TAG_End: //No named tag
			default:
				$tag = new End;
				break;
		}
		return $tag;
	}

	public function writeTag(Tag $tag){
		$this->putByte($tag->getType());
		if($tag instanceof NamedTAG){
			$this->putString($tag->getName());
		}
		$tag->write($this);
	}

	public function getByte($signed = false){
		return Utils::readByte($this->get(1), $signed);
	}

	public function putByte($v){
		$this->buffer .= Utils::writeByte($v);
	}

	public function getShort(){
		return $this->endianness === self::BIG_ENDIAN ? Utils::readShort($this->get(2)) : Utils::readLShort($this->get(2));
	}

	public function putShort($v){
		$this->buffer .= $this->endianness === self::BIG_ENDIAN ? Utils::writeShort($v) : Utils::writeLShort($v);
	}

	public function getInt(){
		return $this->endianness === self::BIG_ENDIAN ? Utils::readInt($this->get(4)) : Utils::readLInt($this->get(4));
	}

	public function putInt($v){
		$this->buffer .= $this->endianness === self::BIG_ENDIAN ? Utils::writeInt($v) : Utils::writeLInt($v);
	}

	public function getLong(){
		return $this->endianness === self::BIG_ENDIAN ? Utils::readLong($this->get(8)) : Utils::readLLong($this->get(8));
	}

	public function putLong($v){
		$this->buffer .= $this->endianness === self::BIG_ENDIAN ? Utils::writeLong($v) : Utils::writeLLong($v);
	}

	public function getFloat(){
		return $this->endianness === self::BIG_ENDIAN ? Utils::readFloat($this->get(4)) : Utils::readLFloat($this->get(4));
	}

	public function putFloat($v){
		$this->buffer .= $this->endianness === self::BIG_ENDIAN ? Utils::writeFloat($v) : Utils::writeLFloat($v);
	}

	public function getDouble(){
		return $this->endianness === self::BIG_ENDIAN ? Utils::readDouble($this->get(8)) : Utils::readLDouble($this->get(8));
	}

	public function putDouble($v){
		$this->buffer .= $this->endianness === self::BIG_ENDIAN ? Utils::writeDouble($v) : Utils::writeLDouble($v);
	}

	public function getString(){
		return $this->get($this->getShort());
	}

	public function putString($v){
		$this->putShort(strlen($v));
		$this->buffer .= $v;
	}

	public function getData(){
		return $this->data;
	}

	public function setData(Compound $data){
		$this->data = $data;
	}

}