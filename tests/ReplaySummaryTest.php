<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\tests;

use holonet\sc2utils\decode\ReplayInfoFile;
use holonet\sc2utils\decode\ReplayMapper;
use holonet\sc2utils\ReplayFile;
use holonet\sc2utils\ReplayParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ReplayFile::class)]
#[CoversClass(ReplayParser::class)]
#[CoversClass(ReplayMapper::class)]
#[CoversClass(ReplayInfoFile::class)]
#[Medium]
class ReplaySummaryTest extends TestCase {
	use UsesFixtures;

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function provide_replay_cases(): iterable {
		foreach (self::keys() as $key) {
			yield $key => array($key);
		}
	}

	#[DataProvider('provide_replay_cases')]
	public function test_summary(string $key): void {
		$this->assertReplayFixture($key, self::replay($key));
	}
}
