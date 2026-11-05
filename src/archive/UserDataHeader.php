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
 * The optional user data block in front of an MPQ archive ("MPQ\x1B").
 * SC2 replays keep their replay header in it (the content), and it says where the archive header starts.
 */
final class UserDataHeader extends Struct {
	protected const Endian ENDIAN = Endian::Little;

	/**
	 * the user data itself (the replay header in SC2 replays).
	 */
	public string $content = '';

	/**
	 * offset of the archive header, relative to the start of this block.
	 */
	public int $headerOffset = 0;

	/**
	 * maximum size of the user data block.
	 */
	public int $userDataSize = 0;

	protected function codec(Io $io): void {
		$io->magic("MPQ\x1B");
		$io->uInt32($this->userDataSize);
		$io->uInt32($this->headerOffset);
		$contentLength = strlen($this->content);
		$io->uInt32($contentLength);
		$io->bytes($this->content, $contentLength);
	}
}
