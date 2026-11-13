<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\model;

/**
 * A position on the map in map cells (the unit of the map's width and height).
 */
final readonly class Point {
	/**
	 * @param float|null $z height, where the event has one
	 */
	public function __construct(
		public float $x,
		public float $y,
		public ?float $z = null,
	) {}
}
