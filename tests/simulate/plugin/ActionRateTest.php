<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\tests\simulate\plugin;

use holonet\sc2utils\model\enum\GameSpeed;
use holonet\sc2utils\model\enum\Race;
use holonet\sc2utils\model\Point;
use holonet\sc2utils\model\simulation\plugin\ActionRate;
use holonet\sc2utils\simulate\plugin\ActionCounter;
use holonet\sc2utils\simulate\plugin\ApmTracker;
use holonet\sc2utils\simulate\plugin\SpmTracker;
use holonet\sc2utils\testing\EventScript;
use holonet\sc2utils\testing\ReplayBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * APM and SPM tracking plugins against constructed events
 * Their ranges are checked by the plausibility analysis.
 *
 * @internal
 */
#[CoversClass(ActionCounter::class)]
#[CoversClass(ActionRate::class)]
#[CoversClass(ApmTracker::class)]
#[CoversClass(SpmTracker::class)]
#[Small]
class ActionRateTest extends TestCase {
	public function test_apm_counts_commands_retargets_selections_and_control_groups_in_real_time(): void {
		$script = new EventScript(new ReplayBuilder()->player('Pia', Race::Protoss)->observer('Obs')->speed(GameSpeed::Faster)->gameloops(4_480)->build());
		$script->at(10)->command('Pia', 'Stimpack')->command('Pia', null)->retarget('Pia', new Point(1, 1));
		$script->at(1_400)->selection('Pia')->controlGroup('Pia');   // 1400 gameloops: 62.5 s at Faster
		$script->at(1_500)->camera('Pia', new Point(1, 1))->chat('Pia', 'hi');

		$apm = $script->simulate(new ApmTracker())->result(ApmTracker::class);
		$pia = $apm?->for($script->replay->users[0]);
		$this->assertSame(array(5, array(3, 2, 0, 0)), array($pia?->total, $pia?->perMinute));
		$this->assertEqualsWithDelta(5 / 200 * 60, $pia?->average ?? 0, 1e-9, '200 s played');
		$this->assertSame(0, $apm?->for($script->replay->users[1])?->total, 'observers get a rate too');
	}

	public function test_spm_counts_from_the_last_counted_screen(): void {
		$script = new EventScript(new ReplayBuilder()->player('Pia')->build());
		$script->at(1)->camera('Pia', new Point(50, 50));       // the initial placement: only the anchor
		$script->at(2)->camera('Pia', new Point(55, 50));       // scrolling, 5 cells
		$script->at(3)->camera('Pia', new Point(60, 50));       // 10 cells
		$script->at(4)->camera('Pia', new Point(65, 50));       // 15 cells from the anchor: a screen
		$script->at(5)->camera('Pia', null);                    // no target: skipped
		$script->at(6)->camera('Pia', new Point(70, 50));       // 5 cells from the new anchor
		$script->at(7)->camera('Pia', new Point(150, 150));     // a jump: a screen

		$this->assertSame(2, $script->simulate(new SpmTracker())->result(SpmTracker::class)?->for($script->replay->users[0])?->total);
	}

	public function test_time_played_ends_with_the_leave_but_not_after_the_end(): void {
		$script = new EventScript(new ReplayBuilder()->player('Pia')->player('Pete')->gameloops(2_240)->build());
		$script->at(10)->command('Pia', 'Stimpack')->command('Pete', 'Stimpack');
		$script->at(1_120)->leave('Pia');
		$script->at(2_300)->leave('Pete');   // the leave event's gameloop is sometimes after the end

		$apm = $script->simulate(new ApmTracker())->result(ApmTracker::class);
		$this->assertSame(array(50.0, 100.0), array($apm?->for($script->replay->users[0])?->secondsPlayed, $apm?->for($script->replay->users[1])?->secondsPlayed));
	}
}
