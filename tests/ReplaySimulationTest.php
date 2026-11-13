<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\tests;

use holonet\sc2utils\simulate\analysis\Plausibility;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

/**
 * Run a simulation on all test corpus replay files. Check plausibility analysis against a fixed list of "known" problems.
 *
 * @internal
 */
#[CoversClass(Plausibility::class)]
#[Large]
class ReplaySimulationTest extends TestCase {
	use UsesFixtures;

	/**
	 * The findings per replay, (2026-11-13); a replay not listed has none.
	 */
	private const array EXPECTED = array(
		'0.19.0.15976' => array(
			'unknownAbilities' => array('none' => array(38 => 3, 41 => 1, 112 => 2, 138 => 8, 142 => 1, 166 => 2, 176 => 19, 211 => 2)),
		),
		'3.0.0.38215' => array(
			'unknownUnitTypes' => array('PointDefenseDrone' => 1),
		),
		'3.2.2.42253' => array(
			'unknownAbilities' => array('LotV/base' => array(453 => 1, 588 => 1)),
		),
		'3.3.1.43199' => array(
			'unknownAbilities' => array('LotV/base' => array(595 => 8, 596 => 2, 610 => 3, 620 => 6)),
			'unknownUnitTypes' => array('Liberator' => 12),
		),
		'3.4.0.44401' => array(
			'unknownAbilities' => array('LotV/44401' => array(68 => 1, 124 => 10, 142 => 2, 144 => 2, 150 => 21, 154 => 12, 156 => 4, 160 => 1, 178 => 12, 185 => 1, 188 => 31, 238 => 2)),
		),
		'3.7.0.47185' => array(
			'unknownAbilities' => array('LotV/47185' => array(128 => 8, 146 => 1, 154 => 16, 158 => 3, 169 => 9, 171 => 4, 174 => 12)),
		),
		'4.11.4.78285' => array(
			'unknownUnitTypes' => array('Liberator' => 5),
		),
		'4.3.1.65094' => array(
			'unknownAbilities' => array('LotV/59587' => array(462 => 2, 708 => 11)),
		),
		'4.3.2.65384' => array(
			'unknownAbilities' => array('LotV/59587' => array(395 => 7, 515 => 3, 708 => 8)),
		),
		'4.4.0.65895' => array(
			'unknownAbilities' => array('LotV/59587' => array(116 => 5, 853 => 15, 912 => 3, 952 => 1, 1_519 => 3, 1_777 => 3, 1_896 => 32, 2_154 => 1, 2_157 => 7, 2_165 => 5, 2_336 => 4, 2_351 => 3, 2_404 => 2, 2_490 => 2, 2_536 => 2, 2_537 => 1)),
			'unknownUnitTypes' => array('ACHeroSpawnPlacement' => 2, 'ArchAngelCoopFighter' => 1, 'AutomatedAssimilator' => 4, 'DarkPylon' => 4, 'DarkTemplarShakuras' => 24, 'GunTower' => 1, 'HotSSplitterlingBig' => 181, 'HotSSwarmling' => 426, 'HybridDestroyer' => 5, 'HybridDominatorVoid' => 3, 'Scourge' => 51, 'ScourgeNest' => 1, 'Scout' => 5, 'SoACasterVorazun' => 1, 'StalkerShakuras' => 31, 'VoidRiftUnselectable' => 10, 'VoidThrasher' => 10, 'WarfieldBasePlatform' => 1, 'WarfieldFortress' => 1, 'ZagaraCorruptor' => 10, 'ZagaraReviveCocoon' => 2, 'ZagaraVoidCoop' => 1, 'ZealotShakuras' => 1),
			'playersWithoutStartBase' => array('Amon\'s Forces', 'Amon\'s Forces'),
		),
		'4.4.1.66668' => array(
			'unknownAbilities' => array('LotV/59587' => array(709 => 2)),
		),
		'4.5.1.67344' => array(
			'unknownAbilities' => array('LotV/59587' => array(709 => 6)),
			'unknownUnitTypes' => array('ShieldBattery' => 6),
		),
		'4.6.0.67926' => array(
			'unknownAbilities' => array('LotV/59587' => array(395 => 4, 515 => 13, 709 => 27, 710 => 1)),
		),
		'4.6.2.69232' => array(
			'unknownAbilities' => array('LotV/59587' => array(709 => 5)),
		),
		'4.7.1.70326' => array(
			'unknownAbilities' => array('LotV/70154' => array(713 => 4)),
		),
		'4.8.0.71061' => array(
			'unknownAbilities' => array('LotV/70154' => array(536 => 1)),
		),
		'5.0.6.83830' => array(
			'unknownUnitTypes' => array('ShieldBattery' => 2),
		),
	);

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function provide_replay_cases(): iterable {
		foreach (self::keys() as $key) {
			if (isset(self::meta($key)['streams']['details']['file'])) {
				yield $key => array($key);
			}
		}
	}

	#[DataProvider('provide_replay_cases')]
	public function test_findings_of_the_test_replays(string $key): void {
		$file = self::parser()->open(self::replayPath($key));
		if ($file->header->legacyFormat) {
			$this->expectNotToPerformAssertions();

			return;
		}

		$this->assertSame(self::EXPECTED[$key] ?? array(), new Plausibility()->analyse(self::parser()->simulate($file)));
	}
}
