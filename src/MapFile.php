<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils;

use holonet\bitstream\EndOfStreamException;
use holonet\bitstream\MalformedDataException;
use holonet\bitstream\stream\StringStream;
use holonet\sc2utils\archive\MpqArchive;
use holonet\sc2utils\decode\MapInfoFile;
use holonet\sc2utils\model\map\Bounds;
use holonet\sc2utils\model\map\Map;

/**
 * An open map file (.s2ma): the archive and the files the library reads from it.
 */
class MapFile {
	/**
	 * the locales Blizzard's maps carry, used when the archive has no listfile to find them.
	 */
	public const array KNOWN_LOCALES = array(
		'deDE', 'enGB', 'enSG', 'enUS', 'esES', 'esMX', 'frFR', 'itIT', 'koKR', 'plPL', 'ptBR', 'ruRU', 'zhCN', 'zhTW',
	);

	/**
	 * the locales to take the name from first; the others follow in alphabetical order.
	 */
	public const array PREFERRED_LOCALES = array('enUS', 'enGB');

	private ?MapInfoFile $mapInfo = null;

	public function __construct(
		private readonly MpqArchive $archive,
	) {}

	/**
	 * The DocInfo/Name entry of a GameStrings.txt, null if it has none.
	 */
	public static function docName(string $gameStrings): ?string {
		if (str_starts_with($gameStrings, "\u{FEFF}")) {
			$gameStrings = substr($gameStrings, 3);
		}
		foreach (preg_split('/\r?\n/', $gameStrings) ?: array() as $line) {
			if (str_starts_with($line, 'DocInfo/Name=')) {
				return substr($line, strlen('DocInfo/Name='));
			}
		}

		return null;
	}

	public function has(string $name): bool {
		return $this->archive->has($name);
	}

	/**
	 * The locales the map has strings for, in the order the name is taken from them.
	 *
	 * @return list<string>
	 */
	public function locales(): array {
		$listed = $this->archive->listFiles();
		if ($listed === null) {
			return self::orderLocales(array_values(array_filter(
				self::KNOWN_LOCALES,
				fn (string $locale): bool => $this->has(self::gameStringsPath($locale)),
			)));
		}

		$found = array();
		foreach ($listed as $entry) {
			if (preg_match('/^(\w{4})\.SC2Data\\\LocalizedData\\\GameStrings\.txt$/', $entry, $match) === 1) {
				$found[] = $match[1];
			}
		}

		return self::orderLocales($found);
	}

	/**
	 * Throws MapException if the map has no MapInfo, one this reader can't read, or no name in any locale.
	 */
	public function map(): Map {
		$info = $this->mapInfo();

		return new Map(
			$this->name(),
			$info->width,
			$info->height,
			new Bounds($info->cameraLeft, $info->cameraBottom, $info->cameraRight, $info->cameraTop),
			$info->tileSet,
			$this->has('Minimap.tga') ? $this->read('Minimap.tga') : null,
		);
	}

	public function mapInfo(): MapInfoFile {
		if ($this->mapInfo === null) {
			if (!$this->has('MapInfo')) {
				throw new MapException('The map has no MapInfo');
			}

			try {
				$this->mapInfo = MapInfoFile::fromStream(new StringStream($this->read('MapInfo')));
			} catch (EndOfStreamException|MalformedDataException $e) {
				throw new MapException("Cannot read MapInfo: {$e->getMessage()}", 0, $e);
			}
		}

		return $this->mapInfo;
	}

	/**
	 * The map's name in the first locale that has a non-empty one (enUS, enGB, then the rest).
	 */
	public function name(): string {
		foreach ($this->locales() as $locale) {
			$name = self::docName($this->read(self::gameStringsPath($locale)));
			if ($name !== null && $name !== '') {
				return $name;
			}
		}

		throw new MapException('The map has no name in any locale');
	}

	public static function open(string $path): self {
		return new self(MpqArchive::open($path));
	}

	/**
	 * The preferred locales first, the rest alphabetically; duplicates removed.
	 *
	 * @param list<string> $locales
	 * @return list<string>
	 */
	public static function orderLocales(array $locales): array {
		$locales = array_unique($locales);
		$preferred = array_values(array_intersect(self::PREFERRED_LOCALES, $locales));
		$rest = array_diff($locales, self::PREFERRED_LOCALES);
		sort($rest, \SORT_STRING);

		return array_merge($preferred, $rest);
	}

	/**
	 * A file of the archive; throws MpqException if it is missing.
	 */
	public function read(string $name): string {
		return $this->archive->read($name);
	}

	/**
	 * MapScript.galaxy, the map's trigger script, null if the map has none.
	 */
	public function script(): ?string {
		return $this->has('MapScript.galaxy') ? $this->read('MapScript.galaxy') : null;
	}

	private static function gameStringsPath(string $locale): string {
		return "{$locale}.SC2Data\\LocalizedData\\GameStrings.txt";
	}
}
