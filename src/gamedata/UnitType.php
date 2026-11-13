<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\gamedata;

use holonet\sc2utils\model\enum\Race;

/**
 * A unit type as sc2reader's data knows it. Unknown types (missiles, beacons, neutral units, anything newer than
 * sc2reader's data) have their id as name, no race, no flags and no costs.
 */
class UnitType {
	/**
	 * @param string $id Blizzard's unit id, as tracker events name it, e.g. "HellionTank"
	 * @param string $name sc2reader's title, e.g. "BattleHellion"
	 * @param float $supply supply used (sc2reader's value; providers have 0)
	 * @param bool $isTemporary spawned by another unit or an ability, a dummy, or a production intermediate
	 */
	public function __construct(
		public string $id,
		public string $name,
		public ?Race $race = null,
		public bool $isArmy = false,
		public bool $isWorker = false,
		public bool $isBuilding = false,
		public int $minerals = 0,
		public int $vespene = 0,
		public float $supply = 0.0,
		public bool $isTemporary = false,
	) {}
}
