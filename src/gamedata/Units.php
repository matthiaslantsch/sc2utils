<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\gamedata;

use holonet\sc2utils\model\enum\Race;

/**
 * Unit types by Blizzard's unit id (data/units.php, generated from sc2reader), and how type changes relate.
 *
 * A unit's type history (tracker events) mixes three kinds of changes:
 * - modes: the same unit, reversible, nothing produced (burrow, lift-off, siege, lowered depots, add-ons renamed
 *   when their building lifts or lands, Gateway/WarpGate). MODES maps each variant to its base type;
 * - morphs: the same unit becomes another type, one way, usually through a cocoon (MORPHS);
 * - a cocoon going back to a type that morphs into it: a cancelled morph, or for banelings the hatch, where the
 *   cocoon turns back into a Zergling and dies while the Baneling is born as a new unit (COCOONS).
 */
final class Units {
	/**
	 * The intermediate types of morphs.
	 */
	public const array COCOONS = array('BanelingCocoon', 'BroodLordCocoon', 'LurkerMPEgg', 'OverlordCocoon', 'RavagerCocoon', 'TransportOverlordCocoon');

	/**
	 * Mode variant => base type.
	 */
	public const array MODES = array(
		// burrow
		'BanelingBurrowed' => 'Baneling',
		'CreepTumorBurrowed' => 'CreepTumor',
		'CreepTumorQueen' => 'CreepTumor',
		'DroneBurrowed' => 'Drone',
		'HunterKillerBurrowed' => 'HunterKiller',
		'HydraliskBurrowed' => 'Hydralisk',
		'InfestorBurrowed' => 'Infestor',
		'InfestorTerranBurrowed' => 'InfestorTerran',
		'LurkerMPBurrowed' => 'LurkerMP',
		'QueenBurrowed' => 'Queen',
		'RavagerBurrowed' => 'Ravager',
		'RoachBurrowed' => 'Roach',
		'SwarmHostBurrowedMP' => 'SwarmHostMP',
		'UltraliskBurrowed' => 'Ultralisk',
		'WidowMineBurrowed' => 'WidowMine',
		'ZerglingBurrowed' => 'Zergling',
		// lift-off
		'BarracksFlying' => 'Barracks',
		'CommandCenterFlying' => 'CommandCenter',
		'FactoryFlying' => 'Factory',
		'OrbitalCommandFlying' => 'OrbitalCommand',
		'StarportFlying' => 'Starport',
		// add-ons: renamed when their building lifts off or lands on them
		'BarracksReactor' => 'Reactor',
		'BarracksTechLab' => 'TechLab',
		'FactoryReactor' => 'Reactor',
		'FactoryTechLab' => 'TechLab',
		'StarportReactor' => 'Reactor',
		'StarportTechLab' => 'TechLab',
		// changeling disguises
		'ChangelingMarine' => 'Changeling',
		'ChangelingMarineShield' => 'Changeling',
		'ChangelingZealot' => 'Changeling',
		'ChangelingZergling' => 'Changeling',
		'ChangelingZerglingWings' => 'Changeling',
		// other modes
		'ArchAngelCoopAssault' => 'ArchAngelCoopFighter',
		'Egg' => 'Larva',
		'HellionTank' => 'Hellion',
		'LiberatorAG' => 'Liberator',
		'LocustMPFlying' => 'LocustMP',
		'ObserverSiegeMode' => 'Observer',
		'OverseerSiegeMode' => 'Overseer',
		'PylonOvercharged' => 'Pylon',
		'SiegeTankSieged' => 'SiegeTank',
		'SpineCrawlerUprooted' => 'SpineCrawler',
		'SporeCrawlerUprooted' => 'SporeCrawler',
		'SupplyDepotLowered' => 'SupplyDepot',
		'ThorAP' => 'Thor',
		'VikingAssault' => 'VikingFighter',
		'WarpGate' => 'Gateway',
		'WarpPrismPhasing' => 'WarpPrism',
	);

