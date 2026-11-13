<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\model\replay;

/**
 * The computer that controls a player.
 */
final class Computer {
	/**
	 * @param int $slot index of the lobby slot
	 * @param string $name as replay.details has it, e.g. "A.I. 1 (Harder)"
	 * @param string|null $difficulty the difficulty attribute, e.g. "Harder"; null if absent
	 */
	public function __construct(
		public int $slot,
		public string $name,
		public ?string $difficulty,
	) {}
}
