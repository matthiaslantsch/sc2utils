<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\model\simulation\plugin;

use holonet\sc2utils\model\replay\Player;

/**
 * The map's expansion slots and every mining base of the game (simulate\plugin\BaseTracker).
 */
class MiningBases {
	/**
	 * @param list<Slot> $slots
	 * @param list<Base> $bases in the order they were established
	 */
	public function __construct(
		public array $slots,
		public array $bases,
	) {}

	/**
	 * A player's mining bases at a gameloop.
	 */
	public function countAt(Player $player, int $gameloop): int {
		return count(array_filter($this->of($player), static fn (Base $base): bool => $base->activeAt($gameloop)));
	}

	/**
	 * @return list<Base>
	 */
	public function of(Player $player): array {
		return array_values(array_filter($this->bases, static fn (Base $base): bool => $base->owner->pid === $player->pid));
	}

	/**
	 * A player's base count over time: [gameloop, count] wherever it changes, from gameloop 0.
	 * @return list<array{int, int}>
	 */
	public function timeline(Player $player): array {
		$changes = array(0 => 0);
		foreach ($this->of($player) as $base) {
			$changes[$base->establishedAt] = ($changes[$base->establishedAt] ?? 0) + 1;
			if ($base->endedAt !== null) {
				$changes[$base->endedAt] = ($changes[$base->endedAt] ?? 0) - 1;
			}
		}
		ksort($changes);
		$ret = array();
		$count = 0;
		foreach ($changes as $gameloop => $change) {
			$count += $change;
			if ($ret === array() || $ret[count($ret) - 1][1] !== $count) {
				$ret[] = array($gameloop, $count);
			}
		}

		return $ret;
	}

	/**
	 * When a player took its expansions: the gameloops its bases were established, the starting base left out.
	 * @return list<int>
	 */
	public function timings(Player $player): array {
		return array_values(array_map(static fn (Base $base): int => $base->establishedAt, array_filter($this->of($player), static fn (Base $base): bool => $base->establishedAt > 0)));
	}
}
