<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\tests;

use holonet\bitstream\stream\StringStream;
use holonet\sc2utils\archive\ArchiveHeader;
use holonet\sc2utils\archive\BlockEntry;
use holonet\sc2utils\archive\HashEntry;
use holonet\sc2utils\archive\MpqArchive;
use holonet\sc2utils\archive\MpqCrypto;
use holonet\sc2utils\archive\MpqException;
use holonet\sc2utils\archive\UserDataHeader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * Checks the MPQ reader against one archive per MPQ variant (format version, sector size, storage, compression),
 * plus the archives where other readers fail.
 * The expected values were taken once with mpyq.
 *
 * @internal
 */
#[CoversClass(MpqArchive::class)]
#[CoversClass(MpqCrypto::class)]
#[CoversClass(ArchiveHeader::class)]
#[CoversClass(UserDataHeader::class)]
#[CoversClass(HashEntry::class)]
#[CoversClass(BlockEntry::class)]
#[Small]
class MpqArchiveTest extends TestCase {
	/**
	 * reference values: the table keys are documented by StormLib, the name hash comes from mpyq.
	 *
	 * @return iterable<string, array{string, int, int}>
	 */
	public static function provide_hash_cases(): iterable {
		yield 'hash table key' => array('(hash table)', MpqCrypto::HASH_FILE_KEY, 0xC3AF_3770);
		yield 'block table key' => array('(block table)', MpqCrypto::HASH_FILE_KEY, 0xEC83_B3A3);
		yield 'file name' => array('replay.details', MpqCrypto::HASH_NAME_A, 0xD383_C29C);
		yield 'case insensitive' => array('REPLAY.Details', MpqCrypto::HASH_NAME_A, 0xD383_C29C);
	}

	/**
	 * Maps: the file count, and a hash for the files the library reads. Every other
	 * file is only checked for extracting at the size its block table entry declares.
	 *
	 * @return iterable<string, array{string, int, array<string, array{string, int}>}>
	 */
	public static function provide_map_files_cases(): iterable {
		// MPQ v2, sector shift 3: zlib, single-unit and compressed sectored files (1.3.3 era)
		yield 'Slag Pits' => array('test_maps/06cf2069f2076fbd8512969d7a5c1e5c1366fddf487c9a98c8464d13f1954183.s2ma', 41, array(
			'DocumentHeader' => array('a330af484b011bd08c76191cfffc9c31438a3fc4', 7_446),
			'MapInfo' => array('f7567e7eefb094ef5817240cfae1f101fb19b45b', 333),
			'MapScript.galaxy' => array('fe99b50af04aa663f9df68c09af5be347239752c', 2_244),
			'Minimap.tga' => array('fcae73265bca43f5c57f19876870af24d197c838', 196_652),
			'enUS.SC2Data\LocalizedData\GameStrings.txt' => array('9627e8d06422fa7cab6716abb24ad2675e0f052c', 494),
		));

		// MPQ v2: uncompressed sectored file (Build.txt), which has no sector offset table.
		// mpyq reads it as empty; the content is "Map Came From Build Server" (1.4.0 era)
		yield 'Discord IV' => array('test_maps/2a8b5bb82153c2b51c8b3880d419e2fdd2947fb21785e9b316ec3cbdeda37420.s2ma', 44, array(
			'Build.txt' => array('e557b06ec05860e0120b489c16ea2fb986d6f5e0', 26),
			'DocumentHeader' => array('112fd4e01624e74aa4ffd4c967136edd3a0f5783', 8_541),
			'MapInfo' => array('db49f505512a3e7defc749ad4d348363c2e02790', 342),
			'MapScript.galaxy' => array('b791472d5ab51af01129665fd5745f7bbae83a4a', 2_233),
			'Minimap.tga' => array('5f78bfa66283e0bafbad12fb334820fb9f0e08f1', 98_348),
			'enUS.SC2Data\LocalizedData\GameStrings.txt' => array('615369c213da7078880ac8ade898868a465489fb', 1_421),
		));

		// MPQ v4, sector shift 5: zlib and bzip2 sectored files; largest map
		yield 'Dreamhack Daybreak' => array('test_maps/fbe44a4e218519271c2b74efe5ef66486e83cb1fd5325c502d36fcb411c6ad93.s2ma', 43, array(
			'DocumentHeader' => array('138372b15bbf3c529b4739502e10c4472c97d6cf', 766),
			'MapInfo' => array('7e73329cae09479456ea0a6177d9b53505f09553', 295),
			'MapScript.galaxy' => array('a305a9c8fdeedf62e3547c19163de090e48a53d1', 2_292),
			'Minimap.tga' => array('c616f0711c58ac9800f2be365b4a49e57d085740', 98_348),
			'enUS.SC2Data\LocalizedData\GameStrings.txt' => array('b77577cebe908abf0c65b6fc6a4d93ae4e8f896f', 294),
		));

		// MPQ v4, current era
		yield 'Void Thrashing' => array('test_maps/5e027e5132846e6a9921eb7146bf31c0e7c287c23fa96ebc64ab153c06b6c871.s2ma', 84, array(
			'DocumentHeader' => array('09d7c2978212ee12849441d45002a62fa03e410e', 8_854),
			'MapInfo' => array('8fea4c84ca95bb2221fe935b0cf47f3526c62af9', 625),
			'MapScript.galaxy' => array('b561640e39b926fe2f344371e481a1101b1362a5', 190_153),
			'Minimap.tga' => array('d680a42c4f19c65d52461125455d7bf6a744d949', 786_476),
			'enUS.SC2Data\LocalizedData\GameStrings.txt' => array('fa667b36db5edb39730126c4df5f452096fc93b7', 2_756),
		));
	}

