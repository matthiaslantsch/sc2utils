#!/usr/bin/env php
<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * Downloads the maps of SC2 replays from the Battle.net depot with the library's MapDownloader (the replay's own
 * depot, then us/eu/kr with the same hash; the SHA-256 is checked before anything is written). Maps are saved as
 * <hash>.s2ma in the output directory; maps already present are skipped, so it can be rerun at any time. Choosing
 * the curated maps for tests/test_maps/ stays manual.
 *
 * usage (from the repo root): php tools/fixtures/fetch_maps.php --out tools/.cache/maps tests/test_replays
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

use holonet\sc2utils\MapDownloader;
use holonet\sc2utils\MapDownloadException;
use holonet\sc2utils\ReplayParser;

require dirname(__DIR__, 2).'/vendor/autoload.php';

const USAGE = <<<'TXT'
usage: fetch_maps.php --out DIR [--timeout SECONDS] [--delay SECONDS] PATH...

  PATH        replay files or directories (searched recursively for *.SC2Replay)
  --out       directory to save the maps in (created if missing)
  --timeout   timeout per request in seconds; default 120
  --delay     seconds to wait before each request; default 0

TXT;

/**
 * @param list<string> $paths
 * @return Generator<int, string>
 */
function findReplays(array $paths): Generator {
	foreach ($paths as $path) {
		if (!is_dir($path)) {
			yield $path;

			continue;
		}
		$files = array();
		foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $file) {
			/** @var SplFileInfo $file */
			if (strtolower($file->getExtension()) === 'sc2replay') {
				$files[] = $file->getPathname();
			}
		}
		sort($files, \SORT_STRING);

		yield from $files;
	}
}

$rest = 0;
$options = getopt('h', array('out:', 'timeout:', 'delay:', 'help'), $rest);
$paths = array_slice($argv, $rest);
if (isset($options['h']) || isset($options['help']) || !isset($options['out']) || $paths === array()) {
	fwrite(\STDERR, USAGE);

	exit(isset($options['h']) || isset($options['help']) ? 0 : 2);
}

$out = (string)$options['out'];
$delay = (float)($options['delay'] ?? 0);
if (!is_dir($out) && !mkdir($out, 0o777, true)) {
	fwrite(\STDERR, "cannot create {$out}\n");

	exit(2);
}

$downloader = new class((float)($options['timeout'] ?? 120), $delay) extends MapDownloader {
	public function __construct(
		float $timeout,
		private readonly float $delay
	) {
		parent::__construct($timeout);
	}

	protected function fetch(string $url): string {
		usleep((int)($this->delay * 1_000_000));

		return parent::fetch($url);
	}
};

$parser = new ReplayParser();
$seen = array();
$counts = array('downloaded' => 0, 'present' => 0, 'skipped' => 0, 'failed' => 0);

foreach (findReplays($paths) as $path) {
	try {
		$file = $parser->open($path);
		if ($file->header->legacyFormat) {
			// the depots of the WoL beta (krb/eub/usb) are gone
			echo "skip      {$path}: WoL beta, its depot is gone\n";
			++$counts['skipped'];

			continue;
		}
		$map = $parser->replay($file)->map;
	} catch (Throwable $e) {
		echo "skip      {$path}: ".$e::class.": {$e->getMessage()}\n";
		++$counts['skipped'];

		continue;
	}

	if (isset($seen[$map->hash])) {
		continue;
	}
	$seen[$map->hash] = true;
	$short = substr($map->hash, 0, 12);

	$target = "{$out}/{$map->hash}.{$map->type}";
	if (file_exists($target)) {
		echo "present   {$short}  {$path}\n";
		++$counts['present'];

		continue;
	}

	try {
		$url = $downloader->download($map, $target);
	} catch (MapDownloadException $e) {
		echo "FAILED    {$short}  {$path}\n";
		foreach (array_slice(explode("\n", $e->getMessage()), 1) as $error) {
			echo "          {$error}\n";
		}
		++$counts['failed'];

		continue;
	}

	$via = $url === $map->depotUrl ? '' : '  (fallback '.parse_url($url, \PHP_URL_HOST).')';
	printf("download  %s  %.1f MB  %s%s\n", $short, filesize($target) / 1e6, $path, $via);
	++$counts['downloaded'];
}

echo implode(', ', array_map(static fn (string $k, int $n): string => "{$n} {$k}", array_keys($counts), $counts)), "\n";

exit($counts['failed'] > 0 ? 1 : 0);
