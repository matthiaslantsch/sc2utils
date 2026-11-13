<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\model\event\game;

use holonet\sc2utils\model\event\Event;
use holonet\sc2utils\model\replay\User;

/**
 * An event of replay.game.events: an input of a user (command, selection, camera), or a global one (no user).
 */
interface GameEvent extends Event {
	public ?User $user { get; }
}
