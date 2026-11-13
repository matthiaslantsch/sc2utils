<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\testing;

use holonet\sc2utils\model\event\engine\UnitOwnerChangeEvent;
use holonet\sc2utils\model\event\Event;
use holonet\sc2utils\model\event\game\CameraEvent;
use holonet\sc2utils\model\event\game\CommandEvent;
use holonet\sc2utils\model\event\game\ControlGroupEvent;
use holonet\sc2utils\model\event\game\PlayerLeaveEvent;
use holonet\sc2utils\model\event\game\RetargetEvent;
use holonet\sc2utils\model\event\game\SelectionEvent;
use holonet\sc2utils\model\event\message\ChatEvent;
use holonet\sc2utils\model\event\message\PingEvent;
use holonet\sc2utils\model\event\message\Recipient;
use holonet\sc2utils\model\event\tracker\PlayerStatsEvent;
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
use holonet\sc2utils\model\simulation\Simulation;
use holonet\sc2utils\simulate\Engine;
use holonet\sc2utils\simulate\Plugin;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Typed events written by hand, run through the real engine (core plugins included) without a replay file, for
 * tests. Players are named by player id, users by name; unit ids are generated (index << 18 | 1).
 *
 *     $script = new EventScript($replay);
 *     $slot = $script->baseLayout(new Point(40, 40));
 *     $nexus = $script->born('Nexus', 1, EventScript::townHallSpot(new Point(40, 40)));
 *     $script->at(500)->born('Probe', 1, new Point(41, 34), creator: $nexus);
 *     $simulation = $script->simulate(new BaseTracker());
 */
final class EventScript {
	/**
	 * @var list<Event>
	 */
	private array $events = array();

	private int $gameloop = 0;

	private int $nextIndex = 1;

	public function __construct(
		public readonly Replay $replay,
	) {}

	/**
	 * Moves on to a gameloop; events are in gameloop order.
	 */
	public function at(int $gameloop): self {
		if ($gameloop < $this->gameloop) {
			throw new InvalidArgumentException("Gameloop {$gameloop} is before {$this->gameloop}");
		}
		$this->gameloop = $gameloop;

		return $this;
	}

	/**
	 * An expansion slot at gameloop 0: 8 mineral fields in a row of 10.5 cells centred on $centre (alternating 1800
	 * and 900 fields), and 2 geysers 7 cells to either side, 4 below. The town hall's spot is townHallSpot($centre).
	 *
	 * @return list<int> the mineral fields' ids
	 */
	public function baseLayout(Point $centre): array {
		if ($this->gameloop !== 0) {
			throw new InvalidArgumentException('Resources are born at gameloop 0');
		}
		$ret = array();
		foreach (array(-5.25, -3.75, -2.25, -0.75, 0.75, 2.25, 3.75, 5.25) as $i => $dx) {
			$ret[] = $this->born($i % 2 === 0 ? 'MineralField' : 'MineralField750', null, new Point($centre->x + $dx, $centre->y));
		}
		$this->born('VespeneGeyser', null, new Point($centre->x - 7.0, $centre->y - 4.0));
		$this->born('VespeneGeyser', null, new Point($centre->x + 7.0, $centre->y - 4.0));

		return $ret;
	}

	/**
	 * A unit appearing finished (UnitBornEvent). The creator is recorded unless $creatorRecorded is false.
	 *
	 * @param int|null $player player id, null for neutral units
	 *
	 * @return int the unit's id
	 */
	public function born(string $type, ?int $player, Point $at, ?int $creator = null, ?string $ability = null, bool $creatorRecorded = true): int {
		$id = $this->newId();
		$owner = $this->player($player);
		$this->events[] = new UnitBornEvent($this->gameloop, $id, $type, $owner, $owner, $at, $creator, $ability, $creatorRecorded);

		return $id;
	}

	public function camera(string $user, ?Point $target): self {
		$this->events[] = new CameraEvent($this->gameloop, $this->user($user), $target, null, null, null);

		return $this;
	}

	public function chat(string $user, string $text, Recipient $recipient = Recipient::All): self {
		$this->events[] = new ChatEvent($this->gameloop, $this->user($user), $recipient, $text);

		return $this;
	}

	/**
	 * A command: $ability is Blizzard's ability id (null with $link null: a right-click).
	 */
	public function command(string $user, ?string $ability, int $commandIndex = 0, ?Point $target = null, ?int $targetUnit = null, ?int $link = null): self {
		$link ??= $ability === null ? null : 1;
		$this->events[] = new CommandEvent($this->gameloop, $this->user($user), 0, $link, $commandIndex, $ability, $target, $targetUnit, null);

		return $this;
	}

