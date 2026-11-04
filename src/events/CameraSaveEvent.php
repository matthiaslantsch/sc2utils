<?php
/**
 * This file is part of the sc2rep replay parser project
 * (c) Matthias Lantsch.
 *
 * class file for the CameraSaveEvent logic class
 */

namespace HIS5\lib\Sc2repParser\events;

/**
 * CameraSaveEvent is an event that is being triggered when a location hotkey is being set.
 *
 * @author  {AUTHOR}
 */
class CameraSaveEvent extends EventBase {
	/**
	 * property containing the saved location on the map.
	 *
	 * @var array location | array with the location with the x and the y index
	 */
	public array $location;

	/**
	 * property containing the location hotkey number.
	 *
	 * @var string number | the camera hotkey number
	 */
	public string $number;

	/**
	 * constructor accepting the gameloop count.
	 *
	 * @param  int gameloops | gameloop counter at the time the event happened
	 * @param  int playerId | playerId of the player that caused the event
	 * @param  int number | integer describing the number of which hotkey was set
	 * @param  array location | array with the location with the x and the y index
	 */
	public function __construct($gameloops, $playerId, $number, $location) {
		parent::__construct($gameloops, $playerId);

		$this->number = $number;
		$this->location = $location;
	}
}
