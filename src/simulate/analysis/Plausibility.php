<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\simulate\analysis;

use holonet\sc2utils\gamedata\Abilities;
use holonet\sc2utils\gamedata\Units;
use holonet\sc2utils\model\simulation\core\Unit;
use holonet\sc2utils\model\simulation\plugin\ActionRate;
use holonet\sc2utils\model\simulation\plugin\Base;
use holonet\sc2utils\model\simulation\plugin\MiningBases;
use holonet\sc2utils\model\simulation\Simulation;
use holonet\sc2utils\simulate\plugin\AbilityTracker;
use holonet\sc2utils\simulate\plugin\ApmTracker;
use holonet\sc2utils\simulate\plugin\BaseTracker;
use holonet\sc2utils\simulate\plugin\SpmTracker;

/**
 * What in a simulation looks off: an empty array when nothing does, otherwise one key per kind of finding with what
 * is needed to look into it. Checks on a plugin's result run only if that plugin ran.
 *
 * - unknownAbilities: [table => [link => commands]]: command links the build's ability table doesn't have
 *   (AbilityTracker; a missing or wrong table)
 * - unknownUnitTypes: [type => units]: owned units of a type the game data doesn't know (a new unit or patch,
 *   co-op, arcade); rally beacons and temporary units left out
 * - unclassifiedTypeChanges: ["From -> To" => changes]: type changes gamedata\Units::change() doesn't know
 * - temporaryCandidates: [type => [deaths, % at one lifetime, % expired]]: owned types that die like temporary
 *   units (at least 5 deaths, 80 % at one lifetime or 90 % expired) but are in neither
 *   Units::TEMPORARY nor Units::NOT_TEMPORARY;
 * - unlinkedBanelings: [unit ids]: Banelings without the cocoon they hatched from (MorphLinker)
 * - noSlots: true: tracker events with units but no expansion slot (BaseTracker; co-op, arcade, or a bug)
 * - slotsOutOfShape: [slot id => mineral fields]: slots outside 6 to 8 fields (SlotFinder; new map layouts)
 * - mineralsOutsideSlots: count of mineral fields in no slot (path blockers, or SlotFinder)
 * - playersWithoutStartBase: [player names]: players with a town hall at the start but no starting base
 * - baseOverlap: [slot ids]: two bases at one slot at the same time (BaseTracker)
 * - actionRates: [user name => [APM, SPM]]: above MAX_APM or MAX_SPM (ApmTracker, SpmTracker)
 * - eventsAfterEnd: [what => last gameloop]: unit events after the header's last gameloop (the time base)
 *
 * @implements Analysis<array<string, mixed>>
 */
class Plausibility implements Analysis {
	public const float MAX_APM = 1_000.0;

	public const float MAX_SPM = 150.0;

	/**
	 * @return array<string, mixed>
	 */
	public function analyse(Simulation $simulation): array {
		$ret = array(
			'unknownAbilities' => self::unknownAbilities($simulation),
			'unknownUnitTypes' => self::unknownUnitTypes($simulation),
			'unclassifiedTypeChanges' => self::unclassifiedTypeChanges($simulation),
			'temporaryCandidates' => self::temporaryCandidates($simulation),
			'unlinkedBanelings' => self::unlinkedBanelings($simulation),
			'actionRates' => self::actionRates($simulation),
			'eventsAfterEnd' => self::eventsAfterEnd($simulation),
		);
		if ($simulation->has(BaseTracker::class)) {
			$ret += self::bases($simulation);
		}

		return array_filter($ret, static fn (mixed $value): bool => $value !== array() && $value !== 0 && $value !== false);
	}

