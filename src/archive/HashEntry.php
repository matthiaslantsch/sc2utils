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
 * One entry of the hash table: identifies a file by two hashes of its name and points to its block.
 * The archive doesn't store file names, only these hashes.
 */
final class HashEntry extends Struct {
	/**
	 * block index of a slot whose file was deleted, a lookup continues past it.
	 */
	public const int DELETED = 0xFFFF_FFFE;

	/**
	 * block index of a slot that was never used, ends a lookup.
	 */
	public const int EMPTY = 0xFFFF_FFFF;

	protected const Endian ENDIAN = Endian::Little;

	public int $blockIndex = self::EMPTY;

	public int $hashA = 0;

	public int $hashB = 0;

	public int $locale = 0;

	public int $platform = 0;

	protected function codec(Io $io): void {
		$io->uInt32($this->hashA);
		$io->uInt32($this->hashB);
		$io->uInt16($this->locale);
		$io->uInt16($this->platform);
		$io->uInt32($this->blockIndex);
	}
}
