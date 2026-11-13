<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\decode;

use holonet\sc2utils\gamedata\Abilities;
use holonet\sc2utils\model\event\engine\UnitOwnerChangeEvent;
use holonet\sc2utils\model\event\Event;
use holonet\sc2utils\model\event\game\CameraEvent;
use holonet\sc2utils\model\event\game\CommandEvent;
use holonet\sc2utils\model\event\game\ControlGroupEvent;
use holonet\sc2utils\model\event\game\GameEvent;
use holonet\sc2utils\model\event\game\GenericGameEvent;
use holonet\sc2utils\model\event\game\PlayerLeaveEvent;
use holonet\sc2utils\model\event\game\RetargetEvent;
use holonet\sc2utils\model\event\game\SelectionEvent;
use holonet\sc2utils\model\event\message\ChatEvent;
use holonet\sc2utils\model\event\message\GenericMessageEvent;
use holonet\sc2utils\model\event\message\MessageEvent;
use holonet\sc2utils\model\event\message\PingEvent;
use holonet\sc2utils\model\event\message\Recipient;
use holonet\sc2utils\model\event\tracker\GenericTrackerEvent;
use holonet\sc2utils\model\event\tracker\PlayerStatsEvent;
use holonet\sc2utils\model\event\tracker\TrackerEvent;
use holonet\sc2utils\model\event\tracker\UnitBornEvent;
use holonet\sc2utils\model\event\tracker\UnitDiedEvent;
use holonet\sc2utils\model\event\tracker\UnitDoneEvent;
use holonet\sc2utils\model\event\tracker\UnitInitEvent;
use holonet\sc2utils\model\event\tracker\UnitPositionsEvent;
use holonet\sc2utils\model\event\tracker\UnitTypeChangeEvent;
use holonet\sc2utils\model\event\tracker\UpgradeEvent;
use holonet\sc2utils\model\Point;
use holonet\sc2utils\model\replay\Player;
use holonet\sc2utils\model\replay\Replay;
use holonet\sc2utils\model\replay\User;
use holonet\sc2utils\protocol\DecodeException;

/**
 * Turns raw events (s2protocol's arrays, as ReplayFile's event streams give them) into the typed events of
 * model\event, resolving users, players and abilities against the replay. Kinds without a typed class become
 * generic events.
 * Who sent a game or message event (`_userid`): from 24247 on Blizzard's user id. Before, the field is a player
 * id in sc2reader's sense: every human slot (observers included) and every computer player, numbered in slot
 * order from 1 (in 1.5.3 only ids 1 and 2 issue commands, the players in slots 0 and 1). 16 means no user.
 *
 * Coordinates are map cells: game event points ÷ 4096, camera ÷ 256, tracker events as they are, but × 4 before
 * build 27950 (4-cell resolution until patch 2.1, as sc2reader has it).
 */
