<?php
/**
 * This file is part of the sc2rep replay parser project
 * (c) Matthias Lantsch.
 *
 * class file for the TargetPointCommandEvent logic class
 */

namespace HIS5\lib\Sc2repParser\events;

/**
 * TargetPointCommandEvent is an event generated for player commands that point to a location.
 *
 * @author  {AUTHOR}
 */
class TargetPointCommandEvent extends CommandEvent {
	/**
	 * property containing the command target location on the map.
	 *
	 * @var array location | array with the location with the x, y an z index
	 */
	public array $location;
}
