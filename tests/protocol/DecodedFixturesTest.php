<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\tests\protocol;

use holonet\sc2utils\archive\MpqArchive;
use holonet\sc2utils\protocol\BitPackedCodec;
use holonet\sc2utils\protocol\EventStream;
use holonet\sc2utils\protocol\Protocol;
use holonet\sc2utils\protocol\ProtocolRegistry;
use holonet\sc2utils\protocol\VersionedCodec;
use holonet\sc2utils\tests\UsesFixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

/**
 * Every decoded stream of every test replay against s2protocol's output in the decoded fixtures.
 * Header, details, initdata and the three event streams must equal the fixture, and the whole file must be consumed.
 *
 * @internal
 */
#[CoversClass(VersionedCodec::class)]
#[CoversClass(BitPackedCodec::class)]
#[CoversClass(EventStream::class)]
#[CoversClass(ProtocolRegistry::class)]
#[CoversClass(Protocol::class)]
#[Medium]
class DecodedFixturesTest extends TestCase {
	use UsesFixtures;

	private const array ARCHIVE_FILES = array(
		'details' => 'replay.details',
		'initdata' => 'replay.initData',
		'game' => 'replay.game.events',
		'message' => 'replay.message.events',
		'tracker' => 'replay.tracker.events',
	);

	private static ?ProtocolRegistry $registry = null;

	/**
	 * @return iterable<string, array{string, string}>
	 */
	public static function provide_stream_cases(): iterable {
		foreach (self::streams() as list($key, $stream, $info)) {
			if (isset($info['file']) && $stream !== 'attributes') {
				yield "{$key} {$stream}" => array($key, $stream);
			}
		}
	}

	#[DataProvider('provide_stream_cases')]
	public function test_stream(string $key, string $stream): void {
		list($archive, $protocol) = $this->open($key);
		list($codec, $value) = $this->decode($archive, $protocol, $stream);

		$this->assertDecodedFixture($key, $stream, $value);
		$this->assertTrue($codec->atEnd(), 'consumed to the end');
		if ($codec instanceof VersionedCodec) {
			$this->assertSame(self::streamMeta($key, $stream)['skipped'] ?? 0, $codec->skipped(), 'skipped fields');
		}
	}

	/**
	 * @return array{BitPackedCodec|VersionedCodec, mixed} the codec and the value (a generator for event streams)
	 */
	private function decode(MpqArchive $archive, Protocol $protocol, string $stream): array {
		if ($stream === 'header') {
			$codec = new VersionedCodec($protocol, (string)$archive->userData?->content);

			return array($codec, $codec->decode($protocol->header));
		}

		$name = self::ARCHIVE_FILES[$stream];
		// anonymised replay packs keep only the .backup copies of details and initData (as decoded.py reads them)
		$data = $archive->has($name) ? $archive->read($name) : $archive->read("{$name}.backup");

		switch ($stream) {
			case 'details':
				$codec = new VersionedCodec($protocol, $data);

				return array($codec, $codec->decode($protocol->details));
			case 'initdata':
				$codec = new BitPackedCodec($protocol, $data);

				return array($codec, $codec->decode($protocol->initdata));
			case 'tracker':
				$codec = new VersionedCodec($protocol, $data);

				// consumed here, so a failing stream throws inside decode()
				return array($codec, iterator_to_array(EventStream::tracker($protocol, $codec), false));
			default:
				$codec = new BitPackedCodec($protocol, $data);
				$events = $stream === 'game' ? EventStream::game($protocol, $codec) : EventStream::message($protocol, $codec);

				return array($codec, iterator_to_array($events, false));
		}
	}

	/**
	 * Opens the replay and picks its protocol the way a parser does, checking the pick against meta.json.
	 *
	 * @return array{MpqArchive, Protocol}
	 */
	private function open(string $key): array {
		$archive = MpqArchive::open(self::replayPath($key));
		$header = $archive->userData?->content;
		$this->assertNotNull($header);

		$registry = self::$registry ??= new ProtocolRegistry();
		/** @var array{m_version: array{m_baseBuild: int}} $latestHeader */
		$latestHeader = (new VersionedCodec($registry->latest(), $header))->decode($registry->latest()->header);
		$baseBuild = $latestHeader['m_version']['m_baseBuild'];
		$meta = self::meta($key);
		$this->assertSame($meta['baseBuild'], $baseBuild, 'base build');
		$this->assertSame($meta['protocol'], $registry->nearestBuild($baseBuild), 'protocol build');

		return array($archive, $registry->forBuild($baseBuild));
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function streamMeta(string $key, string $stream): array {
		/** @var array{streams: array<string, array<string, mixed>>} $meta */
		$meta = self::meta($key);

		return $meta['streams'][$stream];
	}

	/**
	 * @return iterable<array{string, string, array<string, mixed>}> [key, stream, its meta.json entry]
	 */
	private static function streams(): iterable {
		foreach (self::keys() as $key) {
			/** @var array<string, array<string, mixed>> $streams */
			$streams = self::meta($key)['streams'] ?? array();
			foreach ($streams as $stream => $info) {
				yield array($key, $stream, $info);
			}
		}
	}
}
