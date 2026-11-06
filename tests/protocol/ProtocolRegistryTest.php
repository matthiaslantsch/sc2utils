<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\tests\protocol;

use holonet\sc2utils\protocol\Protocol;
use holonet\sc2utils\protocol\ProtocolRegistry;
use holonet\sc2utils\protocol\UnsupportedBuildException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ProtocolRegistry::class)]
#[CoversClass(Protocol::class)]
#[Small]
class ProtocolRegistryTest extends TestCase {
	/**
	 * @return iterable<string, array{int, int}>
	 */
	public static function provide_nearest_cases(): iterable {
		yield 'oldest' => array(15_405, 15_405);
		yield 'exact' => array(41_743, 41_743);
		yield 'between' => array(15_976, 15_405);
		yield 'gap before the regenerated range' => array(51_701, 51_149);
		yield 'newer than the newest' => array(999_999, 98_310);
	}

	public function test_builds(): void {
		$builds = (new ProtocolRegistry())->builds();

		$this->assertCount(144, $builds);
		$sorted = $builds;
		sort($sorted);
		$this->assertSame($sorted, $builds);
	}

	public function test_error_load_unknown_build(): void {
		$this->expectException(UnsupportedBuildException::class);
		$this->expectExceptionMessageIs('There is no protocol 15976');

		(new ProtocolRegistry())->load(15_976);
	}

	public function test_error_too_old(): void {
		$this->expectException(UnsupportedBuildException::class);
		$this->expectExceptionMessageIs('No protocol for base build 15404, the oldest is 15405');

		(new ProtocolRegistry())->nearestBuild(15_404);
	}

	#[DataProvider('provide_nearest_cases')]
	public function test_nearest(int $baseBuild, int $expected): void {
		$registry = new ProtocolRegistry();

		$this->assertSame($expected, $registry->nearestBuild($baseBuild));
		$this->assertSame($expected, $registry->forBuild($baseBuild)->build);
	}
}
