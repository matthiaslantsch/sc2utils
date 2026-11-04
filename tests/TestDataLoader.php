<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\tests;

use RuntimeException;

/**
 * The TestDataLoader loads csv data from the corresponding directory
 * data for the corresponding decoder classes will be saved in csv files and then lodaded through this class.
 */
class TestDataLoader {
	/**
	 * @var string $basename Base name for the test replay file (usually the version string)
	 */
	private string $basename;

	/**
	 * @param string $basename The base name identifier for the test replay file (usually the version string)
	 */
	public function __construct(string $basename) {
		$this->basename = $basename;
	}

	/**
	 * @param string $dataset The name of the test dataset requested
	 */
	public function load(string $dataset): array {
		$filename = __DIR__.\DIRECTORY_SEPARATOR.'test_replays'.\DIRECTORY_SEPARATOR.$this->basename.\DIRECTORY_SEPARATOR.$dataset.'.json';
		if (!file_exists($filename)) {
			throw new RuntimeException("Error loading test data set {$dataset} from file {$filename}", 10);
		}

		$data = file_get_contents($filename);

		return json_decode($data, true, flags: \JSON_THROW_ON_ERROR);
	}
}
