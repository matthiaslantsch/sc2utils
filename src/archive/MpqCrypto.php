<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\archive;

/**
 * The MPQ hash function and decryption (Blizzard's "Storm" algorithms, as documented by StormLib).
 *
 * Both are built on one table of 0x500 pseudo random 32-bit values. All arithmetic is unsigned 32-bit, done on php's
 * 64-bit ints and masked after every step.
 *
 * @internal
 */
final class MpqCrypto {
	// hash types: the same name gives a different hash per type

	/**
	 * the encryption key of a table or file.
	 */
	public const int HASH_FILE_KEY = 3;

	/**
	 * the first of the two hashes that identify a file in the hash table.
	 */
	public const int HASH_NAME_A = 1;

	/**
	 * the second of the two hashes that identify a file in the hash table.
	 */
	public const int HASH_NAME_B = 2;

	/**
	 * where the lookup in the hash table starts.
	 */
	public const int HASH_TABLE_OFFSET = 0;

	private const int MASK = 0xFFFF_FFFF;

	/**
	 * @var ?list<int> 0x500 entries, built on first use
	 */
	private static ?array $table = null;

	/**
	 * decrypt data in place of 32-bit little endian words
	 * a trailing part shorter than 4 bytes is not encrypted and stays as it is.
	 */
	public static function decrypt(string $data, int $key): string {
		$table = self::table();
		$wordBytes = strlen($data) - strlen($data) % 4;
		if ($wordBytes === 0) {
			return $data;
		}

		/** @var array<int, int> $words */
		$words = unpack('V*', substr($data, 0, $wordBytes));
		$seed1 = $key & self::MASK;
		$seed2 = 0xEEEE_EEEE;
		$plain = array();
		foreach ($words as $word) {
			$seed2 = ($seed2 + $table[0x4_00 + ($seed1 & 0xFF)]) & self::MASK;
			$value = ($word ^ ($seed1 + $seed2)) & self::MASK;
			$seed1 = (((~$seed1 << 0x15) + 0x1111_1111) | ($seed1 >> 0x0B)) & self::MASK;
			$seed2 = ($value + $seed2 + ($seed2 << 5) + 3) & self::MASK;
			$plain[] = $value;
		}

		return pack('V*', ...$plain).substr($data, $wordBytes);
	}

	/**
	 * hash a file name
	 * names are case insensitive (ASCII only) and '/' counts as '\', the name is hashed as raw bytes (so UTF-8
	 * names work as they are).
	 */
	public static function hash(string $name, int $type): int {
		$table = self::table();
		$seed1 = 0x7FED_7FED;
		$seed2 = 0xEEEE_EEEE;
		foreach (unpack('C*', strtoupper(strtr($name, '/', '\\'))) ?: array() as $char) {
			/** @var int $char */
			$seed1 = ($table[($type << 8) + $char] ^ ($seed1 + $seed2)) & self::MASK;
			$seed2 = ($char + $seed1 + $seed2 + ($seed2 << 5) + 3) & self::MASK;
		}

		return $seed1;
	}

	/**
	 * @return list<int>
	 */
	private static function table(): array {
		if (self::$table === null) {
			$table = array_fill(0, 0x5_00, 0);
			$seed = 0x0010_0001;
			for ($index1 = 0; $index1 < 0x1_00; $index1++) {
				for ($i = 0, $index2 = $index1; $i < 5; $i++, $index2 += 0x1_00) {
					$seed = ($seed * 125 + 3) % 0x2A_AAAB;
					$high = ($seed & 0xFF_FF) << 16;
					$seed = ($seed * 125 + 3) % 0x2A_AAAB;
					$table[$index2] = $high | ($seed & 0xFF_FF);
				}
			}
			self::$table = $table;
		}

		return self::$table;
	}
}
