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
use holonet\bitstream\codec\ReadIo;
use holonet\bitstream\Endian;
use holonet\bitstream\EndOfStreamException;
use holonet\bitstream\MalformedDataException;
use holonet\bitstream\stream\FileStream;
use holonet\bitstream\stream\Stream;

/**
 * Read-only access to an MPQ archive (SC2 replays and maps).
 *
 * Follows the format as implemented by StormLib, limited to what SC2 archives use: format v1-v4 headers, the
 * classic hash and block tables, files stored as one unit or in sectors, uncompressed or compressed with zlib or
 * bzip2, with or without sector checksums. Encrypted and imploded files, other compressions and archives over 4 GB
 * throw an MpqException.
 */
final class MpqArchive {
	private const int COMPRESSION_BZIP2 = 0x10;

	private const int COMPRESSION_ZLIB = 0x02;

	/**
	 * the header is searched at multiples of this offset.
	 */
	private const int HEADER_ALIGNMENT = 512;

	public readonly ArchiveHeader $header;

	/**
	 * the user data block in front of the archive, if there is one (holds the replay header in SC2 replays).
	 */
	public readonly ?UserDataHeader $userData;

	/**
	 * absolute offset of the archive header in the stream, all archive offsets are relative to it.
	 */
	private int $archiveOffset;

	/**
	 * @var list<BlockEntry>
	 */
	private array $blockTable;

	/**
	 * @var list<HashEntry>
	 */
	private array $hashTable;

	public function __construct(
		private readonly Stream $stream
	) {
		try {
			list($this->archiveOffset, $this->userData) = $this->findHeader();
			$this->stream->seek($this->archiveOffset);
			$this->header = ArchiveHeader::fromStream($this->stream);
			if ($this->header->isLarge()) {
				throw new MpqException('Archives with 64-bit offsets (over 4 GB) are not supported');
			}

			/** @var list<HashEntry> $hashTable */
			$hashTable = $this->readTable($this->header->hashTableOffset, $this->header->hashTableEntries, '(hash table)', HashEntry::class);
			$this->hashTable = $hashTable;
			/** @var list<BlockEntry> $blockTable */
			$blockTable = $this->readTable($this->header->blockTableOffset, $this->header->blockTableEntries, '(block table)', BlockEntry::class);
			$this->blockTable = $blockTable;
		} catch (EndOfStreamException|MalformedDataException $e) {
			throw new MpqException("Not a valid MPQ archive: {$e->getMessage()}", 0, $e);
		}
	}

	/**
	 * size of a file after decompression.
	 */
	public function fileSize(string $name): int {
		return $this->blockOf($name)->size;
	}

	public function has(string $name): bool {
		return $this->findBlock($name) !== null;
	}

	/**
	 * the names in the archive's "(listfile)", sorted, or null if it has none
	 * the archive itself only stores hashes of the names; the listfile is a convention, and some maps remove it.
	 *
	 * @return ?list<string>
	 */
	public function listFiles(): ?array {
		if (!$this->has('(listfile)')) {
			return null;
		}

		$names = preg_split('/[\r\n;]+/', $this->read('(listfile)'), -1, \PREG_SPLIT_NO_EMPTY) ?: array();
		sort($names, \SORT_STRING);

		return $names;
	}

	public static function open(string $path): self {
		return new self(FileStream::create($path, 'rb'));
	}

	/**
	 * the decompressed content of a file.
	 */
	public function read(string $name): string {
		$block = $this->blockOf($name);
		if ($block->has(BlockEntry::FLAG_ENCRYPTED)) {
			throw new MpqException("'{$name}' is encrypted, which is not supported");
		}
		if ($block->has(BlockEntry::FLAG_IMPLODED)) {
			throw new MpqException("'{$name}' is imploded (PKWARE DCL), which is not supported");
		}
		if ($block->size === 0) {
			return '';
		}

		try {
			return $this->readBlock($name, $block);
		} catch (EndOfStreamException $e) {
			throw new MpqException("'{$name}' is truncated: {$e->getMessage()}", 0, $e);
		}
	}

	private function blockOf(string $name): BlockEntry {
		return $this->findBlock($name) ?? throw new MpqException("'{$name}' does not exist in the archive");
	}

	private function decompress(string $name, string $data, int $expectedSize): string {
		$compression = ord($data[0] ?? "\0");
		$payload = substr($data, 1);
		$ret = match ($compression) {
			// zlib_decode() checks the Adler-32 checksum of the stream
			self::COMPRESSION_ZLIB => @zlib_decode($payload),
			// bzdecompress() returns an error code instead of a string on failure
			self::COMPRESSION_BZIP2 => bzdecompress($payload),
			default => throw new MpqException(sprintf("'%s' uses compression 0x%02X, which is not supported", $name, $compression)),
		};

		if (!is_string($ret)) {
			throw new MpqException(sprintf("'%s' could not be decompressed (compression 0x%02X)", $name, $compression));
		}
		if (strlen($ret) !== $expectedSize) {
			throw new MpqException(sprintf("'%s' decompressed to %d bytes instead of %d", $name, strlen($ret), $expectedSize));
		}

		return $ret;
	}

