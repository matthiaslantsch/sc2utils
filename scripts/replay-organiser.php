#!/usr/bin/env php
<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * Renames and sorts SC2 replays by a template, e.g. into subfolders by date and matchup.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

use holonet\sc2utils\ReplayFormatter;
use holonet\sc2utils\ReplayParser;

require dirname(__DIR__).'/vendor/autoload.php';

const USAGE = <<<'TXT'
replay-organiser: rename and sort SC2 replays by a template.

usage: replay-organiser.php -f FORMAT [-t TARGET] [-d DATEFORMAT] [-p PLAYERFORMAT] [--dry-run]

  -f, --format        the new path of each replay relative to the target directory, without ".SC2Replay";
                      "/" makes subfolders. Tokens:
                        {date} {map} {region} {version} {basebuild} {expansion} {speed} {gamelength}
                        {gametype} {matchup} {vs} {mode} {identifier}
  -t, --target        a replay file or a directory (searched recursively); default: the current directory.
                      Replays are moved relative to that directory (a file's own directory).
  -d, --dateformat    PHP date() format for {date} (UTC); default "Y-m-d H-i"
  -p, --playerformat  one player in {vs}; default "{clantag}{name} ({race_short})". Tokens:
                        {name} {clantag} {race} {race_short} {pick} {pick_short} {team} {toon}
      --dry-run       only print what would be moved
  -h, --help          this text

A name that is taken gets " (2)", " (3)", ... appended; nothing is overwritten.

example: replay-organiser.php -t ~/replays -d "Y-m-d" -f "{date}/{gametype}/{matchup}/{vs} on {map}"

TXT;

function fail(string ...$message): never {
	$message = implode('', $message);
	fwrite(\STDERR, "replay-organiser: {$message}\n");

	exit(1);
}

$options = getopt('f:t:d:p:h', array('format:', 'target:', 'dateformat:', 'playerformat:', 'dry-run', 'help'));
if ($options === false || isset($options['h']) || isset($options['help'])) {
	echo USAGE;

	exit(0);
}
$option = static fn (string $short, string $long, ?string $default = null): ?string => (string)($options[$short] ?? $options[$long] ?? $default);
$format = $option('f', 'format') ?: fail('a format is required (-f), see --help');
$target = $option('t', 'target', (string)getcwd());
$dryRun = isset($options['dry-run']);

try {
	ReplayFormatter::validate($format);
	$formatter = ReplayFormatter::forFileNames(
		$option('d', 'dateformat', 'Y-m-d H-i'),
		$option('p', 'playerformat', ReplayFormatter::DEFAULT_PLAYER_FORMAT),
	);
} catch (InvalidArgumentException $e) {
	fail($e->getMessage());
}

if (is_file($target)) {
	$files = array($target);
	$root = dirname($target);
} elseif (is_dir($target)) {
	$files = array();
	foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS)) as $file) {
		if ($file->isFile() && strcasecmp($file->getExtension(), 'SC2Replay') === 0) {
			$files[] = $file->getPathname();
		}
	}
	sort($files);
	$root = $target;
} else {
	fail("{$target} is neither a replay file nor a directory");
}

$parser = new ReplayParser();
$moved = 0;
$failed = 0;
foreach ($files as $file) {
	try {
		$name = $formatter->format($format, $parser->replay($parser->open($file)));
	} catch (Throwable $e) {
		echo "skip  {$file}: {$e->getMessage()}\n";
		$failed++;

		continue;
	}

	// find a free path in case the new name would override an existing file
	// the current path counts as free (it's the same file)
	$targetPath = "{$root}/{$name}";
	for ($i = 1; ; $i++) {
		$destination = sprintf('%s%s.SC2Replay', $targetPath, ($i === 1 ? '' : " ({$i})"));
		if (!file_exists($destination) || realpath($destination) === realpath($file)) {
			break;
		}
	}
	if (realpath($destination) === realpath($file)) {
		continue;
	}

	$prefix = ($dryRun ? 'would ' : '');
	echo "{$prefix} move {$file}\n   -> {$destination}\n";
	if (!$dryRun) {
		if (!is_dir(dirname($destination)) && !mkdir(dirname($destination), 0o777, true)) {
			fail('cannot create ', dirname($destination));
		}
		if (!rename($file, $destination)) {
			fail("cannot move {$file}");
		}
		// a folder the replay leaves empty goes too; fails silently if it isn't empty
		@rmdir(dirname($file));
	}
	$moved++;
}

$prefix = ($dryRun ? 'Would move' : 'Moved');
echo "{$prefix} {$moved} replay(s), skipped {$failed}\n";
