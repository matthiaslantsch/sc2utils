<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\protocol;

/**
 * The tables of one s2protocol protocol version, as generated into data/ by tools/generate/protocols.sh.
 *
 * @psalm-type Typeinfo = list<mixed>
 * @psalm-type EventMap = array<int, array{int, string}>
 */
final readonly class Protocol {
	/**
	 * @param int $userId the type in front of every game/message event
	 * @param EventMap $gameEvents
	 * @param EventMap $messageEvents
	 * @param EventMap|null $trackerEvents null before tracker events existed
	 * @param list<Typeinfo> $typeinfos
	 */
	public function __construct(
		public int $build,
		public int $svaruint32,
		public int $userId,
		public int $header,
		public int $details,
		public int $initdata,
		public int $gameEventId,
		public int $messageEventId,
		public ?int $trackerEventId,
		public array $gameEvents,
		public array $messageEvents,
		public ?array $trackerEvents,
		public array $typeinfos,
	) {}

	/**
	 * @param array<string, mixed> $data the array a generated data file returns
	 */
	public static function fromArray(array $data): self {
		return new self(...$data);
	}
}
