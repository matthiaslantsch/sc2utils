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
use holonet\sc2utils\model\event\game\RetargetEvent;
use holonet\sc2utils\model\replay\Replay;
use holonet\sc2utils\model\simulation\PerUser;
use holonet\sc2utils\model\simulation\plugin\AbilityUse;
use holonet\sc2utils\simulate\Context;
use holonet\sc2utils\simulate\Plugin;

/**
 * Which user used which ability how often, and when first: per user, AbilityUse::key() => AbilityUse, keyed by
 * Blizzard's ability id and command index.
 *
 * @implements Plugin<PerUser<array<string, AbilityUse>>>
 */
final class AbilityTracker implements Plugin {
	private ?Replay $replay = null;

	/**
	 * @var array<int, array<string, AbilityUse>> user id => key => AbilityUse
	 */
	private array $uses = array();

	public function dependencies(): array {
		return array();
	}

	public function finish(Context $context): void {}

	public function init(Context $context): void {
		$this->replay = $context->replay;
	}

	public function result(): PerUser {
		$entries = array();
		foreach ($this->replay?->users ?? array() as $user) {
			$uses = $this->uses[$user->userId] ?? array();
			ksort($uses);
			$entries[$user->userId] = array($user, $uses);
		}

		ksort($entries);

		return new PerUser($entries);
	}

	public function subscriptions(): array {
		return array(CommandEvent::class => $this->command(...));
	}

	public function supports(Replay $replay): bool {
		return true;
	}

	private function command(CommandEvent $event): void {
		if ($event instanceof RetargetEvent || $event->abilityLink === null || $event->user === null) {
			return;
		}
		$key = AbilityUse::key($event->ability, $event->abilityLink, $event->commandIndex);
		$this->uses[$event->user->userId][$key] ??= new AbilityUse($event->ability, $event->abilityLink, $event->commandIndex, 0, $event->gameloop);
		$this->uses[$event->user->userId][$key]->count++;
	}
}
