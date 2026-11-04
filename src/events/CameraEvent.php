<?php
/**
 * This file is part of the sc2rep replay parser project
 * (c) Matthias Lantsch.
 *
 * class file for the CameraEvent logic class
 */

namespace HIS5\lib\Sc2repParser\events;

/**
 * CameraEvent is an event generated when a user moves his camera.
 *
 * @author  {AUTHOR}
 */
class CameraEvent extends EventBase {
	/**
	 * property containing the distance to the camera target ??
	 *
	 * @var int distance | distance to the camera target
	 */
	public int $distance;

	/**
	 * property containing an array with the location described as 2d point.
	 *
	 * @var array location | array with x and y coordinate
	 */
	public array $location;

	/**
	 * property containing the current pitch of the camera.
	 *
	 * @var int pitch | current pitch of the camera
	 */
	public int $pitch;

	/**
	 * property containing the current yaw of the camera.
	 *
	 * @var int yaw | current yaw of the camera
	 */
	public int $yaw;

	/**
	 * constructor accepting the gameloop count.
	 *
	 * @param  int gameloops | gameloop counter at the time the event happened
	 * @param  int playerId | playerId of the player that caused the event
	 * @param  array location | array with x and y coordinate
	 */
	public function __construct($gameloops, $playerId, $location) {
		parent::__construct($gameloops, $playerId);
		$this->location = $location;
	}
}
