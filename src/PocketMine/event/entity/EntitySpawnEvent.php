<?php

/**
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
 * @link   http://www.pocketmine.net/
 *
 *
 */

namespace PocketMine\Event\Entity;

use PocketMine;
use PocketMine\Entity\Entity;

/**
 * Called when a entity is spawned
 */
class EntitySpawnEvent extends EntityEvent{
	public static $handlers;
	public static $handlerPriority;

	/**
	 * @param Entity $entity
	 */
	public function __construct(Entity $entity){
		$this->entity = $entity;
	}

	/**
	 * @return PocketMine\Level\Position
	 */
	public function getPosition(){
		return $this->entity->getPosition();
	}

	/**
	 * @return int
	 */
	public function getType(){
		//TODO: implement Entity types
		return -1;
	}

	/**
	 * @return bool
	 */
	public function isCreature(){
		return $this->entity instanceof PocketMine\Entity\Creature;
	}

	/**
	 * @return bool
	 */
	public function isHuman(){
		return $this->entity instanceof PocketMine\Entity\Human;
	}

	/**
	 * @return bool
	 */
	public function isProjectile(){
		return $this->entity instanceof PocketMine\Entity\Projectile;
	}

	/**
	 * @return bool
	 */
	public function isVehicle(){
		return $this->entity instanceof PocketMine\Entity\Vehicle;
	}

	/**
	 * @return bool
	 */
	public function isItem(){
		return $this->entity instanceof PocketMine\Entity\DroppedItem;
	}

}