<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\tests\decode;

use holonet\sc2utils\decode\ReplayMapper;
use holonet\sc2utils\model\enum\Expansion;
use holonet\sc2utils\model\enum\GameMode;
use holonet\sc2utils\model\enum\GameSpeed;
use holonet\sc2utils\model\enum\Race;
use holonet\sc2utils\model\enum\Result;
use holonet\sc2utils\model\GameVersion;
use holonet\sc2utils\model\replay\Player;
use holonet\sc2utils\model\replay\Replay;
use holonet\sc2utils\model\replay\User;
use holonet\sc2utils\model\ReplayHeader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * The rules of the domain mapping with crafted input: lobby slots to players and users, teams, ladder, times.
 * The corpus comparison (ReplaySummaryTest) covers real data; this covers the cases the corpus lacks.
 *
 * @internal
 */
#[CoversClass(ReplayMapper::class)]
#[Small]
class ReplayMapperTest extends TestCase {
	private const string LIBERTY = 'Standard Data: Liberty.SC2Mod';

	private const string VOID = 'Standard Data: Void.SC2Mod';

	/**
	 * @return iterable<string, array{list<int>, string}>
	 */
	public static function provide_real_type_cases(): iterable {
		yield '1v1' => array(array(1, 2), '1v1');
		yield 'outnumbered, sorted ascending' => array(array(1, 1, 2), '1v2');
		yield 'three teams of one' => array(array(1, 2, 3), 'FFA');
		yield 'four teams of two' => array(array(1, 1, 2, 2, 3, 3, 4, 4), '2v2v2v2');
		yield 'one team' => array(array(1, 1), '2');
	}

	public function test_archon_partners_control_one_player(): void {
		// archon 2v2 vs AI as in same_match/archon_vs_ai: humans 0 + 2 are one unit, computers 1 + 3 another.
		// details lists the human partner (in working set order) but not the computer partner.
		$slots = array(
			self::human(0, 0, workingSet: 0, tandem: array(null, 0)),
			self::computer(1, workingSet: 1, tandem: array(null, 3)),
			self::human(1, 0, workingSet: 2, tandem: array(0, 1)),
			self::computer(1, workingSet: 3, tandem: array(3, 4)),
		);
		$replay = $this->replay(
			slots: $slots,
			details: array(self::detail('A', race: 'Protoss', workingSet: 0), self::detail('B', race: 'Protoss', workingSet: 2), self::detail('AI', race: 'Zerg', workingSet: 1)),
			attributes: array(1 => array('Race' => 'Protoss'), 2 => array('Race' => 'Random')),
		);

		$this->assertSame(array(array(1, array(0, 2), 'A & B'), array(2, array(1), 'AI')), array_map(static fn (Player $p): array => array($p->pid, self::slots($p), $p->name), $replay->players));
		$this->assertSame(array('A', 'B'), array_map(static fn (User $u): string => $u->name, $replay->players[0]->users));
		$this->assertSame(Race::Protoss, $replay->players[0]->pickedRace, 'the pair has one attribute scope');
		$this->assertSame(Race::Random, $replay->players[1]->pickedRace);
		$this->assertSame($replay->players[0], $replay->playerOf($replay->users[1]), 'the partner controls the leader\'s player');
		$this->assertSame(array(), $replay->observers);
		$this->assertTrue($replay->isArchon);
		$this->assertSame('1v1', $replay->realType);
		$this->assertSame(array('P', 'Z'), array_map(static fn ($t): string => $t->lineup, $replay->teams));
	}

	public function test_cache_handle(): void {
		$handle = ReplayMapper::cacheHandle('s2ma'."\0\0SG".hash('sha256', 'map', true));

		$this->assertSame(array('type' => 's2ma', 'server' => 'sg', 'hash' => hash('sha256', 'map')), $handle);
	}

	public function test_computer_slots_beyond_details_are_skipped(): void {
		// co-op: more computer slots than details entries (sc2reader's detail_id guard)
		$replay = $this->replay(
			slots: array(self::human(0, 0), self::computer(1), self::computer(1), self::computer(1)),
			details: array(self::detail('A'), self::detail('AI 1')),
		);

		$this->assertCount(2, $replay->players);
		$this->assertSame('AI 1', $replay->players[1]->name);
	}

