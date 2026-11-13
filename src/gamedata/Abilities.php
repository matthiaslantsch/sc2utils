<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\gamedata;

use holonet\sc2utils\model\enum\Expansion;
use InvalidArgumentException;

/**
 * Ability link numbers (a command's m_abilLink) to Blizzard's ability ids, per build (data/abilities/, generated from
 * sc2reader's <expansion>/<version>_abilities.csv).
 * The numbers shift every few patches, so every replay needs the table of its build.
 */
final class Abilities {
	private const array DATAPACKS = array(
		array('WoL', 16_117, 17_326, '16117', 'WoL_16117'),
		array('WoL', 17_326, 18_092, '17326', 'WoL_17326'),
		array('WoL', 18_092, 19_458, '18092', 'WoL_18092'),
		array('WoL', 19_458, 22_612, '19458', 'WoL_19458'),
		array('WoL', 22_612, 24_944, '22612', 'WoL_22612'),
		array('WoL', 24_944, null, '24944', 'WoL_24944'),
		array('HotS', null, 23_925, 'base', 'HotS_base'),
		array('HotS', 23_925, 24_247, '23925', 'HotS_23925'),
		array('HotS', 24_247, 24_764, '24247', 'HotS_24247'),
		array('HotS', 24_764, 38_215, '24764', 'HotS_24764'),
		array('HotS', 38_215, null, '38215', 'LotV_base'),
		array('LotV', 34_784, null, 'base', 'LotV_base'),
		array('LotV', 44_401, 47_185, '44401', 'LotV_44401'),
		array('LotV', 47_185, 48_258, '47185', 'LotV_47185'),
		array('LotV', 48_258, 53_644, '48258', 'LotV_48258'),
		array('LotV', 53_644, 54_724, '53644', 'LotV_53644'),
		array('LotV', 54_724, 59_587, '54724', 'LotV_54724'),
		array('LotV', 59_587, 70_154, '59587', 'LotV_59587'),
		array('LotV', 70_154, 76_114, '70154', 'LotV_70154'),
		array('LotV', 76_114, 77_379, '76114', 'LotV_76114'),
		array('LotV', 77_379, 80_949, '77379', 'LotV_77379'),
		array('LotV', 80_949, 89_634, '80949', 'LotV_80949'),
		array('LotV', 89_634, 95_122, '89720', 'LotV_89720'),
		array('LotV', 95_122, 97_364, '96883', 'LotV_96883'),
		array('LotV', 97_364, null, '97364', 'LotV_97364'),
	);

	/**
	 * @var array<string, array<int, string>>
	 */
	private static array $tables = array();

	/**
	 * Blizzard's ability id of a link number in a table, e.g. "PsiStorm"; null if the table doesn't have it.
	 */
	public static function id(string $table, int $link): ?string {
		return self::links($table)[$link] ?? null;
	}

	/**
	 * The whole table: link number => Blizzard's ability id.
	 *
	 * @return array<int, string>
	 */
	public static function links(string $table): array {
		if (!isset(self::$tables[$table])) {
			foreach (self::DATAPACKS as list($expansion, , , $id, $file)) {
				if ("{$expansion}/{$id}" === $table) {
					/** @var array<int, string> $links */
					$links = require __DIR__."/data/abilities/{$file}.php";
					self::$tables[$table] = $links;

					return $links;
				}
			}

			throw new InvalidArgumentException("Unknown ability table {$table}");
		}

		return self::$tables[$table];
	}

	/**
	 * The table of a replay as sc2reader picks it, named "<expansion>/<sc2reader's datapack id>", e.g. "LotV/97364",
	 * "HotS/38215"; null where sc2reader has none (no expansion, WoL before 16117).
	 */
	public static function table(?Expansion $expansion, int $build): ?string {
		foreach (array_reverse(self::DATAPACKS) as list($forExpansion, $from, $until, $id)) {
			if ($expansion?->value === $forExpansion && ($from === null || $build >= $from) && ($until === null || $build < $until)) {
				return "{$forExpansion}/{$id}";
			}
		}

		return null;
	}
}
