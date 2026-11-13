<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\simulate\plugin;

use holonet\sc2utils\model\event\game\CameraEvent;
use holonet\sc2utils\model\event\game\PlayerLeaveEvent;
use holonet\sc2utils\model\Point;
use holonet\sc2utils\model\replay\Replay;
use holonet\sc2utils\model\simulation\PerUser;
use holonet\sc2utils\model\simulation\plugin\ActionRate;
use holonet\sc2utils\simulate\Context;
use holonet\sc2utils\simulate\Plugin;

/**
 * Screens per minute: a screen counts when a user's camera is at least 15 map cells from where the last counted
 * screen was (the anchor), so a jump counts at once and scrolling counts once per 15 cells. The first camera event
 * only sets the anchor (the initial placement); events without a target are skipped. Real time, every user.
 *
 * @implements Plugin<PerUser<ActionRate>>
 */
final class SpmTracker implements Plugin {
	/**
	 * Map cells the camera has to move from the anchor to count as a new screen.
	 */
	public const float DISTANCE = 15.0;

	/**
	 * @var array<int, Point> user id => anchor
	 */
	private array $anchors = array();

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
			CameraEvent::class => $this->camera(...),
			PlayerLeaveEvent::class => $this->left(...),
		);
	}

	public function supports(Replay $replay): bool {
		return true;
	}

	private function camera(CameraEvent $event): void {
		if ($event->user === null || $event->target === null) {
			return;
		}
		$anchor = $this->anchors[$event->user->userId] ?? null;
		if ($anchor === null || hypot($event->target->x - $anchor->x, $event->target->y - $anchor->y) >= self::DISTANCE) {
			if ($anchor !== null) {
				$this->counter->count($event->user, $event->gameloop);
			}
			$this->anchors[$event->user->userId] = $event->target;
		}
	}

	private function left(PlayerLeaveEvent $event): void {
		if ($event->user !== null) {
			$this->counter->left($event->user, $event->gameloop);
		}
	}
}
