<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\tests;

use Generator;
use holonet\bitstream\BitArray;
use holonet\sc2utils\model\replay\Observer;
use holonet\sc2utils\model\replay\Player;
use holonet\sc2utils\model\replay\Replay;
use holonet\sc2utils\model\replay\Team;
use holonet\sc2utils\ReplayParser;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * For tests against the test replays and their fixtures in tests/fixtures/<key>/<kind>/.
 * The readers are static because data providers use them.
 *
 * @internal
 */
trait UsesFixtures {
	protected const string FIXTURES = __DIR__.'/fixtures';

	protected const string REPLAYS = __DIR__.'/test_replays';

	private static ?ReplayParser $parser = null;

	/**
	 * A decoded stream against s2protocol's (tests/fixtures/<key>/decoded/), after normalising byte strings and bit
	 * arrays as the fixtures store them. Event streams (a generator, or a list against a .jsonl.gz fixture) are
	 * compared event by event, reporting only the first difference.
	 *
	 * @param mixed $actual a decoded value, or the events of a stream
	 */
	protected function assertDecodedFixture(string $key, string $stream, mixed $actual): void {
		$file = self::fixtureFile($key, $stream, 'decoded');
		if (!$actual instanceof Generator && !str_ends_with($file, '.jsonl.gz')) {
			$this->assertSame(self::readJson(self::fixturePath($key, 'decoded', $file)), self::normalize($actual));

			return;
		}
		/** @var iterable<mixed> $actual */
		$expected = self::events($key, $stream);
		$expected = is_array($expected) ? $expected : iterator_to_array($expected, false);
		$count = 0;
		foreach ($actual as $i => $event) {
			$event = self::normalize($event);
			if (($expected[$i] ?? null) !== $event) {
				$this->assertSame($expected[$i] ?? null, $event, "event {$i}");
			}
			$count++;
		}
		$this->assertSame(count($expected), $count, 'event count');
	}

	/**
	 * The Replay against its summary fixture: our own manual/summary.json if there is one, otherwise sc2reader's
	 * (tools/fixtures/sc2reader_dump.py). Exact, except the end time:
	 * sc2reader converts FILETIME with an epoch 0.4 s off, so its end times are 0 or 1 s later.
	 */
	protected function assertReplayFixture(string $key, Replay $replay): void {
		$manual = self::fixturePath($key, 'manual', 'summary.json');
		/** @var array<string, mixed> $expected */
		$expected = is_file($manual) ? self::readJson($manual) : self::readJson(self::fixturePath($key, 'sc2reader', self::fixtureFile($key, 'summary', 'sc2reader')));
		$actual = self::summary($replay);

		$this->assertContains((int)$expected['endTime'] - (int)$actual['endTime'], array(0, 1), 'endTime (0 or 1 s behind sc2reader)');
		$actual['endTime'] = $expected['endTime'];
		$this->assertSame($expected, $actual);
	}

	/**
	 * Values of one stream: a .json file is one value (or a list of events), a .jsonl.gz file one value per line.
	 *
	 * @return iterable<mixed>
	 */
	protected static function events(string $key, string $stream, string $kind = 'decoded'): iterable {
		$file = self::fixtureFile($key, $stream, $kind);
		if (!str_ends_with($file, '.jsonl.gz')) {
			/** @var list<mixed> */
			return self::readJson(self::fixturePath($key, $kind, $file));
		}

		return self::jsonLines(self::fixturePath($key, $kind, $file));
	}

