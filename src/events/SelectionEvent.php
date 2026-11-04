<?php
/**
 * This file is part of the sc2rep replay parser project
 * (c) Matthias Lantsch.
 *
 * class file for the SelectionEvent logic class
 */

namespace HIS5\lib\Sc2repParser\events;

/**
 * A SelectionEvent is fired whenever the selection of the player changes.
 *
 * @author  {AUTHOR}
 */
class SelectionEvent extends EventBase {
	/**
	 * property containing the control group index being modified
	 * 10 for active selection.
	 *
	 * @var int controlGroupIndex | the control group index being modified
	 */
	public int $controlGroupIndex;

	/**
	 * property containing an array with info about the newly added units.
	 *
	 * @var array newUnitInfo | array with info about the newly added units
	 */
	public array $newUnitInfo;

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
	 * property containing the subgroup index ?!?!?
	 *
	 * @var int subgroupIndex | the subgroup index
	 */
	public int $subgroupIndex;

	/**
	 * constructor accepting the gameloop count.
	 *
	 * @param  int gameloops | gameloop counter at the time the event happened
	 * @param  int playerId | playerId of the player that caused the event
	 * @param  int controlGroup | the control group index being modified
	 * @param  int subgroupIndex | the subgroup index
	 * @param  array removeMask | removal mask array with the mask type as key, and the mask data as value
	 * @param  array addSubGroups | array with unit type data for the new units
	 * @param  array addUnitTags | array with unit tags being added
	 */
	public function __construct($gameloops, $playerId, $controlGroup, $subgroupIndex, $removeMask, $addSubGroups, $addUnitTags) {
		parent::__construct($gameloops, $playerId);

		$this->controlGroup = $controlGroup;
		$this->subgroupIndex = $subgroupIndex;
		$this->removeMask = $removeMask;
		$this->newUnitInfo = array();
		foreach ($addSubGroups as $i => $unitTypeInfo) {
			$unitTypeInfo['unitTag'] = $addUnitTags[$i];
			$this->newUnitInfo[] = $addUnitTags;
		}
	}
}
