<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\gamedata;

use holonet\sc2utils\decode\AttributeEntry;

/**
 * Names and display values of the lobby attributes (data/attributes.php, generated from sc2reader).
 */
final class Attributes {
	/**
	 * @var array<int, array{string, array<string, string>}>|null
	 */
	private static ?array $table = null;

	/**
	 * The attributes by scope and name, as sc2reader's `replay.attributes`: entries in file order, a later entry
	 * with the same name overwrites an earlier one (attributes 2000 and 2001 are both called "Teams"), and a value
	 * missing from the table becomes null. Unknown attribute ids are left out.
	 *
	 * @param list<AttributeEntry> $entries
	 *
	 * @return array<int, array<string, string|null>> scope => name => display value
	 */
	public static function resolve(array $entries): array {
		$table = self::table();
		$ret = array();
		foreach ($entries as $entry) {
			if (!isset($table[$entry->id])) {
				continue;
			}
			list($name, $values) = $table[$entry->id];
			$ret[$entry->scope][$name] = $values[trim($entry->value, "\0 ")] ?? null;
		}

		return $ret;
	}

	/**
	 * @return array<int, array{string, array<string, string>}> attribute id => [name, value => display value]
	 */
	public static function table(): array {
		if (self::$table === null) {
			/** @var array<int, array{string, array<string, string>}> $table */
			$table = require __DIR__.'/data/attributes.php';
			self::$table = $table;
		}

		return self::$table;
	}
}
