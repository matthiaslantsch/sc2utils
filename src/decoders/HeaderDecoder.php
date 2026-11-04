<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\decoders;

use holonet\bitstream\format\BinaryFormatParser;
use holonet\sc2utils\format\VersionBase;

/**
 * The HeaderDecoder class is used to decode the header in an mpq replay archive file:
 *  - game version
 *  - game loops counter
 */
class HeaderDecoder extends DecoderBase {
	/**
	 * actually decode the header
	 * usually decoded data is directly saved, not here since the replay object doesn't exist yet.
	 *
	 * @return array with decoded header data
	 */
	protected function doDecode(): array {
		$ret = array();

		// try to decode with the new packed header format
		$formatTree = VersionBase::getFileFormat('header');
		$parser = new BinaryFormatParser($this->stream, $formatTree);
		$data = $parser->parse();

		if (isset($data['signature'])) {
			$data['versionString'] = sprintf(
				'%d.%d.%d.%d', // major.minor.fix.build
				$data['version']['major'], // major version number
				$data['version']['minor'],// minor version number
				$data['version']['revision'],// fix version number
				$data['version']['build'] // the build
			);
		} else {
			// must be an older replay, try again with the old parser
			$this->stream->rewind();
			$formatTree = VersionBase::getFileFormat('oldheader');
			$parser = new BinaryFormatParser($this->stream, $formatTree);
			$data = $parser->parse();
		}

		if ($data['version']['baseBuild'] < 16_195) {
			$data['expansion'] = 'WoL Beta';
		} elseif ($data['version']['baseBuild'] <= 25_092) {
			$data['expansion'] = 'WoL';
		} elseif ($data['version']['baseBuild'] <= 25_180) {
			$data['expansion'] = 'HotS Beta';
		} elseif ($data['version']['baseBuild'] <= 38_749) {
			$data['expansion'] = 'HotS';
		} elseif ($data['version']['baseBuild'] <= 38_996) {
			$data['expansion'] = 'LotV Beta';
		} else {
			$data['expansion'] = 'LotV';
		}

		return $data;
	}
}
