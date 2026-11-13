<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\model\replay;

use holonet\sc2utils\model\Colour;
use holonet\sc2utils\model\enum\Race;
use holonet\sc2utils\model\enum\Result;
use InvalidArgumentException;

/**
 * A player of the game, Blizzard's player: the controlling unit that owns units, has a race, a colour and a result.
 * Tracker events and attributes name it by player id.
 *
 * A human player is controlled by one user, an archon player by two; a computer player by its computer.
 */
final class Player {
	public bool $isHuman {
		get => $this->computer === null;
	}

	/**
	 * The users' names joined with " & " (archon), or the computer's name.
	 */
	public string $name {
		get => $this->computer->name ?? implode(' & ', array_map(static fn (User $user): string => $user->name, $this->users));
	}

	/**
	 * @param int $pid Blizzard's player id: counts the controlling units from 1 in slot order
	 * @param int $team team number, from 1
	 * @param Race|null $pickedRace the lobby pick; null if the replay has no race attribute for the player
	 * @param Race|null $playedRace null if the race name isn't known
	 * @param Result|null $result null if undecided or a tie
	 * @param list<User> $users the users controlling the player in slot order: one, two for archon, none for a computer
	 * @param Computer|null $computer the computer controlling the player; null for humans
	 */
	public function __construct(
		public readonly int $pid,
		public readonly int $team,
		public readonly ?Race $pickedRace,
		public readonly ?Race $playedRace,
		public readonly Colour $colour,
		public readonly ?Result $result,
		public readonly int $handicap,
		public readonly array $users,
		public readonly ?Computer $computer = null,
	) {
		if (($users === array()) === ($computer === null)) {
			throw new InvalidArgumentException("Player {$pid} needs either users or a computer");
		}
	}
}
