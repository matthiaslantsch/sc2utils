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
 * Reads the versioned encoding: replay header, replay.details and replay.tracker.events.
 *
 * Every value starts with a one-byte marker naming its kind (0 array, 1 bitarray, 2 blob, 3 choice, 4 optional,
 * 5 struct, 6 u8, 7 u32/fourcc, 8 u64, 9 vint). Structs store their field count and a tag in front of every field,
 * so fields the table doesn't know can be skipped. A correct table skips nothing: skipped() counts the skipped
 * fields and choice values, and any count above zero means the table is missing something.
 *
 * Integers are vints: the first byte holds the sign in bit 0 and six value bits, every byte has a continuation flag
 * in bit 7, and further bytes add seven bits each, least significant first. Int bounds don't apply here.
 *
 * Port of s2protocol's VersionedDecoder (5.0.17), with two deliberate differences: unknown skip markers and values
 * that don't fit into a PHP int throw instead of being ignored, and bitarrays throw.
 */
final class VersionedCodec implements TypeDecoder {
	private readonly int $length;

	private int $position = 0;

	private int $skipped = 0;

	/**
	 * @var array<int, array<int, array{string, int, int}>> type id => tag => field, built on first use
	 */
	private array $tagMaps = array();

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
		// always aligned
	}

	public function atEnd(): bool {
		return $this->position >= $this->length;
	}

	public function decode(int $typeId): mixed {
		$value = $this->instance($typeId);
		if (!$this->atEnd()) {
			throw new DecodeException(sprintf('%d of %d bytes not consumed', $this->length - $this->position, $this->length));
		}

		return $value;
	}

	public function instance(int $typeId): mixed {
		$typeinfo = $this->typeinfos[$typeId] ?? throw new DecodeException("Unknown type id {$typeId}");

		switch ($typeinfo[0]) {
			case 'int':
				$this->expect(9);

				return $this->vint();
			case 'struct':
				return $this->struct($typeId, $typeinfo[1]);
			case 'blob':
				$this->expect(2);

				return $this->bytes($this->length());
			case 'array':
				$this->expect(0);
				$count = $this->length();
				$ret = array();
				for ($i = 0; $i < $count; $i++) {
					$ret[] = $this->instance($typeinfo[3]);
				}

				return $ret;
			case 'optional':
				$this->expect(4);

				return $this->byte() !== 0 ? $this->instance($typeinfo[1]) : null;
			case 'choice':
				$this->expect(3);
				$tag = $this->vint();
				if (!isset($typeinfo[3][$tag])) {
					$this->skipped++;
					$this->skip();

					return array();
				}
				list($name, $type) = $typeinfo[3][$tag];

				return array($name => $this->instance($type));
			case 'bool':
				$this->expect(6);

				return $this->byte() !== 0;
			case 'fourcc':
				$this->expect(7);

				return $this->bytes(4);
			case 'null':
				return null;
			case 'bitarray':
				throw new DecodeException("Bitarrays in the versioned encoding are not supported (type {$typeId} at offset {$this->position})");
			default:
				throw new DecodeException("Unknown typeinfo kind of type {$typeId}");
		}
	}

	/**
	 * Fields and choice values skipped because the table doesn't know their tag; zero for a correct table.
	 */
	public function skipped(): int {
		return $this->skipped;
	}

	public function usedBits(): int {
		return $this->position * 8;
	}

	private function byte(): int {
		if ($this->position >= $this->length) {
			throw new DecodeException("Data truncated at offset {$this->position}");
		}

		return ord($this->data[$this->position++]);
	}

	private function bytes(int $count): string {
		if ($this->position + $count > $this->length) {
			throw new DecodeException("Data truncated: {$count} bytes at offset {$this->position}, {$this->length} in total");
		}
		$ret = substr($this->data, $this->position, $count);
		$this->position += $count;

		return $ret;
	}

	private function expect(int $marker): void {
		$actual = $this->byte();
		if ($actual !== $marker) {
			throw new DecodeException(sprintf('Expected marker %d, got %d at offset %d', $marker, $actual, $this->position - 1));
		}
	}

	/**
	 * a vint used as a count or size.
	 */
	private function length(): int {
		$ret = $this->vint();
		if ($ret < 0) {
			throw new DecodeException("Negative length {$ret} at offset {$this->position}");
		}

		return $ret;
	}

	private function skip(): void {
		$marker = $this->byte();
		switch ($marker) {
			case 0: // array
				for ($i = $this->length(); $i > 0; $i--) {
					$this->skip();
				}

				break;
			case 1: // bitarray
				$this->bytes(intdiv($this->length() + 7, 8));

				break;
			case 2: // blob
				$this->bytes($this->length());

				break;
			case 3: // choice
				$this->vint();
				$this->skip();

				break;
			case 4: // optional
				if ($this->byte() !== 0) {
					$this->skip();
				}

				break;
			case 5: // struct
				for ($i = $this->length(); $i > 0; $i--) {
					$this->vint();
					$this->skip();
				}

				break;
			case 6:
				$this->bytes(1);

				break;
			case 7:
				$this->bytes(4);

				break;
			case 8:
				$this->bytes(8);

				break;
			case 9:
				$this->vint();

				break;
			default:
				throw new DecodeException(sprintf('Unknown marker %d at offset %d', $marker, $this->position - 1));
		}
	}

	/**
	 * @param list<array{string, int, int}> $fields [name, type id, tag]
	 */
	private function struct(int $typeId, array $fields): mixed {
		$this->expect(5);
		$tags = $this->tagMaps[$typeId] ??= array_column($fields, null, 2);

		$ret = array();
		for ($i = $this->length(); $i > 0; $i--) {
			$tag = $this->vint();
			if (!isset($tags[$tag])) {
				$this->skipped++;
				$this->skip();

				continue;
			}

			list($name, $type) = $tags[$tag];
			if ($name !== '__parent') {
				$ret[$name] = $this->instance($type);

				continue;
			}

			// s2protocol: a struct or choice parent is merged in, any other parent replaces a struct with no other
			// fields, otherwise it's kept under its name
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

	private function vint(): int {
		$byte = $this->byte();
		$negative = ($byte & 1) !== 0;
		$ret = ($byte >> 1) & 0x3F;
		$bits = 6;
		while (($byte & 0x80) !== 0) {
			$byte = $this->byte();
			$chunk = $byte & 0x7F;
			if ($chunk !== 0 && ($bits >= 63 || ($chunk >> (63 - $bits)) !== 0)) {
				throw new DecodeException("Integer at offset {$this->position} does not fit into a PHP int");
			}
			$ret |= $chunk << $bits;
			$bits += 7;
		}

		return $negative ? -$ret : $ret;
	}
}
