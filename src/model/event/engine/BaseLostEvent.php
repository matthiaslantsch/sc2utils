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
use holonet\sc2utils\model\simulation\plugin\Base;

/**
 * A mining base ended: destroyed, lifted off, or mined out ($base->end).
 */
class BaseLostEvent implements Event {
	public function __construct(
		public int $gameloop,
		public Base $base,
	) {}
}
