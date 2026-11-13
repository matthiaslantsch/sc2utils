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
 * A command: an ability (or a right-click, which carries none), with a target point, a target unit or neither.
 *
 * $abilityLink is the build's link number (null for a right-click); $ability is Blizzard's ability id from the
 * build's table (gamedata\Abilities), null for a right-click or a link the table doesn't have.
 */
class CommandEvent implements GameEvent {
	/**
	 * @param int $flags m_cmdFlags (queued, smart, ...)
	 * @param Point|null $targetPoint in map cells; for unit targets the unit's position when the command was given
	 * @param int|null $targetUnit the target unit's tag (index << 18 | recycle, as tracker unit ids)
	 * @param int|null $otherUnit m_otherUnit, a second unit the command refers to
	 */
	public function __construct(
		public int $gameloop,
		public ?User $user,
		public int $flags,
		public ?int $abilityLink,
		public int $commandIndex,
		public ?string $ability,
		public ?Point $targetPoint,
		public ?int $targetUnit,
		public ?int $otherUnit,
	) {}
}