	public function test_details_pair_by_working_set_slot_not_by_order(): void {
		// the working set slot is not the slot index (an observer in slot 2 took working set slot 1)
		$replay = $this->replay(
			slots: array(self::human(0, 0, workingSet: 0), self::human(1, 1, workingSet: 15), self::human(2, 0, observe: 1, workingSet: 1)),
			details: array(self::detail('B', race: 'Zerg', workingSet: 15), self::detail('A', race: 'Protoss', workingSet: 0)),
			users: array(array('m_name' => 'A'), array('m_name' => 'B'), array('m_name' => 'Obs')),
		);

		$this->assertSame(array('A' => Race::Protoss, 'B' => Race::Zerg), array_combine(
			array_map(static fn ($p): string => $p->name, $replay->players),
			array_map(static fn ($p): ?Race => $p->playedRace, $replay->players),
		));
		$this->assertFalse($replay->isArchon);
	}

	public function test_end_time_is_utc(): void {
		$time = ReplayMapper::endTime(116_444_736_000_000_000 + 1_500_000_000 * 10 ** 7 + 9_999_999);

		$this->assertSame('2017-07-14T02:40:00+00:00', $time->format(\DATE_ATOM));
	}

	public function test_ladder_needs_ladder_mode_without_observers_and_computers(): void {
		$ladder = array(16 => array('Game Mode' => 'Ladder'));
		$this->assertTrue($this->replay(attributes: $ladder)->isLadder);
		$this->assertFalse($this->replay(attributes: array(16 => array('Game Mode' => 'Private')))->isLadder);

		$withObserver = $this->replay(
			slots: array(self::human(0, 0), self::human(1, 1), self::human(2, 0, observe: 1)),
			users: array(array('m_name' => 'A'), array('m_name' => 'B'), array('m_name' => 'Obs')),
			attributes: $ladder,
		);
		$this->assertFalse($withObserver->isLadder);

		$vsAi = $this->replay(slots: array(self::human(0, 0), self::computer(1)), details: array(self::detail('A'), self::detail('AI')), attributes: $ladder);
		$this->assertFalse($vsAi->isLadder);
		$this->assertSame(GameMode::Ladder, $vsAi->gameMode);
	}

	public function test_names_come_from_user_data_and_details_for_computers(): void {
		$replay = $this->replay(
			slots: array(self::human(0, 0), self::computer(1)),
			details: array(self::detail('[CLAN]<sp/>Foo'), self::detail('A.I. 1 (Hard)')),
			users: array(array('m_name' => 'Foo', 'm_clanTag' => 'CLAN')),
			attributes: array(2 => array('Difficulty' => 'Hard')),
		);

		$this->assertSame('Foo', $replay->players[0]->name);
		$this->assertSame('CLAN', $replay->players[0]->users[0]->clanTag);
		$this->assertSame('2-S2-1-100', (string)$replay->players[0]->users[0]->toon);
		$this->assertTrue($replay->players[0]->isHuman);
		$this->assertNull($replay->players[0]->computer);
		$this->assertSame('A.I. 1 (Hard)', $replay->players[1]->name);
		$this->assertSame(array(), $replay->players[1]->users);
		$this->assertFalse($replay->players[1]->isHuman);
		$this->assertSame(1, $replay->players[1]->computer?->slot);
		$this->assertSame('Hard', $replay->players[1]->computer->difficulty);
		$this->assertCount(1, $replay->users, 'computers are no users');
	}

