<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\model\event\engine;

use holonet\sc2utils\model\event\Event;
use holonet\sc2utils\model\simulation\core\Unit;

/**
 * A morph that the game records as a new unit: a Baneling hatched from a Zergling's cocoon.
 */
class UnitMorphedEvent implements Event {
	public function __construct(
		public int $gameloop,
		public Unit $unit,
		public Unit $from,
	) {}
}
