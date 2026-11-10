<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\model\enum;

/**
 * Values are sc2reader's strings. Games are played as Protoss, Terran or Zerg; the others occur as lobby picks.
 */
enum Race: string {
	case InfestedTerran = 'Infested Terran';
	case PrimalZerg = 'Primal Zerg';
	case Protoss = 'Protoss';
	case Random = 'Random';
	case Terran = 'Terran';
	case TerranHorner = 'Terran Horner';
	case TerranTychus = 'Terran Tychus';
	case Zerg = 'Zerg';

	/**
	 * Race names as replay.details has them, in the game's language (sc2reader's LOCALIZED_RACES).
	 */
	private const array LOCALISED = array(
		'Терран' => 'Terran', 'Протосс' => 'Protoss', 'Зерг' => 'Zerg',
		'테란' => 'Terran', '프로토스' => 'Protoss', '저그' => 'Zerg',
		'Terranie' => 'Terran', 'Protosi' => 'Protoss', 'Zergi' => 'Zerg',
		'人类' => 'Terran', '星灵' => 'Protoss', '异虫' => 'Zerg',
		'人類' => 'Terran', '神族' => 'Protoss', '蟲族' => 'Zerg',
		'Terrano' => 'Terran', 'Terraner' => 'Terran',
	);

	/**
	 * The race of a name from replay.details, null for a name nobody knows.
	 */
	public static function fromLocalised(string $name): ?self {
		return self::tryFrom(self::LOCALISED[$name] ?? $name);
	}

	/**
	 * The letter in team lineups like "PZ".
	 */
	public function letter(): string {
		return $this->value[0];
	}
}
