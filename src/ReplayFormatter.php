<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils;

use Closure;
use DateTimeZone;
use holonet\sc2utils\model\enum\Race;
use holonet\sc2utils\model\replay\Player;
use holonet\sc2utils\model\replay\Replay;
use holonet\sc2utils\model\replay\Team;
use InvalidArgumentException;

/**
 * Formats a template with tokens for a replay, e.g. "{date} {vs} - {map}" (file names, titles).
 *
 * Replay tokens:
 *     {date}        start time (UTC) in the date format
 *     {map}         map name, as the replay has it (localised)
 *     {region}      region, e.g. "eu"
 *     {version}     release string, e.g. "4.4.0.65895"
 *     {basebuild}   base build, e.g. "65895"
 *     {expansion}   WoL, HotS, LotV
 *     {speed}       game speed, e.g. "Faster"
 *     {gamelength}  real length, "m:ss" or "h:mm:ss"
 *     {gametype}    team sizes as played, e.g. "1v1", "2v2", "FFA" (Replay::$realType)
 *     {matchup}     races per team, e.g. "PvZ", "PTvZZ"
 *     {vs}          the teams with their players in the player format, joined with " vs "
 *     {mode}        game mode, e.g. "Ladder"
 *     {identifier}  match key
 *
 * Player tokens (in the player format, for {vs}):
 *     {name}        name
 *     {clantag}     clan tag in brackets, e.g. "[TAG]", empty without one
 *     {race} {race_short}  played race, "Zerg" / "Z"
 *     {pick} {pick_short}  lobby pick, "Random" / "R"
 *     {team}        team number
 *     {toon}        Battle.net account, e.g. "2-S2-1-2513209" (empty for computers)
 */
