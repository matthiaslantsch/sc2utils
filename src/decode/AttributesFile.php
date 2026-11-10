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

/**
 * replay.attributes.events: the lobby attributes. Not s2protocol typeinfo, a plain little-endian list.
 * Before build 17326 the file has no source byte (LegacyAttributesFile).
 */
class AttributesFile extends Struct {
	protected const Endian ENDIAN = Endian::Little;

	protected const bool HAS_SOURCE = true;

	/**
	 * @var list<AttributeEntry>
	 */
	public array $entries = array();

	public int $mapNamespace = 0;

	public ?int $source = null;

	/**
	 * The attributes file of a replay of this base build; an empty file has no attributes.
	 */
	public static function read(string $bytes, int $baseBuild): self {
		if ($bytes === '') {
			return new self();
		}

		return $baseBuild >= 17_326 ? self::fromBytes($bytes) : LegacyAttributesFile::fromBytes($bytes);
	}

	protected function codec(Io $io): void {
		if (static::HAS_SOURCE) {
			$io->uInt8($this->source);
		}
		$io->uInt32($this->mapNamespace);
		$count = count($this->entries);
		$io->uInt32($count);
		$io->list($this->entries, $count, static function (Io $io, ?AttributeEntry &$entry): void {
			$io->object($entry, AttributeEntry::class);
		});
	}
}
