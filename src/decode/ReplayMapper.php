<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\decode;

use DateTimeImmutable;
use DateTimeZone;
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
use holonet\sc2utils\model\replay\Team;
use holonet\sc2utils\model\replay\User;
use holonet\sc2utils\model\ReplayHeader;
use holonet\sc2utils\model\Toon;
use holonet\sc2utils\protocol\DecodeException;

/**
 * Turns decoded replay files (s2protocol's arrays, resolved attributes) into the domain model.
 *
 * A port of sc2reader's load_details() and load_players() (resources.py) and the entity classes (objects.py).
 *
 * @psalm-type Attributes = array<int, array<string, string|null>>
 * @internal
 */
final class ReplayMapper {
	/**
	 * Below this, the UTC adjustment is an offset; above, it is the local FILETIME (sc2reader's rule).
	 */
	private const int FILETIME_DAY = 10 ** 7 * 60 * 60 * 24;

	private const int FILETIME_HOUR = 10 ** 7 * 60 * 60;

	/**
	 * FILETIME (100 ns since 1601) of the Unix epoch.
	 */
	private const int FILETIME_UNIX_EPOCH = 116_444_736_000_000_000;

	/**
	 * A cache handle: four bytes file type, four bytes depot region, 32 bytes SHA-256.
	 *
	 * @return array{type: string, server: string, hash: string}
	 */
	public static function cacheHandle(string $bytes): array {
		if (strlen($bytes) !== 40) {
			throw new DecodeException('A cache handle has 40 bytes, got '.strlen($bytes));
		}

		return array(
			'type' => substr($bytes, 0, 4),
			'server' => strtolower(trim(substr($bytes, 4, 4), "\0 ")),
			'hash' => bin2hex(substr($bytes, 8)),
		);
	}

	public static function endTime(int $fileTime): DateTimeImmutable {
		$seconds = intdiv($fileTime - self::FILETIME_UNIX_EPOCH, 10 ** 7);

		return new DateTimeImmutable("@{$seconds}")->setTimezone(new DateTimeZone('UTC'));
	}

	/**
	 * @param array<string, mixed> $raw the replay header as s2protocol decodes it
	 */
	public static function header(array $raw): ReplayHeader {
		/** @var array{m_major: int, m_minor: int, m_revision: int, m_build: int, m_baseBuild: int} $version */
		$version = $raw['m_version'];
		/** @var int $gameloops */
		$gameloops = $raw['m_elapsedGameLoops'];

		return new ReplayHeader(
			new GameVersion($version['m_major'], $version['m_minor'], $version['m_revision'], $version['m_build'], $version['m_baseBuild']),
			$gameloops,
		);
	}

	/**
	 * Length in whole real seconds: 16 gameloops per game second at every speed and in every expansion, divided by
	 * the speed's multiplier (Normal without a speed).
	 */
	public static function realLength(ReplayHeader $header, ?GameSpeed $speed): int {
		// the epsilon keeps exact quotients like 22400 / 16 / 1.4 = 1000 from flooring to 999 in binary floating point
		return (int)floor(($speed ?? GameSpeed::Normal)->seconds($header->gameloops) + 1e-9);
	}

	/**
	 * sc2reader's real_type: team sizes ascending, e.g. "1v1", "1v2"; FFA for more than two teams of one. A team's
	 * size counts its players, so an archon pair counts once and archon 2v2 is "2v2".
	 *
	 * @param list<Team> $teams
	 */
	public static function realType(array $teams): string {
		$sizes = array_map(static fn (Team $team): int => count($team->players), $teams);
		if (count($sizes) > 2 && array_sum($sizes) === count($sizes)) {
			return 'FFA';
		}
		sort($sizes);

		return implode('v', $sizes);
	}

