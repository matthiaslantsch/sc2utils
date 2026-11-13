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
 * A unit died or was removed (SUnitDiedEvent), cancelled structures and hatched cocoons included.
 */
class UnitDiedEvent implements TrackerEvent {
	/**
	 * @param int $unitId index << 18 | recycle
	 * @param int|null $killerPlayerId Blizzard's player id of the killer, null if none
	 * @param Player|null $killer null without a killer or if the replay has no player with that id
	 * @param int|null $killerUnitId the killing unit (from 27950)
	 * @param Point $location in map cells
	 */
	public function __construct(
		public int $gameloop,
		public int $unitId,
		public ?int $killerPlayerId,
		public ?Player $killer,
		public ?int $killerUnitId,
		public Point $location,
	) {}
}
