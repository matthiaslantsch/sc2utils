<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\model\simulation;

use holonet\sc2utils\model\event\tracker\PlayerStatsEvent;

/**
 * A player's stats samples (every 160 gameloops, the game's own values) and what is computed from all of them.
 */
class PlayerStatsSeries {
	/**
	 * @param list<PlayerStatsEvent> $samples in gameloop order
	 */
	public function __construct(
		public array $samples,
	) {}

	/**
	 * The last sample at or before a gameloop; null before the first.
	 */
	public function at(int $gameloop): ?PlayerStatsEvent {
		$ret = null;
		foreach ($this->samples as $sample) {
			if ($sample->gameloop > $gameloop) {
				break;
			}
			$ret = $sample;
		}

		return $ret;
	}

	/**
	 * The mean collection rate, minerals plus vespene (per game minute, as the game counts it), over all samples.
	 */
	public function averageIncome(): float {
		return self::mean(array_map(static fn (PlayerStatsEvent $s): int => $s->mineralsCollectionRate + $s->vespeneCollectionRate, $this->samples));
	}

	/**
	 * The mean bank, minerals plus vespene, over all samples.
	 */
	public function averageUnspent(): float {
		return self::mean(array_map(static fn (PlayerStatsEvent $s): int => $s->mineralsCurrent + $s->vespeneCurrent, $this->samples));
	}

	/**
	 * The spending quotient, SQ = 35 · (0.00137 · I − ln U) + 240 with I the average income and U the average unspent
	 * resources (TeamLiquid, "Do you macro like a pro?"). U is taken as at least 1, so an empty bank doesn't make
	 * the logarithm infinite. Not rounded. 0 without samples.
	 */
	public function spendingQuotient(): float {
		if ($this->samples === array()) {
			return 0.0;
		}

		return 35.0 * (0.001_37 * $this->averageIncome() - log(max(1.0, $this->averageUnspent()))) + 240.0;
	}

	/**
	 * @param list<int> $values
	 */
	private static function mean(array $values): float {
		return $values === array() ? 0.0 : array_sum($values) / count($values);
	}
}