	/**
	 * Replays: the complete file list, and a hash for every file.
	 *
	 * @return iterable<string, array{string, array<string, array{string, int}>}>
	 */
	public static function provide_replay_files_cases(): iterable {
		// MPQ v2, sector shift 3: zlib, single-unit and compressed sectored files (phase-1 beta)
		yield '0.4.0.14133' => array('test_replays/0.4.0.14133/0.4.0.14133.SC2Replay', array(
			'replay.game.events' => array('9556a5102e34e3f5bf6d9da95cd9ab5d322c737b', 498_413),
			'replay.info' => array('acf00bcf00820ac856d9c8222463743388b56f10', 1_246),
			'replay.message.events' => array('9ee9797a9cd6a79df51268121023630e1d1f0a67', 42),
			'replay.smartcam.events' => array('cc680ebb15a3b5f48c63b6274a6fd930aa417a80', 12_192),
			'replay.sync.events' => array('a87266813290bd86e60d2e188465f1fdaae5e1e9', 192_496),
			'save.jpg' => array('9b5f36df7db356679affd84121fb35e4b8600ff7', 6_421),
		));

		// MPQ v2: compressed sectored file storing most sectors raw (save.jpg).
		// mpyq fails on it; it decodes completely in Pillow (180x180 JPEG)
		yield '0.7.0.14356' => array('test_replays/0.7.0.14356/0.7.0.14356.SC2Replay', array(
			'replay.game.events' => array('091a33b9ff0b7b754e33f007de52488b386679a2', 360_737),
			'replay.info' => array('d27dde38077aea0a7482b7b2839c56d2f3380e29', 1_232),
			'replay.message.events' => array('6d99c9028ff1b9d81483867de235fba1d00b789f', 41),
			'replay.smartcam.events' => array('035e9d2247547c2c4744cc6d748c3d67b5f7bb75', 12_140),
			'replay.sync.events' => array('d7008db78fa9829b118f0ed6ec556beebe181d19', 140_084),
			'save.jpg' => array('e57a974bf0bf87bb3c749179d4d351fa37133b75', 8_629),
		));

		// MPQ v2: compressed single-unit file stored raw whose first byte is 0x02 (replay.smartcam.events),
		// which the vendored reader took for zlib
		yield '0.19.0.15976' => array('test_replays/0.19.0.15976/0.19.0.15976.SC2Replay', array(
			'replay.attributes.events' => array('a8024a66f31d189154b57b56fe9954d6fb62d434', 528),
			'replay.details' => array('bd43e05d7d8a6de693d9b3858fe0ab95c7c0b5db', 450),
			'replay.game.events' => array('c50b48951c9bb57760d1bf8268f351b88cb90eae', 13_695),
			'replay.initData' => array('6ab6b8249f282bcd74556952af1334c91750cd15', 1_088),
			'replay.load.info' => array('6d25a25fa93d9686982a4ff530caf8d7c58f5ff5', 96),
			'replay.message.events' => array('cdd516136fa343693259a93ffce6f67df8831d10', 95),
			'replay.smartcam.events' => array('5de2a6d733fb13752812a7474c34cbfb678c0e94', 139),
			'replay.sync.events' => array('911b4b835dbc6ddb6d5aef235a73ee7bd20b7c68', 175),
		));

		// MPQ v2, sector shift 3: bzip2, single-unit files
		yield '1.3.3.18574' => array('test_replays/1.3.3.18574/1.3.3.18574.SC2Replay', array(
			'replay.attributes.events' => array('58cb9290d1d57792c3689475a3e26edba9498617', 581),
			'replay.details' => array('6c743f08a019e27cb60769fb20e65364de19ed4d', 451),
			'replay.game.events' => array('f90fedba36fd98f7ebddceeab23f9848ac63aad6', 22_216),
			'replay.initData' => array('098a1e2370f264c4f3960653db16ddc04432979a', 1_226),
			'replay.load.info' => array('b0472165ac4a604819798f028975cf55701843ca', 97),
			'replay.message.events' => array('9d8814783ed5310b94be82af35e7a76b38717a6c', 63),
			'replay.smartcam.events' => array('3f57af6010df31f3de71f21754a5dedad6f1fc5c', 976),
			'replay.sync.events' => array('58085e4dd9dfe7783f7b99c29cc017115c945aa6', 580),
		));

		// MPQ v4, sector shift 5: bzip2, single-unit files
		yield '2.0.0.24247' => array('test_replays/2.0.0.24247/2.0.0.24247.SC2Replay', array(
			'replay.attributes.events' => array('afd8937f7b5a19a259b312d261690c1edf51fbde', 3_220),
			'replay.details' => array('64a37c045c41d2a7ef44c53a65b4156fe4f7c06a', 971),
			'replay.game.events' => array('f731964b43666134d78816c843a1f246449ab00f', 191_878),
			'replay.initData' => array('c79c82f4a766a937e6c11eb801ff880a0ce7af27', 1_781),
			'replay.load.info' => array('87edb8e558e1ea9595c96c975e3d869b05463eb7', 103),
			'replay.message.events' => array('6563ed7052f5c1cae5c569f7b663a9dbd02a0409', 1_266),
			'replay.resumable.events' => array('f85fccea39fca59b7adb76521cfc184ba1d31f80', 12),
			'replay.smartcam.events' => array('364e15e87ff2e92ed6a20efdd5f7c69c8172c319', 12_660),
			'replay.sync.events' => array('33e0c4eaf87268690a58174bb4a67639c608e24d', 1_139),
		));

		// MPQ v4, sector shift 5: bzip2 single-unit and uncompressed files; an empty file (replay.sync.history)
		yield '4.4.0.65895' => array('test_replays/4.4.0.65895/4.4.0.65895.SC2Replay', array(
			'replay.attributes.events' => array('e40152afffeef4f7bab441496c4c7ac97011e77c', 6_223),
			'replay.details' => array('1dbad0fba4eb2e75f5f871027827dbffd3c39419', 1_099),
			'replay.details.backup' => array('e74ac301d72a8bf776a07a7380c5e42be18e2b76', 979),
			'replay.game.events' => array('e19c94828c386bd25c5e713dc58c962686c81ae3', 114_522),
			'replay.gamemetadata.json' => array('dd65c4994e4c0b9a5d603a8444d761c18e2e26dd', 1_362),
			'replay.initData' => array('ae2fe49dec9a3be1446b8fd02390582fef4b379e', 3_993),
			'replay.initData.backup' => array('aa869d9fa21d919be2eb55dd8de90590075f5d48', 3_863),
			'replay.load.info' => array('2f1050adf64f33298ff0ce423eb86d4728441b21', 52),
			'replay.message.events' => array('dd37c2947f563123be4d023cf3ee5adbdb8826fe', 99),
			'replay.resumable.events' => array('7062a5ff108dfead74d2fa100a69c9d47e8bcbc2', 23),
			'replay.server.battlelobby' => array('bc2bc1e1dd92c269806daf6718af8d1dda21f33e', 32_613),
			'replay.smartcam.events' => array('79c32339316de76bcffbc7123ef17f7ff8763bb5', 4_555),
			'replay.sync.events' => array('42d85e59811f3e4635ec05f75a432f2c309a29b9', 1_275),
			'replay.sync.history' => array('da39a3ee5e6b4b0d3255bfef95601890afd80709', 0),
			'replay.tracker.events' => array('58cd83f74e8dbe2eea5d021b625b5974ec363178', 353_021),
		));
	}

