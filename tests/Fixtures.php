<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\tests;

use holonet\bitstream\BitArray;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Access to the generated fixtures in tests/fixtures/<key>/<kind>/ (see tools/README.md).
 * @internal
 */
final class Fixtures {
	public const string DIR = __DIR__.'/fixtures';

	public const string REPLAYS = __DIR__.'/test_replays';

	/**
	 * Values of one stream: a .json file is one value (or a list of events), a .jsonl.gz file one value per line.
	 *
	 * @return iterable<mixed>
	 */
	public static function events(string $key, string $stream, string $kind = 'decoded'): iterable {
		$file = self::file($key, $stream, $kind);
		if (!str_ends_with($file, '.jsonl.gz')) {
			/** @var list<mixed> */
			return self::readJson(self::path($key, $kind, $file));
		}

		return self::jsonLines(self::path($key, $kind, $file));
	}

	/**
	 * The file a stream was written to, as listed in meta.json.
	 */
	public static function file(string $key, string $stream, string $kind = 'decoded'): string {
		$file = self::meta($key, $kind)['streams'][$stream]['file'] ?? null;
		if (!is_string($file)) {
			throw new RuntimeException("Fixture {$key}/{$kind} has no file for {$stream}");
		}

		return $file;
	}

	public static function json(string $key, string $stream, string $kind = 'decoded'): mixed {
		return self::readJson(self::path($key, $kind, self::file($key, $stream, $kind)));
	}

	/**
	 * @return list<string> every replay key that has fixtures of this kind
	 */
	public static function keys(string $kind = 'decoded'): array {
		$ret = array();
		$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::DIR, RecursiveDirectoryIterator::SKIP_DOTS));
		/** @var SplFileInfo $file */
		foreach ($files as $file) {
			if ($file->getFilename() === 'meta.json' && basename($file->getPath()) === $kind) {
				$ret[] = substr(dirname($file->getPath()), strlen(self::DIR) + 1);
			}
		}
		sort($ret);

		return $ret;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function meta(string $key, string $kind = 'decoded'): array {
		/** @var array<string, mixed> */
		return self::readJson(self::path($key, $kind, 'meta.json'));
	}

	public static function normalize(mixed $value): mixed {
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

	public static function replayPath(string $key, string $kind = 'decoded'): string {
		$replay = self::meta($key, $kind)['replay'] ?? null;
		if (!is_string($replay)) {
			throw new RuntimeException("Fixture {$key}/{$kind} names no replay");
		}

		return self::REPLAYS."/{$replay}";
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

	private static function path(string $key, string $kind, string $file): string {
		return self::DIR."/{$key}/{$kind}/{$file}";
	}

	private static function readJson(string $path): mixed {
		$content = file_get_contents($path);
		if ($content === false) {
			throw new RuntimeException("Cannot read {$path}");
		}

		return json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
	}
}