	/**
	 * The replay from whatever the file has: details (or the details-shaped replay.info of the WoL betas 0.2 to 0.10,
	 * ReplayInfoFile::toDetails()), initdata if a protocol reads it, attributes if the file has them.
	 *
	 * @param array<string, mixed> $details
	 * @param array<string, mixed>|null $initdata
	 * @param Attributes $attributes
	 */
	public static function replay(ReplayHeader $header, array $details, ?array $initdata, array $attributes): Replay {
		$playerList = $details['m_playerList'] ?? null;
		if (!is_array($playerList) || !array_is_list($playerList)) {
			throw new DecodeException(sprintf('Expected a list, got %s', get_debug_type($playerList)));
		}
		$handles = $details['m_cacheHandles'] ?? null;
		if (!is_array($handles) || !array_is_list($handles)) {
			throw new DecodeException(sprintf('Expected a list, got %s', get_debug_type($handles)));
		}
		$handles = array_map(self::cacheHandle(...), $handles);
		if ($handles === array()) {
			throw new DecodeException('replay.details has no cache handles');
		}
		$map = $handles[array_key_last($handles)];
		$expansion = Expansion::fromDependencies(array_column($handles, 'hash'));
		$game = $attributes[AttributeEntry::SCOPE_GAME] ?? array();
		$speed = GameSpeed::tryFrom((string)($game['Game Speed'] ?? ''));
		if (!isset($game['Game Speed']) && is_int($details['m_gameSpeed'] ?? null)) {
			$speed = GameSpeed::fromIndex($details['m_gameSpeed']);
		}
		$fileTime = $details['m_timeUTC'] ?? null;
		$adjustment = $details['m_timeLocalOffset'] ?? null;
		/** @var string $title */
		$title = $details['m_title'];

		/** @var array{m_lobbyState: array{m_slots: list<array<string, mixed>>}, m_userInitialData: list<array<string, mixed>>, m_gameDescription: array{m_randomValue: int, m_gameOptions: array<string, mixed>}}|null $lobby */
		$lobby = $initdata['m_syncLobbyState'] ?? null;
		list($players, $users) = $lobby === null
			? self::fromDetails($playerList, $attributes)
			: self::fromLobby($lobby['m_lobbyState']['m_slots'], $lobby['m_userInitialData'], $playerList, $attributes);
		$teams = self::teams($players);
		$gameMode = GameMode::tryFrom((string)($game['Game Mode'] ?? ''));
		$options = $lobby['m_gameDescription']['m_gameOptions'] ?? array();
		$option = static fn (string $name): bool => ($options[$name] ?? false) === true;

		return new Replay(
			$header,
			$expansion,
			$speed,
			// sc2reader: the first handle's region, Singapore as sea
			$handles[0]['server'] === 'sg' ? 'sea' : $handles[0]['server'],
			new MapRef($title, $map['hash'], $map['server'], $map['type']),
			is_int($fileTime) ? self::endTime($fileTime) : null,
			is_int($fileTime) && is_int($adjustment) ? self::utcOffset($adjustment, $fileTime) : null,
			self::realLength($header, $speed),
			$gameMode,
			$game['Teams'] ?? null,
			self::realType($teams),
			// sc2reader: observers or computers make a ladder game not ladder (HotS marks vs AI games as amm)
			$gameMode === GameMode::Ladder && !self::hasObservers($players, $users) && !in_array(false, array_map(static fn (Player $p): bool => $p->isHuman, $players), true),
			$lobby !== null && in_array(true, array_map(self::isArchonPartner(...), $lobby['m_lobbyState']['m_slots']), true),
			$option('m_amm'),
			$option('m_ranked'),
			$option('m_competitive'),
			$option('m_practice'),
			$option('m_cooperative'),
			$option('m_battleNet'),
			$lobby['m_gameDescription']['m_randomValue'] ?? 0,
			$players,
			$users,
			$teams,
		);
	}

	/**
	 * Players grouped into teams, ordered by team number. A team's result is its players' common result, Unknown if
	 * they differ.
	 *
	 * @param list<Player> $players
	 *
	 * @return list<Team>
	 */
	public static function teams(array $players): array {
		$members = array();
		foreach ($players as $player) {
			$members[$player->team][] = $player;
		}
		ksort($members);

		$ret = array();
		foreach ($members as $number => $teamPlayers) {
			$results = array_unique(array_map(static fn (Player $p): string => $p->result?->value ?? '', $teamPlayers));
			$result = count($results) === 1 ? Result::tryFrom($results[0]) : Result::Unknown;
			$ret[] = new Team($number, $teamPlayers, $result);
		}

		return $ret;
	}

	/**
	 * The recording client's offset from UTC in hours. Depending on the build, replay.details stores the offset or
	 * the local FILETIME (sc2reader's rule).
	 */
	public static function utcOffset(int $adjustment, int $fileTime): float {
		return $adjustment < self::FILETIME_DAY
			? $adjustment / self::FILETIME_HOUR
			: ($adjustment - $fileTime) / self::FILETIME_HOUR;
	}

	/**
	 * @param list<mixed> $detailPlayers
	 *
	 * @return array<string, mixed>
	 */
	private static function detailPlayer(array $detailPlayers, int $index): array {
		$ret = $detailPlayers[$index] ?? null;
		if (!is_array($ret)) {
			throw new DecodeException("replay.details has no player entry {$index}");
		}

		/** @var array<string, mixed> */
		return $ret;
	}

