<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\simulate\core;

use holonet\sc2utils\model\event\engine\UnitMorphedEvent;
use holonet\sc2utils\model\event\tracker\UnitBornEvent;
use holonet\sc2utils\model\event\tracker\UnitDiedEvent;
use holonet\sc2utils\model\Point;
use holonet\sc2utils\model\replay\Replay;
use holonet\sc2utils\model\simulation\core\Unit;
use holonet\sc2utils\simulate\Context;
use holonet\sc2utils\simulate\Plugin;

/**
 * Link each Baneling to the Zergling cocoon it hatched from, while the simulation runs.
 *
 * The game ends the cocoon (it turns back into a Zergling and dies) and creates the Baneling as a new unit, in the
 * same gameloop and place. Within that gameloop the birth comes first or the death does,
 * so the link is made when the second half arrives: a Baneling born where and when a cocoon of its owner
 * died, or the reverse. Then it sets Unit::$morphedFrom and yields a UnitMorphedEvent. Runs after ContextLoader, so
 * the unit model already has the event applied.
 *
 * @implements Plugin<null>
 */
final class MorphLinker implements Plugin {
	/**
	 * Banelings born in the current gameloop without a cocoon yet.
	 * @var list<Unit>
	 */
	private array $banelings = array();

	/**
	 * Cocoons that died in the current gameloop without a Baneling yet.
	 * @var list<Unit>
	 */
	private array $cocoons = array();

	/**
	 * The gameloop of the pending halves; older ones are dropped.
	 */
	private int $gameloop = -1;

	public function dependencies(): array {
		return array();
	}

	public function finish(Context $context): void {}

	public function init(Context $context): void {}

	public function result(): mixed {
		return null;
	}

	public function subscriptions(): array {
		return array(
			UnitBornEvent::class => $this->born(...),
			UnitDiedEvent::class => $this->died(...),
		);
	}

	public function supports(Replay $replay): bool {
		return true;
	}

	/**
	 * Drops the pending halves of earlier gameloops.
	 */
	private function at(int $gameloop): void {
		if ($gameloop !== $this->gameloop) {
			$this->gameloop = $gameloop;
			$this->banelings = array();
			$this->cocoons = array();
		}
	}

	/**
	 * @return list<UnitMorphedEvent>
	 */
	private function born(UnitBornEvent $event, Context $context): array {
		$unit = $context->units->get($event->unitId);
		if ($unit === null || $event->unitType !== 'Baneling') {
			return array();
		}
		$this->at($event->gameloop);
		foreach ($this->cocoons as $i => $cocoon) {
			if (self::matches($unit, $cocoon)) {
				array_splice($this->cocoons, $i, 1);

				return array(self::link($event->gameloop, $unit, $cocoon));
			}
		}
		$this->banelings[] = $unit;

		return array();
	}

	/**
	 * @return list<UnitMorphedEvent>
	 */
	private function died(UnitDiedEvent $event, Context $context): array {
		$unit = $context->units->get($event->unitId);
		if ($unit === null || !in_array('BanelingCocoon', $unit->types, true)) {
			return array();
		}
		$this->at($event->gameloop);
		foreach ($this->banelings as $i => $baneling) {
			if (self::matches($baneling, $unit)) {
				array_splice($this->banelings, $i, 1);

				return array(self::link($event->gameloop, $baneling, $unit));
			}
		}
		$this->cocoons[] = $unit;

		return array();
	}

	private static function link(int $gameloop, Unit $baneling, Unit $cocoon): UnitMorphedEvent {
		$baneling->linkMorph($cocoon);

		return new UnitMorphedEvent($gameloop, $baneling, $cocoon);
	}

	/**
	 * Same owner, and the cocoon died where the Baneling was born.
	 */
	private static function matches(Unit $baneling, Unit $cocoon): bool {
		$born = $baneling->startedAt === null ? null : $baneling->positions[$baneling->startedAt] ?? null;
		$died = $cocoon->diedAt === null ? null : $cocoon->positions[$cocoon->diedAt] ?? null;

		return $baneling->owner === $cocoon->owner && $born instanceof Point && $died instanceof Point && $born->x === $died->x && $born->y === $died->y;
	}
}
