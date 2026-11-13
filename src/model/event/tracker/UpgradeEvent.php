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
 * A player got an upgrade (SUpgradeEvent), research and the cosmetic ones at the start alike.
 */
class UpgradeEvent implements TrackerEvent {
	/**
	 * @param string $upgrade Blizzard's upgrade id, e.g. "TerranInfantryWeaponsLevel1"
	 */
	public function __construct(
		public int $gameloop,
		public int $playerId,
		public ?Player $player,
		public string $upgrade,
		public int $count,
	) {}
}
