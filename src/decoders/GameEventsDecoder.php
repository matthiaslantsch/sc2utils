<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\decoders;

/**
 * The GameEventsDecoder class is used to decode the replay.game.events file contained in the replay archive.
 *
 * @author  matthias.lantsch
 */
class GameEventsDecoder extends DecoderBase {
	/**
	 * decode the replay.game.events file contained in the replay
	 * saves the data directly into the replay object.
	 */
	protected function doDecode(): void {
		$this->replay->rawdata['game.events'] = $this->binaryFormatParse('gameevents');
	}
}
