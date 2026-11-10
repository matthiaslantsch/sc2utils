<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\tests;

use DateTimeImmutable;
use holonet\sc2utils\model\Colour;
use holonet\sc2utils\model\enum\Expansion;
use holonet\sc2utils\model\enum\GameMode;
use holonet\sc2utils\model\enum\GameSpeed;
use holonet\sc2utils\model\enum\Race;
use holonet\sc2utils\model\enum\Result;
use holonet\sc2utils\model\GameVersion;
use holonet\sc2utils\model\MapRef;
use holonet\sc2utils\model\replay\Player;
use holonet\sc2utils\model\replay\Replay;
use holonet\sc2utils\model\replay\Team;
use holonet\sc2utils\model\ReplayHeader;
use holonet\sc2utils\model\Toon;
use holonet\sc2utils\ReplayFormatter;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ReplayFormatter::class)]
#[Small]
class ReplayFormatterTest extends TestCase {
	/**
	 * @return iterable<string, array{string, string}>
	 */
	public static function provide_replay_token_cases(): iterable {
		yield 'date' => array('{date}', '2018-07-10 11:58');
		yield 'map' => array('{map}', 'Void Thrashing LE');
		yield 'region' => array('{region}', 'eu');
		yield 'version' => array('{version}', '4.4.0.65895');
		yield 'basebuild' => array('{basebuild}', '65895');
		yield 'expansion' => array('{expansion}', 'LotV');
		yield 'speed' => array('{speed}', 'Faster');
		yield 'gamelength' => array('{gamelength}', '12:05');
		yield 'gametype' => array('{gametype}', '1v1');
		yield 'matchup' => array('{matchup}', 'PvZ');
		yield 'vs' => array('{vs}', '[Team]Peter (Z) vs Sally (P)');
		yield 'mode' => array('{mode}', 'Ladder');
		yield 'identifier' => array('{identifier}', 'm2:');
		yield 'text around tokens' => array('{date} {vs} - {map}', '2018-07-10 11:58 [Team]Peter (Z) vs Sally (P) - Void Thrashing LE');
	}

	public function test_archon_pair_counts_once_in_the_matchup(): void {
		$replay = self::replay(array(
			self::player(1, 'A', 1, Race::Protoss),
			self::player(1, 'B', 1, Race::Protoss),
			self::player(2, 'C', 2, Race::Zerg),
			self::player(2, 'D', 2, Race::Zerg),
		));

		$this->assertSame('PvZ', new ReplayFormatter()->format('{matchup}', $replay));
		$this->assertSame('A (P) B (P) vs C (Z) D (Z)', new ReplayFormatter()->format('{vs}', $replay));
	}

	public function test_beta_defaults(): void {
		$beta = self::replay(array(
			self::player(1, 'OgerAffe', 1, Race::Terran, toon: false),
			self::player(2, 'LiquidNazgul', 2, Race::Protoss, toon: false),
		), map: 'Metalopolis', realLength: 2_147, endTime: null);

		$this->assertSame('[] OgerAffe (T) vs LiquidNazgul (P) 1v1 PvT 35:47', new ReplayFormatter()->format('[{date}] {vs} {gametype} {matchup} {gamelength}', $beta));
		$this->assertSame('OgerAffe| vs LiquidNazgul|', new ReplayFormatter(playerFormat: '{name}|{toon}')->format('{vs}', $beta));
	}

	public function test_file_names_keep_values_out_of_the_path(): void {
		$replay = self::replay(array(
			self::player(1, 'a/b', 1, Race::Zerg, clanTag: 'C:D'),
			self::player(2, '..', 2, Race::Terran),
		), map: 'What? <Map>');
		$formatter = ReplayFormatter::forFileNames();

		$this->assertSame('What_ _Map_/[C_D]a_b (Z) vs _ (T)', $formatter->format('{map}/{vs}', $replay));
		$this->assertSame('2018-07-10 11-58', $formatter->format('{date}', $replay));

		$beta = self::replay(map: 'Metalopolis', endTime: null);
		$this->assertSame('_/Metalopolis', $formatter->format('{date}/{map}', $beta), 'an empty folder level does not vanish');
	}

	public function test_gamelength_with_hours(): void {
		$this->assertSame('1:01:01', new ReplayFormatter()->format('{gamelength}', self::replay(realLength: 3_661)));
	}

	public function test_player_tokens(): void {
		$formatter = new ReplayFormatter(playerFormat: '{name}|{clantag}|{race}|{race_short}|{pick}|{pick_short}|{team}|{toon}');

		$this->assertSame(
			'Peter|[Team]|Zerg|Z|Random|R|1|2-S2-1-2444444 vs Sally||Protoss|P|Protoss|P|2|2-S2-1-2777777',
			$formatter->format('{vs}', self::replay()),
		);
	}

	#[DataProvider('provide_replay_token_cases')]
	public function test_replay_token(string $template, string $expected): void {
		$formatted = new ReplayFormatter()->format($template, self::replay());

		$this->assertStringStartsWith($expected, $formatted);
		if ($template === '{identifier}') {
			$this->assertSame(self::replay()->matchKey, $formatted);
		}
	}

	public function test_unknown_tokens_fail(): void {
		ReplayFormatter::validate('{date}/{vs} literal {braces');

		try {
			ReplayFormatter::validate('{date} {nope}');
			$this->fail('unknown token accepted');
		} catch (InvalidArgumentException $e) {
			$this->assertStringContainsString('{nope}', $e->getMessage());
		}

		$this->expectException(InvalidArgumentException::class);
		new ReplayFormatter(playerFormat: '{name} {map}');
	}

	/**
	 * @param false|Toon|null $toon false for none, null for a generated one
	 */
	private static function player(int $pid, string $name, int $team, Race $race, ?string $clanTag = null, ?Race $pick = null, null|false|Toon $toon = null): Player {
		return new Player($pid, $pid, $pid - 1, $name, $clanTag, $toon === false ? null : $toon ?? new Toon(2, 1, 1_000 + $pid), $team, $pick ?? $race, $race, new Colour(180, 20, 30), Result::Win, 100, null);
	}

	/**
	 * @param list<Player>|null $players
	 */
	private static function replay(?array $players = null, string $map = 'Void Thrashing LE', int $realLength = 725, ?DateTimeImmutable $endTime = new DateTimeImmutable('2018-07-10 12:10:05 UTC')): Replay {
		$players ??= array(
			self::player(1, 'Peter', 1, Race::Zerg, 'Team', Race::Random, Toon::fromHandle('2-S2-1-2444444')),
			self::player(2, 'Sally', 2, Race::Protoss, '', toon: Toon::fromHandle('2-S2-1-2777777')),
		);
		$teams = array();
		foreach ($players as $player) {
			$teams[$player->team][] = $player;
		}

		return new Replay(
			new ReplayHeader(new GameVersion(4, 4, 0, 65_895, 65_895), 16_338),
			Expansion::LotV,
			GameSpeed::Faster,
			'eu',
			new MapRef($map, str_repeat('ab', 32), 'eu'),
			$endTime,
			2.0,
			$realLength,
			GameMode::Ladder, '1v1', '1v1', true, false, true, true, false, false, false, true, 42, $players, array(),
			array_values(array_map(static fn (int $number, array $members): Team => new Team($number, $members, Result::Win), array_keys($teams), $teams)),
		);
	}
}
