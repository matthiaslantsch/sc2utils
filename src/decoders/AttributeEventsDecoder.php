<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\decoders;

use holonet\sc2utils\data\DataLoader;
use holonet\sc2utils\ParserException;

/**
 * The AttributeEventsDecoder class is used to decode the replay.attributes.events file contained in the replay archive.
 */
class AttributeEventsDecoder extends DecoderBase {
	/**
	 * decode the replay.attributes.events file contained in the replay.
	 */
	protected function doDecode(): void {
		$attributeLookup = DataLoader::loadDataset('attributes', $this->replay->baseBuild);

		$rawattrs = $this->binaryFormatParse('attributeevents');

		$attributes = array();
		foreach ($rawattrs['attributes'] as $attr) {
			$attr['value'] = trim(strrev($attr['value']));
			if (isset($attributeLookup[$attr['attrId']])) {
				$lookup = $attributeLookup[$attr['attrId']];
				$attr['name'] = $lookup[0];
				$attr['value'] = $lookup[1][$attr['value']];
			} else {
				continue;

				throw new ParserException("Unknown attribute id '{$attr['attrId']}'", 100);
			}

			// save it in our temporary array
			$attributes[$attr['scope']][$attr['name']] = $attr['value'];
		}

		$this->replay->attributes = $attributes;
		$this->replay->gamespeed = $attributes[16]['Game Speed'];
		$this->replay->category = $attributes[16]['Game Mode'];
		$this->replay->gametype = $attributes[16]['Teams'];
		if (!isset($attributes[16]['Game Privacy'])) {
			$attributes[16]['Game Privacy'] = 'Normal';
		}
		$this->replay->privacy = $attributes[16]['Game Privacy'];
	}
}
