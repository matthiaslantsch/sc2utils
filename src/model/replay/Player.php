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
use holonet\sc2utils\model\Toon;

/**
 * A player of the game, human or computer.
 */
final class Player {
	/**
	 * Humans have a user id, computers don't.
	 */
	public bool $isHuman {
		get => $this->userId !== null;
	}

	/**
	 * @param int $pid Blizzard's player id (as in tracker events): counts the controlling units from 1, archon
	 *                 partners share it, observers have none
	 * @param int $slot index of the lobby slot
	 * @param int|null $userId the human's user id; null for computers
	 * @param string|null $clanTag null for computers and before clan tags existed (2.0.3)
	 * @param Toon|null $toon null for computers
	 * @param int $team team number, from 1
	 * @param Race|null $pickedRace the lobby pick; null if the replay has no race attribute for the player
	 * @param Race|null $playedRace null if the race name isn't known
	 * @param Result|null $result null if undecided or a tie
	 * @param string|null $difficulty the computer difficulty attribute (humans have "Medium"); null if absent
	 */
	public function __construct(
		public readonly int $pid,
		public readonly int $slot,
		public readonly ?int $userId,
		public readonly string $name,
		public readonly ?string $clanTag,
		public readonly ?Toon $toon,
		public readonly int $team,
		public readonly ?Race $pickedRace,
		public readonly ?Race $playedRace,
		public readonly Colour $colour,
		public readonly ?Result $result,
		public readonly int $handicap,
		public readonly ?string $difficulty,
	) {}
}
