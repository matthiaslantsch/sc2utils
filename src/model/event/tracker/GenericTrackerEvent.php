<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\model\event\tracker;

/**
 * A tracker event of a kind no typed class covers: s2protocol's name and its fields as decoded.
 */
class GenericTrackerEvent implements TrackerEvent {
	/**
	 * @param string $name s2protocol's event name, e.g. "NNet.Game.SBankFileEvent"
	 * @param array<string, mixed> $data the event's fields (without the "_" fields of the event loop)
	 */
	public function __construct(
		public int $gameloop,
		public string $name,
		public array $data,
	) {}
}
