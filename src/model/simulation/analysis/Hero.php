<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\model\simulation\analysis;

use holonet\sc2utils\model\simulation\core\Unit;

/**
 * A player's unit with the most kills.
 */
class Hero {
	/**
	 * @param int $kills the units of other players it killed
	 */
	public function __construct(
		public Unit $unit,
		public int $kills,
	) {}
}
