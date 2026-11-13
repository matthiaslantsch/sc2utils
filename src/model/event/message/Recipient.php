<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\model\event\message;

/**
 * Who a chat message or ping went to (m_recipient). 1 = allies is confirmed by every ping (pings go to allies), 4 =
 * observers by an observer's replay (same_match/1v1_observer/player2). sc2reader reads allies as 2.
 */
enum Recipient: int {
	case All = 0;
	case Allies = 1;
	case Observers = 4;
	case Unknown = -1;

	public static function fromRaw(int $value): self {
		return self::tryFrom($value) ?? self::Unknown;
	}
}
