<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\model\enum;

/**
 * The lobby's game mode attribute. Values are sc2reader's strings.
 */
enum GameMode: string {
	case Ladder = 'Ladder';
	case Private = 'Private';
	case Public = 'Public';
	case SinglePlayer = 'Single Player';
}
