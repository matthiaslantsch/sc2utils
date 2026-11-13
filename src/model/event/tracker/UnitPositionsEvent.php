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

/**
 * Positions of the units damaged in the last interval, every 240 gameloops (SUnitPositionsEvent, at most 255 units).
 * Units are named by tag index only (no recycle): the unit with that index alive at the time.
 */
class UnitPositionsEvent implements TrackerEvent {
	/**
	 * @param array<int, Point> $positions unit tag index => position in map cells
	 */
	public function __construct(
		public int $gameloop,
		public array $positions,
	) {}
}
