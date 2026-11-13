<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\model\simulation\plugin;

/**
 * Actions of one user over time, in real time.
 */
class ActionRate {
	/**
	 * @param float $average actions per real minute over the time played
	 * @param list<int> $perMinute actions in each real minute, from minute 0 to the last one played
	 * @param float $secondsPlayed until the user left, at most the real length
	 * @param int $total every counted action
	 */
	public function __construct(
		public float $average,
		public array $perMinute,
		public float $secondsPlayed,
		public int $total,
	) {}
}