	/**
	 * Morphs: from => list of types it can become.
	 */
	public const array MORPHS = array(
		'BanelingCocoon' => array('Baneling'),
		'BroodLordCocoon' => array('BroodLord'),
		'CommandCenter' => array('OrbitalCommand', 'PlanetaryFortress'),
		'Corruptor' => array('BroodLordCocoon'),
		'Hatchery' => array('Lair'),
		'Hydralisk' => array('LurkerMPEgg'),
		'HotSSwarmling' => array('BanelingCocoon'),
		'HydraliskDen' => array('LurkerDenMP'),
		'InfestedTerransEgg' => array('InfestorTerran'),
		'Lair' => array('Hive'),
		'LurkerMPEgg' => array('LurkerMP'),
		'Overlord' => array('OverlordCocoon', 'TransportOverlordCocoon'),
		'OverlordCocoon' => array('Overseer'),
		'OverlordTransport' => array('OverlordCocoon'),
		'RavagerCocoon' => array('Ravager'),
		'Roach' => array('RavagerCocoon'),
		'Spire' => array('GreaterSpire'),
		'TransportOverlordCocoon' => array('OverlordTransport'),
		'Zergling' => array('BanelingCocoon'),
	);

	/**
	 * Types that look temporary (deaths mostly at one fixed lifetime or by their owner) but aren't, with the reason.
	 */
	public const array NOT_TEMPORARY = array(
		'Baneling' => 'detonations count as kills by the owner',
		'HotSSplitterlingBig' => 'co-op: trained, detonates like a Baneling',
		'MULE' => 'timed, but losing one costs resources',
	);

	/**
	 * Temporary units (base types): spawned by another unit or an ability (their creator, from 54724), effect
	 * dummies, and production intermediates. They never count as kills or losses; what they kill counts for the
	 * unit that spawned them (Unit::creditHolder()). Hand-written from the test replays and the archive (types whose
	 * deaths are mostly at one fixed lifetime or by their owner; UnitsTest flags new candidates). MULEs are timed but
	 * not here: losing one costs resources.
	 */
	public const array TEMPORARY = array(
		// spawned fighters, their kills go to the creator
		'AutoTurret', 'Broodling', 'BroodlingEscort', 'InfestedTerransEgg', 'InfestorTerran', 'Interceptor', 'LocustMP',
		'LocustMPPrecursor', 'ParasiticBombDummy', 'ParasiticBombRelayDummy',
		// effects and dummies
		'AdeptPhaseShift', 'Changeling', 'DisruptorPhased', 'InvisibleTargetDummy', 'KD8Charge', 'Nuke', 'OracleStasisTrap',
		// production intermediates
		'Egg', 'Larva',
		// co-op
		'HotSSplitterlingMedium', 'HunterKiller', 'VorazunShadowGuard',
	);

	/**
	 * @var array<string, array{string, string|null, bool|null, bool|null, bool|null, int|null, int|null, float|null}>|null
	 */
	private static ?array $table = null;

	/**
	 * The base type of a mode variant ("SiegeTankSieged" => "SiegeTank"), the id itself otherwise.
	 */
	public static function base(string $id): string {
		return self::MODES[$id] ?? $id;
	}

	/**
	 * How a type change relates the two types: "mode" (same base type), "morph", "back" (a cocoon to a type that
	 * morphs into it), or null if this class doesn't know the change.
	 */
	public static function change(string $from, string $to): ?string {
		return match (true) {
			self::base($from) === self::base($to) => 'mode',
			in_array($to, self::MORPHS[$from] ?? array(), true) => 'morph',
			in_array($from, self::COCOONS, true) && in_array($from, self::MORPHS[self::base($to)] ?? array(), true) => 'back',
			default => null,
		};
	}

	public static function type(string $id): UnitType {
		self::$table ??= self::load();
		$entry = self::$table[$id] ?? null;
		$temporary = in_array(self::base($id), self::TEMPORARY, true);
		if ($entry === null) {
			return new UnitType($id, $id, isTemporary: $temporary);
		}
		list($name, $race, $isArmy, $isWorker, $isBuilding, $minerals, $vespene, $supply) = $entry;

		return new UnitType($id, $name, $race === null ? null : Race::from($race), $isArmy ?? false, $isWorker ?? false, $isBuilding ?? false, $minerals ?? 0, $vespene ?? 0, $supply ?? 0.0, $temporary);
	}

	/**
	 * @return array<string, array{string, string|null, bool|null, bool|null, bool|null, int|null, int|null, float|null}>
	 */
	private static function load(): array {
		/** @var array<string, array{string, string|null, bool|null, bool|null, bool|null, int|null, int|null, float|null}> */
		return require __DIR__.'/data/units.php';
	}
}
