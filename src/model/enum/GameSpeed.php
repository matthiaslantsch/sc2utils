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
 * Values are sc2reader's strings.
 */
enum GameSpeed: string {
	case Fast = 'Fast';
	case Faster = 'Faster';
	case Normal = 'Normal';
	case Slow = 'Slow';
	case Slower = 'Slower';

	/**
	 * The speed as numbered in replay.details (m_gameSpeed) and replay.info, 0 = Slower.
	 */
	public static function fromIndex(int $index): ?self {
		return match ($index) {
			0 => self::Slower,
			1 => self::Slow,
			2 => self::Normal,
			3 => self::Fast,
			4 => self::Faster,
			default => null,
		};
	}

	public function index(): int {
		return match ($this) {
			self::Slower => 0,
			self::Slow => 1,
			self::Normal => 2,
			self::Fast => 3,
			self::Faster => 4,
		};
	}
}
