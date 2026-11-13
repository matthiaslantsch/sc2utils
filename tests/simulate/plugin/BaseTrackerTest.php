<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\tests\simulate\plugin;

use holonet\sc2utils\model\enum\Race;
use holonet\sc2utils\model\event\engine\BaseEstablishedEvent;
use holonet\sc2utils\model\event\engine\BaseLostEvent;
use holonet\sc2utils\model\Point;
use holonet\sc2utils\model\replay\Replay;
use holonet\sc2utils\model\simulation\plugin\Base;
use holonet\sc2utils\model\simulation\plugin\BaseEnd;
use holonet\sc2utils\model\simulation\plugin\MiningBases;
use holonet\sc2utils\model\simulation\plugin\Slot;
use holonet\sc2utils\simulate\Context;
use holonet\sc2utils\simulate\Plugin;
use holonet\sc2utils\simulate\plugin\BaseTracker;
use holonet\sc2utils\simulate\plugin\SlotFinder;
use holonet\sc2utils\testing\EventScript;
use holonet\sc2utils\testing\ReplayBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * The mining base rules with constructed events.
 * @internal
 */
#[CoversClass(BaseTracker::class)]
#[CoversClass(SlotFinder::class)]
#[CoversClass(MiningBases::class)]
#[CoversClass(Base::class)]
#[CoversClass(Slot::class)]
#[Small]
class BaseTrackerTest extends TestCase {
	private const int TERRAN = 1;

	private Point $main;

	private Point $natural;

	private EventScript $script;

	protected function setUp(): void {
		$this->main = new Point(40, 40);
		$this->natural = new Point(100, 100);
		$this->script = new EventScript(new ReplayBuilder()->player('Tom', Race::Terran)->player('Zara', Race::Zerg)->build());
		$this->script->baseLayout($this->main);
		$this->script->baseLayout($this->natural);
	}

	public function test_a_landed_town_hall_is_found_by_a_position_event(): void {
		$cc = $this->startingBase();
		$this->script->at(3_000)->typeChange($cc, 'CommandCenterFlying')->at(3_400)->typeChange($cc, 'CommandCenter');
		$this->script->at(3_840)->positions(array($cc => EventScript::townHallSpot($this->natural)));

		$this->assertSame(array(array(0, 1), array(3_000, 0), array(3_400, 1)), $this->bases()->timeline($this->player()));
	}

	public function test_a_landed_town_hall_is_found_by_the_units_it_creates(): void {
		$cc = $this->startingBase();
		$this->script->at(3_000)->typeChange($cc, 'CommandCenterFlying')->at(3_400)->typeChange($cc, 'CommandCenter');
		$this->script->at(3_500)->born('SCV', self::TERRAN, new Point(101, 92), creator: $cc);

		$bases = $this->bases();
		$this->assertSame(array(array(0, 1), array(3_000, 0), array(3_400, 1)), $bases->timeline($this->player()));
		$this->assertSame(BaseEnd::Lifted, $bases->bases[0]->end);
		$this->assertSame(2, $bases->bases[1]->slot->id, 'landed at the natural');
	}

	public function test_a_landing_learned_later_starts_after_the_slot_was_free(): void {
		$cc = $this->startingBase();
		$other = $this->script->at(100)->init('CommandCenter', self::TERRAN, EventScript::townHallSpot($this->natural));
		$this->script->at(1_000)->done($other);
		$this->script->at(3_000)->typeChange($cc, 'CommandCenterFlying')->at(3_400)->typeChange($cc, 'CommandCenter');
		$this->script->at(3_500)->typeChange($other, 'CommandCenterFlying');
		$this->script->at(3_600)->born('SCV', self::TERRAN, new Point(101, 92), creator: $cc);

		$natural = array_values(array_filter($this->bases()->bases, static fn (Base $base): bool => $base->slot->id === 2));
		$this->assertSame(array(array(1_000, 3_500), array(3_500, null)), array_map(static fn (Base $base): array => array($base->establishedAt, $base->endedAt), $natural));
	}

	public function test_a_town_hall_far_from_minerals_or_at_a_held_slot_is_no_base(): void {
		$this->startingBase();
		$macro = $this->script->at(100)->init('CommandCenter', self::TERRAN, new Point(44, 30));   // 10 cells from the main's minerals
		$far = $this->script->init('CommandCenter', self::TERRAN, new Point(70, 70));
		$this->script->at(1_500)->done($macro)->done($far);

		$this->assertSame(array(array(0, 1)), $this->bases()->timeline($this->player()));
	}

