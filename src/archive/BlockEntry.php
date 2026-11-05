<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\archive;

use holonet\bitstream\codec\Io;
use holonet\bitstream\codec\Struct;
use holonet\bitstream\Endian;

/**
 * One entry of the block table: where a file's data is stored and how.
 */
final class BlockEntry extends Struct {
	/**
	 * compressed, each stored unit starts with a byte naming the compression.
	 */
	public const int FLAG_COMPRESSED = 0x0000_0200;

	/**
	 * the file was deleted (in patch archives).
	 */
	public const int FLAG_DELETE_MARKER = 0x0200_0000;

	public const int FLAG_ENCRYPTED = 0x0001_0000;

	/**
	 * the entry describes an existing file.
	 */
	public const int FLAG_EXISTS = 0x8000_0000;

	/**
	 * the encryption key is adjusted by the file's offset and size.
	 */
	public const int FLAG_FIX_KEY = 0x0002_0000;

	/**
	 * compressed with PKWARE Data Compression Library (old format).
	 */
	public const int FLAG_IMPLODED = 0x0000_0100;

	/**
	 * the sector offset table has one more entry, pointing to a table of sector checksums.
	 */
	public const int FLAG_SECTOR_CRC = 0x0400_0000;

	/**
	 * the file is stored as one unit instead of in sectors.
	 */
	public const int FLAG_SINGLE_UNIT = 0x0100_0000;

	protected const Endian ENDIAN = Endian::Little;

	/**
	 * size of the file as stored.
	 */
	public int $archivedSize = 0;

	public int $flags = 0;

	/**
	 * offset of the file data, relative to the start of the archive header.
	 */
	public int $offset = 0;

	/**
	 * size of the file after decompression.
	 */
	public int $size = 0;

	public function has(int $flag): bool {
		return ($this->flags & $flag) !== 0;
	}

	protected function codec(Io $io): void {
		$io->uInt32($this->offset);
		$io->uInt32($this->archivedSize);
		$io->uInt32($this->size);
		$io->uInt32($this->flags);
	}
}
