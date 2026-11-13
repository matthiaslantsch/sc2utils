<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\simulate\plugin;

use holonet\sc2utils\gamedata\Units;
use holonet\sc2utils\model\event\engine\BaseEstablishedEvent;
use holonet\sc2utils\model\event\engine\BaseLostEvent;
use holonet\sc2utils\model\event\Event;
use holonet\sc2utils\model\event\tracker\UnitBornEvent;
use holonet\sc2utils\model\event\tracker\UnitDiedEvent;
use holonet\sc2utils\model\event\tracker\UnitDoneEvent;
use holonet\sc2utils\model\event\tracker\UnitPositionsEvent;
use holonet\sc2utils\model\event\tracker\UnitTypeChangeEvent;
use holonet\sc2utils\model\Point;
use holonet\sc2utils\model\replay\Replay;
use holonet\sc2utils\model\simulation\core\Unit;
use holonet\sc2utils\model\simulation\plugin\Base;
use holonet\sc2utils\model\simulation\plugin\BaseEnd;
use holonet\sc2utils\model\simulation\plugin\MiningBases;
use holonet\sc2utils\model\simulation\plugin\Slot;
use holonet\sc2utils\simulate\Context;
use holonet\sc2utils\simulate\Plugin;

/**
 * Mining bases: a finished town hall at an expansion slot that still has minerals to mine.
 *
 * The slots come from the resources born at gameloop 0 (SlotFinder) and are found when the first later event
 * arrives, together with the starting bases. A town hall belongs to the nearest slot within ATTACH cells; a slot
 * has one base at a time, so a second town hall there (a macro hatchery) or one far from every slot is no base.
 * Yields BaseEstablishedEvent and BaseLostEvent:
 * - established when the town hall finishes (or at the start) at a free slot that isn't mined out;
 * - lost when it dies (destroyed), lifts off (lifted), or its slot falls below the mined-out threshold (mined
 *   out; a mineral field's death means it is empty).
 * A town hall that lands isn't placed by the tracker, its base is established
 * when its position becomes known: a unit it creates or a position event (when damaged).
 *
 * @implements Plugin<MiningBases>
 */
final class BaseTracker implements Plugin {
	/**
	 * How far a town hall may be from a slot's mineral centre.
	 */
	public const float ATTACH = 12.0;

	/**
	 * Town hall types (base types: flying Command Centers count as Command Centers).
	 */
	public const array TOWN_HALLS = array('CommandCenter', 'Hatchery', 'Hive', 'Lair', 'Nexus', 'OrbitalCommand', 'PlanetaryFortress');

	/**
	 * @var array<int, Base> slot id => its active base
	 */
	private array $active = array();

	/**
	 * @var list<Base>
	 */
	private array $bases = array();

	/**
	 * @var array<int, Base> town hall id => its active base
	 */
	private array $byHall = array();

	/**
	 * @var array<int, Slot> mineral field id => its slot
	 */
	private array $fieldSlots = array();

	/**
	 * @var array<int, true> town halls in the air
	 */
	private array $flying = array();

	/**
	 * @var array<int, int> town hall id => gameloop it landed, position unknown yet
	 */
	private array $landed = array();

	/**
	 * @var list<Slot>|null null until the resources are complete
	 */
	private ?array $slots = null;

	/**
	 * @param float $minedOutBelow a base is mined out when fewer than this share of its slot's mineral fields are left
	 */
	public function __construct(
		private readonly float $minedOutBelow = 0.5,
	) {}

	/**
	 * Finds the slots and establishes the starting bases, once.
	 *
	 * @return list<Event>
	 */
	private function setUp(Context $context): array {
		if ($this->slots !== null) {
			return array();
		}
		$resources = array_values(array_filter($context->units->all(), static fn (Unit $unit): bool => $unit->owner === null && $unit->startedAt === 0));
		$this->slots = SlotFinder::find($resources);
		foreach ($this->slots as $slot) {
			foreach ($slot->minerals as $field) {
				$this->fieldSlots[$field->id] = $slot;
			}
		}
		$ret = array();
		foreach ($context->units->all() as $unit) {
			if ($unit->startedAt === 0 && $unit->finishedAt === 0 && self::isTownHall($unit)) {
				array_push($ret, ...$this->establish($unit, 0, $unit->positions[0] ?? new Point(-1_000.0, -1_000.0), 0));
			}
		}

		return $ret;
	}

	public function dependencies(): array {
		return array();
	}

	public function finish(Context $context): void {
		$this->setUp($context);
	}

	public function init(Context $context): void {}

	public function result(): MiningBases {
		return new MiningBases($this->slots ?? array(), $this->bases);
	}

	public function subscriptions(): array {
		return array(
			UnitBornEvent::class => $this->born(...),
			UnitDoneEvent::class => $this->done(...),
			UnitDiedEvent::class => $this->died(...),
			UnitTypeChangeEvent::class => $this->typeChanged(...),
			UnitPositionsEvent::class => $this->moved(...),
		);
	}

	public function supports(Replay $replay): bool {
		return true;
	}

	/**
	 * At the first event after gameloop 0 the resources are complete: find the slots, establish the starting bases.
	 *
	 * @return list<Event>
	 */
	private function begin(int $gameloop, Context $context): array {
		return $gameloop > 0 ? $this->setUp($context) : array();
	}

