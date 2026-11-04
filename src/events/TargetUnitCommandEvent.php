<?php
/**
 * This file is part of the sc2rep replay parser project
 * (c) Matthias Lantsch.
 *
 * class file for the TargetUnitCommandEvent logic class
 */

namespace HIS5\lib\Sc2repParser\events;

/**
 * TargetUnitCommandEvent is an event generated for player commands that targets a unit.
 *
 * @author  {AUTHOR}
 */
class TargetUnitCommandEvent extends TargetPointCommandEvent {
	/**
	 * property containing the player id of the controlling player.
	 *
	 * @var int controlPlayerId | player id of the player controlling the unit
	 */
	public int $controlPlayerId;

	/**
	 * property containing the flags to be applied to the target.
	 *
	 * @var int targetFlags | flags applied to the target unit
	 */
	public int $targetFlags;

	/**
	 * property containing a target timer.
	 *
	 * @var int targetTimer | timer on the target
	 */
	public int $targetTimer;

	/**
	 * property containing the unique identifier of the target unit
	 * is 0 if the unit is in the fog of war.
	 *
	 * @var int targetUnitId | unique identifier of the target unit
	 */
	public int $targetUnitId;

	/**
	 * property containing the player id of the player paying upkeep.
	 *
	 * @var int upkeepPlayerId | player id of the player paying upkeep
	 */
	public int $upkeepPlayerId;
}
