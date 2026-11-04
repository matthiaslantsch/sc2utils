<?php
/**
 * This file is part of the sc2rep replay parser project
 * (c) Matthias Lantsch.
 *
 * class file for the ResourceTradeEvent logic class
 */

namespace HIS5\lib\Sc2repParser\events;

/**
 * A ResourceTradeEvent is fired whenever a player sends resources to another player without a prior request.
 *
 * @author  {AUTHOR}
 */
class ResourceTradeEvent extends EventBase {
	/**
	 * property containing the player id of the recipient of the resources.
	 *
	 * @var int recipientId | player id of the recipient
	 */
	public int $recipientId;

	/**
	 * property containing an array of resources traded.
	 *
	 * @var array resources | array with the traded resources
	 */
	public array $resources;

	/**
	 * constructor accepting the gameloop count.
	 *
	 * @param  int gameloops | gameloop counter at the time the event happened
	 * @param  int playerId | playerId of the player that caused the event
	 * @param  int recipientId | player id of the recipient
	 * @param  array resources | array with the traded resources
	 */
	public function __construct($gameloops, $playerId, $recipientId, $resources) {
		parent::__construct($gameloops, $playerId);

		$this->recipientId = $recipientId;
		exit(var_dump('parse resource at trade', $resources));
		$this->resources = $resources;
	}
}
