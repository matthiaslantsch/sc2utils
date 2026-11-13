<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\model\event\message;

use holonet\sc2utils\model\event\Event;
use holonet\sc2utils\model\replay\User;

/**
 * An event of replay.message.events (chat, pings, loading progress). A replay has only the messages its recorder
 * received.
 */
interface MessageEvent extends Event {
	public ?User $user { get; }
}
