<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\tests\map;

use holonet\sc2utils\decode\MapInfoFile;
use holonet\sc2utils\MapFile;
use holonet\sc2utils\model\map\Bounds;
use holonet\sc2utils\model\map\Map;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * Ensure all corpus test maps can be read by the library.
 * @internal
 */
#[CoversClass(MapFile::class)]
#[CoversClass(MapInfoFile::class)]
#[CoversClass(Map::class)]
#[CoversClass(Bounds::class)]
#[Small]
class MapFileTest extends TestCase {
	/**
	 * @return iterable<string, array{string}>
	 */
	public static function provide_map_cases(): iterable {
		// MapInfo 0x18 (1.3 era, MPQ v2)
		yield 'Slag Pits' => array('06cf2069f2076fbd8512969d7a5c1e5c1366fddf487c9a98c8464d13f1954183');
		yield 'Discord IV' => array('2a8b5bb82153c2b51c8b3880d419e2fdd2947fb21785e9b316ec3cbdeda37420');
		// MapInfo 0x20; enUS and enGB only, and the names differ (enGB: "GSL Daybreak (Official)")
		yield 'GSL Daybreak' => array('fbe44a4e218519271c2b74efe5ef66486e83cb1fd5325c502d36fcb411c6ad93');
		// MapInfo 0x27
		yield 'Void Thrashing' => array('5e027e5132846e6a9921eb7146bf31c0e7c287c23fa96ebc64ab153c06b6c871');
		yield 'Ujari' => array('1fce2ad4284e3987cc41cc9e691d12abd1ea185b63ded8adb385bc0a7dc76119');
	}

	public function test_doc_name(): void {
		$this->assertSame('Lost Temple', MapFile::docName("DocInfo/Author=Blizzard\r\nDocInfo/Name=Lost Temple\r\n"));
		$this->assertSame('A=B', MapFile::docName("DocInfo/Name=A=B\n"), 'only the first "=" separates');
		$this->assertSame('Lost Temple', MapFile::docName("\u{FEFF}DocInfo/Name=Lost Temple"), 'byte order mark');
		$this->assertSame('', MapFile::docName("DocInfo/Name=\r\n"));
		$this->assertNull(MapFile::docName("DocInfo/NameX=No\r\nDocInfo/Author=Blizzard\r\n"));
		$this->assertNull(MapFile::docName(''));
	}

	#[DataProvider('provide_map_cases')]
	public function test_map(string $hash): void {
		$file = MapFile::open(dirname(__DIR__)."/test_maps/{$hash}.s2ma");
		$map = $file->map();

		$this->assertSame($this->dataName(), $map->name);
		$this->assertGreaterThan(0, $map->width);
		$this->assertGreaterThan(0, $map->height);
		$this->assertGreaterThanOrEqual(0, $map->camera->left);
		$this->assertGreaterThanOrEqual(0, $map->camera->bottom);
		$this->assertLessThan($map->camera->right, $map->camera->left);
		$this->assertLessThan($map->camera->top, $map->camera->bottom);
		$this->assertLessThanOrEqual($map->width, $map->camera->right);
		$this->assertLessThanOrEqual($map->height, $map->camera->top);
		$this->assertNotSame('', $map->tileSet);
		$this->assertContains('enUS', $file->locales());
		$this->assertNotNull($file->script());

		$this->assertNotNull($map->minimap);
		$image = imagecreatefromtga('data://application/octet-stream;base64,'.base64_encode($map->minimap));
		$this->assertNotFalse($image, 'the minimap decodes');
		$this->assertGreaterThan(0, imagesx($image));
		$this->assertGreaterThan(0, imagesy($image));
	}

	public function test_order_locales(): void {
		$this->assertSame(array('enUS', 'enGB', 'deDE', 'koKR'), MapFile::orderLocales(array('koKR', 'enGB', 'deDE', 'enUS')));
		$this->assertSame(array('enGB', 'deDE'), MapFile::orderLocales(array('deDE', 'enGB', 'deDE')));
		$this->assertSame(array('frFR'), MapFile::orderLocales(array('frFR')));
		$this->assertSame(array(), MapFile::orderLocales(array()));
	}
}
