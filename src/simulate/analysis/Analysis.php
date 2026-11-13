<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\simulate\analysis;

use holonet\sc2utils\model\simulation\Simulation;

/**
 * A result computed from a finished simulation.
 *
 * Plugins need the events as they happen; an analysis needs only the end state, so it is not part of the engine: it
 * runs on any Simulation (e.g. cached, faked)
 *
 * @template R the result
 */
interface Analysis {
	/**
	 * @return R
	 */
	public function analyse(Simulation $simulation): mixed;
}
