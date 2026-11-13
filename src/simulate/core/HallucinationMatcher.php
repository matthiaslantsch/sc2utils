<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\simulate\core;

use holonet\sc2utils\model\event\game\CommandEvent;
use holonet\sc2utils\model\event\game\RetargetEvent;
use holonet\sc2utils\model\event\tracker\UnitBornEvent;
use holonet\sc2utils\model\replay\Replay;
use holonet\sc2utils\model\simulation\core\Unit;
use holonet\sc2utils\simulate\Context;
use holonet\sc2utils\simulate\Plugin;

/**
 * A core plugin for replays that don't record which ability created a unit (before protocol 54724, patch 3.15):
 * marks a unit as a hallucination when it is born of a type its owner just hallucinated, i.e. within 32 gameloops
 * after the owner's Hallucination<Type> command (in either event order within the gameloop). Units it doesn't match
 * stay unknown (null), since a miss doesn't prove a unit real.
 *
 * @implements Plugin<null>
 */
final class HallucinationMatcher implements Plugin {
	/**
	 * How long after the command a birth still matches.
	 */
	private const int WINDOW = 32;

	/**
	 * Units born in the current gameloop, for a command that comes after them in the same gameloop.
	 * @var list<Unit>
	 */
	private array $born = array();

	/**
	 * Recent Hallucination commands: [gameloop, player id, unit type].
	 * @var list<array{int, int, string}>
	 */
	private array $commands = array();

	private int $gameloop = -1;

	public function __construct(
		private readonly bool $always = false,
	) {}

	public function dependencies(): array {
		return array();
	}

	public function finish(Context $context): void {}

	public function init(Context $context): void {}

	public function result(): mixed {
		return null;
	}

	public function subscriptions(): array {
		return array(
			CommandEvent::class => $this->command(...),
			UnitBornEvent::class => $this->birth(...),
		);
	}

	public function supports(Replay $replay): bool {
		return $this->always || $replay->header->version->baseBuild < 54_724;
	}

	/**
	 * Moves to a gameloop: forgets the births of earlier gameloops and commands older than the window.
	 */
	private function at(int $gameloop): void {
		if ($gameloop !== $this->gameloop) {
			$this->gameloop = $gameloop;
			$this->born = array();
			$this->commands = array_values(array_filter($this->commands, static fn (array $command): bool => $gameloop - $command[0] <= self::WINDOW));
		}
	}

	private function birth(UnitBornEvent $event, Context $context): void {
		$unit = $context->units->get($event->unitId);
		if ($unit === null || $unit->owner === null) {
			return;
		}
		$this->at($event->gameloop);
		$this->born[] = $unit;
		foreach ($this->commands as list($gameloop, $pid, $type)) {
			if ($pid === $unit->owner->pid && $type === $event->unitType && $event->gameloop - $gameloop <= self::WINDOW) {
				$unit->markHallucinated();

				return;
			}
		}
	}

	private function command(CommandEvent $event, Context $context): void {
		if ($event instanceof RetargetEvent || $event->ability === null || !str_starts_with($event->ability, 'Hallucination') || $event->user === null) {
			return;
		}
		$player = $context->replay->playerOf($event->user);
		if ($player === null) {
			return;
		}
		$this->at($event->gameloop);
		$type = substr($event->ability, strlen('Hallucination'));
		$this->commands[] = array($event->gameloop, $player->pid, $type);
		foreach ($this->born as $unit) {
			if ($unit->owner?->pid === $player->pid && ($unit->types[$event->gameloop] ?? null) === $type) {
				$unit->markHallucinated();
			}
		}
	}
}
