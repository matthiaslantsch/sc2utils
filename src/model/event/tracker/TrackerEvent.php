<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\model\event\tracker;

use holonet\sc2utils\model\event\Event;

/**
 * An event of replay.tracker.events (from 2.0.8, protocol 24944): the game's own record of units, player stats and
 * upgrades, by player id.
 */
interface TrackerEvent extends Event {}
