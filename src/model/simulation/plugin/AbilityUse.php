<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\model\simulation\plugin;

/**
 * How often a user used one command of an ability, and when first.
 */
class AbilityUse {
	/**
	 * @param string|null $ability Blizzard's ability id, e.g. "PsiStorm"; null if the build's table doesn't know the link
	 * @param int $link the build's link number
	 * @param int $commandIndex the command within the ability (0 cloak / 1 decloak, one per trainable unit)
	 * @param int $firstUse gameloop
	 */
	public function __construct(
		public ?string $ability,
		public int $link,
		public int $commandIndex,
		public int $count,
		public int $firstUse,
	) {}

	/**
	 * The key of a command: "PsiStorm#0", or for a link the table doesn't know "?178#0".
	 */
	public static function key(?string $ability, int $link, int $commandIndex): string {
		$prefix = ($ability ?? "?{$link}");

		return "{$prefix}#{$commandIndex}";
	}
}