	/**
	 * The details entries by m_workingSetSlotId, null before the field existed (24764).
	 *
	 * @param list<mixed> $detailPlayers
	 *
	 * @return array<int, array<string, mixed>>|null
	 */
	private static function detailsByWorkingSetSlot(array $detailPlayers): ?array {
		$ret = array();
		foreach ($detailPlayers as $index => $detail) {
			/** @var array<string, mixed> $detail */
			$workingSetSlot = $detail['m_workingSetSlotId'] ?? null;
			if (!is_int($workingSetSlot)) {
				return null;
			}
			$ret[$workingSetSlot] = $detail;
		}

		return $ret === array() ? null : $ret;
	}

	/**
	 * The players as replay.details lists them: entries without a name are empty
	 * slots and left out, the slot is the index in the list, player ids count from 1 in list order,
	 * and the attribute scope is the player id. Each player has one user or a computer.
	 * What the entry lacks takes defaults.
	 *
	 * @param list<mixed> $detailPlayers
	 * @param Attributes $attributes
	 *
	 * @return array{list<Player>, list<User>}
	 */
	private static function fromDetails(array $detailPlayers, array $attributes): array {
		$players = array();
		$users = array();
		foreach ($detailPlayers as $slotId => $detail) {
			/** @var array{m_name: string, m_race: string, m_color: array{m_r: int, m_g: int, m_b: int, m_a: int}, m_toon?: array<string, mixed>, m_control?: int, m_teamId?: int, m_result?: int, m_handicap?: int} $detail */
			if ($detail['m_name'] === '') {
				continue;
			}
			$pid = count($players) + 1;
			$playerAttributes = $attributes[$pid] ?? array();
			$computer = null;
			$controlling = array();
			if (($detail['m_control'] ?? 2) === 3) {
				$computer = new Computer($slotId, $detail['m_name'], $playerAttributes['Difficulty'] ?? null);
			} else {
				$toon = $detail['m_toon'] ?? array();
				$controlling[] = $users[] = new User(
					$slotId,
					count($users),
					$detail['m_name'],
					null,
					is_int($toon['m_region'] ?? null) && is_int($toon['m_realm'] ?? null) && is_int($toon['m_id'] ?? null)
						? new Toon($toon['m_region'], $toon['m_realm'], $toon['m_id'])
						: null,
				);
			}
			$players[] = self::player(
				$pid,
				isset($detail['m_teamId']) ? $detail['m_teamId'] + 1 : $pid,
				$detail['m_handicap'] ?? 100,
				$detail,
				$playerAttributes,
				$controlling,
				$computer,
			);
		}

		return array($players, $users);
	}

