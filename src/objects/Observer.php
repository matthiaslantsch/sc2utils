<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\objects;

/**
 * the Observer class represents an observer in game (can be referee or not).
 */
class Observer extends Entity {
	/**
	 * @var bool $isReferee Flag used to mark this as a referee
	 */
	public bool $isReferee = false;
}
