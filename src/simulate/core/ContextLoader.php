<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\simulate\core;

use holonet\sc2utils\model\event\engine\UnitOwnerChangeEvent;
use holonet\sc2utils\model\event\tracker\UnitBornEvent;
use holonet\sc2utils\model\event\tracker\UnitDiedEvent;
use holonet\sc2utils\model\event\tracker\UnitDoneEvent;
use holonet\sc2utils\model\event\tracker\UnitInitEvent;
use holonet\sc2utils\model\event\tracker\UnitPositionsEvent;
use holonet\sc2utils\model\event\tracker\UnitTypeChangeEvent;
use holonet\sc2utils\model\event\tracker\UpgradeEvent;
use holonet\sc2utils\model\Point;
use holonet\sc2utils\model\replay\Player;
use holonet\sc2utils\model\replay\Replay;
use holonet\sc2utils\model\simulation\core\Unit;
use holonet\sc2utils\model\simulation\core\UnitModel;
use holonet\sc2utils\simulate\Context;
use holonet\sc2utils\simulate\Plugin;

/**
 * The core plugin: builds the unit model from the tracker events, as sc2reader's ContextLoader does
 * (engine/plugins/context.py).
 *
 * As sc2reader: the owner is the upkeeper (unchanged when the event has none); a unit born or started under an id
 * that exists keeps its object and type; events for unknown units are ignored; a killer counts only if it is a player
 * of the replay and the killing unit only if it exists.
 *
 * @implements Plugin<UnitModel>
 */
final class ContextLoader implements Plugin {
	private UnitModel $units;

	public function __construct() {
		$this->units = new UnitModel();
	}

	public function dependencies(): array {
		return array();
	}

	public function finish(Context $context): void {}

	public function init(Context $context): void {}

	public function result(): UnitModel {
		return $this->units;
	}

	public function subscriptions(): array {
		return array(
			UnitBornEvent::class => $this->born(...),
			UnitInitEvent::class => $this->started(...),
			UnitDoneEvent::class => $this->done(...),
			UnitDiedEvent::class => $this->died(...),
			UnitTypeChangeEvent::class => $this->typeChanged(...),
			UnitOwnerChangeEvent::class => $this->ownerChanged(...),
			UnitPositionsEvent::class => $this->moved(...),
			UpgradeEvent::class => $this->units->addUpgrade(...),
		);
	}

	public function supports(Replay $replay): bool {
		return true;
	}

	/**
	 * A born unit also gets its origin: the creating ability and unit, and whether it is a hallucination.
	 */
	private function born(UnitBornEvent $event): void {
		$unit = $this->start($event->gameloop, $event->unitId, $event->unitType, $event->upkeeper, $event->location, true);
		$unit->originate(
			$event->creatorAbility,
			$event->creatorUnitId === null ? null : $this->units->get($event->creatorUnitId),
			$event->creatorRecorded ? $event->creatorAbility !== null && str_starts_with($event->creatorAbility, 'Hallucination') : null,
		);
	}

	private function died(UnitDiedEvent $event): void {
		$unit = $this->units->get($event->unitId);
		if ($unit === null) {
			return;
		}
		$unit->die($event->gameloop, $event->location, $event->killer, $event->killerUnitId === null ? null : $this->units->get($event->killerUnitId));
		$this->units->deactivate($unit);
	}

	private function done(UnitDoneEvent $event): void {
		$this->units->get($event->unitId)?->finish($event->gameloop);
	}

	private function moved(UnitPositionsEvent $event): void {
		foreach ($event->positions as $index => $position) {
			$this->units->active($index)?->move($event->gameloop, $position);
		}
	}

	private function ownerChanged(UnitOwnerChangeEvent $event): void {
		if ($event->upkeeper !== null) {
			$this->units->get($event->unitId)?->changeOwner($event->upkeeper);
		}
	}

	private function start(int $gameloop, int $id, string $type, ?Player $upkeeper, Point $location, bool $finished): Unit {
		$unit = $this->units->get($id);
		if ($unit === null) {
			$unit = new Unit($id);
			$unit->changeType($gameloop, $type);
		}
		$this->units->activate($unit);
		$unit->start($gameloop, $location, $finished);
		if ($upkeeper !== null) {
			$unit->changeOwner($upkeeper);
		}

		return $unit;
	}

	private function started(UnitInitEvent $event): void {
		$this->start($event->gameloop, $event->unitId, $event->unitType, $event->upkeeper, $event->location, false)->originate(null, null, false);
	}

	private function typeChanged(UnitTypeChangeEvent $event): void {
		$this->units->get($event->unitId)?->changeType($event->gameloop, $event->unitType);
	}
}
