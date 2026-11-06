<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\protocol;

/**
 * Decodes typeinfo instances from one buffer, the way s2protocol's decoders do.
 *
 * Values have s2protocol's shapes: struct => array<string, mixed> in data order, choice => array{name: value} (or
 * an empty array for an unknown tag), array => list, optional => value or null, int => int, bool => bool,
 * blob and fourcc => string, null => null.
 */
interface TypeDecoder {
	public function align(): void;

	public function atEnd(): bool;

	/**
	 * Decode one instance and require the buffer to be consumed to its end (padding up to the next byte allowed).
	 */
	public function decode(int $typeId): mixed;

	public function instance(int $typeId): mixed;

	public function usedBits(): int;
}
