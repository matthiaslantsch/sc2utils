<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\protocol;

use GMP;
use holonet\bitstream\BitArray;

/**
 * Reads the bit-packed encoding: replay.initData, replay.game.events and replay.message.events.
 *
 * No markers or tags: values follow each other in typeinfo order, each int taking exactly the bits of its bounds
 * (value = offset + bits). Blobs are byte-aligned, fourccs aren't.
 *
 * Port of s2protocol's BitPackedDecoder (5.0.17). Like upstream, an unknown choice tag throws. Ints that don't fit
 * into a PHP int throw too.
 */
final class BitPackedCodec implements TypeDecoder {
	private readonly int $length;

	/**
	 * the rest of the current byte, shifted down so its next bit is bit 0.
	 */
	private int $next = 0;

	/**
	 * how many bits of the current byte are left.
	 */
	private int $nextBits = 0;

	private int $position = 0;

	/**
	 * @var list<list<mixed>>
	 */
	private readonly array $typeinfos;

	public function __construct(
		Protocol $protocol,
		private readonly string $data
	) {
		$this->typeinfos = $protocol->typeinfos;
		$this->length = strlen($data);
	}

	public function align(): void {
		$this->nextBits = 0;
	}

	public function atEnd(): bool {
		return $this->nextBits === 0 && $this->position >= $this->length;
	}

	public function decode(int $typeId): mixed {
		$value = $this->instance($typeId);
		$this->align();
		if (!$this->atEnd()) {
			throw new DecodeException(sprintf('%d of %d bytes not consumed', $this->length - $this->position, $this->length));
		}

		return $value;
	}

	public function instance(int $typeId): mixed {
		$typeinfo = $this->typeinfos[$typeId] ?? throw new DecodeException("Unknown type id {$typeId}");

		switch ($typeinfo[0]) {
			case 'int':
				return $this->int($typeinfo[1], $typeinfo[2]);
			case 'struct':
				return $this->struct($typeinfo[1]);
			case 'optional':
				return $this->bits(1) !== 0 ? $this->instance($typeinfo[1]) : null;
			case 'choice':
				$tag = $this->int($typeinfo[1], $typeinfo[2]);
				if (!isset($typeinfo[3][$tag])) {
					throw new DecodeException("Unknown choice tag {$tag} of type {$typeId} at bit {$this->usedBits()}");
				}
				list($name, $type) = $typeinfo[3][$tag];

				return array($name => $this->instance($type));
			case 'array':
				$count = $this->int($typeinfo[1], $typeinfo[2]);
				$ret = array();
				for ($i = 0; $i < $count; $i++) {
					$ret[] = $this->instance($typeinfo[3]);
				}

				return $ret;
			case 'bool':
				return $this->bits(1) !== 0;
			case 'blob':
				return $this->alignedBytes($this->int($typeinfo[1], $typeinfo[2]));
			case 'bitarray':
				return $this->bitArray($this->int($typeinfo[1], $typeinfo[2]));
			case 'fourcc':
				return chr($this->bits(8)).chr($this->bits(8)).chr($this->bits(8)).chr($this->bits(8));
			case 'null':
				return null;
			default:
				throw new DecodeException("Unknown typeinfo kind of type {$typeId}");
		}
	}

	public function usedBits(): int {
		return $this->position * 8 - $this->nextBits;
	}

	private function alignedBytes(int $count): string {
		$this->align();
		if ($count < 0 || $this->position + $count > $this->length) {
			throw new DecodeException("Data truncated: {$count} bytes at offset {$this->position}, {$this->length} in total");
		}
		$ret = substr($this->data, $this->position, $count);
		$this->position += $count;

		return $ret;
	}

	private function bitArray(int $width): BitArray {
		if ($width <= 62) {
			return BitArray::fromNumber($this->bits($width), $width);
		}

		$value = gmp_init(0);
		for ($left = $width; $left > 0; $left -= $take) {
			$take = min($left, $this->nextBits > 0 ? $this->nextBits : 32);
			$value = ($value << $take) | $this->bits($take);
		}

		/** @var GMP $value */
		return BitArray::fromNumber($value, $width);
	}

	/**
	 * Read up to 64 bits as s2protocol's read_bits(). 64 bits come back as their two's complement bit pattern.
	 */
	private function bits(int $count): int {
		$ret = 0;
		$have = 0;
		while ($have !== $count) {
			if ($this->nextBits === 0) {
				if ($this->position >= $this->length) {
					throw new DecodeException("Data truncated at bit {$this->usedBits()}");
				}
				$this->next = ord($this->data[$this->position++]);
				$this->nextBits = 8;
			}
			$copy = min($count - $have, $this->nextBits);
			$ret |= ($this->next & ((1 << $copy) - 1)) << ($count - $have - $copy);
			$this->next >>= $copy;
			$this->nextBits -= $copy;
			$have += $copy;
		}

		return $ret;
	}

	private function int(int $offset, int $width): int {
		if ($width < 64) {
			$ret = $offset + $this->bits($width);
		} elseif ($width === 64) {
			$raw = $this->bits(64);
			if ($offset === \PHP_INT_MIN) {
				// PHP_INT_MIN + unsigned raw, computed on the bit pattern
				return $raw ^ \PHP_INT_MIN;
			}
			if ($raw < 0) {
				throw new DecodeException("64-bit value at bit {$this->usedBits()} does not fit into a PHP int");
			}
			$ret = $offset + $raw;
		} else {
			throw new DecodeException("Ints wider than 64 bits are not supported ({$width} bits)");
		}

		if (!is_int($ret)) {
			throw new DecodeException("Value at bit {$this->usedBits()} does not fit into a PHP int");
		}

		return $ret;
	}

	/**
	 * @param list<array{string, int, int}> $fields [name, type id, tag]
	 */
	private function struct(array $fields): mixed {
		$ret = array();
		foreach ($fields as list($name, $type)) {
			if ($name !== '__parent') {
				$ret[$name] = $this->instance($type);

				continue;
			}

			// as in VersionedCodec::struct()
			$parent = $this->instance($type);
			$kind = $this->typeinfos[$type][0];
			if (is_array($parent) && ($kind === 'struct' || $kind === 'choice')) {
				foreach ($parent as $key => $value) {
					$ret[$key] = $value;
				}
			} elseif (count($fields) === 1) {
				$ret = $parent;
			} else {
				$ret[$name] = $parent;
			}
		}

		return $ret;
	}
}
