<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\model\event\message;

use holonet\sc2utils\model\Point;
use holonet\sc2utils\model\replay\User;

/**
 * A minimap ping (SPingMessage).
 */
class PingEvent implements MessageEvent {
	/**
	 * @param Point $point in map cells (m_point / 4096)
	 */
	public function __construct(
		public int $gameloop,
		public ?User $user,
		public Recipient $recipient,
		public Point $point,
	) {}
}
