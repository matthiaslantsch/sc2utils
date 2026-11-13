<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\model\event\tracker;

use holonet\sc2utils\model\Point;
use holonet\sc2utils\model\replay\Player;

/**
 * A unit started (SUnitInitEvent): structures placed, units warped in. UnitDoneEvent follows when it finishes.
 */
class UnitInitEvent implements TrackerEvent {
	/**
	 * @param int $unitId index << 18 | recycle
	 * @param string $unitType Blizzard's unit id
	 * @param Point $location in map cells
	 */
	public function __construct(
		public int $gameloop,
		public int $unitId,
		public string $unitType,
		public ?Player $controller,
		public ?Player $upkeeper,
		public Point $location,
	) {}
}
