<?php
/**
 * This file is part of the sc2rep replay parser project
 * (c) Matthias Lantsch.
 *
 * class file for the CommandEvent logic class
 */

namespace HIS5\lib\Sc2repParser\events;

/**
 * A CommandEvent is fired whenever a player issues a command to a unit/a couple of units
 * this includes: Move and Attack Move, Abilities, Train commands
 * Note that an event is getting recorded even if the command was unsuccessfull
 * Has subclasses for commands that have target location/target unit.
 *
 * @author  {AUTHOR}
 */
class CommandEvent extends EventBase {
	/**
	 * property containing additional data for that ability.
	 *
	 * @var mixed abilityData | additional data for the ability
	 */
	public mixed $abilityData;

	/**
	 * property containing the unique identifier of the used ability (null if none).
	 *
	 * @var int abilityId | unique ability identifier
	 */
	public int $abilityId;

	/**
	 * property containing flags describing the the command event.
	 *
	 * @var array flags | associative array with flags parsed from that flags bitmask
	 */
	public array $flags;

	/**
	 * property containing the id for the other unit.
	 *
	 * @var int otherUnitId | id of the other unit
	 */
	public int $otherUnitId;

	/**
	 * constructor accepting the gameloop count.
	 *
	 * @param  int gameloops | gameloop counter at the time the event happened
	 * @param  int playerId | playerId of the player that caused the event
	 * @param  int flagBitMask | bit mask containing the options as bit flags
	 * @param  array ability | optional array with information about the ability used
	 */
	public function __construct($gameloops, $playerId, $flagBitMask, $ability = null) {
		parent::__construct($gameloops, $playerId);

		$this->flags = array(
			'alternate' => (0x1 & $flagBitMask !== 0),
			'queued' => (0x2 & $flagBitMask !== 0),
			'preempt' => (0x4 & $flagBitMask !== 0),
			'smartClick' => (0x8 & $flagBitMask !== 0),
			'smartRally' => (0x10 & $flagBitMask !== 0),
			'subgroup' => (0x20 & $flagBitMask !== 0),
			'setAutocast' => (0x40 & $flagBitMask !== 0),
			'setAutocastOn' => (0x80 & $flagBitMask !== 0),
			'user' => (0x1_00 & $flagBitMask !== 0),
			'dataPassenger' => (0x2_00 & $flagBitMask !== 0),
			'dataAbilityQueueOrderId' => (0x4_00 & $flagBitMask !== 0),
			'ai' => (0x8_00 & $flagBitMask !== 0),
			'aiIgnoreOnFinish' => (0x10_00 & $flagBitMask !== 0),
			'isOrder' => (0x20_00 & $flagBitMask !== 0),
			'script' => (0x40_00 & $flagBitMask !== 0),
			'homogenousInterruption' => (0x80_00 & $flagBitMask !== 0),
			'minimap' => (0x1_00_00 & $flagBitMask !== 0),
			'repeat' => (0x2_00_00 & $flagBitMask !== 0),
			'dispatchToOtherUnit' => (0x4_00_00 & $flagBitMask !== 0),
			'targetSelf' => (0x8_00_00 & $flagBitMask !== 0)
		);

		if ($ability !== null) {
			$this->abilityId = $ability['abilityLink'] << 5 | $ability['commandIndex'];
			$this->abilityData = $ability['commandData'];
		}
	}
}
