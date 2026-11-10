<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\model\replay;

use holonet\sc2utils\model\Toon;

/**
 * A human in an observer (or referee) slot.
 */
final readonly class Observer {
	/**
	 * @param int $slot index of the lobby slot
	 * @param string|null $clanTag null before clan tags existed (2.0.3)
	 * @param Toon|null $toon null before the lobby slots had toon handles (17266)
	 */
	public function __construct(
		public int $slot,
		public int $userId,
		public string $name,
		public ?string $clanTag,
		public ?Toon $toon,
	) {}
}
