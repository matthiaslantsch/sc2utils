<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\protocol;

use Generator;

/**
 * The event loop of the three event files, as s2protocol's `_decode_event_stream()` (5.0.17).
 *
 * Each event is: gameloop delta (svaruint32), user id (game and message events only), event id, the event struct,
 * then padding to the next byte. Events come out as s2protocol has them, the struct's fields plus `_event` (name),
 * `_eventid`, `_gameloop` (absolute), `_userid` (game and message events) and `_bits` (size of the event).
 */
final class EventStream {
	/**
	 * @param BitPackedCodec|string $data the file content, or a codec over it
	 *
	 * @return Generator<int, array<string, mixed>>
	 */
	public static function game(Protocol $protocol, BitPackedCodec|string $data): Generator {
		$codec = is_string($data) ? new BitPackedCodec($protocol, $data) : $data;

		return self::decode($protocol, $codec, $protocol->gameEventId, $protocol->gameEvents, true);
	}

	/**
	 * @param BitPackedCodec|string $data the file content, or a codec over it
	 *
	 * @return Generator<int, array<string, mixed>>
	 */
	public static function message(Protocol $protocol, BitPackedCodec|string $data): Generator {
		$codec = is_string($data) ? new BitPackedCodec($protocol, $data) : $data;

		return self::decode($protocol, $codec, $protocol->messageEventId, $protocol->messageEvents, true);
	}

	/**
	 * @param string|VersionedCodec $data the file content, or a codec over it (to check its skipped() afterwards)
	 *
	 * @return Generator<int, array<string, mixed>>
	 */
	public static function tracker(Protocol $protocol, string|VersionedCodec $data): Generator {
		if ($protocol->trackerEventId === null || $protocol->trackerEvents === null) {
			throw new DecodeException("Protocol {$protocol->build} has no tracker events");
		}
		$codec = is_string($data) ? new VersionedCodec($protocol, $data) : $data;

		return self::decode($protocol, $codec, $protocol->trackerEventId, $protocol->trackerEvents, false);
	}

	/**
	 * @param array<int, array{int, string}> $events event id => [type id, name]
	 *
	 * @return Generator<int, array<string, mixed>>
	 */
	private static function decode(Protocol $protocol, TypeDecoder $decoder, int $eventIdType, array $events, bool $withUser): Generator {
		$gameloop = 0;
		while (!$decoder->atEnd()) {
			$start = $decoder->usedBits();

			/** @var array<string, int> $delta */
			$delta = $decoder->instance($protocol->svaruint32);
			$gameloop += reset($delta) ?: 0;
			$userId = $withUser ? $decoder->instance($protocol->userId) : null;

			$eventId = $decoder->instance($eventIdType);
			if (!is_int($eventId) || !isset($events[$eventId])) {
				throw new DecodeException(sprintf('Unknown event id %s at bit %d', var_export($eventId, true), $decoder->usedBits()));
			}
			list($type, $name) = $events[$eventId];

			/** @var array<string, mixed> $event */
			$event = $decoder->instance($type);
			$event['_event'] = $name;
			$event['_eventid'] = $eventId;
			$event['_gameloop'] = $gameloop;
			if ($withUser) {
				$event['_userid'] = $userId;
			}
			$decoder->align();
			$event['_bits'] = $decoder->usedBits() - $start;

			yield $event;
		}
	}
}
