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
				foreach ($player->users as $user) {
					if ($user->toon !== null) {
						$toons[] = (string)$user->toon;
					}
				}
			}
			sort($toons);

			$hash = hash('sha256', implode("\n", array($this->region, $this->seed, ...$toons)));

			return implode('', array(self::MATCH_KEY_PREFIX, $hash));
		}
	}

	/**
	 * The users that control no player: observers and referees, in slot order.
	 * @var list<User>
	 */
	public array $observers {
		get {
			$controlling = array();
			foreach ($this->players as $player) {
				foreach ($player->users as $user) {
					$controlling[$user->userId] = true;
				}
			}

			return array_values(array_filter($this->users, static fn (User $user): bool => !isset($controlling[$user->userId])));
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
	 * @param int $realLength length in real seconds, floor(realSeconds(gameloops))
	 * @param GameMode|null $gameMode null if the replay has no game mode attribute
	 * @param string|null $teamSize the lobby's "Teams" attribute, e.g. "1v1"; null if absent
	 * @param string $realType team sizes as played, e.g. "2v2", "1v2" or "FFA" (sc2reader's real_type)
	 * @param bool $isLadder a ladder game without observers or computers
	 * @param bool $isArchon some slot plays an archon partner's units
	 * @param int $seed the game's random value, shared by every client of the game
	 * @param list<Player> $players ordered by player id
	 * @param list<User> $users every human in a slot, observers and referees included, in slot order
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
		public readonly array $users,
		public readonly array $teams,
	) {}

	/**
	 * The player a user controls; null for observers and referees.
	 */
	public function playerOf(User $user): ?Player {
		foreach ($this->players as $player) {
			foreach ($player->users as $controller) {
				if ($controller->userId === $user->userId) {
					return $player;
				}
			}
		}

		return null;
	}

	/**
	 * Real seconds since the game started at a gameloop: 16 gameloops per game second, divided by the speed's
	 * multiplier (1.4 at Faster). Without a known speed, Normal.
	 */
	public function realSeconds(int $gameloop): float {
		return ($this->speed ?? GameSpeed::Normal)->seconds($gameloop);
	}
}
