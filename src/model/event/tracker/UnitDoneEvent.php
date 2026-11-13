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
 * A started unit finished (SUnitDoneEvent).
 */
class UnitDoneEvent implements TrackerEvent {
	/**
	 * @param int $unitId index << 18 | recycle
	 */
	public function __construct(
		public int $gameloop,
		public int $unitId,
	) {}
}
