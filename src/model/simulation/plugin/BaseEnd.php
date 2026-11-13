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
 * Why a mining base ended.
 */
enum BaseEnd: string {
	case Destroyed = 'destroyed';
	case Lifted = 'lifted';
	case MinedOut = 'mined out';
}
