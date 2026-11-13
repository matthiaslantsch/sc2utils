<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\simulate;

use Closure;
use Generator;
use holonet\sc2utils\decode\EventBuilder;
use holonet\sc2utils\model\event\Event;
use holonet\sc2utils\model\replay\Replay;
use holonet\sc2utils\model\simulation\Simulation;
use holonet\sc2utils\ReplayFile;
use holonet\sc2utils\simulate\core\ContextLoader;
use holonet\sc2utils\simulate\core\HallucinationMatcher;
use holonet\sc2utils\simulate\core\MorphLinker;
use holonet\sc2utils\simulate\plugin\AbilityTracker;
use holonet\sc2utils\simulate\plugin\ApmTracker;
use holonet\sc2utils\simulate\plugin\BaseTracker;
use holonet\sc2utils\simulate\plugin\SpmTracker;
use LogicException;

/**
 * Runs a replay's events through plugins, as sc2reader's GameEngine (engine/engine.py).
 * Only hydrates and distributes events from the stream a plugin subscribed to.
 */
class Engine {
	/**
	 * Tie order within a gameloop.
	 */
	private const array STREAMS = array('tracker', 'message', 'game');

	/**
	 * The plugins simulate() runs unless the caller passes its own.
	 *
	 * @return list<Plugin>
	 */
	public static function defaultPlugins(): array {
		return array(new ApmTracker(), new SpmTracker(), new AbilityTracker(), new BaseTracker());
	}

	/**
	 * @param list<Plugin> $plugins in registration order, after the core plugins
	 * @throws LogicException if a plugin is registered twice or before a plugin it depends on
	 */
	public function run(Replay $replay, ReplayFile $file, array $plugins): Simulation {
		$builder = EventBuilder::for($replay);

		return $this->simulate($replay, $plugins, static function (Closure $wanted) use ($file, $builder): Generator {
			foreach (self::merge($file) as list($stream, $raw)) {
				/** @var string $name */
				$name = $raw['_event'];
				if ($wanted(EventBuilder::classOf($stream, $name))) {
					yield $builder->{$stream}($raw);
				}
			}
		});
	}

	/**
	 * Runs events that are already built, in their order (testing\EventScript: simulations without a replay file).
	 * @param iterable<Event> $events in gameloop order
	 * @param list<Plugin> $plugins as for run()
	 * @throws LogicException as run()
	 */
	public function runEvents(Replay $replay, iterable $events, array $plugins): Simulation {
		return $this->simulate($replay, $plugins, static function (Closure $wanted) use ($events): Generator {
			foreach ($events as $event) {
				if ($wanted($event::class)) {
					yield $event;
				}
			}
		});
	}

	/**
	 * The handlers for an event class, in registration order.
	 * @param list<array{class-string<Event>, Closure}> $subscriptions
	 * @return list<Closure>
	 */
	private static function handlers(string $class, array $subscriptions): array {
		$ret = array();
		foreach ($subscriptions as list($subscribed, $handler)) {
			if (is_a($class, $subscribed, true)) {
				$ret[] = $handler;
			}
		}

		return $ret;
	}

	/**
	 * The raw events of every stream the replay has, merged by gameloop.
	 * @return Generator<int, array{'game'|'message'|'tracker', array<string, mixed>}>
	 */
	private static function merge(ReplayFile $file): Generator {
		if ($file->header->legacyFormat) {
			return;
		}
		$streams = array();
		if ($file->protocol->trackerEvents !== null && $file->has('replay.tracker.events')) {
			$streams['tracker'] = $file->trackerEvents();
		}
		if ($file->has('replay.message.events')) {
			$streams['message'] = $file->messageEvents();
		}
		if ($file->has('replay.game.events')) {
			$streams['game'] = $file->gameEvents();
		}
		/** @var array<'game'|'message'|'tracker', array<string, mixed>> $current */
		$current = array();
		foreach ($streams as $stream => $events) {
			if ($events->valid()) {
				$current[$stream] = $events->current();
			}
		}

		while ($current !== array()) {
			$next = null;
			$at = null;
			foreach (self::STREAMS as $stream) {
				if (isset($current[$stream]) && ($at === null || EventBuilder::gameloop($current[$stream]) < $at)) {
					$next = $stream;
					$at = EventBuilder::gameloop($current[$stream]);
				}
			}
			if ($next === null) {
				return;
			}

			yield array($next, $current[$next]);
			$streams[$next]->next();
			if ($streams[$next]->valid()) {
				$current[$next] = $streams[$next]->current();
			} else {
				unset($current[$next]);
			}
		}
	}

	/**
	 * The engine itself: registers the plugins, dispatches the source's events and what the handlers produce, and
	 * collects the results. The source gets a filter (event class => whether anyone subscribed), so it can skip
	 * building events nobody wants.
	 * @param list<Plugin> $plugins
	 * @param Closure(Closure(class-string<Event>): bool): iterable<Event> $source
	 */
	private function simulate(Replay $replay, array $plugins, Closure $source): Simulation {
		$core = new ContextLoader();
		/** @var array<class-string<Plugin>, Plugin> $active */
		$active = array(ContextLoader::class => $core, MorphLinker::class => new MorphLinker());
		$matcher = new HallucinationMatcher();
		if ($matcher->supports($replay)) {
			$active[HallucinationMatcher::class] = $matcher;
		}
		/** @var array<class-string<Plugin>, mixed> $results */
		$results = array();
		foreach ($plugins as $plugin) {
			$class = $plugin::class;
			if (array_key_exists($class, $results) || isset($active[$class])) {
				throw new LogicException("{$class} is registered twice");
			}
			$applies = $plugin->supports($replay);
			foreach ($plugin->dependencies() as $dependency) {
				if (!array_key_exists($dependency, $results) && !isset($active[$dependency])) {
					throw new LogicException("{$class} depends on {$dependency}, which must be registered before it");
				}
				$applies = $applies && isset($active[$dependency]);
			}
			if ($applies) {
				$active[$class] = $plugin;
			} else {
				$results[$class] = null;
			}
		}

		$context = new Context($replay, $core->result(), $active);
		/** @var list<array{class-string<Event>, Closure}> $subscriptions */
		$subscriptions = array();
		foreach ($active as $plugin) {
			$plugin->init($context);
			foreach ($plugin->subscriptions() as $class => $handler) {
				$subscriptions[] = array($class, $handler);
			}
		}

		$handlers = array();
		$wanted = static function (string $class) use (&$handlers, $subscriptions): bool {
			$handlers[$class] ??= self::handlers($class, $subscriptions);

			return $handlers[$class] !== array();
		};
		foreach ($source($wanted) as $first) {
			$queue = array($first);
			while ($queue !== array()) {
				$event = array_shift($queue);
				$handlers[$event::class] ??= self::handlers($event::class, $subscriptions);
				$produced = array();
				foreach ($handlers[$event::class] as $handler) {
					$ret = $handler($event, $context);
					if (is_iterable($ret)) {
						foreach ($ret as $new) {
							$produced[] = $new;
						}
					}
				}
				$queue = array(...$produced, ...$queue);
			}
		}

		foreach ($active as $class => $plugin) {
			$plugin->finish($context);
			$results[$class] = $plugin->result();
		}
		unset($results[ContextLoader::class], $results[MorphLinker::class], $results[HallucinationMatcher::class]);

		return new Simulation($replay, $core->result(), $results);
	}
}