	/**
	 * look a name up in the hash table: start at its table offset hash and probe forward until an empty slot.
	 */
	private function findBlock(string $name): ?BlockEntry {
		$count = count($this->hashTable);
		if ($count === 0) {
			return null;
		}

		$hashA = MpqCrypto::hash($name, MpqCrypto::HASH_NAME_A);
		$hashB = MpqCrypto::hash($name, MpqCrypto::HASH_NAME_B);
		$start = MpqCrypto::hash($name, MpqCrypto::HASH_TABLE_OFFSET) % $count;
		for ($i = 0; $i < $count; $i++) {
			$entry = $this->hashTable[($start + $i) % $count];
			if ($entry->blockIndex === HashEntry::EMPTY) {
				return null;
			}
			if ($entry->blockIndex === HashEntry::DELETED || $entry->hashA !== $hashA || $entry->hashB !== $hashB) {
				continue;
			}

			$block = $this->blockTable[$entry->blockIndex] ?? null;
			if ($block !== null && $block->has(BlockEntry::FLAG_EXISTS) && !$block->has(BlockEntry::FLAG_DELETE_MARKER)) {
				return $block;
			}
		}

		return null;
	}

	/**
	 * find the archive header: at offset 0 or a multiple of 512, directly or behind a user data block.
	 *
	 * @return array{int, ?UserDataHeader} absolute offset of the archive header and the user data block
	 */
	private function findHeader(): array {
		for ($offset = 0; ; $offset += self::HEADER_ALIGNMENT) {
			$this->stream->seek($offset);
			$magic = $this->stream->read(4);
			if (strlen($magic) < 4) {
				throw new MpqException('No MPQ header found');
			}

			if ($magic === "MPQ\x1A") {
				return array($offset, null);
			}
			if ($magic === "MPQ\x1B") {
				$this->stream->seek($offset);
				$userData = UserDataHeader::fromStream($this->stream);

				return array($offset + $userData->headerOffset, $userData);
			}
		}
	}

	private function readAt(int $archiveOffset, int $length): string {
		$this->stream->seek($this->archiveOffset + $archiveOffset);

		return $this->stream->readExact($length);
	}

	private function readBlock(string $name, BlockEntry $block): string {
		$compressed = $block->has(BlockEntry::FLAG_COMPRESSED);

		if ($block->has(BlockEntry::FLAG_SINGLE_UNIT)) {
			$data = $this->readAt($block->offset, $block->archivedSize);

			return $compressed && $block->archivedSize < $block->size
				? $this->decompress($name, $data, $block->size)
				: substr($data, 0, $block->size);
		}

		// uncompressed sectors follow each other without a sector offset table
		if (!$compressed) {
			return $this->readAt($block->offset, $block->size);
		}

		$sectorSize = $this->header->sectorSize();
		$sectorCount = intdiv($block->size + $sectorSize - 1, $sectorSize);
		// one offset per sector plus the end offset, plus one for the checksum table if present
		$entries = $sectorCount + 1 + ($block->has(BlockEntry::FLAG_SECTOR_CRC) ? 1 : 0);
		/** @var list<int> $offsets */
		$offsets = array_values(unpack('V*', $this->readAt($block->offset, 4 * $entries)) ?: array());

		$ret = '';
		for ($i = 0; $i < $sectorCount; $i++) {
			$expected = min($sectorSize, $block->size - $i * $sectorSize);
			$stored = $offsets[$i + 1] - $offsets[$i];
			if ($stored <= 0 || $offsets[$i + 1] > $block->archivedSize) {
				throw new MpqException("'{$name}' has an invalid sector offset table");
			}

			$data = $this->readAt($block->offset + $offsets[$i], $stored);
			// a sector is only compressed if that made it smaller
			$ret .= $stored < $expected ? $this->decompress($name, $data, $expected) : substr($data, 0, $expected);
		}

		return $ret;
	}

	/**
	 * read and decrypt a table, then decode its entries.
	 *
	 * @template T of HashEntry|BlockEntry
	 * @param class-string<T> $class
	 * @return list<T>
	 */
	private function readTable(int $offset, int $count, string $keyName, string $class): array {
		$data = MpqCrypto::decrypt($this->readAt($offset, 16 * $count), MpqCrypto::hash($keyName, MpqCrypto::HASH_FILE_KEY));

		$entries = null;
		ReadIo::ofBytes($data, Endian::Little)->list($entries, $count, static fn (Io $io, ?object &$entry) => $io->object($entry, $class));

		/** @var list<T> $entries */
		return $entries;
	}
}
