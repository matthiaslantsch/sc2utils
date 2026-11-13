<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\model\event\game;

use holonet\sc2utils\model\replay\User;

/**
 * A user left the game (SPlayerLeaveEvent up to 23925, SGameUserLeaveEvent from 24247).
 */
class PlayerLeaveEvent implements GameEvent {
	public function __construct(
		public int $gameloop,
		public ?User $user,
	) {}
}
