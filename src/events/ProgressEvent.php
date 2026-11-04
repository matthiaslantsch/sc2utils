<?php
/**
 * This file is part of the sc2rep replay parser project
 * (c) Matthias Lantsch.
 *
 * class file for the ProgressEvent logic class
 */

namespace HIS5\lib\Sc2repParser\events;

/**
 * A ProgressEvent is fired whenever a client sends packages to the other clients to inform them about the loading progress.
 *
 * @author  {AUTHOR}
 */
class ProgressEvent extends EventBase {
	/**
	 * property containing the loading progress at the time.
	 *
	 * @var int progress | the loading progress at the time
	 */
	public int $progress;

	/**
	 * constructor accepting the gameloop count.
	 *
	 * @param  int gameloops | gameloop counter at the time the event happened
	 * @param  int playerId | playerId of the player that caused the event
	 * @param  int progress | progress of at the moment of the event
	 */
	public function __construct($gameloops, $playerId, $progress) {
		parent::__construct($gameloops, $playerId);

		$this->progress = $progress;
	}
}
