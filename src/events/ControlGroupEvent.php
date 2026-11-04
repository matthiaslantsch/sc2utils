<?php
/**
 * This file is part of the sc2rep replay parser project
 * (c) Matthias Lantsch.
 *
 * class file for the ControlGroupEvent logic class
 */

namespace HIS5\lib\Sc2repParser\events;

/**
 * A ControlGroupEvent is fired whenever a control group is changed/recalled.
 *
 * @author  {AUTHOR}
 */
class ControlGroupEvent extends EventBase {
	/**
	 * property containing a string determing which kind of update is performed on the control group
	 * One of: Set, AddTo, Get, Unknown.
	 *
	 * @var string action | string determing the kind of update
	 */
	public string $action = 'Unknown';

	/**
	 * property containing the control group index being modified.
	 *
	 * @var int controlGroupIndex | the control group index being modified
	 */
	public int $controlGroupIndex;

	/**
	 * property containing the data for the removal mask (maskType as key, maskData as value)
	 *  "None" => null (no data)
	 *  "Mask" => integer with a bitmask where every bit indice is an include or exclude on a unit
	 *  "OneIndices" => array with integer indices being removed
	 *  "ZeroIndices" => array with integer indices marking the units to stay, the others will be removed.
	 *
	 * @var array removeMask | removal mask array with the mask type as key, and the mask data as value
	 */
	public array $removeMask;

	/**
	 * constructor accepting the gameloop count.
	 *
	 * @param  int gameloops | gameloop counter at the time the event happened
	 * @param  int playerId | playerId of the player that caused the event
	 * @param  int controlGroup | the control group index being modified
	 * @param  array removeMask | removal mask array with the mask type as key, and the mask data as value
	 * @param  int actionType | integer determing the kind of update performed on the group
	 */
	public function __construct($gameloops, $playerId, $controlGroup, $removeMask, $actionType) {
		parent::__construct($gameloops, $playerId);

		$this->controlGroup = $controlGroup;
		$this->removeMask = $removeMask;

		if ($actionType === 0) {
			$this->action = 'Set';
		} elseif ($actionType === 1) {
			$this->action = 'AddTo';
		} elseif ($actionType === 2) {
			$this->action = 'Get';
		}
	}
}
