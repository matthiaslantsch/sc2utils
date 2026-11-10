<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\decode;

use holonet\bitstream\codec\Io;
use holonet\bitstream\codec\Struct;
use holonet\bitstream\Endian;
use holonet\bitstream\MalformedDataException;

/**
 * The front of a map's MapInfo file, up to the base height: the part sc2reader reads (its objects.MapInfo).
 * The layout after it is not known reliably, so a read can't check that it consumed the file (use fromStream()).
 *
 * The version checks only add fields: from 0x18 two numbers before the width, from 0x1F a string and a number
 * before the fog type, from 0x26 another string. Versions below 0x18 (WoL beta maps) are not supported.
 */
final class MapInfoFile extends Struct {
	public const string MAGIC = 'IpaM';

	public const int MIN_VERSION = 0x18;

	protected const Endian ENDIAN = Endian::Little;

	/**
	 * the base height, in 1/4096 (the editor's value * 4096).
	 */
	public int $baseHeight = 0;

	public int $cameraBottom = 0;

	/**
	 * the camera bounds; sc2reader notes they are 7 (left/right) and 4 (bottom/top) outside the editor's values.
	 */
	public int $cameraLeft = 0;

	public int $cameraRight = 0;

	public int $cameraTop = 0;

	public string $fogType = '';

	public int $height = 0;

	public string $largePreviewPath = '';

	/**
	 * 0 none, 1 minimap, 2 custom (then with a path).
	 */
	public int $largePreviewType = 1;

	public string $smallPreviewPath = '';

	/**
	 * 0 none, 1 minimap, 2 custom (then with a path).
	 */
	public int $smallPreviewType = 1;

	public string $tileSet = '';

	/**
	 * unknown, between 63952 and 170972 in the maps at hand, so not a build number.
	 */
	public int $unknown1 = 0;

	/**
	 * unknown, always 0.
	 */
	public int $unknown2 = 0;

	/**
	 * unknown, from 0x1F, always empty.
	 */
	public string $unknown3 = '';

	/**
	 * unknown, from 0x26, always empty.
	 */
	public string $unknown4 = '';

	/**
	 * unknown, from 0x1F, mostly 1.
	 */
	public int $unknown5 = 0;

	/**
	 * unknown, mostly 0.
	 */
	public int $unknown6 = 0;

	public int $version = 0x27;

	public int $width = 0;

	protected function codec(Io $io): void {
		$io->magic(self::MAGIC);
		$io->uInt32($this->version);
		if ($this->version < self::MIN_VERSION) {
			throw new MalformedDataException(sprintf('MapInfo version 0x%X is not supported (the oldest is 0x%X)', $this->version, self::MIN_VERSION));
		}
		$io->uInt32($this->unknown1);
		$io->uInt32($this->unknown2);
		$io->uInt32($this->width);
		$io->uInt32($this->height);
		$io->uInt32($this->smallPreviewType);
		if ($this->smallPreviewType === 2) {
			$io->cStr($this->smallPreviewPath);
		}
		$io->uInt32($this->largePreviewType);
		if ($this->largePreviewType === 2) {
			$io->cStr($this->largePreviewPath);
		}
		if ($this->version >= 0x1F) {
			$io->cStr($this->unknown3);
		}
		if ($this->version >= 0x26) {
			$io->cStr($this->unknown4);
		}
		if ($this->version >= 0x1F) {
			$io->uInt32($this->unknown5);
		}
		$io->uInt32($this->unknown6);
		$io->cStr($this->fogType);
		$io->cStr($this->tileSet);
		$io->uInt32($this->cameraLeft);
		$io->uInt32($this->cameraBottom);
		$io->uInt32($this->cameraRight);
		$io->uInt32($this->cameraTop);
		$io->uInt32($this->baseHeight);
	}
}
