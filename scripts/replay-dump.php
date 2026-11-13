#!/usr/bin/env php
<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * Prints what the library reads from a replay: the game, its players with APM, SPM, bases, hero unit and most used
 * abilities, the chat, and the plausibility findings. An example of using the library, and a dev tool.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

use holonet\sc2utils\model\event\message\ChatEvent;
use holonet\sc2utils\model\replay\Replay;
use holonet\sc2utils\model\simulation\plugin\AbilityUse;
use holonet\sc2utils\model\simulation\plugin\MiningBases;
use holonet\sc2utils\ReplayParser;
use holonet\sc2utils\simulate\analysis\HeroUnits;
use holonet\sc2utils\simulate\analysis\Plausibility;
use holonet\sc2utils\simulate\plugin\AbilityTracker;
use holonet\sc2utils\simulate\plugin\ApmTracker;
use holonet\sc2utils\simulate\plugin\BaseTracker;
use holonet\sc2utils\simulate\plugin\SpmTracker;

require dirname(__DIR__).'/vendor/autoload.php';

const USAGE = <<<'TXT'
replay-dump: print what the library reads from SC2 replays.

usage: replay-dump.php [--strict] [--findings-php] REPLAY...

      --strict        exit with 1 if any replay has plausibility findings
      --findings-php  print only the findings, as a PHP array per replay
  -h, --help          this text

TXT;

$options = getopt('h', array('strict', 'findings-php', 'help'), $rest);
$paths = array_slice($argv, $rest);
if ($options === false || isset($options['h']) || isset($options['help']) || $paths === array()) {
	echo USAGE;

	exit($paths === array() && !isset($options['h']) && !isset($options['help']) ? 1 : 0);
}

$time = static fn (Replay $replay, int $gameloop): string => sprintf('%d:%02d', intdiv((int)$replay->realSeconds($gameloop), 60), (int)$replay->realSeconds($gameloop) % 60);

$parser = new ReplayParser();
$found = false;
foreach ($paths as $path) {
	try {
		$file = $parser->open($path);
		$simulation = $parser->simulate($file);
	} catch (Throwable $e) {
		fwrite(\STDERR, "replay-dump: {$path}: {$e->getMessage()}\n");
		$found = true;

		continue;
	}
	$findings = new Plausibility()->analyse($simulation);
	$found = $found || $findings !== array();
	if (isset($options['findings-php'])) {
		echo var_export($path, true), ' => ', var_export($findings, true), ",\n";

		continue;
	}

	$replay = $simulation->replay;
	echo "{$path}\n";
	printf("  %s, %s %s, %s, %s, %s, %s\n", $replay->map->name, $replay->expansion?->value ?? '?', $replay->header->version, $replay->speed?->value ?? '?', $replay->gameMode?->value ?? '?', $replay->realType, $time($replay, $replay->header->gameloops));
	printf("  played %s UTC\n", $replay->startTime?->format('Y-m-d H:i') ?? '?');

	/** @var MiningBases|null $bases */
	$bases = $simulation->result(BaseTracker::class);
	$heroes = new HeroUnits()->analyse($simulation);
	foreach ($replay->players as $player) {
		printf("\n  %s (%s, team %d, %s)\n", $player->name, $player->playedRace?->value ?? '?', $player->team, $player->result?->value ?? 'no result');
		foreach ($player->users as $user) {
			printf("    %-16s APM %5.0f  SPM %4.0f\n", $user->name, $simulation->result(ApmTracker::class)?->for($user)?->average ?? 0, $simulation->result(SpmTracker::class)?->for($user)?->average ?? 0);
		}
		if ($bases !== null && $bases->slots !== array()) {
			printf("    bases: %d at the end, expansions at %s\n", $bases->countAt($player, $replay->header->gameloops), implode(', ', array_map(static fn (int $gameloop): string => $time($replay, $gameloop), $bases->timings($player))) ?: '-');
		}
		$hero = $heroes->for($player);
		if ($hero !== null) {
			printf("    hero: %s, %d kills\n", $hero->unit->type->name, $hero->kills);
		}
		$abilities = array();
		foreach ($player->users as $user) {
			foreach ($simulation->result(AbilityTracker::class)?->for($user) ?? array() as $key => $use) {
				/** @var AbilityUse $use */
				$abilities[$key] = ($abilities[$key] ?? 0) + $use->count;
			}
		}
		arsort($abilities);
		if ($abilities !== array()) {
			printf("    abilities: %s\n", implode(', ', array_map(static fn (string $key, int $count): string => "{$key} {$count}", array_keys(array_slice($abilities, 0, 6, true)), array_slice($abilities, 0, 6, true))));
		}
	}
	foreach ($replay->observers as $observer) {
		printf("\n  observer %s\n", $observer->name);
	}

	$chat = array_filter($parser->messages($replay, $file), static fn ($message): bool => $message instanceof ChatEvent);
	if ($chat !== array()) {
		echo "\n  chat:\n";
		foreach ($chat as $message) {
			/** @var ChatEvent $message */
			printf("    %s %s to %s: %s\n", $time($replay, $message->gameloop), $message->user?->name ?? '?', $message->recipient->name, $message->text);
		}
	}

	echo "\n  plausibility: ", $findings === array() ? "nothing found\n" : "\n";
	foreach ($findings as $key => $value) {
		printf("    %s: %s\n", $key, json_encode($value, \JSON_UNESCAPED_UNICODE));
	}
	echo "\n";
}

exit(isset($options['strict']) && $found ? 1 : 0);
