<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\model;

/**
 * The replay header (the MPQ user data): what every replay file has, beta replays included.
 */
final readonly class ReplayHeader {
	/**
	 * @param int $gameloops length of the game in gameloops (16 per game second)
	 * @param bool $legacyFormat the beta header format before 0.19 (no s2protocol protocol for the rest of the file)
	 */
	public function __construct(
		public GameVersion $version,
		public int $gameloops,
		public bool $legacyFormat = false,
	) {}
}