	/**
	 * @return list<string> every replay key that has fixtures of this kind
	 */
	protected static function keys(string $kind = 'decoded'): array {
		$ret = array();
		$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::FIXTURES, RecursiveDirectoryIterator::SKIP_DOTS));
		/** @var SplFileInfo $file */
		foreach ($files as $file) {
			if ($file->getFilename() === 'meta.json' && basename($file->getPath()) === $kind) {
				$ret[] = substr(dirname($file->getPath()), strlen(self::FIXTURES) + 1);
			}
		}
		sort($ret);

		return $ret;
	}

	/**
	 * @return array<string, mixed>
	 */
	protected static function meta(string $key, string $kind = 'decoded'): array {
		/** @var array<string, mixed> */
		return self::readJson(self::fixturePath($key, $kind, 'meta.json'));
	}

	protected static function parser(): ReplayParser {
		return self::$parser ??= new ReplayParser();
	}

	protected static function replay(string $key): Replay {
		return self::parser()->replay(self::parser()->open(self::replayPath($key)));
	}

	protected static function replayPath(string $key, string $kind = 'decoded'): string {
		$replay = self::meta($key, $kind)['replay'] ?? null;
		if (!is_string($replay)) {
			throw new RuntimeException("Fixture {$key}/{$kind} names no replay");
		}

		return self::REPLAYS."/{$replay}";
	}

	/**
	 * The file a stream was written to, as listed in meta.json.
	 */
	private static function fixtureFile(string $key, string $stream, string $kind): string {
		$file = self::meta($key, $kind)['streams'][$stream]['file'] ?? null;
		if (!is_string($file)) {
			throw new RuntimeException("Fixture {$key}/{$kind} has no file for {$stream}");
		}

		return $file;
	}

	private static function fixturePath(string $key, string $kind, string $file): string {
		return self::FIXTURES."/{$key}/{$kind}/{$file}";
	}

	/**
	 * @return iterable<mixed>
	 */
	private static function jsonLines(string $path): iterable {
		$gz = gzopen($path, 'rb');
		if ($gz === false) {
			throw new RuntimeException("Cannot open {$path}");
		}

		try {
			while (($line = gzgets($gz)) !== false) {
				yield json_decode($line, true, 512, \JSON_THROW_ON_ERROR);
			}
		} finally {
			gzclose($gz);
		}
	}

	/**
	 * Byte strings that aren't UTF-8 as {hex}, bit arrays as {bits, hex}, as tools/common/jsonio.py writes them.
	 */
	private static function normalize(mixed $value): mixed {
		if (is_string($value)) {
			return mb_check_encoding($value, 'UTF-8') ? $value : array('hex' => bin2hex($value));
		}
		if ($value instanceof BitArray) {
			return array('bits' => $value->width, 'hex' => $value->hex);
		}
		if (is_array($value)) {
			return array_map(self::normalize(...), $value);
		}

		return $value;
	}

	private static function readJson(string $path): mixed {
		$content = file_get_contents($path);
		if ($content === false) {
			throw new RuntimeException("Cannot read {$path}");
		}

		return json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
	}

	/**
	 * The Replay in the summary fixture's shape. Reshapes only: enums as their values, times as Unix timestamps; the
	 * one translation is a missing race pick, which sc2reader calls "Unknown".
	 *
	 * @return array<string, mixed>
	 */
	private static function summary(Replay $replay): array {
		return array(
			'version' => (string)$replay->header->version,
			'baseBuild' => $replay->header->version->baseBuild,
			'gameloops' => $replay->header->gameloops,
			'expansion' => $replay->expansion?->value,
			'speed' => $replay->speed?->value,
			'region' => $replay->region,
			'map' => array(
				'name' => $replay->map->name,
				'hash' => $replay->map->hash,
				'depotRegion' => $replay->map->depotRegion,
			),
			'endTime' => $replay->endTime?->getTimestamp(),
			'utcOffset' => $replay->utcOffset,
			'realLength' => $replay->realLength,
			'gameMode' => $replay->gameMode?->value,
			'teamSize' => $replay->teamSize,
			'realType' => $replay->realType,
			'isLadder' => $replay->isLadder,
			'players' => array_map(static fn (Player $p): array => array(
				'pid' => $p->pid,
				'slot' => $p->slot,
				'name' => $p->name,
				'clanTag' => $p->clanTag,
				'toon' => $p->toon === null ? null : array($p->toon->region, $p->toon->realm, $p->toon->id),
				'pickedRace' => $p->pickedRace->value ?? 'Unknown',
				'playedRace' => $p->playedRace?->value,
				'result' => $p->result?->value,
				'human' => $p->isHuman,
				'color' => $p->colour->name,
				'rgba' => array($p->colour->r, $p->colour->g, $p->colour->b, $p->colour->a),
				'team' => $p->team,
			), $replay->players),
			'observers' => array_map(static fn (Observer $o): array => array(
				'name' => $o->name,
				'clanTag' => $o->clanTag,
				'toon' => $o->toon === null ? null : (string)$o->toon,
			), $replay->observers),
			'teams' => array_map(static fn (Team $t): array => array(
				'number' => $t->number,
				'players' => array_map(static fn (Player $p): int => $p->pid, $t->players),
				'result' => $t->result?->value,
				'lineup' => $t->lineup,
			), $replay->teams),
		);
	}
}
