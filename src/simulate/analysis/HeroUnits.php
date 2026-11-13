<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\simulate\analysis;

use holonet\sc2utils\model\simulation\analysis\Hero;
use holonet\sc2utils\model\simulation\core\Unit;
use holonet\sc2utils\model\simulation\PerPlayer;
use holonet\sc2utils\model\simulation\Simulation;

/**
 * Each player's hero: the unit with the most kills. Ties go to the unit that came first; a player whose units
 * killed nothing has no entry.
 *
 * @implements Analysis<PerPlayer<Hero>>
 */
class HeroUnits implements Analysis {
	public function analyse(Simulation $simulation): PerPlayer {
		$kills = array();
		$holders = array();
		foreach ($simulation->units->all() as $victim) {
			$holder = self::creditedKiller($victim);
			if ($holder !== null) {
				$kills[$holder->id] = ($kills[$holder->id] ?? 0) + 1;
				$holders[$holder->id] = $holder;
			}
		}

		$entries = array();
		foreach ($simulation->replay->players as $player) {
			$best = null;
			foreach ($holders as $id => $unit) {
				if ($unit->owner?->pid !== $player->pid) {
					continue;
				}
				if ($best === null || $kills[$id] > $best->kills || ($kills[$id] === $best->kills && ($unit->startedAt ?? 0) < ($best->unit->startedAt ?? 0))) {
					$best = new Hero($unit, $kills[$id]);
				}
			}
			if ($best !== null) {
				$entries[$player->pid] = array($player, $best);
			}
		}

		return new PerPlayer($entries);
	}

	/**
	 * The unit a death counts for as a kill; null if it isn't one.
	 */
	public static function creditedKiller(Unit $victim): ?Unit {
		if ($victim->diedAt === null || $victim->owner === null || $victim->isTemporary() || $victim->hallucinated === true) {
			return null;
		}
		$holder = $victim->killingUnit?->creditHolder();

		return $holder === null || $holder->owner === null || $holder->owner->pid === $victim->owner->pid ? null : $holder;
	}
}
