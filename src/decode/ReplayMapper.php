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
use holonet\sc2utils\model\replay\Observer;
use holonet\sc2utils\model\replay\Player;
use holonet\sc2utils\model\replay\Replay;
use holonet\sc2utils\model\replay\Team;
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
	 * Since this build gameloops are counted at Faster: 22.4 per real second instead of 16.
	 */
	private const int LOTV_BUILD = 34_784;

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
	 * Python's float floor division (CPython's float_floor_div), which sc2reader uses for the real length. It takes
	 * the floor of the exact quotient of the two doubles, so 4 // 0.8 is 4, not floor(4 / 0.8) = 5.
	 */
	public static function floorDiv(float $x, float $y): float {
		$mod = fmod($x, $y);
		$div = ($x - $mod) / $y;
		if ($mod !== 0.0 && (($y < 0) !== ($mod < 0))) {
			$div -= 1.0;
		}
		if ($div === 0.0) {
			return 0.0;
		}
		$floor = floor($div);

		return $div - $floor > 0.5 ? $floor + 1.0 : $floor;
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
	 * Length in real seconds as sc2reader computes it: whole game seconds (LotV builds count 22.4 gameloops per
	 * second), divided by the speed factor with Python's floor division.
	 */
	public static function realLength(ReplayHeader $header, ?Expansion $expansion, ?GameSpeed $speed): int {
		$fps = $header->version->build >= self::LOTV_BUILD ? 16.0 * 1.4 : 16.0;
		$seconds = (int)((float)$header->gameloops / $fps);
		$factor = $expansion !== null && $speed !== null ? $expansion->speedFactor($speed) : 1.0;

		return (int)self::floorDiv($seconds, $factor);
	}

	/**
	 * sc2reader's real_type: team sizes ascending, e.g. "1v1", "1v2"; FFA for more than two teams of one. A team's
	 * size counts its controlling units (player ids), so an archon pair counts once and archon 2v2 is "2v2".
	 *
	 * @param list<Team> $teams
	 */
	public static function realType(array $teams): string {
		$sizes = array_map(static fn (Team $team): int => count(array_unique(array_map(static fn (Player $p): int => $p->pid, $team->players))), $teams);
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
	 * Without initdata (the WoL betas before 0.19) the lobby defaults apply: seed 0, every lobby flag false, no
	 * archon, no observers, the players as details lists them (playersFromDetails()). Without m_timeUTC the times
	 * are null. Some betas have no game speed attribute; then the speed comes from details.
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
		list($players, $observers) = $lobby === null
			? array(self::playersFromDetails($playerList, $attributes), array())
			: self::entities($lobby['m_lobbyState']['m_slots'], $lobby['m_userInitialData'], $playerList, $attributes);
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
			self::realLength($header, $expansion, $speed),
			$gameMode,
			$game['Teams'] ?? null,
			self::realType($teams),
			// sc2reader: observers or computers make a ladder game not ladder (HotS marks vs AI games as amm)
			$gameMode === GameMode::Ladder && $observers === array() && !in_array(false, array_map(static fn (Player $p): bool => $p->isHuman, $players), true),
			$lobby !== null && in_array(true, array_map(self::isArchonPartner(...), $lobby['m_lobbyState']['m_slots']), true),
			$option('m_amm'),
			$option('m_ranked'),
			$option('m_competitive'),
			$option('m_practice'),
			$option('m_cooperative'),
			$option('m_battleNet'),
			$lobby['m_gameDescription']['m_randomValue'] ?? 0,
			$players,
			$observers,
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
	 * Players and observers from the lobby slots. Differs from sc2reader's load_players() where that one is wrong
	 * (archon games) and agrees everywhere else:
	 * - a human slot (control 2) is an observer if it observes (referees too), otherwise a player;
	 * - a computer slot (control 3) is a player if it has a replay.details entry; computer archon partners and the
	 *   extra co-op slots have none;
	 * - every other slot is open or closed and skipped.
	 *
	 * Slots and details entries are paired by m_workingSetSlotId (both have it since 24764; it is not the slot
	 * index). Before that they are paired in order, as sc2reader does; archon didn't exist yet. sc2reader always
	 * pairs in order, which misassigns archon games: details lists human partners in working set order and leaves
	 * computer partners out.
	 *
	 * Player ids are Blizzard's (the m_playerId of the tracker's player setup events): they count the controlling
	 * units from 1 in slot order, an archon partner shares its leader's id, observers have none. The attribute scope
	 * of a player is its player id. (sc2reader gives observers ids too; no replay has an observer slot before a
	 * player slot, so that never made a difference.)
	 *
	 * @param list<array<string, mixed>> $slots initdata lobby slots
	 * @param list<array<string, mixed>> $users initdata user initial data, indexed by user id
	 * @param list<mixed> $detailPlayers replay.details player list
	 * @param Attributes $attributes
	 *
	 * @return array{list<Player>, list<Observer>}
	 */
	private static function entities(array $slots, array $users, array $detailPlayers, array $attributes): array {
		$byWorkingSetSlot = self::detailsByWorkingSetSlot($detailPlayers);
		$entries = array();
		$observers = array();
		$detailId = 0;
		foreach ($slots as $slotId => $slot) {
			/** @var int|null $userId */
			$userId = $slot['m_userId'];
			$user = null;
			if ($slot['m_control'] === 2 && $userId !== null) {
				$user = $users[$userId] ?? throw new DecodeException("initdata has no user data for user {$userId} of slot {$slotId}");
				if ($slot['m_observe'] !== 0) {
					$observers[] = self::observer($slotId, $slot, $userId, $user);

					continue;
				}
			} elseif ($slot['m_control'] === 3) {
				$userId = null;
			} else {
				continue;
			}

			if ($byWorkingSetSlot !== null) {
				$detail = $byWorkingSetSlot[$slot['m_workingSetSlotId'] ?? -1] ?? null;
				if ($detail === null) {
					if ($user !== null) {
						throw new DecodeException("replay.details has no entry for the human in slot {$slotId}");
					}

					continue;
				}
			} elseif ($user === null && $detailId >= count($detailPlayers)) {
				continue;
			} else {
				$detail = self::detailPlayer($detailPlayers, $detailId++);
			}
			$entries[$slotId] = array($slot, $userId, $user, $detail);
		}

		$pids = array();
		$byTandem = array();
		foreach ($entries as $slotId => list($slot)) {
			if (!self::isArchonPartner($slot)) {
				$pids[$slotId] = count($byTandem) + 1;
				$byTandem[$slot['m_tandemId'] ?? "slot {$slotId}"] = $pids[$slotId];
			}
		}
		foreach ($entries as $slotId => list($slot)) {
			if (self::isArchonPartner($slot)) {
				$pids[$slotId] = $byTandem[$slot['m_tandemLeaderId']] ?? throw new DecodeException("The archon leader of slot {$slotId} is not a player");
			}
		}

		$players = array();
		foreach ($entries as $slotId => list($slot, $userId, $user, $detail)) {
			$players[] = self::player($pids[$slotId], $slotId, $slot, $userId, $user, $detail, $attributes[$pids[$slotId]] ?? array());
		}

		return array($players, $observers);
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
	 * @param array<string, mixed> $slot
	 * @param array<string, mixed> $user
	 */
	private static function observer(int $slotId, array $slot, int $userId, array $user): Observer {
		$handle = $slot['m_toonHandle'] ?? null;
		/** @var string $name */
		$name = $user['m_name'];
		/** @var string|null $clanTag */
		$clanTag = $user['m_clanTag'] ?? null;

		return new Observer($slotId, $userId, $name, $clanTag, is_string($handle) && $handle !== '' ? Toon::fromHandle($handle) : null);
	}

	/**
	 * A human (with user data) or a computer (without): name and clan tag from the user data, or the details name
	 * for computers; race, colour, result and toon from details; team and handicap from the slot.
	 *
	 * @param array<string, mixed> $slot
	 * @param array<string, mixed>|null $user
	 * @param array<string, mixed> $detail
	 * @param array<string, string|null> $attributes
	 */
	private static function player(int $pid, int $slotId, array $slot, ?int $userId, ?array $user, array $detail, array $attributes): Player {
		/** @var array{m_name: string, m_race: string, m_color: array{m_r: int, m_g: int, m_b: int, m_a: int}, m_toon: array{m_region: int, m_realm: int, m_id: int}, m_result: int} $detail */
		/** @var string $name */
		$name = $user['m_name'] ?? $detail['m_name'];
		/** @var string|null $clanTag */
		$clanTag = $user['m_clanTag'] ?? null;
		/** @var int $team */
		$team = $slot['m_teamId'];
		/** @var int $handicap */
		$handicap = $slot['m_handicap'];

		return new Player(
			$pid,
			$slotId,
			$userId,
			$name,
			$clanTag,
			$user !== null ? Toon::fromDetails($detail['m_toon']) : null,
			$team + 1,
			Race::tryFrom((string)($attributes['Race'] ?? '')),
			Race::fromLocalised($detail['m_race']),
			Colour::fromDetails($detail['m_color']),
			Result::fromDetails($detail['m_result']),
			$handicap,
			$attributes['Difficulty'] ?? null,
		);
	}

	/**
	 * The players as replay.details lists them, for replays without initdata: entries
	 * without a name are empty slots and left out, the slot is the index in the list, player ids count from 1 in
	 * list order (no archon then), and the attribute scope is the player id. What the entry lacks takes a default:
	 * - human unless m_control says computer (3); humans get user ids from 0 in list order;
	 * - a toon only with an m_id (the betas have a "name#id" handle instead, which no Toon is);
	 * - the team from m_teamId, else a team of its own
	 * - no result without m_result, handicap 100 without m_handicap.
	 *
	 * @param list<mixed> $detailPlayers
	 * @param Attributes $attributes
	 *
	 * @return list<Player>
	 */
	private static function playersFromDetails(array $detailPlayers, array $attributes): array {
		$players = array();
		$humans = 0;
		foreach ($detailPlayers as $slotId => $detail) {
			/** @var array{m_name: string, m_race: string, m_color: array{m_r: int, m_g: int, m_b: int, m_a: int}, m_toon?: array<string, mixed>, m_control?: int, m_teamId?: int, m_result?: int, m_handicap?: int} $detail */
			if ($detail['m_name'] === '') {
				continue;
			}
			$pid = count($players) + 1;
			$human = ($detail['m_control'] ?? 2) !== 3;
			$toon = $detail['m_toon'] ?? array();
			$playerAttributes = $attributes[$pid] ?? array();
			$players[] = new Player(
				$pid,
				$slotId,
				$human ? $humans++ : null,
				$detail['m_name'],
				null,
				$human && is_int($toon['m_region'] ?? null) && is_int($toon['m_realm'] ?? null) && is_int($toon['m_id'] ?? null)
					? new Toon($toon['m_region'], $toon['m_realm'], $toon['m_id'])
					: null,
				isset($detail['m_teamId']) ? $detail['m_teamId'] + 1 : $pid,
				Race::tryFrom((string)($playerAttributes['Race'] ?? '')),
				Race::fromLocalised($detail['m_race']),
				Colour::fromDetails($detail['m_color']),
				Result::fromDetails($detail['m_result'] ?? 0),
				$detail['m_handicap'] ?? 100,
				$playerAttributes['Difficulty'] ?? null,
			);
		}

		return $players;
	}
}
