<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\tests\decode;

use holonet\sc2utils\archive\MpqArchive;
use holonet\sc2utils\decode\AttributeEntry;
use holonet\sc2utils\decode\AttributesFile;
use holonet\sc2utils\decode\LegacyAttributesFile;
use holonet\sc2utils\gamedata\Attributes;
use holonet\sc2utils\tests\UsesFixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * replay.attributes.events of every test replay against s2protocol's decoded attributes, plus the name lookup.
 *
 * @internal
 */
#[CoversClass(AttributesFile::class)]
#[CoversClass(LegacyAttributesFile::class)]
#[CoversClass(AttributeEntry::class)]
#[CoversClass(Attributes::class)]
#[Small]
class AttributesFileTest extends TestCase {
	use UsesFixtures;

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function provide_replay_cases(): iterable {
		foreach (self::keys() as $key) {
			if (isset(self::meta($key)['streams']['attributes']['file'])) {
				yield $key => array($key);
			}
		}
	}

	public function test_empty_file_has_no_attributes(): void {
		$this->assertSame(array(), AttributesFile::read('', 65_895)->entries);
	}

	#[DataProvider('provide_replay_cases')]
	public function test_equals_s2protocol(string $key): void {
		$bytes = MpqArchive::open(self::replayPath($key))->read('replay.attributes.events');
		/** @var int $baseBuild */
		$baseBuild = self::meta($key)['baseBuild'];
		$file = AttributesFile::read($bytes, $baseBuild);

		$scopes = array();
		foreach ($file->entries as $entry) {
			$scopes[(string)$entry->scope][(string)$entry->id][] = array('namespace' => $entry->namespace, 'attrid' => $entry->id, 'value' => $entry->value);
		}
		$this->assertDecodedFixture($key, 'attributes', array(
			'source' => $file->source,
			'mapNamespace' => $file->mapNamespace,
			'scopes' => $scopes,
		));
		$this->assertSame($bytes, $file->toBytes(), 'round trip');
	}

	public function test_resolve_last_entry_wins_and_unknown_values_are_null(): void {
		$entry = static function (int $id, int $scope, string $value): AttributeEntry {
			$ret = new AttributeEntry();
			$ret->id = $id;
			$ret->scope = $scope;
			$ret->raw = strrev(str_pad($value, 4, "\0"));

			return $ret;
		};

		$resolved = Attributes::resolve(array(
			$entry(3_000, 16, 'Fasr'),
			$entry(2_000, 16, 't2'),
			$entry(2_001, 16, '1v1'),
			$entry(3_001, 1, 'Zerg'),
			$entry(3_001, 2, 'Nope'),
			$entry(99_999, 16, 'x'),
			$entry(3_011, 1, '   0'),
		));

		$this->assertSame(array(
			16 => array('Game Speed' => 'Faster', 'Teams' => '1v1'),
			1 => array('Race' => 'Zerg', 'Player Logo Index' => '0'),
			2 => array('Race' => null),
		), $resolved);
	}
}
