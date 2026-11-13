<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\simulate\plugin;

use holonet\sc2utils\model\replay\Replay;
use holonet\sc2utils\model\replay\User;
use holonet\sc2utils\model\simulation\PerUser;
use holonet\sc2utils\model\simulation\plugin\ActionRate;

/**
 * Counts actions per user and turns them into rates (APM, SPM).
 * Time played ends when the user leaves, but never after the real length of the replay.
 */
final class ActionCounter {
	/**
	 * @var array<int, list<int>> user id => gameloops of the actions
	 */
	private array $actions = array();

	/**
	 * @var array<int, int> user id => gameloop they left
	 */
	private array $left = array();

	public function count(User $user, int $gameloop): void {
		$this->actions[$user->userId][] = $gameloop;
	}

	public function left(User $user, int $gameloop): void {
		$this->left[$user->userId] ??= $gameloop;
	}

	/**
	 * A rate for every user of the replay, observers included (those without actions with zeros).
	 * @return PerUser<ActionRate>
	 */
	public function result(Replay $replay): PerUser {
		$entries = array();
		foreach ($replay->users as $user) {
			$played = min((float)$replay->realLength, $replay->realSeconds($this->left[$user->userId] ?? $replay->header->gameloops));
			$minutes = $played > 0 ? array_fill(0, (int)ceil($played / 60.0), 0) : array();
			$actions = $this->actions[$user->userId] ?? array();
			foreach ($actions as $gameloop) {
				$minute = (int)floor($replay->realSeconds($gameloop) / 60.0);
				if ($minute >= count($minutes)) {
					$minutes = array_pad($minutes, $minute + 1, 0);
				}
				$minutes[$minute]++;
			}
			$entries[$user->userId] = array($user, new ActionRate($played > 0 ? (float)count($actions) / $played * 60.0 : 0.0, $minutes, $played, count($actions)));
		}

		return new PerUser($entries);
	}
}
