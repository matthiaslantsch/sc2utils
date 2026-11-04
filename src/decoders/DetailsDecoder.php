<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\decoders;

use holonet\sc2utils\objects\Sc2CacheHandle;
use holonet\sc2utils\utils;

/**
 * The DetailsDecoder class is used to decode the replay.details subfile in a replay file.
 */
class DetailsDecoder extends DecoderBase {
	/**
	 * actually decode the details file
	 * saves the information in the contained replay object.
	 */
	protected function doDecode(): void {
		$details = $this->binaryFormatParse('details');

		exit(var_dump($details['cacheHandles']));
		foreach ($details['cacheHandles'] as $i => $handle) {
			$details['cacheHandles'][$i] = new Sc2CacheHandle($handle);
		}

		$mapCache = end($details['cacheHandles']);
		$this->replay->mapHash = $mapCache->hash;
		$this->replay->mapUrl = $mapCache->url;

		foreach ($details['playerList'] as $pl) {
			if ($pl['control'] === 2) {
				// is a player and can be used to figure out the region
				$this->replay->region = utils\gatewayLookup($pl['toon']['region']);
			}

			$details['players'][] = $pl;
		}

		$this->replay->rawdata['details'] = $details;
		$this->replay->mapName = $details['mapFileName'];
		$this->replay->ntTimestamp = $details['timeUTC'];

		// The utc_adjustment is either the adjusted windows timestamp OR
		// the value required to get the adjusted timestamp. We know the upper
		// limit for any adjustment number so use that to distinguish between the two cases.
		$maxAdjustment = 10 ** 7 * 60 * 60 * 24;
		if ($details['timeLocalOffset'] < $maxAdjustment) {
			$this->replay->timezone = $details['timeLocalOffset'] / 10 ** 7 * 60 * 60;
		} else {
			$this->replay->timezone = ($details['timeLocalOffset'] - $details['timeUTC']) / 10 ** 7 * 60 * 60;
		}

		// This windows timestamp measures the number of 100 nanosecond periods since
		// January 1st, 1601.
		$this->replay->endTimestamp = (int)($details['timeUTC'] / 10_000_000 - 11_644_473_600);

		$this->replay->reallength = utils\loopsToRealTime($this->replay->gameloops, $this->replay->gamespeed);
		$this->replay->gamelength = utils\createTimeString($this->replay->reallength);
		$this->replay->startTimestamp = $this->replay->endTimestamp - $this->replay->reallength;
	}
}
