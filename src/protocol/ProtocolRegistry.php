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
 * Picks and loads the protocol for a replay: the highest protocol build not above the replay's base build.
 *
 * Read the header with latest() first (its layout is stable from 15405 on), then decode everything else, header
 * included, with forBuild(m_version.m_baseBuild). Loaded protocols are kept.
 */
final class ProtocolRegistry {
	private const string DATA_DIR = __DIR__.'/data';

	/**
	 * @var list<int>|null
	 */
	private ?array $builds = null;

	/**
	 * @var array<int, Protocol>
	 */
	private array $loaded = array();

	public function __construct(
		private readonly string $dataDir = self::DATA_DIR
	) {}

	/**
	 * @return list<int> every protocol build, ascending
	 */
	public function builds(): array {
		if ($this->builds === null) {
			/** @var list<int> $builds */
			$builds = require "{$this->dataDir}/builds.php";
			$this->builds = $builds;
		}

		return $this->builds;
	}

	public function forBuild(int $baseBuild): Protocol {
		return $this->load($this->nearestBuild($baseBuild));
	}

	public function latest(): Protocol {
		$builds = $this->builds();

		return $this->load($builds[array_key_last($builds)]);
	}

	/**
	 * The protocol of exactly this build.
	 */
	public function load(int $build): Protocol {
		if (!isset($this->loaded[$build])) {
			if (!in_array($build, $this->builds(), true)) {
				throw new UnsupportedBuildException("There is no protocol {$build}");
			}

			/** @var array<string, mixed> $data */
			$data = require sprintf('%s/protocol%05d.php', $this->dataDir, $build);
			$this->loaded[$build] = Protocol::fromArray($data);
		}

		return $this->loaded[$build];
	}

	public function nearestBuild(int $baseBuild): int {
		$ret = null;
		foreach ($this->builds() as $build) {
			if ($build > $baseBuild) {
				break;
			}
			$ret = $build;
		}

		if ($ret === null) {
			throw new UnsupportedBuildException("No protocol for base build {$baseBuild}, the oldest is {$this->builds()[0]}");
		}

		return $ret;
	}
}
