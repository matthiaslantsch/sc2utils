<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\tests\simulate\analysis;

use holonet\sc2utils\model\enum\Race;
use holonet\sc2utils\model\Point;
use holonet\sc2utils\model\simulation\analysis\Hero;
use holonet\sc2utils\simulate\analysis\HeroUnits;
use holonet\sc2utils\testing\EventScript;
use holonet\sc2utils\testing\ReplayBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * The kill rule with constructed events: credit through the creator chain, what doesn't count, ties.
 * @internal
 */
#[CoversClass(HeroUnits::class)]
#[CoversClass(Hero::class)]
#[Small]
class HeroUnitsTest extends TestCase {
	private const int PROTOSS = 1;

	private const int ZERG = 2;

	private int $gameloop = 300;

	private EventScript $script;

	protected function setUp(): void {
		$this->script = new EventScript(new ReplayBuilder()->player('Pia', Race::Protoss)->player('Zack', Race::Zerg)->build());
	}

	public function test_kills_of_spawned_units_count_for_their_creator(): void {
		$carrier = $this->script->at(100)->born('Carrier', self::PROTOSS, new Point(10, 10));
		$interceptor = $this->script->born('Interceptor', self::PROTOSS, new Point(10, 10), creator: $carrier);
		$this->kill($interceptor, 3);

		$hero = $this->heroes()[self::PROTOSS] ?? null;
		$this->assertSame(array($carrier, 3), array($hero?->unit->id, $hero?->kills), 'the tracker credits the Interceptor');
	}

	public function test_temporary_hallucinated_own_and_neutral_victims_dont_count(): void {
		$stalker = $this->script->at(100)->born('Stalker', self::PROTOSS, new Point(10, 10));
		$swarmHost = $this->script->born('SwarmHostMP', self::ZERG, new Point(20, 20));
		$locust = $this->script->born('LocustMP', self::ZERG, new Point(20, 20), creator: $swarmHost);
		$phoenix = $this->script->born('Phoenix', self::PROTOSS, new Point(10, 10), ability: 'HallucinationPhoenix');
		$probe = $this->script->born('Probe', self::PROTOSS, new Point(10, 10));
		$rock = $this->script->born('DestructibleRock', null, new Point(30, 30));
		$this->script->at(200)->died($locust, new Point(20, 20), self::PROTOSS, $stalker);
		$this->script->died($phoenix, new Point(10, 10), self::ZERG, $swarmHost);
		$this->script->died($probe, new Point(10, 10), self::PROTOSS, $stalker);
		$this->script->died($rock, new Point(30, 30), self::PROTOSS, $stalker);

		$this->assertSame(array(), $this->heroes());
	}

	public function test_ties_go_to_the_unit_that_came_first(): void {
		$early = $this->script->at(100)->born('Zealot', self::PROTOSS, new Point(10, 10));
		$late = $this->script->at(200)->born('Zealot', self::PROTOSS, new Point(10, 10));
		$this->kill($late, 2);
		$this->kill($early, 2);

		$this->assertSame($early, $this->heroes()[self::PROTOSS]?->unit->id);
	}

	/**
	 * @return array<int, Hero> by player id
	 */
	private function heroes(): array {
		$ret = array();
		foreach (new HeroUnits()->analyse($this->script->simulate()) as $pid => list(, $hero)) {
			$ret[$pid] = $hero;
		}

		return $ret;
	}

	private function kill(int $killer, int $victims): void {
		for ($i = 0; $i < $victims; $i++) {
			$zergling = $this->script->at($this->gameloop++)->born('Zergling', self::ZERG, new Point(15, 15));
			$this->script->at($this->gameloop++)->died($zergling, new Point(15, 15), self::PROTOSS, $killer);
		}
	}
}
