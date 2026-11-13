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
 * A control group hotkey (SControlGroupUpdateEvent).
 */
class ControlGroupEvent implements GameEvent {
	/**
	 * @param int $group 0 to 9
	 * @param int $update m_controlGroupUpdate: 0 set, 1 append, 2 recall, 3 clear (s2protocol's raw value)
	 */
	public function __construct(
		public int $gameloop,
		public ?User $user,
		public int $group,
		public int $update,
	) {}
}
