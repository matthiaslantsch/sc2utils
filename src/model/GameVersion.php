<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\model;

use Stringable;

final readonly class GameVersion implements Stringable {
	public function __construct(
		public int $major,
		public int $minor,
		public int $patch,
		public int $build,
		public int $baseBuild,
	) {}

	/**
	 * The release string, e.g. "4.4.0.65895".
	 */
	public function __toString(): string {
		return "{$this->major}.{$this->minor}.{$this->patch}.{$this->build}";
	}
}
