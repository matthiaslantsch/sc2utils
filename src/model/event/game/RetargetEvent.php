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
 * A repeated command with a new target (SCmdUpdateTargetPointEvent / SCmdUpdateTargetUnitEvent, from 34784): the
 * same command clicked again, encoded compactly. It carries no ability.
 */
class RetargetEvent extends CommandEvent {
	public function __construct(int $gameloop, ?User $user, ?Point $targetPoint, ?int $targetUnit) {
		parent::__construct($gameloop, $user, 0, null, 0, null, $targetPoint, $targetUnit, null);
	}
}
