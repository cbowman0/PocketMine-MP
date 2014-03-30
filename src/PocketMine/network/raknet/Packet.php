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

namespace PocketMine\Network\RakNet;

use PocketMine\Network;
use PocketMine\Network\Protocol\Info as ProtocolInfo;
use PocketMine\Utils\Utils;

class Packet extends Network\Packet{
	private $packetID;
	private $offset = 1;
	public $data = array();

	public function __construct($packetID){
		$this->packetID = (int) $packetID;
	}

	public function pid(){
		return $this->packetID;
	}

	protected function get($len){
		if($len <= 0){
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

	private function getLong($unsigned = false){
		return Utils::readLong($this->get(8), $unsigned);
	}

	private function getInt(){
		return Utils::readInt($this->get(4));
	}

	private function getShort($unsigned = false){
		return Utils::readShort($this->get(2), $unsigned);
	}

	private function getLTriad(){
		return Utils::readTriad(strrev($this->get(3)));
	}

	private function getByte(){
		return ord($this->buffer{$this->offset++});
	}

	private function feof(){
		return !isset($this->buffer{$this->offset});
	}

	public function decode(){
		$this->offset = 1;
		switch($this->packetID){
			case Info::UNCONNECTED_PING:
			case Info::UNCONNECTED_PING_OPEN_CONNECTIONS:
				$this->pingID = $this->getLong();
				$this->offset += 16; //Magic
				break;
			case Info::OPEN_CONNECTION_REQUEST_1:
				$this->offset += 16; //Magic
				$this->structure = $this->getByte();
				$this->mtuSize = strlen($this->get(true));
				break;
			case Info::OPEN_CONNECTION_REQUEST_2:
				$this->offset += 16; //Magic
				$this->security = $this->get(5);
				$this->clientPort = $this->getShort(false);
				$this->mtuSize = $this->getShort(false);
				$this->clientID = $this->getLong();
				break;
			case Info::DATA_PACKET_0:
			case Info::DATA_PACKET_1:
			case Info::DATA_PACKET_2:
			case Info::DATA_PACKET_3:
			case Info::DATA_PACKET_4:
			case Info::DATA_PACKET_5:
			case Info::DATA_PACKET_6:
			case Info::DATA_PACKET_7:
			case Info::DATA_PACKET_8:
			case Info::DATA_PACKET_9:
			case Info::DATA_PACKET_A:
			case Info::DATA_PACKET_B:
			case Info::DATA_PACKET_C:
			case Info::DATA_PACKET_D:
			case Info::DATA_PACKET_E:
			case Info::DATA_PACKET_F:
				$this->seqNumber = $this->getLTriad();
				$this->data = array();
				while(!$this->feof() and $this->parseDataPacket() !== false){

				}
				break;
			case Info::NACK:
			case Info::ACK:
				$count = $this->getShort();
				$this->packets = array();
				for($i = 0; $i < $count and !$this->feof(); ++$i){
					if($this->getByte() === 0){
						$start = $this->getLTriad();
						$end = $this->getLTriad();
						if(($end - $start) > 4096){
							$end = $start + 4096;
						}
						for($c = $start; $c <= $end; ++$c){
							$this->packets[] = $c;
						}
					}else{
						$this->packets[] = $this->getLTriad();
					}
				}
				break;
			default:
				break;
		}
	}

	private function parseDataPacket(){
		$packetFlags = $this->getByte();
		$reliability = ($packetFlags & 0b11100000) >> 5;
		$hasSplit = ($packetFlags & 0b00010000) > 0;
		$length = (int) ceil($this->getShort() / 8);
		if($reliability === 2
			or $reliability === 3
			or $reliability === 4
			or $reliability === 6
			or $reliability === 7
		){
			$messageIndex = $this->getLTriad();
		}else{
			$messageIndex = false;
		}

		if($reliability === 1
			or $reliability === 3
			or $reliability === 4
			or $reliability === 7
		){
			$orderIndex = $this->getLTriad();
			$orderChannel = $this->getByte();
		}else{
			$orderIndex = false;
			$orderChannel = false;
		}

		if($hasSplit == true){
			$splitCount = $this->getInt();
			$splitID = $this->getShort();
			$splitIndex = $this->getInt();
		}else{
			$splitCount = false;
			$splitID = false;
			$splitIndex = false;
		}

		if($length <= 0
			or $orderChannel >= 32
			or ($hasSplit === true and $splitIndex >= $splitCount)
		){
			return false;
		}else{
			$pid = $this->getByte();
			$buffer = $this->get($length - 1);
			if(strlen($buffer) < ($length - 1)){
				return false;
			}
			switch($pid){
				case ProtocolInfo::PING_PACKET:
					$data = new Network\Protocol\PingPacket;
					break;
				case ProtocolInfo::PONG_PACKET:
					$data = new Network\Protocol\PongPacket;
					break;
				case ProtocolInfo::CLIENT_CONNECT_PACKET:
					$data = new Network\Protocol\ClientConnectPacket;
					break;
				case ProtocolInfo::SERVER_HANDSHAKE_PACKET:
					$data = new Network\Protocol\ServerHandshakePacket;
					break;
				case ProtocolInfo::DISCONNECT_PACKET:
					$data = new Network\Protocol\DisconnectPacket;
					break;
				case ProtocolInfo::LOGIN_PACKET:
					$data = new Network\Protocol\LoginPacket;
					break;
				case ProtocolInfo::LOGIN_STATUS_PACKET:
					$data = new Network\Protocol\LoginStatusPacket;
					break;
				case ProtocolInfo::READY_PACKET:
					$data = new Network\Protocol\ReadyPacket;
					break;
				case ProtocolInfo::MESSAGE_PACKET:
					$data = new Network\Protocol\MessagePacket;
					break;
				case ProtocolInfo::SET_TIME_PACKET:
					$data = new Network\Protocol\SetTimePacket;
					break;
				case ProtocolInfo::START_GAME_PACKET:
					$data = new Network\Protocol\StartGamePacket;
					break;
				case ProtocolInfo::ADD_MOB_PACKET:
					$data = new Network\Protocol\AddMobPacket;
					break;
				case ProtocolInfo::ADD_PLAYER_PACKET:
					$data = new Network\Protocol\AddPlayerPacket;
					break;
				case ProtocolInfo::REMOVE_PLAYER_PACKET:
					$data = new Network\Protocol\RemovePlayerPacket;
					break;
				case ProtocolInfo::ADD_ENTITY_PACKET:
					$data = new Network\Protocol\AddEntityPacket;
					break;
				case ProtocolInfo::REMOVE_ENTITY_PACKET:
					$data = new Network\Protocol\RemoveEntityPacket;
					break;
				case ProtocolInfo::ADD_ITEM_ENTITY_PACKET:
					$data = new Network\Protocol\AddItemEntityPacket;
					break;
				case ProtocolInfo::TAKE_ITEM_ENTITY_PACKET:
					$data = new Network\Protocol\TakeItemEntityPacket;
					break;
				case ProtocolInfo::MOVE_ENTITY_PACKET:
					$data = new Network\Protocol\MoveEntityPacket;
					break;
				case ProtocolInfo::MOVE_ENTITY_PACKET_POSROT:
					$data = new Network\Protocol\MoveEntityPacket_PosRot;
					break;
				case ProtocolInfo::ROTATE_HEAD_PACKET:
					$data = new Network\Protocol\RotateHeadPacket;
					break;
				case ProtocolInfo::MOVE_PLAYER_PACKET:
					$data = new Network\Protocol\MovePlayerPacket;
					break;
				case ProtocolInfo::REMOVE_BLOCK_PACKET:
					$data = new Network\Protocol\RemoveBlockPacket;
					break;
				case ProtocolInfo::UPDATE_BLOCK_PACKET:
					$data = new Network\Protocol\UpdateBlockPacket;
					break;
				case ProtocolInfo::ADD_PAINTING_PACKET:
					$data = new Network\Protocol\AddPaintingPacket;
					break;
				case ProtocolInfo::EXPLODE_PACKET:
					$data = new Network\Protocol\ExplodePacket;
					break;
				case ProtocolInfo::LEVEL_EVENT_PACKET:
					$data = new Network\Protocol\LevelEventPacket;
					break;
				case ProtocolInfo::TILE_EVENT_PACKET:
					$data = new Network\Protocol\TileEventPacket;
					break;
				case ProtocolInfo::ENTITY_EVENT_PACKET:
					$data = new Network\Protocol\EntityEventPacket;
					break;
				case ProtocolInfo::REQUEST_CHUNK_PACKET:
					$data = new Network\Protocol\RequestChunkPacket;
					break;
				case ProtocolInfo::CHUNK_DATA_PACKET:
					$data = new Network\Protocol\ChunkDataPacket;
					break;
				case ProtocolInfo::PLAYER_EQUIPMENT_PACKET:
					$data = new Network\Protocol\PlayerEquipmentPacket;
					break;
				case ProtocolInfo::PLAYER_ARMOR_EQUIPMENT_PACKET:
					$data = new Network\Protocol\PlayerArmorEquipmentPacket;
					break;
				case ProtocolInfo::INTERACT_PACKET:
					$data = new Network\Protocol\InteractPacket;
					break;
				case ProtocolInfo::USE_ITEM_PACKET:
					$data = new Network\Protocol\UseItemPacket;
					break;
				case ProtocolInfo::PLAYER_ACTION_PACKET:
					$data = new Network\Protocol\PlayerActionPacket;
					break;
				case ProtocolInfo::HURT_ARMOR_PACKET:
					$data = new Network\Protocol\HurtArmorPacket;
					break;
				case ProtocolInfo::SET_ENTITY_DATA_PACKET:
					$data = new Network\Protocol\SetEntityDataPacket;
					break;
				case ProtocolInfo::SET_ENTITY_MOTION_PACKET:
					$data = new Network\Protocol\SetEntityMotionPacket;
					break;
				case ProtocolInfo::SET_HEALTH_PACKET:
					$data = new Network\Protocol\SetHealthPacket;
					break;
				case ProtocolInfo::SET_SPAWN_POSITION_PACKET:
					$data = new Network\Protocol\SetSpawnPositionPacket;
					break;
				case ProtocolInfo::ANIMATE_PACKET:
					$data = new Network\Protocol\AnimatePacket;
					break;
				case ProtocolInfo::RESPAWN_PACKET:
					$data = new Network\Protocol\RespawnPacket;
					break;
				case ProtocolInfo::SEND_INVENTORY_PACKET:
					$data = new Network\Protocol\SendInventoryPacket;
					break;
				case ProtocolInfo::DROP_ITEM_PACKET:
					$data = new Network\Protocol\DropItemPacket;
					break;
				case ProtocolInfo::CONTAINER_OPEN_PACKET:
					$data = new Network\Protocol\ContainerOpenPacket;
					break;
				case ProtocolInfo::CONTAINER_CLOSE_PACKET:
					$data = new Network\Protocol\ContainerClosePacket;
					break;
				case ProtocolInfo::CONTAINER_SET_SLOT_PACKET:
					$data = new Network\Protocol\ContainerSetSlotPacket;
					break;
				case ProtocolInfo::CONTAINER_SET_DATA_PACKET:
					$data = new Network\Protocol\ContainerSetDataPacket;
					break;
				case ProtocolInfo::CONTAINER_SET_CONTENT_PACKET:
					$data = new Network\Protocol\ContainerSetContentPacket;
					break;
				case ProtocolInfo::CHAT_PACKET:
					$data = new Network\Protocol\ChatPacket;
					break;
				case ProtocolInfo::ADVENTURE_SETTINGS_PACKET:
					$data = new Network\Protocol\AdventureSettingsPacket;
					break;
				case ProtocolInfo::ENTITY_DATA_PACKET:
					$data = new Network\Protocol\EntityDataPacket;
					break;
				default:
					$data = new Network\Protocol\UnknownPacket();
					$data->packetID = $pid;
					break;
			}
			$data->reliability = $reliability;
			$data->hasSplit = $hasSplit;
			$data->messageIndex = $messageIndex;
			$data->orderIndex = $orderIndex;
			$data->orderChannel = $orderChannel;
			$data->splitCount = $splitCount;
			$data->splitID = $splitID;
			$data->splitIndex = $splitIndex;
			$data->setBuffer($buffer);
			$this->data[] = $data;
		}

		return true;
	}

	public function encode(){
		if(strlen($this->buffer) > 0){
			return;
		}
		$this->buffer = chr($this->packetID);

		switch($this->packetID){
			case Info::OPEN_CONNECTION_REPLY_1:
				$this->buffer .= Info::MAGIC;
				$this->putLong($this->serverID);
				$this->putByte(0); //server security
				$this->putShort($this->mtuSize);
				break;
			case Info::OPEN_CONNECTION_REPLY_2:
				$this->buffer .= Info::MAGIC;
				$this->putLong($this->serverID);
				$this->putShort($this->serverPort);
				$this->putShort($this->mtuSize);
				$this->putByte(0); //Server security
				break;
			case Info::INCOMPATIBLE_PROTOCOL_VERSION:
				$this->putByte(Info::STRUCTURE);
				$this->buffer .= Info::MAGIC;
				$this->putLong($this->serverID);
				break;
			case Info::UNCONNECTED_PONG:
			case Info::ADVERTISE_SYSTEM:
				$this->putLong($this->pingID);
				$this->putLong($this->serverID);
				$this->buffer .= Info::MAGIC;
				$this->putString($this->serverType);
				break;
			case Info::DATA_PACKET_0:
			case Info::DATA_PACKET_1:
			case Info::DATA_PACKET_2:
			case Info::DATA_PACKET_3:
			case Info::DATA_PACKET_4:
			case Info::DATA_PACKET_5:
			case Info::DATA_PACKET_6:
			case Info::DATA_PACKET_7:
			case Info::DATA_PACKET_8:
			case Info::DATA_PACKET_9:
			case Info::DATA_PACKET_A:
			case Info::DATA_PACKET_B:
			case Info::DATA_PACKET_C:
			case Info::DATA_PACKET_D:
			case Info::DATA_PACKET_E:
			case Info::DATA_PACKET_F:
				$this->putLTriad($this->seqNumber);
				foreach($this->data as $pk){
					$this->encodeDataPacket($pk);
				}
				break;
			case Info::NACK:
			case Info::ACK:
				$payload = "";
				$records = 0;
				$pointer = 0;
				sort($this->packets, SORT_NUMERIC);
				$max = count($this->packets);

				while($pointer < $max){
					$type = true;
					$curr = $start = $this->packets[$pointer];
					for($i = $start + 1; $i < $max; ++$i){
						$n = $this->packets[$i];
						if(($n - $curr) === 1){
							$curr = $end = $n;
							$type = false;
							$pointer = $i + 1;
						}else{
							break;
						}
					}
					++$pointer;
					if($type === false){
						$payload .= "\x00";
						$payload .= strrev(Utils::writeTriad($start));
						$payload .= strrev(Utils::writeTriad($end));
					}else{
						$payload .= Utils::writeBool(true);
						$payload .= strrev(Utils::writeTriad($start));
					}
					++$records;
				}
				$this->putShort($records);
				$this->buffer .= $payload;
				break;
			default:

		}

	}

	private function encodeDataPacket(Network\Protocol\DataPacket $pk){
		$this->putByte(($pk->reliability << 5) | ($pk->hasSplit > 0 ? 0b00010000 : 0));
		$this->putShort(strlen($pk->buffer) << 3);
		if($pk->reliability === 2
			or $pk->reliability === 3
			or $pk->reliability === 4
			or $pk->reliability === 6
			or $pk->reliability === 7
		){
			$this->putLTriad($pk->messageIndex);
		}

		if($pk->reliability === 1
			or $pk->reliability === 3
			or $pk->reliability === 4
			or $pk->reliability === 7
		){
			$this->putLTriad($pk->orderIndex);
			$this->putByte($pk->orderChannel);
		}

		if($pk->hasSplit === true){
			$this->putInt($pk->splitCount);
			$this->putShort($pk->splitID);
			$this->putInt($pk->splitIndex);
		}

		$this->buffer .= $pk->buffer;
	}

	protected function put($str){
		$this->buffer .= $str;
	}

	protected function putLong($v){
		$this->buffer .= Utils::writeLong($v);
	}

	protected function putInt($v){
		$this->buffer .= Utils::writeInt($v);
	}

	protected function putShort($v){
		$this->buffer .= Utils::writeShort($v);
	}

	protected function putTriad($v){
		$this->buffer .= Utils::writeTriad($v);
	}

	protected function putLTriad($v){
		$this->buffer .= strrev(Utils::writeTriad($v));
	}

	protected function putByte($v){
		$this->buffer .= chr($v);
	}

	protected function putString($v){
		$this->putShort(strlen($v));
		$this->put($v);
	}

	public function __destruct(){
	}
}