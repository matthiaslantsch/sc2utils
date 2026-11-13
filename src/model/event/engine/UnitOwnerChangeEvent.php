<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\model\event\engine;

use holonet\sc2utils\model\event\tracker\TrackerEvent;
use holonet\sc2utils\model\replay\Player;

/**
 * A unit changed its owner (SUnitOwnerChangeEvent), e.g. a captured Xel'Naga tower or a neural parasite.
 */
class UnitOwnerChangeEvent implements TrackerEvent {
	/**
	 * @param int $unitId index << 18 | recycle
	 */
	public function __construct(
		public int $gameloop,
		public int $unitId,
		public ?Player $controller,
		public ?Player $upkeeper,
	) {}
}
