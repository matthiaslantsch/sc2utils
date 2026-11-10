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
use holonet\sc2utils\model\GameVersion;
use holonet\sc2utils\model\ReplayHeader;

/**
 * The replay header of the beta builds before 0.19 (13891 to 15623).
 */
final class LegacyHeader extends Struct {
	public const string MAGIC = "\x15StarCraft II replay\x1B";

	protected const Endian ENDIAN = Endian::Big;

	public int $baseBuild = 0;

	public int $build = 0;

	/**
	 * the length in half seconds (gameloops = duration / 2 * 16).
	 */
	public int $duration = 0;

	/**
	 * the minor version, the "4" in 0.4.
	 */
	public int $minor = 0;

	/**
	 * the unknown bytes, kept as they are: after the magic (0x32 0x01 or 0x36 0x01), after the minor version (0x00),
	 * between the builds and the duration (0x0200), and the trailing one.
	 */
	public string $unknown1 = "\0\0";

	public string $unknown2 = "\0";

	public int $unknown3 = 0;

	public string $unknown4 = "\0";

	public function header(): ReplayHeader {
		return new ReplayHeader(
			new GameVersion(0, $this->minor, 0, $this->build, $this->baseBuild),
			intdiv($this->duration, 2) * 16,
			true,
		);
	}

	public static function matches(string $userData): bool {
		return str_starts_with($userData, self::MAGIC);
	}

	protected function codec(Io $io): void {
		$io->magic(self::MAGIC);
		$io->bytes($this->unknown1, 2);
		$io->uInt16($this->minor);
		$io->bytes($this->unknown2, 1);
		$io->uInt32($this->build);
		$io->uInt32($this->baseBuild);
		$io->uInt16($this->unknown3);
		$io->uInt16($this->duration);
		$io->bytes($this->unknown4, 1);
	}
}
