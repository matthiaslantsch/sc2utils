<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\data;

use LogicException;

class DataLoader {
	/**
	 * load a version specified dataset
	 * usually contains a base json with changes per version to overwrite.
	 *
	 * @param string $dataset The name of the data set to load
	 * @param int $baseBuild The base build to load the data for
	 * @return array with data from the requested data set
	 */
	public static function loadDataset(string $dataset, int $baseBuild): array {
		$dir = __DIR__.\DIRECTORY_SEPARATOR.$dataset.\DIRECTORY_SEPARATOR;
		$ret = self::loadFile("{$dir}base.json");

		foreach (glob("{$dir}*") as $availableVersion) {
			$filesAvailable[] = str_replace('.json', '', basename($availableVersion));
		}
		sort($filesAvailable);

		foreach ($filesAvailable as $version) {
			if ($baseBuild <= $version) {
				foreach (self::loadFile("{$dir}{$version}.json") as $key => $value) {
					$ret[$key] = $value;
				}
			}
		}

		return $ret;
	}

	/**
	 * read a specified json file and return its contents as an array.
	 *
	 * @param  string file The name of the file to load
	 * @return array with data from the requested data file
	 */
	private static function loadFile(string $file): array {
		if (file_exists($file)) {
			$content = file_get_contents($file);

			return json_decode($content, true);
		}

		throw new LogicException("File '{$file}' not found.");
	}
}