	/**
	 * @return list<Event>
	 */
	private function born(UnitBornEvent $event, Context $context): array {
		$ret = $this->begin($event->gameloop, $context);
		$unit = $context->units->get($event->unitId);
		if ($event->gameloop > 0 && $unit !== null && self::isTownHall($unit)) {
			array_push($ret, ...$this->establish($unit, $event->gameloop, $event->location, $event->gameloop));
		}
		$creator = $event->creatorUnitId === null ? null : $context->units->get($event->creatorUnitId);
		if ($creator !== null && isset($this->landed[$creator->id])) {
			// a landed town hall shows where it is by the units it creates
			array_push($ret, ...$this->establish($creator, $this->landed[$creator->id], $event->location, $event->gameloop));
			unset($this->landed[$creator->id]);
		}

		return $ret;
	}

	/**
	 * @return list<Event>
	 */
	private function died(UnitDiedEvent $event, Context $context): array {
		$ret = $this->begin($event->gameloop, $context);
		unset($this->landed[$event->unitId], $this->flying[$event->unitId]);
		if (isset($this->byHall[$event->unitId])) {
			$ret[] = $this->end($this->byHall[$event->unitId], $event->gameloop, BaseEnd::Destroyed);
		}
		$slot = $this->fieldSlots[$event->unitId] ?? null;
		if ($slot !== null && isset($this->active[$slot->id]) && $this->minedOut($slot, $event->gameloop)) {
			$ret[] = $this->end($this->active[$slot->id], $event->gameloop, BaseEnd::MinedOut);
		}

		return $ret;
	}

	/**
	 * @return list<Event>
	 */
	private function done(UnitDoneEvent $event, Context $context): array {
		$ret = $this->begin($event->gameloop, $context);
		$unit = $context->units->get($event->unitId);
		$position = $unit === null || $unit->startedAt === null ? null : $unit->positions[$unit->startedAt] ?? null;
		if ($unit !== null && $position !== null && self::isTownHall($unit)) {
			array_push($ret, ...$this->establish($unit, $event->gameloop, $position, $event->gameloop));
		}

		return $ret;
	}

	private function end(Base $base, int $gameloop, BaseEnd $end): BaseLostEvent {
		$base->close($gameloop, $end);
		unset($this->active[$base->slot->id], $this->byHall[$base->townHall->id]);

		return new BaseLostEvent($gameloop, $base);
	}

	/**
	 * @param int $at when the base begins (a landing can be learned later)
	 *
	 * @return list<BaseEstablishedEvent>
	 */
	private function establish(Unit $townHall, int $at, Point $position, int $now): array {
		if ($townHall->owner === null || isset($this->byHall[$townHall->id])) {
			return array();
		}
		$slot = null;
		$distance = self::ATTACH;
		foreach ($this->slots ?? array() as $candidate) {
			if ($candidate->distance($position) <= $distance) {
				$slot = $candidate;
				$distance = $candidate->distance($position);
			}
		}
		if ($slot === null || isset($this->active[$slot->id]) || $this->minedOut($slot, $now)) {
			return array();
		}
		// a landing learned later starts no earlier than the end of the slot's previous base
		foreach ($this->bases as $previous) {
			if ($previous->slot === $slot && $previous->endedAt !== null) {
				$at = max($at, $previous->endedAt);
			}
		}
		$base = new Base($slot, $townHall->owner, $townHall, $at);
		$this->bases[] = $base;
		$this->active[$slot->id] = $base;
		$this->byHall[$townHall->id] = $base;

		return array(new BaseEstablishedEvent($now, $base));
	}

	private static function isTownHall(Unit $unit): bool {
		return $unit->owner !== null && in_array(Units::base($unit->type->id), self::TOWN_HALLS, true);
	}

	private function minedOut(Slot $slot, int $gameloop): bool {
		return (float)$slot->fieldsAt($gameloop) < (float)count($slot->minerals) * $this->minedOutBelow;
	}

	/**
	 * @return list<Event>
	 */
	private function moved(UnitPositionsEvent $event, Context $context): array {
		$ret = $this->begin($event->gameloop, $context);
		foreach ($event->positions as $index => $position) {
			$unit = $context->units->active($index);
			if ($unit !== null && isset($this->landed[$unit->id])) {
				array_push($ret, ...$this->establish($unit, $this->landed[$unit->id], $position, $event->gameloop));
				unset($this->landed[$unit->id]);
			}
		}

		return $ret;
	}

	/**
	 * @return list<Event>
	 */
	private function typeChanged(UnitTypeChangeEvent $event, Context $context): array {
		$ret = $this->begin($event->gameloop, $context);
		$unit = $context->units->get($event->unitId);
		if ($unit === null || !self::isTownHall($unit)) {
			return $ret;
		}
		if (str_ends_with($event->unitType, 'Flying')) {
			$this->flying[$unit->id] = true;
			unset($this->landed[$unit->id]);
			if (isset($this->byHall[$unit->id])) {
				$ret[] = $this->end($this->byHall[$unit->id], $event->gameloop, BaseEnd::Lifted);
			}
		} elseif (isset($this->flying[$unit->id])) {
			unset($this->flying[$unit->id]);
			$this->landed[$unit->id] = $event->gameloop;
		}

		return $ret;
	}
}
