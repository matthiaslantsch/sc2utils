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
use holonet\sc2utils\model\replay\User;
use IteratorAggregate;
use Traversable;

/**
 * A result per user (Blizzard's user id: APM, SPM, abilities); both archon partners have their own entry, observers
 * too. Iterates as user id => [User, value], in the replay's user order.
 *
 * @template T
 *
 * @implements IteratorAggregate<int, array{User, T}>
 */
class PerUser implements Countable, IteratorAggregate {
	/**
	 * @param array<int, array{User, T}> $entries by user id
	 */
	public function __construct(
		private array $entries = array(),
	) {}

	public function count(): int {
		return count($this->entries);
	}

	/**
	 * The value of a user; null if it has none.
	 *
	 * @return T|null
	 */
	public function for(User $user): mixed {
		return $this->entries[$user->userId][1] ?? null;
	}

	/**
	 * @return Traversable<int, array{User, T}>
	 */
	public function getIterator(): Traversable {
		return new ArrayIterator($this->entries);
	}
}
