<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\tests\gamedata;

use DateTimeImmutable;
use DateTimeZone;
use holonet\sc2utils\gamedata\Season;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Season::class)]
#[Small]
class SeasonTest extends TestCase {
	public function test_newest_season_is_recent(): void {
		$seasons = Season::all();
		$newest = $seasons[array_key_last($seasons)];
		$days = (int)$newest->start->diff(new DateTimeImmutable('now', new DateTimeZone('UTC')))->days;
		$this->assertLessThanOrEqual(130, $days);
	}

	public function test_season_table_is_consistent(): void {
		$seasons = Season::all();
		$expectedNumber = array();
		foreach ($seasons as $i => $season) {
			$name = $season->name();
			$next = $seasons[$i + 1] ?? null;

			$expectedNumber[$season->year] = ($expectedNumber[$season->year] ?? 0) + 1;
			$this->assertSame($expectedNumber[$season->year], $season->number, "{$name} is numbered out of order");

			if ($season->lock !== null) {
				$this->assertGreaterThan($season->start, $season->lock, "{$name} locks before it starts");
				$this->assertTrue($season->close === null || $season->lock < $season->close, "{$name} locks after it closes");
			}

			if ($next === null) {
				$this->assertNull($season->close, "the newest season {$name} is closed");

				continue;
			}

			$this->assertGreaterThan($season->start, $next->start, "{$next->name()} starts before {$name}");
			$this->assertTrue($season->lock === null || $season->lock < $next->start, "{$name} locks after {$next->name()} starts");
			if ($season->close !== null) {
				$this->assertGreaterThan($season->start, $season->close, "{$name} closes before it starts");
				$this->assertLessThanOrEqual($next->start, $season->close, "{$name} closes after {$next->name()} starts");
			}
		}
	}
}
