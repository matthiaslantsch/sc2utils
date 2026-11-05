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
use holonet\bitstream\MalformedDataException;

/**
 * The MPQ archive header ("MPQ\x1A").
 *
 * Format versions (stored zero-based): 0 = v1 (32 bytes), 1 = v2 (44 bytes, adds the hi-block table and the high
 * 16 bits of the table offsets), 2 = v3 and 3 = v4 (208 bytes, add 64-bit sizes and the HET/BET tables). SC2 uses
 * v2 and v4. Only the classic hash and block tables are read, and SC2 archives always have them; the fields beyond
 * v2 are skipped.
 */
final class ArchiveHeader extends Struct {
	protected const Endian ENDIAN = Endian::Little;

	private const int V1_SIZE = 32;

	private const int V2_SIZE = 44;

	/**
	 * size of the archive (v1 field, may be outdated in later versions).
	 */
	public int $archiveSize = 0;

	/**
	 * number of entries in the block table.
	 */
	public int $blockTableEntries = 0;

	/**
	 * offset of the block table, relative to the start of this header.
	 */
	public int $blockTableOffset = 0;

	/**
	 * v2+: high 16 bits of the block table offset.
	 */
	public int $blockTableOffsetHigh = 0;

	/**
	 * zero-based: 0 = MPQ format v1, 1 = v2, 2 = v3, 3 = v4.
	 */
	public int $formatVersion = 0;

	/**
	 * number of entries in the hash table (a power of two).
	 */
	public int $hashTableEntries = 0;

	/**
	 * offset of the hash table, relative to the start of this header.
	 */
	public int $hashTableOffset = 0;

	/**
	 * v2+: high 16 bits of the hash table offset.
	 */
	public int $hashTableOffsetHigh = 0;

	/**
	 * size of this header in bytes, depends on the format version.
	 */
	public int $headerSize = self::V1_SIZE;

	/**
	 * v2+: offset of the hi-block table (high 16 bits of file offsets), 0 if there is none.
	 */
	public int $hiBlockTableOffset = 0;

	/**
	 * the sector size is 512 << shift.
	 */
	public int $sectorSizeShift = 3;

	/**
	 * whether the archive needs offsets beyond 32 bits (archives over 4 GB), which this reader doesn't support.
	 */
	public function isLarge(): bool {
		return $this->hiBlockTableOffset !== 0 || $this->hashTableOffsetHigh !== 0 || $this->blockTableOffsetHigh !== 0;
	}

	public function sectorSize(): int {
		return 512 << $this->sectorSizeShift;
	}

	protected function codec(Io $io): void {
		$io->magic("MPQ\x1A");
		$io->uInt32($this->headerSize);
		$io->uInt32($this->archiveSize);
		$io->uInt16($this->formatVersion);
		$io->uInt16($this->sectorSizeShift);
		$io->uInt32($this->hashTableOffset);
		$io->uInt32($this->blockTableOffset);
		$io->uInt32($this->hashTableEntries);
		$io->uInt32($this->blockTableEntries);

		$size = self::V1_SIZE;
		if ($this->formatVersion >= 1) {
			$io->uInt64($this->hiBlockTableOffset);
			$io->uInt16($this->hashTableOffsetHigh);
			$io->uInt16($this->blockTableOffsetHigh);
			$size = self::V2_SIZE;
		}

		if ($this->headerSize < $size) {
			throw new MalformedDataException("MPQ header size {$this->headerSize} is too small for format version {$this->formatVersion}");
		}
		$io->skip($this->headerSize - $size);
	}
}
