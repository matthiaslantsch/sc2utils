<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\simulate\plugin;

use holonet\sc2utils\model\event\game\CommandEvent;
use holonet\sc2utils\model\event\game\ControlGroupEvent;
use holonet\sc2utils\model\event\game\PlayerLeaveEvent;
use holonet\sc2utils\model\event\game\SelectionEvent;
use holonet\sc2utils\model\replay\Replay;
use holonet\sc2utils\model\simulation\PerUser;
use holonet\sc2utils\model\simulation\plugin\ActionRate;
use holonet\sc2utils\simulate\Context;
use holonet\sc2utils\simulate\Plugin;

/**
 * Actions per minute: every command (retargets included),
 * selection and control group event counts 1, in real time, for every user.
 *
 * @implements Plugin<PerUser<ActionRate>>
 */
final class ApmTracker implements Plugin {
	private ActionCounter $counter;

	private ?Replay $replay = null;

	public function __construct() {
		$this->counter = new ActionCounter();
	}

	public function dependencies(): array {
		return array();
	}

	public function finish(Context $context): void {}

	public function init(Context $context): void {
		$this->replay = $context->replay;
	}

	public function result(): PerUser {
		return $this->replay === null ? new PerUser() : $this->counter->result($this->replay);
	}

	public function subscriptions(): array {
		return array(
			CommandEvent::class => $this->action(...),
			SelectionEvent::class => $this->action(...),
			ControlGroupEvent::class => $this->action(...),
			PlayerLeaveEvent::class => $this->left(...),
		);
	}

	public function supports(Replay $replay): bool {
		return true;
	}

	private function action(CommandEvent|ControlGroupEvent|SelectionEvent $event): void {
		if ($event->user !== null) {
			$this->counter->count($event->user, $event->gameloop);
		}
	}

	private function left(PlayerLeaveEvent $event): void {
		if ($event->user !== null) {
			$this->counter->left($event->user, $event->gameloop);
		}
	}
}
