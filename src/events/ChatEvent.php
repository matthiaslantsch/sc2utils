<?php
/**
 * This file is part of the sc2rep replay parser project
 * (c) Matthias Lantsch.
 *
 * class file for the ChatEvent logic class
 */

namespace HIS5\lib\Sc2repParser\events;

/**
 * ChatEvent is an event generated for chat messages.
 *
 * @author  {AUTHOR}
 */
class ChatEvent extends MessageEvent {
	/**
	 * property containing the message string.
	 *
	 * @var string msg | the chat message
	 */
	public string $msg;

	/**
	 * constructor accepting the gameloop count.
	 *
	 * @param  int gameloops | gameloop counter at the time the event happened
	 * @param  int playerId | playerId of the player that caused the event
	 * @param  int recipient | integer describing the recipient of the message
	 * @param  string msg | string with the chat message
	 */
	public function __construct($gameloops, $playerId, $recipient, $msg) {
		parent::__construct($gameloops, $playerId, $recipient);

		$this->msg = $msg;
	}
}