final readonly class EventBuilder {
	/**
	 * s2protocol's event name (without namespace) => the class it becomes; other kinds become generic events.
	 */
	private const array CLASSES = array(
		'SCmdEvent' => CommandEvent::class,
		'SCmdUpdateTargetPointEvent' => RetargetEvent::class,
		'SCmdUpdateTargetUnitEvent' => RetargetEvent::class,
		'SSelectionDeltaEvent' => SelectionEvent::class,
		'SControlGroupUpdateEvent' => ControlGroupEvent::class,
		'SCameraUpdateEvent' => CameraEvent::class,
		'SPlayerLeaveEvent' => PlayerLeaveEvent::class,
		'SGameUserLeaveEvent' => PlayerLeaveEvent::class,
		'SChatMessage' => ChatEvent::class,
		'SPingMessage' => PingEvent::class,
		'SPlayerStatsEvent' => PlayerStatsEvent::class,
		'SUnitBornEvent' => UnitBornEvent::class,
		'SUnitInitEvent' => UnitInitEvent::class,
		'SUnitDoneEvent' => UnitDoneEvent::class,
		'SUnitDiedEvent' => UnitDiedEvent::class,
		'SUnitTypeChangeEvent' => UnitTypeChangeEvent::class,
		'SUnitOwnerChangeEvent' => UnitOwnerChangeEvent::class,
		'SUnitPositionsEvent' => UnitPositionsEvent::class,
		'SUpgradeEvent' => UpgradeEvent::class,
	);

	/**
	 * Before this build, tracker coordinates are in units of 4 cells.
	 */
	private const int FINE_COORDINATES_BUILD = 27_950;

	/**
	 * The `_userid` of global events.
	 */
	private const int NO_USER = 16;

	/**
	 * From this build on, `_userid` is the user id.
	 */
	private const int USER_ID_BUILD = 24_247;

	/**
	 * @param array<int, User> $users by the id events carry
	 * @param array<int, Player> $players by player id
	 * @param string|null $abilityTable gamedata\Abilities table of the replay, null if it has none
	 */
	private function __construct(
		private array $users,
		private array $players,
		private ?string $abilityTable,
		private int $coordinateFactor,
	) {}

	/**
	 * The class a raw event becomes, without building it.
	 * @param 'game'|'message'|'tracker' $stream
	 * @param string $name s2protocol's full event name
	 *
	 * @return class-string<Event>
	 */
	public static function classOf(string $stream, string $name): string {
		return self::CLASSES[substr($name, (int)strrpos($name, '.') + 1)] ?? match ($stream) {
			'game' => GenericGameEvent::class,
			'message' => GenericMessageEvent::class,
			'tracker' => GenericTrackerEvent::class,
		};
	}

	public static function for(Replay $replay): self {
		$build = $replay->header->version->build;
		$users = array();
		if ($replay->header->version->baseBuild < self::USER_ID_BUILD) {
			$entities = array();
			foreach ($replay->users as $user) {
				$entities[$user->slot] = $user;
			}
			foreach ($replay->players as $player) {
				if ($player->computer !== null) {
					$entities[$player->computer->slot] = null;
				}
			}
			ksort($entities);
			$number = 0;
			foreach ($entities as $user) {
				$number++;
				if ($user !== null) {
					$users[$number] = $user;
				}
			}
		} else {
			foreach ($replay->users as $user) {
				$users[$user->userId] = $user;
			}
		}
		$players = array();
		foreach ($replay->players as $player) {
			$players[$player->pid] = $player;
		}

		return new self($users, $players, Abilities::table($replay->expansion, $build), $build < self::FINE_COORDINATES_BUILD ? 4 : 1);
	}

	/**
	 * @param array<string, mixed> $raw
	 */
	public function game(array $raw): GameEvent {
		$gameloop = self::gameloop($raw);
		$user = $this->user($raw);

		return match (self::name($raw)) {
			'SCmdEvent' => $this->command($gameloop, $user, $raw),
			'SCmdUpdateTargetPointEvent' => new RetargetEvent($gameloop, $user, self::point(self::arr($raw, 'm_target'), 4_096), null),
			'SCmdUpdateTargetUnitEvent' => new RetargetEvent($gameloop, $user, self::point(self::arr(self::arr($raw, 'm_target'), 'm_snapshotPoint'), 4_096), self::int(self::arr($raw, 'm_target'), 'm_tag')),
			'SSelectionDeltaEvent' => new SelectionEvent($gameloop, $user, self::int($raw, 'm_controlGroupId')),
			'SControlGroupUpdateEvent' => new ControlGroupEvent($gameloop, $user, self::int($raw, 'm_controlGroupIndex'), self::int($raw, 'm_controlGroupUpdate')),
			'SCameraUpdateEvent' => new CameraEvent(
				$gameloop,
				$user,
				is_array($raw['m_target'] ?? null) ? self::point(self::arr($raw, 'm_target'), 256) : null,
				self::optInt($raw, 'm_distance'),
				self::optInt($raw, 'm_pitch'),
				self::optInt($raw, 'm_yaw'),
			),
			'SPlayerLeaveEvent', 'SGameUserLeaveEvent' => new PlayerLeaveEvent($gameloop, $user),
			default => new GenericGameEvent($gameloop, $user, self::fullName($raw), self::data($raw)),
		};
	}

	/**
	 * A raw event's gameloop, wrapped to 32 bits: the game's counter is 32 bits and its deltas are signed. In
	 * 2.1.3.30508 the tracker writes four player setup events late with a delta of -3040 (back to gameloop 0) and
	 * continues at 3120; s2protocol, with Python's unbounded ints, makes that 2^32 and counts on from there
	 * (the raw streams keep its value), sc2reader wraps.
	 *
	 * @param array<string, mixed> $raw
	 */
	public static function gameloop(array $raw): int {
		return self::int($raw, '_gameloop') & 0xFFFF_FFFF;
	}

	/**
	 * @param array<string, mixed> $raw
	 */
	public function message(array $raw): MessageEvent {
		$gameloop = self::gameloop($raw);
		$user = $this->user($raw);

		return match (self::name($raw)) {
			'SChatMessage' => new ChatEvent($gameloop, $user, Recipient::fromRaw(self::int($raw, 'm_recipient')), self::str($raw, 'm_string')),
			'SPingMessage' => new PingEvent($gameloop, $user, Recipient::fromRaw(self::int($raw, 'm_recipient')), self::point(self::arr($raw, 'm_point'), 4_096)),
			default => new GenericMessageEvent($gameloop, $user, self::fullName($raw), self::data($raw)),
		};
	}

	/**
	 * @param array<string, mixed> $raw
	 */
	public function tracker(array $raw): TrackerEvent {
		$gameloop = self::gameloop($raw);

		return match (self::name($raw)) {
			'SPlayerStatsEvent' => $this->stats($gameloop, $raw),
			'SUnitBornEvent' => new UnitBornEvent(
				$gameloop,
				self::unitId($raw, 'm_unitTag'),
				self::str($raw, 'm_unitTypeName'),
				$this->player(self::int($raw, 'm_controlPlayerId')),
				$this->player(self::int($raw, 'm_upkeepPlayerId')),
				$this->location($raw),
				isset($raw['m_creatorUnitTagIndex']) ? self::unitId($raw, 'm_creatorUnitTag') : null,
				isset($raw['m_creatorAbilityName']) ? self::str($raw, 'm_creatorAbilityName') : null,
				array_key_exists('m_creatorAbilityName', $raw),
			),
			'SUnitInitEvent' => new UnitInitEvent(
				$gameloop,
				self::unitId($raw, 'm_unitTag'),
				self::str($raw, 'm_unitTypeName'),
				$this->player(self::int($raw, 'm_controlPlayerId')),
				$this->player(self::int($raw, 'm_upkeepPlayerId')),
				$this->location($raw),
			),
			'SUnitDoneEvent' => new UnitDoneEvent($gameloop, self::unitId($raw, 'm_unitTag')),
			'SUnitDiedEvent' => new UnitDiedEvent(
				$gameloop,
				self::unitId($raw, 'm_unitTag'),
				self::optInt($raw, 'm_killerPlayerId'),
				$this->player(self::optInt($raw, 'm_killerPlayerId')),
				// sc2reader: index 0 means no killing unit
				(self::optInt($raw, 'm_killerUnitTagIndex') ?? 0) !== 0 ? self::unitId($raw, 'm_killerUnitTag') : null,
				$this->location($raw),
			),
			'SUnitTypeChangeEvent' => new UnitTypeChangeEvent($gameloop, self::unitId($raw, 'm_unitTag'), self::str($raw, 'm_unitTypeName')),
			'SUnitOwnerChangeEvent' => new UnitOwnerChangeEvent(
				$gameloop,
				self::unitId($raw, 'm_unitTag'),
				$this->player(self::int($raw, 'm_controlPlayerId')),
				$this->player(self::int($raw, 'm_upkeepPlayerId')),
			),
			'SUnitPositionsEvent' => $this->positions($gameloop, $raw),
			'SUpgradeEvent' => new UpgradeEvent($gameloop, self::int($raw, 'm_playerId'), $this->player(self::int($raw, 'm_playerId')), self::str($raw, 'm_upgradeTypeName'), self::int($raw, 'm_count')),
			default => new GenericTrackerEvent($gameloop, self::fullName($raw), self::data($raw)),
		};
	}

	/**
	 * @param array<string, mixed> $raw
	 *
	 * @return array<string, mixed>
	 */
	private static function arr(array $raw, string $key): array {
		$value = $raw[$key] ?? null;
		if (!is_array($value)) {
			throw new DecodeException(sprintf('%s: expected %s to be an array, got %s', self::fullName($raw), $key, get_debug_type($value)));
		}

		/** @var array<string, mixed> */
		return $value;
	}

	/**
	 * A command. From 16561 the ability (null for a right-click) and the target (a choice of None, TargetPoint,
	 * TargetUnit, Data) are nested; protocol 15405 has one flat struct where link 65535 means no ability, and tag 0
	 * and point (0, 0, 0) no target.
	 *
	 * @param array<string, mixed> $raw
	 */
	private function command(int $gameloop, ?User $user, array $raw): CommandEvent {
		$point = null;
		$unit = null;
		if (array_key_exists('m_abil', $raw)) {
			$abil = is_array($raw['m_abil']) ? $raw['m_abil'] : null;
			$link = $abil === null ? null : self::int($abil, 'm_abilLink');
			$index = $abil === null ? 0 : self::int($abil, 'm_abilCmdIndex');
			$data = self::arr($raw, 'm_data');
			if (is_array($data['TargetPoint'] ?? null)) {
				$point = self::point(self::arr($data, 'TargetPoint'), 4_096);
			} elseif (is_array($data['TargetUnit'] ?? null)) {
				$target = self::arr($data, 'TargetUnit');
				$point = self::point(self::arr($target, 'm_snapshotPoint'), 4_096);
				$unit = self::int($target, 'm_tag');
			}
			$other = self::optInt($raw, 'm_otherUnit');
		} else {
			$link = self::int($raw, 'm_abilLink');
			$link = $link === 65_535 ? null : $link;
			$index = $link === null ? 0 : self::int($raw, 'm_abilCmdIndex');
			$target = self::arr($raw, 'm_targetPoint');
			$point = $target === array('x' => 0, 'y' => 0, 'z' => 0) ? null : self::point($target, 4_096);
			$unit = self::int($raw, 'm_targetUnitTag') ?: null;
			$other = self::int($raw, 'm_otherUnit') ?: null;
		}
		$ability = $link === null || $this->abilityTable === null ? null : Abilities::id($this->abilityTable, $link);

		return new CommandEvent($gameloop, $user, self::int($raw, 'm_cmdFlags'), $link, $index, $ability, $point, $unit, $other);
	}

	/**
	 * The event's own fields, without those of the event loop (`_event`, `_gameloop`, ...).
	 *
	 * @param array<string, mixed> $raw
	 *
	 * @return array<string, mixed>
	 */
	private static function data(array $raw): array {
		return array_filter($raw, static fn (string $key): bool => !str_starts_with($key, '_'), \ARRAY_FILTER_USE_KEY);
	}

	/**
	 * @param array<string, mixed> $raw
	 */
	private static function fullName(array $raw): string {
		$name = $raw['_event'] ?? null;

		return is_string($name) ? $name : 'a nested field';
	}

	/**
	 * @param array<string, mixed> $raw
	 */
	private static function int(array $raw, string $key): int {
		$value = $raw[$key] ?? null;
		if (!is_int($value)) {
			throw new DecodeException(sprintf('%s: expected %s to be an int, got %s', self::fullName($raw), $key, get_debug_type($value)));
		}

		return $value;
	}

	/**
	 * A tracker event's position: m_x/m_y in map cells, × 4 before 27950.
	 *
	 * @param array<string, mixed> $raw
	 */
	private function location(array $raw): Point {
		return new Point(self::int($raw, 'm_x') * $this->coordinateFactor, self::int($raw, 'm_y') * $this->coordinateFactor);
	}

	/**
	 * s2protocol's name without the namespace, e.g. "SCmdEvent".
	 *
	 * @param array<string, mixed> $raw
	 */
	private static function name(array $raw): string {
		$name = self::fullName($raw);

		return substr($name, (int)strrpos($name, '.') + 1);
	}

	/**
	 * @param array<string, mixed> $raw
	 */
	private static function optInt(array $raw, string $key): ?int {
		return isset($raw[$key]) ? self::int($raw, $key) : null;
	}

	private function player(?int $pid): ?Player {
		return $pid === null ? null : $this->players[$pid] ?? null;
	}

	/**
	 * @param array<string, mixed> $raw {x, y} or {x, y, z}
	 */
	private static function point(array $raw, int $scale): Point {
		return new Point(self::int($raw, 'x') / $scale, self::int($raw, 'y') / $scale, isset($raw['z']) ? self::int($raw, 'z') / $scale : null);
	}

	/**
	 * Positions: m_items are triples (index delta, x, y) from m_firstUnitIndex on.
	 *
	 * @param array<string, mixed> $raw
	 */
	private function positions(int $gameloop, array $raw): UnitPositionsEvent {
		$index = self::int($raw, 'm_firstUnitIndex');
		/** @var list<int> $items */
		$items = self::arr($raw, 'm_items');
		$positions = array();
		for ($i = 0; $i + 2 < count($items); $i += 3) {
			$index += $items[$i];
			$positions[$index] = new Point($items[$i + 1] * $this->coordinateFactor, $items[$i + 2] * $this->coordinateFactor);
		}

		return new UnitPositionsEvent($gameloop, $positions);
	}

	/**
	 * @param array<string, mixed> $raw
	 */
	private function stats(int $gameloop, array $raw): PlayerStatsEvent {
		$pid = self::int($raw, 'm_playerId');
		$stats = self::arr($raw, 'm_stats');
		$value = static fn (string $name): int => self::int($stats, "m_scoreValue{$name}");
		$optional = static fn (string $name): ?int => self::optInt($stats, "m_scoreValue{$name}");

		return new PlayerStatsEvent(
			$gameloop,
			$pid,
			$this->player($pid),
			$value('MineralsCurrent'),
			$value('VespeneCurrent'),
			$value('MineralsCollectionRate'),
			$value('VespeneCollectionRate'),
			$value('WorkersActiveCount'),
			$value('MineralsUsedInProgressArmy'),
			$value('MineralsUsedInProgressEconomy'),
			$value('MineralsUsedInProgressTechnology'),
			$value('VespeneUsedInProgressArmy'),
			$value('VespeneUsedInProgressEconomy'),
			$value('VespeneUsedInProgressTechnology'),
			$value('MineralsUsedCurrentArmy'),
			$value('MineralsUsedCurrentEconomy'),
			$value('MineralsUsedCurrentTechnology'),
			$value('VespeneUsedCurrentArmy'),
			$value('VespeneUsedCurrentEconomy'),
			$value('VespeneUsedCurrentTechnology'),
			$value('MineralsLostArmy'),
			$value('MineralsLostEconomy'),
			$value('MineralsLostTechnology'),
			$value('VespeneLostArmy'),
			$value('VespeneLostEconomy'),
			$value('VespeneLostTechnology'),
			$value('MineralsKilledArmy'),
			$value('MineralsKilledEconomy'),
			$value('MineralsKilledTechnology'),
			$value('VespeneKilledArmy'),
			$value('VespeneKilledEconomy'),
			$value('VespeneKilledTechnology'),
			$value('FoodUsed') / 4_096,
			$value('FoodMade') / 4_096,
			$value('MineralsUsedActiveForces'),
			$value('VespeneUsedActiveForces'),
			$optional('MineralsFriendlyFireArmy'),
			$optional('MineralsFriendlyFireEconomy'),
			$optional('MineralsFriendlyFireTechnology'),
			$optional('VespeneFriendlyFireArmy'),
			$optional('VespeneFriendlyFireEconomy'),
			$optional('VespeneFriendlyFireTechnology'),
		);
	}

	/**
	 * @param array<string, mixed> $raw
	 */
	private static function str(array $raw, string $key): string {
		$value = $raw[$key] ?? null;
		if (!is_string($value)) {
			throw new DecodeException(sprintf('%s: expected %s to be a string, got %s', self::fullName($raw), $key, get_debug_type($value)));
		}

		return $value;
	}

	/**
	 * A unit id from a tag's index and recycle fields: index << 18 | recycle, as game events' tags.
	 * @param array<string, mixed> $raw
	 */
	private static function unitId(array $raw, string $prefix): int {
		return self::int($raw, "{$prefix}Index") << 18 | self::int($raw, "{$prefix}Recycle");
	}

	/**
	 * @param array<string, mixed> $raw
	 */
	private function user(array $raw): ?User {
		$field = $raw['_userid'] ?? null;
		$id = is_array($field) ? $field['m_userId'] ?? $field['m_playerId'] ?? null : null;
		if (!is_int($id)) {
			throw new DecodeException(sprintf('%s at gameloop %s has no user id', self::fullName($raw), var_export($raw['_gameloop'] ?? null, true)));
		}
		if ($id === self::NO_USER) {
			return null;
		}

		return $this->users[$id] ?? throw new DecodeException(sprintf('%s at gameloop %s: no user %d in the replay', self::fullName($raw), var_export($raw['_gameloop'] ?? null, true), $id));
	}
}