	/**
	 * @return array<string, array{float, float}>
	 */
	private static function actionRates(Simulation $simulation): array {
		if (!$simulation->has(ApmTracker::class)) {
			return array();
		}
		$ret = array();
		foreach ($simulation->result(ApmTracker::class) ?? array() as list($user, $apm)) {
			/** @var ActionRate $apm */
			$spm = $simulation->has(SpmTracker::class) ? $simulation->result(SpmTracker::class)?->for($user)?->average ?? 0.0 : 0.0;
			if ($apm->average > self::MAX_APM || $spm > self::MAX_SPM) {
				$ret[$user->name] = array(round($apm->average, 1), round($spm, 1));
			}
		}

		return $ret;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function bases(Simulation $simulation): array {
		$bases = $simulation->result(BaseTracker::class);
		if (!$bases instanceof MiningBases) {
			return array();
		}
		$ret = array();
		$minerals = 0;
		foreach ($simulation->units->all() as $unit) {
			$minerals += $unit->owner === null && $unit->startedAt === 0 && str_contains(self::firstType($unit), 'MineralField') ? 1 : 0;
		}
		$ret['noSlots'] = $bases->slots === array() && $simulation->units->all() !== array();
		$inSlots = 0;
		$shapes = array();
		foreach ($bases->slots as $slot) {
			$inSlots += count($slot->minerals);
			if (count($slot->minerals) < 6 || count($slot->minerals) > 8) {
				$shapes[$slot->id] = count($slot->minerals);
			}
		}
		$ret['slotsOutOfShape'] = $shapes;
		$ret['mineralsOutsideSlots'] = $minerals - $inSlots;

		$withoutStart = array();
		foreach ($simulation->replay->players as $player) {
			$startHall = array_filter($simulation->units->ownedBy($player), static fn (Unit $unit): bool => $unit->startedAt === 0 && in_array(Units::base($unit->type->id), BaseTracker::TOWN_HALLS, true));
			$startBase = array_filter($bases->of($player), static fn (Base $base): bool => $base->establishedAt === 0);
			if ($startHall !== array() && $startBase === array()) {
				$withoutStart[] = $player->name;
			}
		}
		$ret['playersWithoutStartBase'] = $withoutStart;

		$overlap = array();
		foreach ($bases->bases as $i => $a) {
			foreach (array_slice($bases->bases, $i + 1) as $b) {
				$apart = ($a->endedAt !== null && $a->endedAt <= $b->establishedAt) || ($b->endedAt !== null && $b->endedAt <= $a->establishedAt);
				if ($a->slot === $b->slot && !$apart) {
					$overlap[$a->slot->id] = true;
				}
			}
		}
		$ret['baseOverlap'] = array_keys($overlap);

		return $ret;
	}

	/**
	 * @return array<string, int>
	 */
	private static function eventsAfterEnd(Simulation $simulation): array {
		$end = $simulation->replay->header->gameloops;
		$ret = array();
		foreach ($simulation->units->all() as $unit) {
			foreach (array('born' => $unit->startedAt, 'died' => $unit->diedAt) as $what => $gameloop) {
				if ($gameloop !== null && $gameloop > $end) {
					$ret[$what] = max($ret[$what] ?? 0, $gameloop);
				}
			}
		}

		return $ret;
	}

	private static function firstType(Unit $unit): string {
		return $unit->types === array() ? '' : $unit->types[array_key_first($unit->types)];
	}

	/**
	 * @return array<string, array{int, int, int}>
	 */
	private static function temporaryCandidates(Simulation $simulation): array {
		$deaths = array();
		foreach ($simulation->units->all() as $unit) {
			if ($unit->owner === null || $unit->diedAt === null || $unit->startedAt === null || $unit->hallucinated === true) {
				continue;
			}
			// by the owner without a killing unit: an expiry (self-destructs and friendly fire name a killing unit)
			$deaths[Units::base(self::firstType($unit))][] = array($unit->diedAt - $unit->startedAt, $unit->killer?->pid === $unit->owner->pid && $unit->killingUnit === null);
		}
		$ret = array();
		foreach ($deaths as $type => $list) {
			if (count($list) < 5 || in_array($type, Units::TEMPORARY, true) || isset(Units::NOT_TEMPORARY[$type])) {
				continue;
			}
			$fixed = (float)max(array_count_values(array_column($list, 0))) / (float)count($list);
			$byOwner = (float)count(array_filter(array_column($list, 1))) / (float)count($list);
			if ($fixed >= 0.8 || $byOwner >= 0.9) {
				$ret[$type] = array(count($list), (int)round($fixed * 100.0), (int)round($byOwner * 100.0));
			}
		}
		ksort($ret);

		return $ret;
	}

	/**
	 * @return array<string, int>
	 */
	private static function unclassifiedTypeChanges(Simulation $simulation): array {
		$ret = array();
		foreach ($simulation->units->all() as $unit) {
			$previous = null;
			foreach ($unit->types as $type) {
				if ($previous !== null && Units::change($previous, $type) === null) {
					$ret["{$previous} -> {$type}"] = ($ret["{$previous} -> {$type}"] ?? 0) + 1;
				}
				$previous = $type;
			}
		}
		ksort($ret);

		return $ret;
	}

	/**
	 * @return array<string, array<int, int>>
	 */
	private static function unknownAbilities(Simulation $simulation): array {
		if (!$simulation->has(AbilityTracker::class)) {
			return array();
		}
		$table = Abilities::table($simulation->replay->expansion, $simulation->replay->header->version->build) ?? 'none';
		$links = array();
		foreach ($simulation->result(AbilityTracker::class) ?? array() as list(, $uses)) {
			foreach ($uses as $use) {
				if ($use->ability === null) {
					$links[$use->link] = ($links[$use->link] ?? 0) + $use->count;
				}
			}
		}
		ksort($links);

		return $links === array() ? array() : array($table => $links);
	}

	/**
	 * @return array<string, int>
	 */
	private static function unknownUnitTypes(Simulation $simulation): array {
		$ret = array();
		foreach ($simulation->units->all() as $unit) {
			$type = Units::type(Units::base(self::firstType($unit)));
			if ($unit->owner === null || $type->race !== null || $type->isTemporary || str_starts_with($type->id, 'Beacon')) {
				continue;
			}
			$ret[$type->id] = ($ret[$type->id] ?? 0) + 1;
		}
		ksort($ret);

		return $ret;
	}

	/**
	 * @return list<int>
	 */
	private static function unlinkedBanelings(Simulation $simulation): array {
		$ret = array();
		foreach ($simulation->units->all() as $unit) {
			if ($unit->startedAt !== null && $unit->startedAt > 0 && ($unit->types[$unit->startedAt] ?? null) === 'Baneling' && $unit->morphedFrom === null) {
				$ret[] = $unit->id;
			}
		}

		return $ret;
	}
}
