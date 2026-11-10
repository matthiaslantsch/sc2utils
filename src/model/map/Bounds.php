<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\model\map;

/**
 * A rectangle in map cells, origin bottom left.
 */
final readonly class Bounds {
	public function __construct(
		public int $left,
		public int $bottom,
		public int $right,
		public int $top,
	) {}
}
