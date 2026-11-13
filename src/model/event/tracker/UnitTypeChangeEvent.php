<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\model\event\tracker;

/**
 * A unit changed its type (SUnitTypeChangeEvent): modes, morphs, cocoons (gamedata\Units::change()).
 */
class UnitTypeChangeEvent implements TrackerEvent {
	/**
	 * @param int $unitId index << 18 | recycle
	 * @param string $unitType the new type, Blizzard's unit id
	 */
	public function __construct(
		public int $gameloop,
		public int $unitId,
		public string $unitType,
	) {}
}
