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
 * Values are sc2reader's strings. A player without a decided result has none (null).
 */
enum Result: string {
	case Loss = 'Loss';

	/**
	 * Teams only: the players of the team have different results.
	 */
	case Unknown = 'Unknown';

	case Win = 'Win';

	/**
	 * The result of replay.details' m_result, null for 0 (undecided) and 3 (tie), as in sc2reader.
	 */
	public static function fromDetails(int $result): ?self {
		return match ($result) {
			1 => self::Win,
			2 => self::Loss,
			default => null,
		};
	}
}