	/**
	 * Players and users from the lobby slots. Differs from sc2reader's load_players() where that one is wrong
	 * (archon games) and agrees everywhere else:
	 * - a human slot (control 2) is a user; it controls a player unless it observes (observers and referees);
	 * - a computer slot (control 3) controls a player if it has a replay.details entry; computer archon partners and
	 *   the extra co-op slots have none;
	 * - every other slot is open or closed and skipped.
	 *
	 * Slots and details entries are paired by m_workingSetSlotId (both have it since 24764; it is not the slot
	 * index). Before that they are paired in order, as sc2reader does; archon didn't exist yet. sc2reader always
	 * pairs in order, which misassigns archon games: details lists human partners in working set order and leaves
	 * computer partners out.
	 *
	 * Player ids are Blizzard's (the m_playerId of the tracker's player setup events): they count the controlling
	 * units from 1 in slot order. An archon partner (m_tandemLeaderId = its leader's m_tandemId) controls its
	 * leader's player, which takes team, race, colour, result and handicap from the leader's slot and details entry.
	 * The attribute scope of a player is its player id.
	 *
	 * Toons: a user controlling a player has the toon of its details entry, an observer the toon handle of its slot.
	 *
	 * @param list<array<string, mixed>> $slots initdata lobby slots
	 * @param list<array<string, mixed>> $userData initdata user initial data, indexed by user id
	 * @param list<mixed> $detailPlayers replay.details player list
	 * @param Attributes $attributes
	 *
	 * @return array{list<Player>, list<User>}
	 */
	private static function fromLobby(array $slots, array $userData, array $detailPlayers, array $attributes): array {
		$byWorkingSetSlot = self::detailsByWorkingSetSlot($detailPlayers);
		$entries = array();
		$users = array();
		$detailId = 0;
		foreach ($slots as $slotId => $slot) {
			/** @var int|null $userId */
			$userId = $slot['m_userId'];
			$data = null;
			if ($slot['m_control'] === 2 && $userId !== null) {
				$data = $userData[$userId] ?? throw new DecodeException("initdata has no user data for user {$userId} of slot {$slotId}");
				if ($slot['m_observe'] !== 0) {
					$handle = $slot['m_toonHandle'] ?? null;
					$users[$slotId] = self::user($slotId, $userId, $data, is_string($handle) && $handle !== '' ? Toon::fromHandle($handle) : null);

					continue;
				}
			} elseif ($slot['m_control'] !== 3) {
				continue;
			}

			if ($byWorkingSetSlot !== null) {
				$detail = $byWorkingSetSlot[$slot['m_workingSetSlotId'] ?? -1] ?? null;
				if ($detail === null) {
					if ($data !== null) {
						throw new DecodeException("replay.details has no entry for the human in slot {$slotId}");
					}

					continue;
				}
			} elseif ($data === null && $detailId >= count($detailPlayers)) {
				continue;
			} else {
				$detail = self::detailPlayer($detailPlayers, $detailId++);
			}
			/** @var array{m_name: string, m_race: string, m_color: array{m_r: int, m_g: int, m_b: int, m_a: int}, m_toon: array{m_region: int, m_realm: int, m_id: int}, m_result: int} $detail */
			$entries[$slotId] = array($slot, $detail, $data === null || $userId === null ? null : self::user($slotId, $userId, $data, Toon::fromDetails($detail['m_toon'])));
		}

		$pids = array();
		$byTandem = array();
		foreach ($entries as $slotId => list($slot)) {
			if (!self::isArchonPartner($slot)) {
				$pids[$slotId] = count($byTandem) + 1;
				$byTandem[$slot['m_tandemId'] ?? "slot {$slotId}"] = $pids[$slotId];
			}
		}

		$controllers = array();
		foreach ($entries as $slotId => list($slot, $detail, $user)) {
			if (self::isArchonPartner($slot)) {
				$pid = $byTandem[$slot['m_tandemLeaderId']] ?? throw new DecodeException("The archon leader of slot {$slotId} is not a player");
			} else {
				$pid = $pids[$slotId];
				$controllers[$pid]['leader'] = array($slot, $detail);
			}
			if ($user !== null) {
				$controllers[$pid]['users'][] = $user;
				$users[$slotId] = $user;
			} else {
				$controllers[$pid]['computer'] = new Computer($slotId, $detail['m_name'], $attributes[$pid]['Difficulty'] ?? null);
			}
		}

		$players = array();
		foreach ($controllers as $pid => $controller) {
			$computer = $controller['computer'] ?? null;
			$controlling = $controller['users'] ?? array();
			if ($computer !== null && $controlling !== array()) {
				throw new DecodeException("Player {$pid} is controlled by humans and a computer");
			}
			list($slot, $detail) = $controller['leader'] ?? throw new DecodeException("Player {$pid} has no leader slot");
			/** @var int $team */
			$team = $slot['m_teamId'];
			/** @var int $handicap */
			$handicap = $slot['m_handicap'];
			$players[] = self::player($pid, $team + 1, $handicap, $detail, $attributes[$pid] ?? array(), $controlling, $computer);
		}
		ksort($users);

		return array($players, array_values($users));
	}

	/**
	 * Whether some user controls no player.
	 * @param list<Player> $players
	 * @param list<User> $users
	 */
	private static function hasObservers(array $players, array $users): bool {
		return count($users) > array_sum(array_map(static fn (Player $p): int => count($p->users), $players));
	}

	/**
	 * An archon partner: a slot that plays the units of another slot (its tandem leader).
	 *
	 * @param array<string, mixed> $slot
	 */
	private static function isArchonPartner(array $slot): bool {
		$leader = $slot['m_tandemLeaderId'] ?? null;

		return $leader !== null && $leader !== ($slot['m_tandemId'] ?? null);
	}

	/**
	 * A player: race, colour and result from its (leader's) details entry, the race pick from its attributes.
	 * @param array{m_race: string, m_color: array{m_r: int, m_g: int, m_b: int, m_a: int}, m_result?: int, ...} $detail
	 * @param array<string, string|null> $attributes
	 * @param list<User> $users
	 */
	private static function player(int $pid, int $team, int $handicap, array $detail, array $attributes, array $users, ?Computer $computer): Player {
		return new Player(
			$pid,
			$team,
			Race::tryFrom((string)($attributes['Race'] ?? '')),
			Race::fromLocalised($detail['m_race']),
			Colour::fromDetails($detail['m_color']),
			Result::fromDetails($detail['m_result'] ?? 0),
			$handicap,
			$users,
			$computer,
		);
	}

	/**
	 * A user: name and clan tag from the initdata user data.
	 * @param array<string, mixed> $data
	 */
	private static function user(int $slotId, int $userId, array $data, ?Toon $toon): User {
		/** @var string $name */
		$name = $data['m_name'];
		/** @var string|null $clanTag */
		$clanTag = $data['m_clanTag'] ?? null;

		return new User($slotId, $userId, $name, $clanTag, $toon);
	}
}