final readonly class ReplayFormatter {
	public const string DEFAULT_DATE_FORMAT = 'Y-m-d H:i';

	public const string DEFAULT_PLAYER_FORMAT = '{clantag}{name} ({race_short})';

	private const array PLAYER_TOKENS = array('name', 'clantag', 'race', 'race_short', 'pick', 'pick_short', 'team', 'toon');

	private const array REPLAY_TOKENS = array(
		'date', 'map', 'region', 'version', 'basebuild', 'expansion', 'speed', 'gamelength', 'gametype', 'matchup', 'vs',
		'mode', 'identifier',
	);

	private const string TOKEN = '/\{(\w+)\}/';

	/**
	 * @param string $dateFormat a date() format for {date}
	 * @param string $playerFormat the format of one player in {vs}, with player tokens
	 * @param Closure(string): string|null $valueFilter applied to every value that goes into the result
	 * @param Closure(string): string|null $resultFilter applied to the formatted result
	 */
	public function __construct(
		public string $dateFormat = self::DEFAULT_DATE_FORMAT,
		public string $playerFormat = self::DEFAULT_PLAYER_FORMAT,
		private ?Closure $valueFilter = null,
		private ?Closure $resultFilter = null,
	) {
		self::check($playerFormat, self::PLAYER_TOKENS, 'player format');
	}

	/**
	 * A formatter for file and folder names: the template may contain "/" for subfolders, but no value may. Path
	 * separators and the characters Windows forbids in names become "_", and so do leading and trailing dots and
	 * spaces, which some systems drop. A folder level that comes out empty becomes "_" instead of vanishing.
	 */
	public static function forFileNames(string $dateFormat = 'Y-m-d H-i', string $playerFormat = self::DEFAULT_PLAYER_FORMAT): self {
		return new self($dateFormat, $playerFormat, static function (string $value): string {
			$value = (string)preg_replace('#[/\\\\:*?"<>|\x00-\x1F]#u', '_', $value);

			return (string)preg_replace('/^[. ]+|[. ]+$/', '_', $value);
		}, static fn (string $path): string => implode('/', array_map(
			static fn (string $segment): string => trim($segment) === '' ? '_' : $segment,
			explode('/', $path),
		)));
	}

	public function format(string $template, Replay $replay): string {
		self::validate($template);

		$ret = (string)preg_replace_callback(self::TOKEN, function (array $match) use ($replay): string {
			$value = $this->replayToken($match[1], $replay);

			return $match[1] === 'vs' ? $value : $this->filter($value);
		}, $template);

		return $this->resultFilter === null ? $ret : ($this->resultFilter)($ret);
	}

	/**
	 * @throws InvalidArgumentException if the template has a token this class doesn't know
	 */
	public static function validate(string $template): void {
		self::check($template, self::REPLAY_TOKENS, 'template');
	}

	/**
	 * @param list<string> $known
	 */
	private static function check(string $format, array $known, string $what): void {
		preg_match_all(self::TOKEN, $format, $matches);
		$unknown = array_diff($matches[1], $known);
		if ($unknown !== array()) {
			throw new InvalidArgumentException(sprintf('Unknown token(s) in the %s: {%s}; known are {%s}', $what, implode('}, {', $unknown), implode('}, {', $known)));
		}
	}

	private function filter(string $value): string {
		return $this->valueFilter === null ? $value : ($this->valueFilter)($value);
	}

	private static function length(int $seconds): string {
		return $seconds >= 3_600
			? sprintf('%d:%02d:%02d', intdiv($seconds, 3_600), intdiv($seconds % 3_600, 60), $seconds % 60)
			: sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
	}

	/**
	 * Race letters per team, sorted, e.g. "PvZ".
	 *
	 * @param list<list<Race|null>> $teams
	 */
	private static function matchup(array $teams): string {
		$lineups = array_map(static function (array $races): string {
			$letters = array_map(static fn (?Race $race): string => $race?->letter() ?? '?', $races);
			sort($letters);

			return implode('', $letters);
		}, $teams);
		sort($lineups);

		return implode('v', $lineups);
	}

	private function player(Player $player): string {
		return (string)preg_replace_callback(self::TOKEN, fn (array $match): string => $this->filter(match ($match[1]) {
			'name' => $player->name,
			'clantag' => $player->clanTag === null || $player->clanTag === '' ? '' : "[{$player->clanTag}]",
			'race' => $player->playedRace?->value ?? '',
			'race_short' => $player->playedRace?->letter() ?? '',
			'pick' => $player->pickedRace?->value ?? '',
			'pick_short' => $player->pickedRace?->letter() ?? '',
			'team' => (string)$player->team,
			'toon' => (string)$player->toon,
			default => '',
		}), $this->playerFormat);
	}

	private function replayToken(string $token, Replay $replay): string {
		return match ($token) {
			'date' => $replay->startTime?->setTimezone(new DateTimeZone('UTC'))->format($this->dateFormat) ?? '',
			'map' => $replay->map->name,
			'region' => $replay->region,
			'version' => (string)$replay->header->version,
			'basebuild' => (string)$replay->header->version->baseBuild,
			'expansion' => $replay->expansion?->value ?? '',
			'speed' => $replay->speed?->value ?? '',
			'gamelength' => self::length($replay->realLength),
			'gametype' => $replay->realType,
			'matchup' => self::matchup(array_map(
				static fn (array $players): array => array_map(static fn (Player $p): ?Race => $p->playedRace, $players),
				self::unitsPerTeam($replay),
			)),
			'vs' => implode(' vs ', array_map(
				fn (Team $team): string => implode(' ', array_map($this->player(...), $team->players)),
				$replay->teams,
			)),
			'mode' => $replay->gameMode?->value ?? '',
			'identifier' => $replay->matchKey,
			default => throw new InvalidArgumentException("Unknown token {{$token}}"),
		};
	}

	/**
	 * Teams with one player per controlling unit (archon partners share a player id), for the matchup.
	 *
	 * @return list<list<Player>>
	 */
	private static function unitsPerTeam(Replay $replay): array {
		return array_map(static function (Team $team): array {
			$units = array();
			foreach ($team->players as $player) {
				$units[$player->pid] ??= $player;
			}

			return array_values($units);
		}, $replay->teams);
	}
}
