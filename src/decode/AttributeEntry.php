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
 * One lobby attribute: its id, the scope it applies to (a player id, or 16 for the game) and a 4-byte value.
 */
final class AttributeEntry extends Struct {
	/**
	 * The scope of attributes that apply to the whole game.
	 */
	public const int SCOPE_GAME = 16;

	protected const Endian ENDIAN = Endian::Little;

	public int $id = 0;

	public int $namespace = 0;

	/**
	 * the value as stored: four bytes, reversed and zero-padded.
	 */
	public string $raw = "\0\0\0\0";

	public int $scope = 0;

	/**
	 * The value as s2protocol decodes it: the bytes reversed, zero bytes stripped (e.g. "Humn", "   0").
	 */
	public string $value {
		get => trim(strrev($this->raw), "\0");
	}

	protected function codec(Io $io): void {
		$io->uInt32($this->namespace);
		$io->uInt32($this->id);
		$io->uInt8($this->scope);
		$io->bytes($this->raw, 4);
	}
}
