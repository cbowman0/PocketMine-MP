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

namespace PocketMine\Network\Query;

use PocketMine;
use PocketMine\Network\Packet;
use PocketMine\Utils\Utils;

class QueryPacket extends Packet{
	const HANDSHAKE = 9;
	const STATISTICS = 0;

	public $packetType;
	public $sessionID;
	public $payload;

	public function decode(){
		$this->packetType = ord($this->buffer{2});
		$this->sessionID = Utils::readInt(substr($this->buffer, 3, 4));
		$this->payload = substr($this->buffer, 7);
	}

	public function encode(){
		$this->buffer .= chr($this->packetType);
		$this->buffer .= Utils::writeInt($this->sessionID);
		$this->buffer .= $this->payload;
	}
}