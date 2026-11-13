<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\testing;

use DateTimeImmutable;
use holonet\sc2utils\decode\ReplayMapper;
use holonet\sc2utils\model\Colour;
use holonet\sc2utils\model\enum\Expansion;
use holonet\sc2utils\model\enum\GameMode;
use holonet\sc2utils\model\enum\GameSpeed;
use holonet\sc2utils\model\enum\Race;
use holonet\sc2utils\model\enum\Result;
use holonet\sc2utils\model\GameVersion;
use holonet\sc2utils\model\MapRef;
use holonet\sc2utils\model\replay\Computer;
use holonet\sc2utils\model\replay\Player;
use holonet\sc2utils\model\replay\Replay;
use holonet\sc2utils\model\replay\User;
use holonet\sc2utils\model\ReplayHeader;
use holonet\sc2utils\model\Toon;

/**
 * Builds a Replay without a file, for tests. Defaults: a LotV ladder game at
 * Faster on the current build, 10 minutes long. Players get player ids, slots and user ids in the order they are
 * added; each player is a team of its own unless a team is given.
 *
 *     $replay = new ReplayBuilder()->player('Peter', Race::Zerg)->player('Sally', Race::Protoss)->build();
 */
final class ReplayBuilder {
	private Expansion $expansion = Expansion::LotV;

	private int $gameloops;

	private ?GameMode $gameMode = GameMode::Ladder;

	private string $map = 'Test Map LE';

	/**
	 * @var list<string>
	 */
	private array $observers = array();

	/**
	 * @var list<array{list<string>, ?string, Race, ?int, ?Race, ?Result, ?string}> names, computer name, race, team, pick, result, difficulty
	 */
	private array $players = array();

	private int $seed = 42;

	private GameSpeed $speed = GameSpeed::Faster;

	private GameVersion $version;

	public function __construct() {
		$this->version = new GameVersion(5, 0, 17, 98_310, 98_310);
		$this->gameloops = 13_440; // 10 minutes at 22.4 gameloops per second
	}

	/**
	 * One player controlled by two users (archon).
	 * @param array{string, string} $names
	 */
	public function archon(array $names, Race $race = Race::Protoss, ?int $team = null, ?Result $result = null): self {
		$this->players[] = array($names, null, $race, $team, $race, $result, null);

		return $this;
	}

	public function build(): Replay {
		$users = array();
		$players = array();
		$slot = 0;
		$teams = 0;
		foreach ($this->players as $i => list($names, $computer, $race, $team, $pick, $result, $difficulty)) {
			$controlling = array();
			foreach ($names as $name) {
				$controlling[] = $users[] = new User($slot, count($users), $name, null, new Toon(2, 1, 1_000 + $slot));
				$slot++;
			}
			$teamNumber = $team ?? ++$teams;
			$teams = max($teams, $teamNumber);
			$players[] = new Player(
				$i + 1,
				$teamNumber,
				$pick,
				$race,
				new Colour(180, 20, 30),
				$result,
				100,
				$controlling,
				$computer === null ? null : new Computer($slot++, $computer, $difficulty),
			);
		}
		foreach ($this->observers as $name) {
			$users[] = new User($slot++, count($users), $name, null, new Toon(2, 1, 1_000 + $slot));
		}
		$teamList = ReplayMapper::teams($players);
		$header = new ReplayHeader($this->version, $this->gameloops);

		return new Replay(
			$header,
			$this->expansion,
			$this->speed,
			'eu',
			new MapRef($this->map, str_repeat('ab', 32), 'eu'),
			new DateTimeImmutable('2026-01-01 12:00:00 UTC'),
			1.0,
			ReplayMapper::realLength($header, $this->speed),
			$this->gameMode,
			null,
			ReplayMapper::realType($teamList),
			$this->gameMode === GameMode::Ladder && $this->observers === array() && $this->computers() === 0,
			in_array(true, array_map(static fn (array $player): bool => count($player[0]) > 1, $this->players), true),
			$this->gameMode === GameMode::Ladder,
			$this->gameMode === GameMode::Ladder,
			false,
			false,
			false,
			true,
			$this->seed,
			$players,
			$users,
			$teamList,
		);
	}

	public function computer(string $name = 'A.I. 1 (Harder)', Race $race = Race::Terran, ?int $team = null, string $difficulty = 'Harder', ?Result $result = null): self {
		$this->players[] = array(array(), $name, $race, $team, $race, $result, $difficulty);

		return $this;
	}

	public function expansion(Expansion $expansion): self {
		$this->expansion = $expansion;

		return $this;
	}

	/**
	 * The length in gameloops (22.4 per second at Faster).
	 */
	public function gameloops(int $gameloops): self {
		$this->gameloops = $gameloops;

		return $this;
	}

	public function gameMode(?GameMode $gameMode): self {
		$this->gameMode = $gameMode;

		return $this;
	}

	public function map(string $name): self {
		$this->map = $name;

		return $this;
	}

	public function observer(string $name): self {
		$this->observers[] = $name;

		return $this;
	}

	public function player(string $name, Race $race = Race::Terran, ?int $team = null, ?Race $pick = null, ?Result $result = null): self {
		$this->players[] = array(array($name), null, $race, $team, $pick ?? $race, $result, null);

		return $this;
	}

	public function seed(int $seed): self {
		$this->seed = $seed;

		return $this;
	}

	public function speed(GameSpeed $speed): self {
		$this->speed = $speed;

		return $this;
	}

	/**
	 * The build and base build (major, minor, patch stay 5.0.17 unless given).
	 */
	public function version(int $build, ?int $baseBuild = null, int $major = 5, int $minor = 0, int $patch = 17): self {
		$this->version = new GameVersion($major, $minor, $patch, $build, $baseBuild ?? $build);

		return $this;
	}

	private function computers(): int {
		return count(array_filter($this->players, static fn (array $player): bool => $player[1] !== null));
	}
}
