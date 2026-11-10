<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils;

use RuntimeException;

/**
 * A map file lacks what MapFile::map() needs (MapInfo, a name), or has a MapInfo it can't read.
 */
class MapException extends RuntimeException {}
