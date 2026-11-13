<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\tests\simulate\plugin;

use holonet\sc2utils\model\Point;
use holonet\sc2utils\model\simulation\plugin\AbilityUse;
use holonet\sc2utils\simulate\plugin\AbilityTracker;
use holonet\sc2utils\testing\EventScript;
use holonet\sc2utils\testing\ReplayBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * Ability usage statistics against constructed events.
 * @internal
 */
#[CoversClass(AbilityTracker::class)]
#[CoversClass(AbilityUse::class)]
#[Small]
class AbilityTrackerTest extends TestCase {
	public function test_uses_by_ability_and_command_with_first_use(): void {
		$script = new EventScript(new ReplayBuilder()->player('Pia')->build());
		$script->at(100)->command('Pia', 'PsiStorm')->command('Pia', 'BansheeCloak', 1);
		$script->at(200)->command('Pia', 'PsiStorm')->command('Pia', null)->retarget('Pia', new Point(1, 1));
		$script->at(300)->command('Pia', null, link: 709);

		$uses = $script->simulate(new AbilityTracker())->result(AbilityTracker::class)?->for($script->replay->users[0]) ?? array();
		$this->assertSame(
			array('?709#0' => array(1, 300), 'BansheeCloak#1' => array(1, 100), 'PsiStorm#0' => array(2, 100)),
			array_map(static fn (AbilityUse $use): array => array($use->count, $use->firstUse), $uses),
			'right-clicks and retargets left out, unknown links by number',
		);
	}
}
