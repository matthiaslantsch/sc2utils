<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\model\simulation\core;

use holonet\sc2utils\gamedata\Units;
use holonet\sc2utils\gamedata\UnitType;
use holonet\sc2utils\model\Point;
use holonet\sc2utils\model\replay\Player;

/**
 * A unit as the tracker events describe it (sc2reader's unit model, simulate\core\ContextLoader builds it).
 */
final class Unit {
	/**
	 * The unit that created it (a Swarm Host for its Locusts, a Carrier for its Interceptors); null if none or not
	 * recorded (before 54724).
	 */
	public private(set) ?Unit $creator = null;

	/**
	 * The ability that created it, e.g. "BarracksTrain", "SpawnLocustsTargeted"; null if none or not recorded
	 * (before 54724).
	 */
	public private(set) ?string $creatorAbility = null;

	public private(set) ?int $diedAt = null;

	/**
	 * Born or done gameloop; null while under construction.
	 */
	public private(set) ?int $finishedAt = null;

	/**
	 * Whether the unit is a hallucination: exact from the creating ability where the replay records creators (from
	 * protocol 54724, patch 3.15); before, true where a Hallucination command of its owner matches its birth
	 * (simulate\core\HallucinationMatcher), else null (unknown). Units started by UnitInitEvent (structures, warp-ins) are
	 * never hallucinations.
	 */
	public private(set) ?bool $hallucinated = null;

	/**
	 * The units this unit killed.
	 * @var list<Unit>
	 */
	public private(set) array $killedUnits = array();

	/**
	 * The player that killed it; null if none or not a player of the replay.
	 */
	public private(set) ?Player $killer = null;

	public private(set) ?Unit $killingUnit = null;

	/**
	 * For a Baneling: the Zergling's cocoon it hatched from. The game ends the cocoon (it turns back into a Zergling
	 * and dies) and creates the Baneling as a new unit in the same gameloop and place.
	 */
	public private(set) ?Unit $morphedFrom = null;

	/**
	 * The owner: the player paying its upkeep (sc2reader's owner); null for neutral units.
	 */
	public private(set) ?Player $owner = null;

	/**
	 * Known positions: gameloop => position in map cells. Born/init, the position events while it was damaged (every
	 * 240 gameloops), died.
	 * @var array<int, Point>
	 */
	public private(set) array $positions = array();

	/**
	 * Born or init gameloop.
	 */
	public private(set) ?int $startedAt = null;

	/**
	 * The current type.
	 */
	public UnitType $type {
		get => Units::type($this->types === array() ? '' : $this->types[array_key_last($this->types)]);
	}

	/**
	 * The type history: gameloop => Blizzard's unit id. As in sc2reader, a later change in the same gameloop
	 * replaces an earlier one, and a "change" to the same type is kept.
	 * @var array<int, string>
	 */
	public private(set) array $types = array();

	/**
	 * @param int $id the unit tag: index << 18 | recycle
	 */
	public function __construct(
		public readonly int $id,
	) {}

	public function changeOwner(?Player $owner): void {
		$this->owner = $owner;
	}

	public function changeType(int $gameloop, string $type): void {
		$this->types[$gameloop] = $type;
	}

	/**
	 * Whom this unit's kills count for: the unit itself, or for a temporary unit the
	 * first non-temporary unit up its creator chain (Locust → Swarm Host, Broodling → Broodling Escort → Brood
	 * Lord, Interceptor → Carrier, Parasitic Bomb dummy → Viper). Null for a temporary unit without one (Broodlings
	 * from dying structures, anything before 54724) and for hallucinations.
	 */
	public function creditHolder(): ?self {
		$unit = $this;
		for ($depth = 0; $unit !== null && $depth < 8; $depth++) {
			if ($unit->hallucinated === true) {
				return null;
			}
			if (!$unit->isTemporary()) {
				return $unit;
			}
			$unit = $unit->creator;
		}

		return null;
	}

	public function die(int $gameloop, Point $position, ?Player $killer, ?self $killingUnit): void {
		$this->diedAt = $gameloop;
		$this->positions[$gameloop] = $position;
		$this->killer = $killer;
		$this->killingUnit = $killingUnit;
		if ($killingUnit !== null) {
			$killingUnit->killedUnits[] = $this;
		}
	}

	public function finish(int $gameloop): void {
		$this->finishedAt = $gameloop;
	}

	/**
	 * Whether its first type is temporary (gamedata\Units::TEMPORARY): spawned, a dummy, or a production
	 * intermediate. Such units never count as kills or losses.
	 */
	public function isTemporary(): bool {
		return $this->types !== array() && Units::type($this->types[array_key_first($this->types)])->isTemporary;
	}

	public function linkMorph(self $from): void {
		$this->morphedFrom = $from;
	}

	public function markHallucinated(): void {
		$this->hallucinated = true;
	}

	public function move(int $gameloop, Point $position): void {
		$this->positions[$gameloop] = $position;
	}

	public function originate(?string $ability, ?self $creator, ?bool $hallucinated): void {
		$this->creatorAbility = $ability;
		$this->creator = $creator;
		$this->hallucinated = $hallucinated;
	}

	/**
	 * The last known position at or before a gameloop, with the gameloop it was recorded at; null before the first.
	 * @return array{int, Point}|null
	 */
	public function positionAt(int $gameloop): ?array {
		$ret = null;
		foreach ($this->positions as $at => $position) {
			if ($at > $gameloop) {
				break;
			}
			$ret = array($at, $position);
		}

		return $ret;
	}

	/**
	 * The type history with modes collapsed to their base type (gamedata\Units::MODES) and repeats merged: the
	 * unit's stages, e.g. Zergling, BanelingCocoon, Zergling for a hatched baneling's cocoon, or Hatchery, Lair. Each
	 * stage with the gameloop it began.
	 * @return list<array{int, string}>
	 */
	public function stages(): array {
		$ret = array();
		$last = null;
		foreach ($this->types as $gameloop => $type) {
			$base = Units::base($type);
			if ($base !== $last) {
				$ret[] = array($gameloop, $base);
				$last = $base;
			}
		}

		return $ret;
	}

	public function start(int $gameloop, Point $position, bool $finished): void {
		$this->startedAt = $gameloop;
		if ($finished) {
			$this->finishedAt = $gameloop;
		}
		$this->positions[$gameloop] = $position;
	}

	/**
	 * The exact type at a gameloop (modes included, e.g. SiegeTankSieged); null before the unit existed.
	 */
	public function typeAt(int $gameloop): ?string {
		$ret = null;
		foreach ($this->types as $at => $type) {
			if ($at > $gameloop) {
				break;
			}
			$ret = $type;
		}

		return $ret;
	}
}
