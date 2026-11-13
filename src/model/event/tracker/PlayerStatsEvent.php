<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\model\event\tracker;

use holonet\sc2utils\model\replay\Player;

/**
 * The game's own score values of a player, every 160 gameloops (SPlayerStatsEvent): the m_scoreValue* fields without
 * the prefix. Supply (food) is scaled from fixed point (÷ 4096); the friendly fire values exist from 26490 on.
 *
 * Values are as the game wrote them, negative ones included (probably refunds); sc2reader clamps every value
 * to 0.
 */
class PlayerStatsEvent implements TrackerEvent {
	/**
	 * @param int $playerId Blizzard's player id
	 * @param Player|null $player null if the replay has no player with that id
	 */
	public function __construct(
		public int $gameloop,
		public int $playerId,
		public ?Player $player,
		public int $mineralsCurrent,
		public int $vespeneCurrent,
		public int $mineralsCollectionRate,
		public int $vespeneCollectionRate,
		public int $workersActiveCount,
		public int $mineralsUsedInProgressArmy,
		public int $mineralsUsedInProgressEconomy,
		public int $mineralsUsedInProgressTechnology,
		public int $vespeneUsedInProgressArmy,
		public int $vespeneUsedInProgressEconomy,
		public int $vespeneUsedInProgressTechnology,
		public int $mineralsUsedCurrentArmy,
		public int $mineralsUsedCurrentEconomy,
		public int $mineralsUsedCurrentTechnology,
		public int $vespeneUsedCurrentArmy,
		public int $vespeneUsedCurrentEconomy,
		public int $vespeneUsedCurrentTechnology,
		public int $mineralsLostArmy,
		public int $mineralsLostEconomy,
		public int $mineralsLostTechnology,
		public int $vespeneLostArmy,
		public int $vespeneLostEconomy,
		public int $vespeneLostTechnology,
		public int $mineralsKilledArmy,
		public int $mineralsKilledEconomy,
		public int $mineralsKilledTechnology,
		public int $vespeneKilledArmy,
		public int $vespeneKilledEconomy,
		public int $vespeneKilledTechnology,
		public float $foodUsed,
		public float $foodMade,
		public int $mineralsUsedActiveForces,
		public int $vespeneUsedActiveForces,
		public ?int $mineralsFriendlyFireArmy,
		public ?int $mineralsFriendlyFireEconomy,
		public ?int $mineralsFriendlyFireTechnology,
		public ?int $vespeneFriendlyFireArmy,
		public ?int $vespeneFriendlyFireEconomy,
		public ?int $vespeneFriendlyFireTechnology,
	) {}
}