	#[DataProvider('provide_hash_cases')]
	public function test_hash(string $name, int $type, int $expected): void {
		$this->assertSame($expected, MpqCrypto::hash($name, $type));
	}

	#[DataProvider('provide_map_files_cases')]
	/**
	 * @param array<string, array{string, int}> $expected file name => [sha1, size]
	 */
	public function test_map_files(string $path, int $fileCount, array $expected): void {
		$archive = MpqArchive::open(__DIR__."/{$path}");
		$names = $archive->listFiles();

		$this->assertNotNull($names);
		$this->assertCount($fileCount, $names);
		foreach ($names as $name) {
			$data = $archive->read($name);
			$this->assertSame($archive->fileSize($name), strlen($data), "size of {$name}");

			if (isset($expected[$name])) {
				$this->assertSame($expected[$name][1], strlen($data), "size of {$name}");
				$this->assertSame($expected[$name][0], sha1($data), "content of {$name}");
			}
		}
		$this->assertSame(array(), array_diff(array_keys($expected), $names), 'expected files missing from the archive');
	}

	public function test_missing_file(): void {
		$archive = MpqArchive::open(__DIR__.'/test_replays/4.4.0.65895/4.4.0.65895.SC2Replay');

		$this->assertFalse($archive->has('replay.missing'));
		$this->expectException(MpqException::class);
		$this->expectExceptionMessage("'replay.missing' does not exist in the archive");
		$archive->read('replay.missing');
	}

