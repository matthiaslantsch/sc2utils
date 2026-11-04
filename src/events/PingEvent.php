<?php
/**
 * This file is part of the sc2rep replay parser project
 * (c) Matthias Lantsch.
 *
 * class file for the PingEvent logic class
 */

namespace HIS5\lib\Sc2repParser\events;

/**
 * PingEvent is an event generated for player pings.
 *
 * @author  {AUTHOR}
 */
class PingEvent extends MessageEvent {
	/**
	 * property containing the ping location on the map.
	 *
	 * @var array location | array with the location with the x and the y index
	 */
	public array $location;

	/**
	 * constructor accepting the gameloop count.
	 *
	 * @param  int gameloops | gameloop counter at the time the event happened
	 * @param  int playerId | playerId of the player that caused the event
	 * @param  int recipient | integer describing the recipient of the message
	 * @param  array location | array with the location with the x and the y index
	 */
	public function __construct($gameloops, $playerId, $recipient, $location) {
		parent::__construct($gameloops, $playerId, $recipient);

		$this->location = $location;
	}
}
