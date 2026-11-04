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
 * The MessageEventsDecoder class is used to decode the replay.message.events file contained in the replay archive.
 *
 * @author  matthias.lantsch
 */
class MessageEventsDecoder extends DecoderBase {
	/**
	 * decode the replay.message.events file contained in the replay
	 * saves the raw events right into the replay.
	 */
	protected function doDecode(): void {
		$this->replay->rawdata['message.events'] = $this->binaryFormatParse('messageevents');
	}
}