	public function test_not_an_archive(): void {
		$this->expectException(MpqException::class);
		$this->expectExceptionMessage('No MPQ header found');
		new MpqArchive(new StringStream(str_repeat("\0", 1_500)));
	}

	#[DataProvider('provide_replay_files_cases')]
	/**
	 * @param array<string, array{string, int}> $expected file name => [sha1, size]
	 */
	public function test_replay_files(string $path, array $expected): void {
		$archive = MpqArchive::open(__DIR__."/{$path}");

		$this->assertSame(array_keys($expected), $archive->listFiles());
		foreach ($expected as $name => list($sha1, $size)) {
			$data = $archive->read($name);
			$this->assertSame($size, strlen($data), "size of {$name}");
			$this->assertSame($sha1, sha1($data), "content of {$name}");
		}
	}

	public function test_user_data(): void {
		// replays keep their replay header in the user data block, maps have none
		$replay = MpqArchive::open(__DIR__.'/test_replays/4.4.0.65895/4.4.0.65895.SC2Replay');
		$this->assertNotNull($replay->userData);
		$this->assertStringContainsString('StarCraft II replay', $replay->userData->content);

		$map = MpqArchive::open(__DIR__.'/test_maps/5e027e5132846e6a9921eb7146bf31c0e7c287c23fa96ebc64ab153c06b6c871.s2ma');
		$this->assertNull($map->userData);
	}
}
