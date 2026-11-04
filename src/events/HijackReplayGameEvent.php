<?php
/**
 * This file is part of the sc2rep replay parser project
 * (c) Matthias Lantsch.
 *
 * class file for the HijackReplayGameEvent logic class
 */

namespace HIS5\lib\Sc2repParser\events;

/**
 * HijackReplayGameEvent is an event generated when players take over from replay.
 *
 * @author  {AUTHOR}
 */
class HijackReplayGameEvent extends EventBase {
	/**
	 * property containing the method used to resume from the replay.
	 *
	 * @var int method | integer marking the method used to resume
	 */
	public int $method;

	/**
	 * property containing the user info data about the players taking over.
	 *
	 * @var array userInfo | user info data about the players taking over
	 */
	public array $userInfo = array();

	/**
	 * constructor accepting the gameloop count.
	 *
	 * @param  int gameloops | gameloop counter at the time the event happened
	 * @param  int playerId | playerId of the player that caused the event
	 * @param  int method | integer marking the method used to resume
	 * @param  array userInfo | user info data about the players taking over
	 */
	public function __construct($gameloops, $playerId, $method, $userInfo) {
		parent::__construct($gameloops, $playerId);

		$this->method = $method;
		$this->userInfo = $userInfo;
	}
}
