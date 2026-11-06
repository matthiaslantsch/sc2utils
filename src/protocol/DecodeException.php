<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\protocol;

use RuntimeException;

/**
 * Replay data doesn't match its protocol: truncated, corrupt, not consumed to the end, or a value PHP can't hold.
 */
class DecodeException extends RuntimeException {}
