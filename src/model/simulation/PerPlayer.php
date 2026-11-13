<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\model\simulation;

use ArrayIterator;
use Countable;
use holonet\sc2utils\model\replay\Player;
use IteratorAggregate;
use Traversable;

/**
 * A result per player (Blizzard's player id: units, stats, upgrades, bases); archon partners share one entry.
 * Iterates as player id => [Player, value], in the replay's player order.
 *
 * @template T
 *
 * @implements IteratorAggregate<int, array{Player, T}>
 */
class PerPlayer implements Countable, IteratorAggregate {
	/**
	 * @param array<int, array{Player, T}> $entries by player id
	 */
	public function __construct(
		private array $entries = array(),
	) {}

	public function count(): int {
		return count($this->entries);
	}

	/**
	 * The value of a player; null if it has none.
	 *
	 * @return T|null
	 */
	public function for(Player $player): mixed {
		return $this->entries[$player->pid][1] ?? null;
	}

	/**
	 * @return Traversable<int, array{Player, T}>
	 */
	public function getIterator(): Traversable {
		return new ArrayIterator($this->entries);
	}
}
