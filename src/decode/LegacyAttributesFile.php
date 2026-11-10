<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\decode;

/**
 * replay.attributes.events before build 17326: no source byte.
 */
final class LegacyAttributesFile extends AttributesFile {
	protected const bool HAS_SOURCE = false;
}
