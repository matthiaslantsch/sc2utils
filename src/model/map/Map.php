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
 * A map file (.s2ma), as MapFile::map() reads it.
 */
final readonly class Map {
	/**
	 * @param string $name the map's own name, in the first locale it has of enUS, enGB, then the rest
	 * @param int $width the full width in cells
	 * @param int $height the full height in cells
	 * @param Bounds $camera the camera bounds, roughly the playable area
	 * @param string $tileSet the terrain tile set, e.g. "Avernus"
	 * @param ?string $minimap the raw Minimap.tga, null if the map has none
	 */
	public function __construct(
		public string $name,
		public int $width,
		public int $height,
		public Bounds $camera,
		public string $tileSet,
		public ?string $minimap,
	) {}
}