	public function controlGroup(string $user, int $group = 1, int $update = 2): self {
		$this->events[] = new ControlGroupEvent($this->gameloop, $this->user($user), $group, $update);

		return $this;
	}

	/**
	 * @param int|null $killer the killing player's id
	 */
	public function died(int $unit, Point $at, ?int $killer = null, ?int $killingUnit = null): self {
		$this->events[] = new UnitDiedEvent($this->gameloop, $unit, $killer, $this->player($killer), $killingUnit, $at);

		return $this;
	}

	public function done(int $unit): self {
		$this->events[] = new UnitDoneEvent($this->gameloop, $unit);

		return $this;
	}

	/**
	 * A unit started (UnitInitEvent): a structure placed or a unit warping in.
	 *
	 * @return int the unit's id
	 */
	public function init(string $type, int $player, Point $at): int {
		$id = $this->newId();
		$owner = $this->player($player);
		$this->events[] = new UnitInitEvent($this->gameloop, $id, $type, $owner, $owner, $at);

		return $id;
	}

	public function leave(string $user): self {
		$this->events[] = new PlayerLeaveEvent($this->gameloop, $this->user($user));

		return $this;
	}

	public function ownerChange(int $unit, int $player): self {
		$owner = $this->player($player);
		$this->events[] = new UnitOwnerChangeEvent($this->gameloop, $unit, $owner, $owner);

		return $this;
	}

	public function ping(string $user, Point $point, Recipient $recipient = Recipient::Allies): self {
		$this->events[] = new PingEvent($this->gameloop, $this->user($user), $recipient, $point);

		return $this;
	}

	/**
	 * @param array<int, Point> $positions unit id => position
	 */
	public function positions(array $positions): self {
		$byIndex = array();
		foreach ($positions as $unit => $position) {
			$byIndex[$unit >> 18] = $position;
		}
		$this->events[] = new UnitPositionsEvent($this->gameloop, $byIndex);

		return $this;
	}

	public function retarget(string $user, Point $target, ?int $targetUnit = null): self {
		$this->events[] = new RetargetEvent($this->gameloop, $this->user($user), $target, $targetUnit);

		return $this;
	}

	public function selection(string $user, int $controlGroup = 10): self {
		$this->events[] = new SelectionEvent($this->gameloop, $this->user($user), $controlGroup);

		return $this;
	}

	/**
	 * Runs the events through the engine (the core plugins and the given ones).
	 */
	public function simulate(Plugin ...$plugins): Simulation {
		return new Engine()->runEvents($this->replay, $this->events, array_values($plugins));
	}

	/**
	 * A player's stats sample; every value 0 unless given (named as PlayerStatsEvent's parameters, e.g.
	 * mineralsCollectionRate: 900).
	 */
	public function stats(int $player, float|int ...$values): self {
		$defaults = array();
		foreach (new ReflectionMethod(PlayerStatsEvent::class, '__construct')->getParameters() as $parameter) {
			if (!in_array($parameter->getName(), array('gameloop', 'playerId', 'player'), true)) {
				$type = $parameter->getType();
				$defaults[$parameter->getName()] = $type instanceof ReflectionNamedType && $type->getName() === 'float' ? 0.0 : 0;
			}
		}
		$arguments = array('gameloop' => $this->gameloop, 'playerId' => $player, 'player' => $this->player($player)) + array_merge($defaults, $values);
		$this->events[] = new ReflectionClass(PlayerStatsEvent::class)->newInstanceArgs($arguments);

		return $this;
	}

	/**
	 * Where a town hall stands for the minerals of baseLayout($centre): 7 cells above their row.
	 */
	public static function townHallSpot(Point $centre): Point {
		return new Point($centre->x, $centre->y - 7.0);
	}

	public function typeChange(int $unit, string $type): self {
		$this->events[] = new UnitTypeChangeEvent($this->gameloop, $unit, $type);

		return $this;
	}

	public function upgrade(int $player, string $upgrade, int $count = 1): self {
		$this->events[] = new UpgradeEvent($this->gameloop, $player, $this->player($player), $upgrade, $count);

		return $this;
	}

	private function newId(): int {
		return $this->nextIndex++ << 18 | 1;
	}

	private function player(?int $pid): ?Player {
		if ($pid === null) {
			return null;
		}
		foreach ($this->replay->players as $player) {
			if ($player->pid === $pid) {
				return $player;
			}
		}

		throw new InvalidArgumentException("The replay has no player {$pid}");
	}

	private function user(string $name): User {
		foreach ($this->replay->users as $user) {
			if ($user->name === $name) {
				return $user;
			}
		}

		throw new InvalidArgumentException("The replay has no user {$name}");
	}
}
