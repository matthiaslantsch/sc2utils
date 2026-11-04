<?php
/**
 * This file is part of the sc2rep replay parser project
 * (c) Matthias Lantsch.
 *
 * class file for the AICommunicateEvent logic class
 */

namespace HIS5\lib\Sc2repParser\events;

/**
 * AICommunicateEvent is an event generated when a player signals commands to the AI.
 * @author  {AUTHOR}
 */
class AICommunicateEvent extends EventBase {
	/**
	 * property containing an ally integer.
	 *
	 * @var int ally | player id of the ai getting the command
	 */
	public int $ally;

	/**
	 * property containing the type of beacon sent to the ai.
	 *
	 * @var string beacon | the type of beacon sent to the ai
	 */
	public string $beacon;

	/**
	 * property containing the AI build identifier for the lateGame.
	 *
	 * @var string build | AI build identifier lateGame
	 */
	public string $lateGame;

	/**
	 * property containing the target location on the map.
	 *
	 * @var array location | array with the location with the x, y an z index
	 */
	public array $location;

	/**
	 * property containing the AI build identifier for the opener.
	 *
	 * @var string build | AI build identifier opener
	 */
	public string $opener;

	/**
	 * property containing the player id of the controlling player.
	 *
	 * @var int controlPlayerId | player id of the player controlling the unit
	 */
	public int $targetControlPlayerId;

	/**
	 * property containing the unique identifier of the target unit
	 * is 0 if the unit is in the fog of war.
	 *
	 * @var int targetUnitId | unique identifier of the target unit
	 */
	public int $targetUnitId;

	/**
	 * property containing the player id of the player paying upkeep.
	 *
	 * @var int upkeepPlayerId | player id of the player paying upkeep
	 */
	public int $targetUpkeepPlayerId;

	/**
	 * constructor accepting the gameloop count.
	 *
	 * @param  int gameloops | gameloop counter at the time the event happened
	 * @param  int playerId | playerId of the player that caused the event
	 * @param  int beacon | the type of beacon sent to the ai
	 * @param  int ally | player id of the ai getting the command
	 * @param  int build | AI build identifier
	 */
	public function __construct($gameloops, $playerId, $beacon, $ally, $build) {
		parent::__construct($gameloops, $playerId);
		exit(var_dump('remove parsing'));

		if ($beacon !== -1) {
			switch ($beacon) {
				case 0:
					$this->beacon = 'attack';

					break;
				case 5:
					$this->beacon = 'clear';

					break;
				case 6:
					$this->beacon = 'detect';

					break;
				case 7:
					$this->beacon = 'scout';

					break;
				case 9:
					$this->beacon = 'expand';

					break;
				default:
					exit(var_dump("uNKNOWN ai beacon {$beacon}"));

					break;
			}
		}

		$this->ally = $ally;

		if ($build !== 0) {
			switch ($beacon) {
				case 2:
					$this->opener = 'rush';

					break;
				case 3:
					$this->opener = 'timing attack';

					break;
				case 4:
					$this->opener = 'aggresive push';

					break;
				case 5:
					$this->opener = 'economic focus';

					break;
				case 9:
					$this->lateGame = 'create basic units';

					break;
				case 10:
					$this->lateGame = 'create advanced units';

					break;
				case 11:
					$this->lateGame = 'create air';

					break;
				case 12:
					$this->lateGame = 'create casters';

					break;
				default:
					exit(var_dump("uNKNOWN ai build {$build}"));

					break;
			}
		}
	}
}
