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
 * A change of a selection (SSelectionDeltaEvent).
 */
class SelectionEvent implements GameEvent {
	/**
	 * @param int $controlGroup 0 to 9 for the control groups, 10 for the active selection
	 */
	public function __construct(
		public int $gameloop,
		public ?User $user,
		public int $controlGroup,
	) {}
}
