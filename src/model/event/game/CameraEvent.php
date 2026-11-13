<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\model\event\game;

use holonet\sc2utils\model\Point;
use holonet\sc2utils\model\replay\User;

/**
 * The camera of a user after it moved (SCameraUpdateEvent).
 */
class CameraEvent implements GameEvent {
	/**
	 * @param Point|null $target the camera's centre in map cells; null when the event has none (from 23925)
	 */
	public function __construct(
		public int $gameloop,
		public ?User $user,
		public ?Point $target,
		public ?int $distance,
		public ?int $pitch,
		public ?int $yaw,
	) {}
}
