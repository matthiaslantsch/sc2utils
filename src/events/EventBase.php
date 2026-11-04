<?php
/**
 * This file is part of the sc2rep replay parser project
 * (c) Matthias Lantsch.
 *
 * class file for the abstract Event base class
 */

namespace HIS5\lib\Sc2repParser\events;

/**
 * The abstract Event base class is used as a base class for all game events.
 *
 * @author  {AUTHOR}
 */
abstract class EventBase {
	/**
	 * property containing the number of ingame engine loops at the time the event happened.
	 *
	 * @var int gameloop | gameloops number at the time the event happened
	 */
	public int $gameloop;

	/**
	 * property containing the player id of the player that caused the event.
	 *
	 * @var int playerId | player id of the player that caused the event
	 */
	public int $playerId;

	/**
	 * protected constructor accepting the gameloop count from the child classes
	 * all game events must have a gameloop counter.
	 *
	 * @param  int gameloops | gameloop counter at the time the event happened
	 * @param  int playerId | playerId of the player that caused the event
	 */
	protected function __construct($gameloops, $playerId) {
		$this->frame = $gameloops;
		$this->playerId = $playerId;
	}
}
