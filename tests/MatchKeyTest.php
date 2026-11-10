<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\tests;

use holonet\sc2utils\model\replay\Replay;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Replay::class)]
#[Medium]
class MatchKeyTest extends TestCase {
	use UsesFixtures;

	/**
	 * Every directory in here holds the recordings of one match (different players' views, observers, players who left early).
	 */
	private const string SAME_MATCH = self::REPLAYS.'/same_match';

	public function test_same_match_shares_one_key_and_different_matches_never(): void {
		$groups = array();
		$keysOfMatch = array();
		$paths = array_merge(glob(self::REPLAYS.'/*/*.SC2Replay') ?: array(), glob(self::SAME_MATCH.'/*/*.SC2Replay') ?: array());
		foreach ($paths as $path) {
			$file = self::parser()->open($path);
			if ($file->header->legacyFormat) {
				continue;
			}
			$key = self::parser()->replay($file)->matchKey;
			$match = dirname($path, 2) === self::SAME_MATCH ? basename(dirname($path)) : $path;
			$groups[$key][$match] = true;
			$keysOfMatch[$match][$key] = true;
		}

		$this->assertGreaterThan(40, count($groups), 'matches with a key');
		foreach ($groups as $key => $matches) {
			$this->assertCount(1, $matches, "key {$key} is shared by different matches: ".implode(', ', array_keys($matches)));
		}
		$sameMatch = array_map(basename(...), glob(self::SAME_MATCH.'/*', \GLOB_ONLYDIR) ?: array());
		$this->assertGreaterThanOrEqual(5, count($sameMatch));
		foreach ($sameMatch as $match) {
			$this->assertCount(1, $keysOfMatch[$match] ?? array(), "{$match} must have exactly one key");
		}
	}
}