	public function test_bases_are_destroyed_or_mined_out(): void {
		$minerals = $this->script->baseLayout(new Point(160, 40));
		$cc = $this->startingBase();
		$third = $this->script->at(100)->init('CommandCenter', self::TERRAN, EventScript::townHallSpot(new Point(160, 40)));
		$this->script->at(1_000)->done($third);
		foreach (array_slice($minerals, 0, 5) as $i => $field) {
			$this->script->at(5_000 + $i)->died($field, new Point(160, 40));
		}
		$this->script->at(6_000)->died($cc, EventScript::townHallSpot($this->main), 2);

		$bases = $this->bases();
		$this->assertSame(array(array(BaseEnd::Destroyed, 6_000), array(BaseEnd::MinedOut, 5_004)), array_map(static fn (Base $base): array => array($base->end, $base->endedAt), $bases->bases), 'mined out at the fifth of eight fields: fewer than half left');
		$this->assertSame(array(1_000), $bases->timings($this->player()));
		$this->assertSame(0, $bases->countAt($this->player(), 6_000));
	}

	public function test_events_and_threshold(): void {
		$minerals = $this->script->baseLayout(new Point(160, 40));
		$cc = $this->script->born('CommandCenter', self::TERRAN, EventScript::townHallSpot(new Point(160, 40)));
		foreach (array_slice($minerals, 0, 5) as $i => $field) {
			$this->script->at(5_000 + $i)->died($field, new Point(160, 40));
		}
		$this->script->at(6_000)->died($cc, EventScript::townHallSpot(new Point(160, 40)));
		$events = array();
		$listener = new class($events) implements Plugin {
			/**
			 * @param list<string> $events
			 */
			public function __construct(
				public array &$events
			) {}

			public function dependencies(): array {
				return array(BaseTracker::class);
			}

			public function finish(Context $context): void {}

			public function init(Context $context): void {}

			public function result(): mixed {
				return null;
			}

			public function subscriptions(): array {
				return array(
					BaseEstablishedEvent::class => function (BaseEstablishedEvent $event): void {
						$this->events[] = "established {$event->gameloop}";
					},
					BaseLostEvent::class => function (BaseLostEvent $event): void {
						$this->events[] = "lost {$event->gameloop} {$event->base->end?->value}";
					},
				);
			}

			public function supports(Replay $replay): bool {
				return true;
			}
		};
		$this->script->simulate(new BaseTracker(minedOutBelow: 0.25), $listener);

		$this->assertSame(array('established 0', 'lost 6000 destroyed'), $events, 'with a threshold of a quarter, 3 of 8 fields still mine');
	}

	public function test_slots_from_resources(): void {
		$script = new EventScript(new ReplayBuilder()->player('Tom')->build());
		$script->baseLayout(new Point(40, 40));
		// a gold base in two halves of 3 rich fields, 12 cells apart, sharing two geysers
		foreach (array(array(100, 83), array(101, 84), array(102, 85), array(113, 85), array(114, 84), array(115, 83)) as list($x, $y)) {
			$script->born('RichMineralField', null, new Point($x, $y));
		}
		$script->born('RichVespeneGeyser', null, new Point(106, 91));
		$script->born('RichVespeneGeyser', null, new Point(109, 91));
		// two bases whose nearest fields are 5.5 cells apart: one group of 16 at first
		foreach (array(160, 176) as $x) {
			foreach (array(-5.25, -3.75, -2.25, -0.75, 0.75, 2.25, 3.75, 5.25) as $dx) {
				$script->born('MineralField', null, new Point($x + $dx, 40));
			}
		}
		// a path blocker
		$script->born('MineralField', null, new Point(60, 140));
		$script->born('MineralField', null, new Point(61, 141));

		$slots = SlotFinder::find(array_values(array_filter($script->simulate()->units->all(), static fn ($unit): bool => $unit->owner === null)));
		$this->assertSame(array(8, 6, 8, 8), array_map(static fn (Slot $slot): int => count($slot->minerals), $slots));
		$this->assertSame(array(2, 2), array(count($slots[0]->geysers), count($slots[1]->geysers)));
	}

	public function test_starting_bases(): void {
		$this->startingBase();
		$this->script->born('Hatchery', 2, EventScript::townHallSpot($this->natural));

		$bases = $this->bases();
		$this->assertCount(2, $bases->slots);
		$this->assertSame(array(array(1, 0), array(2, 0)), array_map(static fn (Base $base): array => array($base->owner->pid, $base->establishedAt), $bases->bases));
		$this->assertSame(array(), $bases->timings($this->player()));
	}

	public function test_unfinished_town_halls_are_no_bases(): void {
		$this->startingBase();
		$cc = $this->script->at(100)->init('CommandCenter', self::TERRAN, EventScript::townHallSpot($this->natural));
		$this->script->at(1_500)->died($cc, EventScript::townHallSpot($this->natural), 2);

		$this->assertSame(array(array(0, 1)), $this->bases()->timeline($this->player()));
	}

	private function bases(): MiningBases {
		$bases = $this->script->simulate(new BaseTracker())->result(BaseTracker::class);
		$this->assertInstanceOf(MiningBases::class, $bases);

		return $bases;
	}

	private function player(): \holonet\sc2utils\model\replay\Player {
		return $this->script->replay->players[0];
	}

	private function startingBase(): int {
		return $this->script->born('CommandCenter', self::TERRAN, EventScript::townHallSpot($this->main));
	}
}
