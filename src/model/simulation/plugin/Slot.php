<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\model\simulation\plugin;

use holonet\sc2utils\model\Point;
use holonet\sc2utils\model\simulation\core\Unit;

/**
 * An expansion slot of the map: a group of mineral fields (and the geysers near it) where a base can mine.
 */
class Slot {
	/**
	 * @param int $id 1, 2, … in order of the map's mineral fields
	 * @param Point $centre the mineral fields' centroid
	 * @param list<Unit> $minerals the fields at the start (a field's death means it is mined out)
	 * @param list<Unit> $geysers within 12 cells of the centre
	 */
	public function __construct(
		public int $id,
		public Point $centre,
		public array $minerals,
		public array $geysers,
	) {}

	public function distance(Point $point): float {
		return hypot($point->x - $this->centre->x, $point->y - $this->centre->y);
	}

	/**
	 * Mineral fields not mined out at a gameloop.
	 */
	public function fieldsAt(int $gameloop): int {
		return count(array_filter($this->minerals, static fn (Unit $field): bool => $field->diedAt === null || $field->diedAt > $gameloop));
	}
}
