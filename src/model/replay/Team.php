<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\model\replay;

use holonet\sc2utils\model\enum\Result;

final class Team {
	/**
	 * The played races as sorted letters, e.g. "PZ" (sc2reader's lineup), Unknown races count as "?".
	 */
	public string $lineup {
		get {
			$letters = array();
			foreach ($this->players as $player) {
				$letters[$player->pid] ??= $player->playedRace?->letter() ?? '?';
			}
			sort($letters);

			return implode('', $letters);
		}
	}

	/**
	 * @param list<Player> $players
	 * @param Result|null $result the players' common result, Unknown if they differ, null if none has one
	 */
	public function __construct(
		public readonly int $number,
		public readonly array $players,
		public readonly ?Result $result,
	) {}
}
