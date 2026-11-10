<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\model\replay;

use DateInterval;
use DateTimeImmutable;
use holonet\sc2utils\model\enum\Expansion;
use holonet\sc2utils\model\enum\GameMode;
use holonet\sc2utils\model\enum\GameSpeed;
use holonet\sc2utils\model\MapRef;
use holonet\sc2utils\model\ReplayHeader;

/**
 * The full domain model of a replay from header, details, initdata, replay.info and attributes (where each applicable).
 */
final class Replay {
	/**
	 * Version prefix of match keys, so keys of another derivation can be told apart.
	 */
	public const string MATCH_KEY_PREFIX = 'm2:';

	/**
	 * Identifies the match: every replay of the same game has the same key, whoever recorded it and however long
	 * they stayed. Built from the region, the shared random seed and the human players' toons.
	 */
	public string $matchKey {
		get {
			$toons = array();
			foreach ($this->players as $player) {
				if ($player->toon !== null) {
					$toons[] = (string)$player->toon;
				}
			}
			sort($toons);

			$hash = hash('sha256', implode("\n", array($this->region, $this->seed, ...$toons)));

			return implode('', array(self::MATCH_KEY_PREFIX, $hash));
		}
	}

	/**
	 * End time minus the real length, as sc2reader computes it.
	 */
	public ?DateTimeImmutable $startTime {
		get => $this->endTime?->sub(new DateInterval("PT{$this->realLength}S"));
	}

	/**
	 * @param Expansion|null $expansion null if the replay depends on no known standard data
	 * @param GameSpeed|null $speed null if the replay has no game speed
	 * @param string $region the region of the replay's first cache handle, lowercase, sg as sea (sc2reader's region)
	 * @param DateTimeImmutable|null $endTime UTC
	 * @param float|null $utcOffset the recording client's offset from UTC in hours
	 * @param int $realLength length in real seconds, sc2reader's real_length
	 * @param GameMode|null $gameMode null if the replay has no game mode attribute
	 * @param string|null $teamSize the lobby's "Teams" attribute, e.g. "1v1"; null if absent
	 * @param string $realType team sizes as played, e.g. "2v2", "1v2" or "FFA" (sc2reader's real_type)
	 * @param bool $isLadder a ladder game without observers or computers
	 * @param bool $isArchon some slot plays an archon partner's units (computer pairs included)
	 * @param int $seed the game's random value, shared by every client of the game
	 * @param list<Player> $players
	 * @param list<Observer> $observers
	 * @param list<Team> $teams ordered by number
	 */
	public function __construct(
		public readonly ReplayHeader $header,
		public readonly ?Expansion $expansion,
		public readonly ?GameSpeed $speed,
		public readonly string $region,
		public readonly MapRef $map,
		public readonly ?DateTimeImmutable $endTime,
		public readonly ?float $utcOffset,
		public readonly int $realLength,
		public readonly ?GameMode $gameMode,
		public readonly ?string $teamSize,
		public readonly string $realType,
		public readonly bool $isLadder,
		public readonly bool $isArchon,
		public readonly bool $amm,
		public readonly bool $ranked,
		public readonly bool $competitive,
		public readonly bool $practice,
		public readonly bool $cooperative,
		public readonly bool $battleNet,
		public readonly int $seed,
		public readonly array $players,
		public readonly array $observers,
		public readonly array $teams,
	) {}
}