	public function test_observers_take_no_player_id(): void {
		$replay = $this->replay(
			slots: array(self::human(0, 0), self::human(1, 0, observe: 1, toon: '2-S2-1-7'), self::human(2, 1)),
			details: array(self::detail('A'), self::detail('B')),
			users: array(array('m_name' => 'A'), array('m_name' => 'Obs'), array('m_name' => 'B')),
			attributes: array(2 => array('Race' => 'Zerg')),
		);

		$this->assertSame(array(1, 2), array_map(static fn ($p): int => $p->pid, $replay->players));
		$this->assertSame(array(array(0), array(2)), array_map(self::slots(...), $replay->players));
		$this->assertSame(array(0, 1, 2), array_map(static fn (User $u): int => $u->slot, $replay->users), 'users are every human, observers included');
		$this->assertNull($replay->playerOf($replay->users[1]));
		$this->assertSame(Race::Zerg, $replay->players[1]->pickedRace, 'scope 2 is the second player, not the observer');
		$this->assertCount(1, $replay->observers);
		$this->assertSame('Obs', $replay->observers[0]->name);
		$this->assertSame('2-S2-1-7', (string)$replay->observers[0]->toon);
		$this->assertNull($replay->observers[0]->clanTag);
	}

	public function test_open_and_closed_slots_are_skipped(): void {
		$open = array('m_control' => 0, 'm_userId' => null, 'm_observe' => 0, 'm_teamId' => 0, 'm_handicap' => 100);
		$closed = array('m_control' => 1) + $open;
		$users = array(array(), array('m_name' => 'A'), array(), array('m_name' => 'B'));
		$replay = $this->replay(slots: array($open, self::human(1, 0), $closed, self::human(3, 1)), users: $users);

		$this->assertSame(array(1, 2), array_map(static fn ($p): int => $p->pid, $replay->players));
		$this->assertSame(array(array(1), array(3)), array_map(self::slots(...), $replay->players));
	}

	public function test_race_pick_and_play(): void {
		$replay = $this->replay(
			details: array(self::detail('A', race: '저그'), self::detail('B', race: 'Unknown race')),
			attributes: array(1 => array('Race' => 'Random')),
		);

		$this->assertSame(Race::Random, $replay->players[0]->pickedRace);
		$this->assertSame(Race::Zerg, $replay->players[0]->playedRace);
		$this->assertNull($replay->players[1]->pickedRace);
		$this->assertNull($replay->players[1]->playedRace);
		$this->assertSame('?', $replay->teams[1]->lineup);
	}

	#[DataProvider('provide_real_type_cases')]
	public function test_real_type(array $teamOfEachPlayer, string $expected): void {
		$slots = array();
		$details = array();
		$users = array();
		foreach ($teamOfEachPlayer as $i => $team) {
			$slots[] = self::human($i, $team - 1);
			$details[] = self::detail("P{$i}");
			$users[] = array('m_name' => "P{$i}");
		}

		$this->assertSame($expected, $this->replay(slots: $slots, details: $details, users: $users)->realType);
	}

	public function test_region_singapore_is_sea(): void {
		$this->assertSame('sea', $this->replay(region: 'SG')->region);
		$this->assertSame('us', $this->replay(region: 'US')->region);
	}

	public function test_speed_sets_the_real_length(): void {
		// 16 gameloops per game second at every speed and in every expansion, Faster = 1.4 game seconds per second
		$replay = $this->replay(build: 65_895, gameloops: 22_400, dependencies: array(self::VOID));
		$this->assertSame(Expansion::LotV, $replay->expansion);
		$this->assertSame(1_000, $replay->realLength);
		$this->assertEqualsWithDelta(10.0, $replay->realSeconds(224), 1e-9, '22.4 gameloops per real second at Faster');

		$this->assertSame(1_000, $this->replay(gameloops: 16 * 1_400)->realLength, 'WoL at Faster');
		$this->assertSame(1_666, $this->replay(gameloops: 16_000, attributes: array(16 => array('Game Speed' => 'Slower')))->realLength, 'Slower: 0.6 game seconds per second');
		$this->assertSame(1_000, $this->replay(build: 65_895, gameloops: 16_000, dependencies: array(self::VOID), attributes: array(16 => array('Game Speed' => 'Normal')))->realLength, 'LotV at Normal');
		$this->assertSame(714, $this->replay(build: 38_215, gameloops: 16_000)->realLength, 'HotS on the LotV client counts like any other game');
	}

