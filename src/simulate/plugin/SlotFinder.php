<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\simulate\plugin;

use holonet\sc2utils\model\Point;
use holonet\sc2utils\model\simulation\core\Unit;
use holonet\sc2utils\model\simulation\plugin\Slot;

/**
 * Finds the expansion slots of a map from its resources, the neutral mineral fields and geysers born at gameloop 0.
 *
 * Minerals within LINK cells of each other form a group. A group larger than a base is split in two (neighbouring
 * bases that touch); small groups are merged when they share a geyser or lie close together (gold bases on some maps
 * have their rich fields in two halves of 3 on either side of the town hall, about 12 cells apart, sharing two
 * geysers). A group of at least MIN_FIELDS minerals is a slot; smaller ones are path blockers.
 */
final class SlotFinder {
	/**
	 * Geysers within this distance of a slot's centre belong to it.
	 */
	public const float GEYSER = 12.0;

	/**
	 * Minerals at most this far apart belong together.
	 */
	public const float LINK = 6.0;

	/**
	 * The most minerals a slot has; larger groups are split.
	 */
	public const int MAX_FIELDS = 10;

	/**
	 * Small groups whose nearest fields are at most this far apart are merged.
	 */
	public const float MERGE = 10.0;

	/**
	 * The fewest minerals a slot has (gold bases have 6, standard ones 8).
	 */
	public const int MIN_FIELDS = 5;

	/**
	 * @param list<Unit> $resources the neutral units born at gameloop 0; filter type names mineral field / geyser are used
	 *
	 * @return list<Slot>
	 */
	public static function find(array $resources): array {
		$minerals = array();
		$geysers = array();
		foreach ($resources as $unit) {
			$type = $unit->types[array_key_first($unit->types)] ?? '';
			if (str_contains($type, 'MineralField')) {
				$minerals[] = $unit;
			} elseif (str_contains($type, 'Geyser')) {
				$geysers[] = $unit;
			}
		}

		$groups = array();
		foreach (self::link($minerals) as $group) {
			array_push($groups, ...(count($group) > self::MAX_FIELDS ? self::split($group) : array($group)));
		}
		$groups = self::mergeSmall($groups, $geysers);

		$slots = array();
		foreach ($groups as $group) {
			if (count($group) < self::MIN_FIELDS) {
				continue;
			}
			$centre = self::centre($group);
			$near = array_values(array_filter($geysers, static fn (Unit $geyser): bool => self::dist(self::position($geyser), $centre) <= self::GEYSER));
			$slots[] = new Slot(count($slots) + 1, $centre, $group, $near);
		}

		return $slots;
	}

	/**
	 * @param list<Unit> $group
	 */
	private static function centre(array $group): Point {
		$x = 0.0;
		$y = 0.0;
		foreach ($group as $unit) {
			$x += self::position($unit)->x;
			$y += self::position($unit)->y;
		}

		return new Point($x / (float)count($group), $y / (float)count($group));
	}

	private static function dist(Point $a, Point $b): float {
		return hypot($a->x - $b->x, $a->y - $b->y);
	}

	/**
	 * Single linkage within LINK cells.
	 *
	 * @param list<Unit> $minerals
	 *
	 * @return list<list<Unit>>
	 */
	private static function link(array $minerals): array {
		$groups = array();
		$seen = array();
		foreach ($minerals as $i => $start) {
			if (isset($seen[$i])) {
				continue;
			}
			$seen[$i] = true;
			$queue = array($start);
			$group = array();
			while ($queue !== array()) {
				$unit = array_pop($queue);
				$group[] = $unit;
				foreach ($minerals as $j => $other) {
					if (!isset($seen[$j]) && self::dist(self::position($unit), self::position($other)) <= self::LINK) {
						$seen[$j] = true;
						$queue[] = $other;
					}
				}
			}
			$groups[] = $group;
		}

		return $groups;
	}

	/**
	 * Merges groups below MIN_FIELDS that share a geyser (within GEYSER cells of both centres) or whose nearest
	 * fields are within MERGE cells, as long as the result isn't larger than a slot.
	 *
	 * @param list<list<Unit>> $groups
	 * @param list<Unit> $geysers
	 *
	 * @return list<list<Unit>>
	 */
	private static function mergeSmall(array $groups, array $geysers): array {
		do {
			$merged = false;
			foreach ($groups as $i => $a) {
				if (count($a) >= self::MIN_FIELDS) {
					continue;
				}
				foreach ($groups as $j => $b) {
					if ($j === $i || count($a) + count($b) > self::MAX_FIELDS || count($b) >= self::MIN_FIELDS) {
						continue;
					}
					if (self::nearest($a, $b) <= self::MERGE || self::shareGeyser($a, $b, $geysers)) {
						$groups[$i] = array_merge($a, $b);
						unset($groups[$j]);
						$groups = array_values($groups);
						$merged = true;

						break 2;
					}
				}
			}
		} while ($merged);

		return $groups;
	}

	/**
	 * @param list<Unit> $a
	 * @param list<Unit> $b
	 */
	private static function nearest(array $a, array $b): float {
		$ret = \INF;
		foreach ($a as $x) {
			foreach ($b as $y) {
				$ret = min($ret, self::dist(self::position($x), self::position($y)));
			}
		}

		return $ret;
	}

	private static function position(Unit $unit): Point {
		return $unit->positions[array_key_first($unit->positions)] ?? new Point(0.0, 0.0);
	}

	/**
	 * @param list<Unit> $a
	 * @param list<Unit> $b
	 * @param list<Unit> $geysers
	 */
	private static function shareGeyser(array $a, array $b, array $geysers): bool {
		$ca = self::centre($a);
		$cb = self::centre($b);
		foreach ($geysers as $geyser) {
			$g = self::position($geyser);
			if (self::dist($g, $ca) <= self::GEYSER && self::dist($g, $cb) <= self::GEYSER) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Two-means along the group's longest extent: two touching bases.
	 *
	 * @param list<Unit> $group
	 *
	 * @return list<list<Unit>>
	 */
	private static function split(array $group): array {
		$a = self::position($group[0]);
		$b = $a;
		$far = -1.0;
		foreach ($group as $x) {
			foreach ($group as $y) {
				$d = self::dist(self::position($x), self::position($y));
				if ($d > $far) {
					$far = $d;
					$a = self::position($x);
					$b = self::position($y);
				}
			}
		}
		$halves = array(array(), array());
		for ($round = 0; $round < 10; $round++) {
			$halves = array(array(), array());
			foreach ($group as $unit) {
				$halves[self::dist(self::position($unit), $a) <= self::dist(self::position($unit), $b) ? 0 : 1][] = $unit;
			}
			if ($halves[0] === array() || $halves[1] === array()) {
				return array($group);
			}
			$a = self::centre($halves[0]);
			$b = self::centre($halves[1]);
		}

		return $halves;
	}
}
