<?php
/**
 * This file is part of the sc2rep replay parser project
 * (c) Matthias Lantsch.
 *
 * class file for the MessageEvent logic class
 */

namespace HIS5\lib\Sc2repParser\events;

/**
 * MessageEvent is the base class for all message type events that have a recipient.
 *
 * @author  {AUTHOR}
 */
abstract class MessageEvent extends EventBase {
	/**
	 * property containing the recipient.
	 *
	 * @var string recipient | the recipient of the message
	 */
	public string $recipient;

	/**
	 * constructor accepting the gameloop count.
	 *
	 * @param  int gameloops | gameloop counter at the time the event happened
	 * @param  int playerId | playerId of the player that caused the event
	 * @param  int recipient | integer describing the recipient of the message
	 */
	public function __construct($gameloops, $playerId, $recipient) {
		parent::__construct($gameloops, $playerId);

		switch ($recipient) {
			case 0:
				$this->recipient = 'all';

				break;
			case 1:
				// if a referee talks to all?????
				$this->recipient = 'all';

				break;
			case 2:
				$this->recipient = 'allies';

				break;
			case 4:
				$this->recipient = 'observers';

				break;
			default:
				exit(var_dump("Unknown message recipient {$recipient}"));

				break;
		}
	}
}
