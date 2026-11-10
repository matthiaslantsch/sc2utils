<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\model\enum;

/**
 * Values are sc2reader's strings.
 */
enum Expansion: string {
	case HotS = 'HotS';
	case LotV = 'LotV';
	case WoL = 'WoL';

	/**
	 * The expansion whose standard data a replay depends on, from the hashes of its cache handles, as sc2reader
	 * does it: the newest one present wins, null if none is.
	 *
	 * @param list<string> $hashes hex SHA-256 of each dependency
	 */
	public static function fromDependencies(array $hashes): ?self {
		foreach (array(self::LotV, self::HotS, self::WoL) as $expansion) {
			if (in_array(hash('sha256', $expansion->standardData()), $hashes, true)) {
				return $expansion;
			}
		}

		return null;
	}

	/**
	 * How much faster than real time the game runs at each speed (sc2reader's GAME_SPEED_FACTOR).
	 * LotV counts its gameloops at Faster already.
	 */
	public function speedFactor(GameSpeed $speed): float {
		$factors = $this === self::LotV ? array(0.2, 0.4, 0.6, 0.8, 1.0) : array(0.6, 0.8, 1.0, 1.2, 1.4);

		return $factors[$speed->index()];
	}

	private function standardData(): string {
		return match ($this) {
			self::WoL => 'Standard Data: Liberty.SC2Mod',
			self::HotS => 'Standard Data: Swarm.SC2Mod',
			self::LotV => 'Standard Data: Void.SC2Mod',
		};
	}
}