	public function test_team_results(): void {
		$replay = $this->replay(
			slots: array(self::human(0, 0), self::human(1, 0), self::human(2, 1), self::human(3, 1), self::human(4, 2)),
			details: array(self::detail('A', result: 1), self::detail('B', result: 1), self::detail('C', result: 2), self::detail('D', result: 1), self::detail('E', result: 0)),
			users: array(array('m_name' => 'A'), array('m_name' => 'B'), array('m_name' => 'C'), array('m_name' => 'D'), array('m_name' => 'E')),
		);

		$this->assertSame(array(1, 2, 3), array_map(static fn ($t): int => $t->number, $replay->teams));
		$this->assertSame(Result::Win, $replay->teams[0]->result);
		$this->assertSame(Result::Unknown, $replay->teams[1]->result);
		$this->assertNull($replay->teams[2]->result);
		$this->assertNull($replay->players[4]->result);
	}

	public function test_utc_offset_both_encodings(): void {
		$fileTime = 130_000_000_000_000_000;

		$this->assertSame(2.0, ReplayMapper::utcOffset(72_000_000_000, $fileTime));
		$this->assertSame(-7.0, ReplayMapper::utcOffset(-252_000_000_000, $fileTime));
		// newer builds store the local FILETIME instead of the offset
		$this->assertSame(-4.0, ReplayMapper::utcOffset($fileTime - 144_000_000_000, $fileTime));
	}

	public function test_without_initdata_the_lobby_defaults_apply(): void {
		$computer = array('m_control' => 3) + self::detail('AI');
		$empty = array('m_name' => '') + self::detail('');
		$noToon = self::detail('B', 'Zerg', 2);
		unset($noToon['m_toon']['m_id']);
		$replay = $this->replay(details: array($empty, self::detail('A'), $noToon, $computer), attributes: array(2 => array('Race' => 'Random')), lobby: false);

		$this->assertSame(0, $replay->seed);
		$this->assertFalse($replay->amm || $replay->battleNet || $replay->ranked || $replay->isArchon || $replay->isLadder);
		$this->assertSame(array(), $replay->observers);
		$this->assertSame(array('A', 'B', 'AI'), array_map(static fn (Player $p): string => $p->name, $replay->players), 'empty entries are left out');
		$this->assertSame(array(1, 2, 3), array_map(static fn (Player $p): int => $p->pid, $replay->players));
		$this->assertSame(array(array(1), array(2), array(3)), array_map(self::slots(...), $replay->players), 'the slot is the index in details');
		$this->assertSame(array(0, 1), array_map(static fn (User $u): int => $u->userId, $replay->users), 'users unless m_control says computer');
		$this->assertSame('AI', $replay->players[2]->computer?->name);
		$this->assertSame(array('2-S2-1-100', null), array_map(static fn (User $u): ?string => $u->toon === null ? null : (string)$u->toon, $replay->users), 'a toon needs an m_id');
		$this->assertSame(array(1, 2, 3), array_map(static fn (Player $p): int => $p->team, $replay->players), 'no m_teamId: a team of its own');
		$this->assertSame(array(null, Race::Random, null), array_map(static fn (Player $p): ?Race => $p->pickedRace, $replay->players), 'attribute scope = pid');
		$this->assertSame(array(Result::Win, Result::Loss, Result::Win), array_map(static fn (Player $p): ?Result => $p->result, $replay->players));
		$this->assertSame(array(100, 100, 100), array_map(static fn (Player $p): int => $p->handicap, $replay->players));
	}

	public function test_without_times_and_speed_attribute(): void {
		$replay = ReplayMapper::replay(
			new ReplayHeader(new GameVersion(0, 4, 0, 14_133, 14_133), 1_600, true),
			array('m_title' => 'Map', 'm_playerList' => array(), 'm_gameSpeed' => 2, 'm_cacheHandles' => array('s2mh'."\0\0eu".str_repeat("\0", 32))),
			null,
			array(),
		);

		$this->assertNull($replay->endTime);
		$this->assertNull($replay->utcOffset);
		$this->assertNull($replay->startTime);
		$this->assertSame(GameSpeed::Normal, $replay->speed, 'the speed from details without the attribute');
	}

