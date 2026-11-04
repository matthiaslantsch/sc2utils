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
 * The InitdataDecoder class is used to decode the "initdata" file contained within the replay archive
 * information on the format can be found here: https://github.com/GraylinKim/sc2reader/wiki/replay.initData.
 *
 * @author  matthias.lantsch
 */
class InitdataDecoder extends DecoderBase {
	/**
	 * actually decode the initdata file
	 * saves decoded data directly in the replay object.
	 */
	protected function doDecode(): void {
		$data = $this->binaryFormatParse('initdata')['syncLobbyState'];

		$this->replay->rawdata['initdata'] = $data;

		$options = $data['gameDescription']['gameOptions'];
		$this->replay->amm = $options['amm'];
		$this->replay->ranked = $options['ranked'] ?? false;
		$this->replay->competitive = $options['competitive'] ?? false;
		$this->replay->practice = $options['practice'] ?? false;
		$this->replay->cooperative = $options['cooperative'] ?? false;
		$this->replay->battlenet = $options['battleNet'];

	}
}
