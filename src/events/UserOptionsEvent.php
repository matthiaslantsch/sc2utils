<?php
/**
 * This file is part of the sc2rep replay parser project
 * (c) Matthias Lantsch.
 *
 * class file for the UserOptionsEvent logic class
 */

namespace HIS5\lib\Sc2repParser\events;

/**
 * A UserOptionsEvent is fired at the start of a game with user options.
 *
 * @author  {AUTHOR}
 */
class UserOptionsEvent extends EventBase {
	/**
	 * property containing an array with user options, indexed by string key.
	 *
	 * @var array useroptions | associative array with user options
	 */
	public array $useroptions;

	/**
	 * constructor accepting the gameloop count.
	 *
	 * @param  int gameloops | gameloop counter at the time the event happened
	 * @param  int playerId | playerId of the player that caused the event
	 * @param  array useroptions | associative array with user options
	 */
	public function __construct($gameloops, $playerId, $useroptions) {
		parent::__construct($gameloops, $playerId);

		$this->useroptions = $useroptions;
	}
}