	/**
	 * @return array<string, mixed>
	 */
	/**
	 * @param array{int|null, int}|null $tandem tandem leader id and tandem id (archon), null before archon existed
	 */
	private static function computer(int $team, ?int $workingSet = null, ?array $tandem = null): array {
		return array('m_control' => 3, 'm_userId' => null, 'm_observe' => 0, 'm_teamId' => $team, 'm_handicap' => 100) + self::since24764($workingSet, $tandem);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function detail(string $name, string $race = 'Terran', int $result = 1, ?int $workingSet = null): array {
		return ($workingSet === null ? array() : array('m_workingSetSlotId' => $workingSet)) + array(
			'm_name' => $name,
			'm_toon' => array('m_region' => 2, 'm_programId' => "\0\0S2", 'm_realm' => 1, 'm_id' => 100),
			'm_race' => $race,
			'm_color' => array('m_a' => 255, 'm_r' => 180, 'm_g' => 20, 'm_b' => 30),
			'm_result' => $result,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	/**
	 * @param array{int|null, int}|null $tandem tandem leader id and tandem id (archon), null before archon existed
	 */
	private static function human(int $userId, int $team, int $observe = 0, string $toon = '', ?int $workingSet = null, ?array $tandem = null): array {
		return array('m_control' => 2, 'm_userId' => $userId, 'm_observe' => $observe, 'm_teamId' => $team, 'm_handicap' => 100, 'm_toonHandle' => $toon) + self::since24764($workingSet, $tandem);
	}

	/**
	 * @param list<array<string, mixed>>|null $slots
	 * @param list<array<string, mixed>>|null $details
	 * @param list<array<string, mixed>>|null $users
	 * @param array<int, array<string, string|null>> $attributes
	 * @param list<string> $dependencies
	 */
	private function replay(
		?array $slots = null,
		?array $details = null,
		?array $users = null,
		array $attributes = array(),
		int $build = 25_604,
		int $gameloops = 16_000,
		array $dependencies = array(self::LIBERTY),
		string $region = 'EU',
		bool $lobby = true,
	): Replay {
		$slots ??= array(self::human(0, 0), self::human(1, 1));
		$details ??= array(self::detail('A'), self::detail('B'));
		$users ??= array(array('m_name' => 'A'), array('m_name' => 'B'));
		$attributes += array(16 => array());
		$attributes[16] += array('Game Speed' => 'Faster');

		$handle = static fn (string $name): string => 's2mh'.str_pad($region, 4, "\0", \STR_PAD_LEFT).hash('sha256', $name, true);
		$detailsFile = array(
			'm_playerList' => $details,
			'm_title' => 'Test Map',
			'm_cacheHandles' => array(...array_map($handle, $dependencies), $handle('map')),
			'm_timeUTC' => 130_000_000_000_000_000,
			'm_timeLocalOffset' => 72_000_000_000,
		);
		$initdata = array('m_syncLobbyState' => array(
			'm_lobbyState' => array('m_slots' => $slots),
			'm_userInitialData' => $users,
			'm_gameDescription' => array('m_randomValue' => 42, 'm_gameOptions' => array('m_amm' => true, 'm_battleNet' => true)),
		));
		$header = new ReplayHeader(new GameVersion(2, 0, 8, $build, $build), $gameloops);

		return ReplayMapper::replay($header, $detailsFile, $lobby ? $initdata : null, $attributes);
	}

	/**
	 * @param array{int|null, int}|null $tandem
	 *
	 * @return array<string, mixed>
	 */
	private static function since24764(?int $workingSet, ?array $tandem): array {
		$ret = $workingSet === null ? array() : array('m_workingSetSlotId' => $workingSet);
		if ($tandem !== null) {
			$ret += array('m_tandemLeaderId' => $tandem[0], 'm_tandemId' => $tandem[1]);
		}

		return $ret;
	}

	/**
	 * The slots of the users controlling a player, or of its computer.
	 *
	 * @return list<int>
	 */
	private static function slots(Player $player): array {
		return $player->computer === null ? array_map(static fn (User $u): int => $u->slot, $player->users) : array($player->computer->slot);
	}
}
