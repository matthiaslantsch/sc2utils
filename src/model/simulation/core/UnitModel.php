<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\model\simulation\core;

use holonet\sc2utils\gamedata\Units;
use holonet\sc2utils\model\event\tracker\UpgradeEvent;
use holonet\sc2utils\model\replay\Player;

/**
 * Every unit of a replay and the players' upgrades, from the tracker events (simulate\core\ContextLoader).
 */
final class UnitModel {
	/**
	 * The units alive by tag index (position events name units by index only).
	 * @var array<int, Unit>
	 */
	private array $active = array();

	/**
	 * @var array<int, Unit> by id
	 */
	private array $units = array();

	/**
	 * @var array<int, UpgradeEvent[]> by player id
	 */
	private array $upgrades = array();

	public function activate(Unit $unit): void {
		$this->units[$unit->id] = $unit;
		$this->active[$unit->id >> 18] = $unit;
	}

	public function active(int $index): ?Unit {
		return $this->active[$index] ?? null;
	}

	public function addUpgrade(UpgradeEvent $event): void {
		$this->upgrades[$event->playerId][] = $event;
	}

	/**
	 * The units of a player alive at a gameloop: started at or before it and not dead yet (structures under
	 * construction and cocoons included; filter on Unit::$finishedAt for finished ones). Ownership is the owner at
	 * the end, as in sc2reader.
	 * @return list<Unit>
	 */
	public function aliveAt(Player $player, int $gameloop): array {
		return array_values(array_filter(
			$this->units,
			static fn (Unit $unit): bool => $unit->owner?->pid === $player->pid && $unit->startedAt !== null && $unit->startedAt <= $gameloop && ($unit->diedAt === null || $unit->diedAt > $gameloop),
		));
	}

	/**
	 * Every unit, by id (neutral units included).
	 * @return array<int, Unit>
	 */
	public function all(): array {
		return $this->units;
	}

	/**
	 * A player's composition at a gameloop: finished units alive then, counted by the base type of their type at that
	 * moment (gamedata\Units::base(), so sieged tanks count as SiegeTank).
	 * @return array<string, int> base type => count, sorted by type
	 */
	public function compositionAt(Player $player, int $gameloop): array {
		$ret = array();
		foreach ($this->aliveAt($player, $gameloop) as $unit) {
			$type = $unit->typeAt($gameloop);
			if ($type !== null && $unit->finishedAt !== null && $unit->finishedAt <= $gameloop) {
				$base = Units::base($type);
				$ret[$base] = ($ret[$base] ?? 0) + 1;
			}
		}
		ksort($ret);

		return $ret;
	}

	public function deactivate(Unit $unit): void {
		if (($this->active[$unit->id >> 18] ?? null) === $unit) {
			unset($this->active[$unit->id >> 18]);
		}
	}

	public function get(int $id): ?Unit {
		return $this->units[$id] ?? null;
	}

	/**
	 * The units a player owned at the end (sc2reader's player.units).
	 * @return list<Unit>
	 */
	public function ownedBy(Player $player): array {
		return array_values(array_filter($this->units, static fn (Unit $unit): bool => $unit->owner?->pid === $player->pid));
	}

	/**
	 * A player's upgrades in the order the game gave them, the cosmetic ones at the start included.
	 * @return list<UpgradeEvent>
	 */
	public function upgrades(Player $player): array {
		return $this->upgrades[$player->pid] ?? array();
	}
}
