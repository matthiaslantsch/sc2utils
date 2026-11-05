<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\archive;

use RuntimeException;

/**
 * An MPQ archive is broken, uses a feature this reader doesn't support, or doesn't contain the requested file.
 */
class MpqException extends RuntimeException {}
