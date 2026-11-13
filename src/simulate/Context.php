<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\simulate;

use holonet\sc2utils\model\replay\Replay;
use holonet\sc2utils\model\simulation\core\UnitModel;
use LogicException;

/**
 * Context containing information about the current state of the simulation. Handed to plugins next to events.
 */
class Context {
	/**
	 * @param array<class-string<Plugin>, Plugin> $plugins the plugins that run
	 */
	public function __construct(
		public Replay $replay,
		public UnitModel $units,
		private array $plugins,
	) {}

	/**
	 * A plugin this one depends on.
	 *
	 * @template T of Plugin
	 *
	 * @param class-string<T> $class
	 *
	 * @return T
	 */
	public function plugin(string $class): Plugin {
		$plugin = $this->plugins[$class] ?? null;
		if (!$plugin instanceof $class) {
			throw new LogicException("{$class} doesn't run in this simulation");
		}

		return $plugin;
	}
}
