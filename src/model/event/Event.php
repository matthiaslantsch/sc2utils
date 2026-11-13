<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\model\event;

/**
 * An event of a replay's game, message or tracker stream. Every event has its gameloop (16 per game second).
 */
interface Event {
	public int $gameloop { get; }
}
