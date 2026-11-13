<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\model\simulation\plugin;

use holonet\sc2utils\model\replay\Player;
use holonet\sc2utils\model\simulation\core\Unit;

/**
 * A mining base: a finished town hall at an expansion slot that still has minerals to mine (simulate\plugin\BaseTracker).
 */
class Base {
	public private(set) ?BaseEnd $end = null;

	public private(set) ?int $endedAt = null;

	/**
	 * @param int $establishedAt the gameloop the town hall finished (or landed) there; 0 for starting bases
	 */
	public function __construct(
		public readonly Slot $slot,
		public readonly Player $owner,
		public readonly Unit $townHall,
		public readonly int $establishedAt,
	) {}

	public function activeAt(int $gameloop): bool {
		return $this->establishedAt <= $gameloop && ($this->endedAt === null || $this->endedAt > $gameloop);
	}

	public function close(int $gameloop, BaseEnd $end): void {
		$this->endedAt ??= $gameloop;
		$this->end ??= $end;
	}
}
