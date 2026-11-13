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
 * A unit appeared finished (SUnitBornEvent): trained units, starting units, neutral units at gameloop 0.
 */
class UnitBornEvent implements TrackerEvent {
	/**
	 * @param int $unitId index << 18 | recycle (the unit's tag)
	 * @param string $unitType Blizzard's unit id, e.g. "Zergling"
	 * @param Player|null $controller null for neutral units
	 * @param Player|null $upkeeper null for neutral units
	 * @param Point $location in map cells
	 * @param int|null $creatorUnitId the unit that created it (from 54724)
	 * @param string|null $creatorAbility the ability, e.g. "BarracksTrain", "HallucinationPhoenix" (from 54724)
	 * @param bool $creatorRecorded whether the replay records creators at all (protocol 54724 on)
	 */
	public function __construct(
		public int $gameloop,
		public int $unitId,
		public string $unitType,
		public ?Player $controller,
		public ?Player $upkeeper,
		public Point $location,
		public ?int $creatorUnitId,
		public ?string $creatorAbility,
		public bool $creatorRecorded = false,
	) {}
}
