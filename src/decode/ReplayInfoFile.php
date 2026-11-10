<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\decode;

use holonet\sc2utils\protocol\DecodeException;

/**
 * replay.info, the only metadata file of the beta builds 13891 to 14803 (0.2 to 0.10):
 *
 *     u8 slot count, per slot: u8 length, name, 5 bytes       (players and observers)
 *     u32 random seed (little-endian), u8 length + game cache name ("Dflt")
 *     u8 options, u8 0, u8 game speed, 11 unknown bytes, u8 length + map file name, 686 unknown bytes
 *     5 cache handles of 40 bytes, the last one is the map
 *     bit-packed data, then five zero bytes, one or two length bytes (not byte-aligned), map name, 0x00
 *     u32 player count, per player: u8 length + name, u8 length + race (localised), u8 length + colour "a,r,g,b"
 *
 * The part after the cache handles is not understood, so the map name is found the old way: after the first two
 * zero bytes and three more, skip the length bytes (all below 30, names start with a letter or a UTF-8 lead byte)
 * and read up to 0x00. The player list ends early (the file is shorter than the count says);
 * entries without a name are empty slots.
 */
final readonly class ReplayInfoFile {
	private const int CACHE_HANDLES = 5;

	private const int UNKNOWN_BLOCK = 686;

	/**
	 * @param list<string> $slots names in the lobby slots, players and observers, empty ones left out
	 * @param list<string> $cacheHandles 40 bytes each
	 * @param list<array{string, string, string}> $players name, race, colour
	 */
	public function __construct(
		public array $slots,
		public int $seed,
		public int $speed,
		public string $mapFile,
		public array $cacheHandles,
		public string $mapName,
		public array $players,
	) {}

	public static function fromBytes(string $bytes): self {
		$cursor = new class($bytes) {
			public int $position = 0;

			public function __construct(
				private readonly string $bytes
			) {}

			public function bytes(int $length): string {
				if ($this->position + $length > strlen($this->bytes)) {
					throw new DecodeException("replay.info ends at {$this->position}, {$length} more bytes expected");
				}
				$ret = substr($this->bytes, $this->position, $length);
				$this->position += $length;

				return $ret;
			}

			public function eof(): bool {
				return $this->position >= strlen($this->bytes);
			}

			public function find(string $needle): void {
				$found = strpos($this->bytes, $needle, $this->position);
				if ($found === false) {
					throw new DecodeException('replay.info: map name not found');
				}
				$this->position = $found;
			}

			public function peek(): int {
				return ord($this->bytes[$this->position] ?? "\0");
			}

			public function string(): string {
				return $this->bytes($this->u8());
			}

			public function u8(): int {
				return ord($this->bytes(1));
			}

			public function untilZero(): string {
				$end = strpos($this->bytes, "\0", $this->position);
				if ($end === false) {
					throw new DecodeException('replay.info: unterminated map name');
				}

				$ret = $this->bytes($end - $this->position);
				$this->position++;

				return $ret;
			}
		};

		$slots = array();
		for ($i = $cursor->u8(); $i > 0; $i--) {
			$slots[] = $cursor->string();
			$cursor->bytes(5);
		}
		/** @var array{1: int} $seed */
		$seed = unpack('V', $cursor->bytes(4));
		$cursor->string();
		$cursor->bytes(2);
		$speed = $cursor->u8();
		$cursor->bytes(11);
		$mapFile = $cursor->string();
		$cursor->bytes(self::UNKNOWN_BLOCK);
		$handles = array();
		for ($i = 0; $i < self::CACHE_HANDLES; $i++) {
			$handles[] = $cursor->bytes(40);
		}

		$cursor->find("\0\0\0\0\0");
		$cursor->bytes(5);
		while ($cursor->peek() < 30) {
			$cursor->bytes(1);
		}
		$mapName = $cursor->untilZero();

		$players = array();
		/** @var array{1: int} $count */
		$count = unpack('V', $cursor->bytes(4));
		for ($i = 0; $i < $count[1] && !$cursor->eof(); $i++) {
			$name = $cursor->string();
			if ($name === '') {
				continue;
			}
			$players[] = array($name, $cursor->string(), $cursor->string());
		}

		return new self(array_values(array_filter($slots, static fn (string $s): bool => $s !== '')), $seed[1], $speed, $mapFile, $handles, $mapName, $players);
	}

	/**
	 * The fields replay.details would have, under details' names, so ReplayMapper::replay() reads this file like
	 * details: m_title, m_cacheHandles, m_gameSpeed and per player m_name, m_race, m_color. No times, toons, teams or
	 * results (the format has none); the mapper's defaults fill those in.
	 *
	 * @return array{m_title: string, m_cacheHandles: list<string>, m_gameSpeed: int, m_playerList: list<array{m_name: string, m_race: string, m_color: array{m_a: int, m_r: int, m_g: int, m_b: int}}>}
	 */
	public function toDetails(): array {
		$players = array();
		foreach ($this->players as list($name, $race, $color)) {
			$argb = array_map(intval(...), explode(',', $color));
			if (count($argb) !== 4) {
				throw new DecodeException("replay.info: malformed colour '{$color}' of {$name}");
			}
			$players[] = array(
				'm_name' => $name,
				'm_race' => $race,
				'm_color' => array('m_a' => $argb[0], 'm_r' => $argb[1], 'm_g' => $argb[2], 'm_b' => $argb[3]),
			);
		}

		return array(
			'm_title' => $this->mapName,
			'm_cacheHandles' => $this->cacheHandles,
			'm_gameSpeed' => $this->speed,
			'm_playerList' => $players,
		);
	}
}
