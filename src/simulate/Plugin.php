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
use holonet\sc2utils\model\event\Event;
use holonet\sc2utils\model\replay\Replay;

/**
 * A plugin of the engine: it subscribes to event classes, keeps its own state and returns a result.
 *
 * A handler gets the event and the context and may yield events of its own: they are processed right
 * after the event that produced them, before the next replay event. Any exception stops the simulation.
 *
 * @template R the result
 */
interface Plugin {
	/**
	 * Plugins this one needs (through Context::plugin()); they must be registered before it. A plugin whose
	 * dependency doesn't apply to a replay doesn't apply either.
	 * @return list<class-string<Plugin>>
	 */
	public function dependencies(): array;

	/**
	 * After every event: the plugin builds its result.
	 */
	public function finish(Context $context): void;

	/**
	 * Before the first event.
	 */
	public function init(Context $context): void;

	/**
	 * @return R
	 */
	public function result(): mixed;

	/**
	 * Event class => handler. A subscription to a class or interface gets its subclasses too (CommandEvent includes
	 * RetargetEvent, GameEvent every game event). The engine builds only events someone subscribed to.
	 * @return array<class-string<Event>, Closure(Event, Context): (iterable<Event>|void|null)>
	 */
	public function subscriptions(): array;

	/**
	 * Whether the plugin applies to the replay at all (e.g. not without tracker events); if not, it isn't run and
	 * its result is null.
	 */
	public function supports(Replay $replay): bool;
}
