<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\model\simulation;

use holonet\sc2utils\model\replay\Replay;
use holonet\sc2utils\model\simulation\core\UnitModel;
use holonet\sc2utils\simulate\Plugin;
use InvalidArgumentException;

/**
 * The result of running a replay through the engine: the replay, the unit model and each plugin's result.
 */
class Simulation {
	/**
	 * @param array<class-string<Plugin>, mixed> $results by plugin class; null for a plugin that doesn't apply to the replay
	 */
	public function __construct(
		public Replay $replay,
		public UnitModel $units,
		private array $results,
	) {}

	/**
	 * Whether a plugin was part of the simulation (not whether it applied).
	 * @param class-string<Plugin> $class
	 */
	public function has(string $class): bool {
		return array_key_exists($class, $this->results);
	}

	/**
	 * A plugin's result; null if the plugin didn't apply to the replay.
	 *
	 * @template R
	 *
	 * @param class-string<Plugin<R>> $class
	 *
	 * @throws InvalidArgumentException if the plugin wasn't part of the simulation
	 * @return R|null
	 */
	public function result(string $class): mixed {
		if (!array_key_exists($class, $this->results)) {
			throw new InvalidArgumentException("{$class} was not part of the simulation");
		}

		/** @var R|null */
		return $this->results[$class];
	}
}
