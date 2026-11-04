<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch
 *
 * THIS FILE WAS AUTOMATICALLY GENERATED
 * class file for the Version{{VERSION}} class
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\format;

/**
 * The Version{{VERSION}} class links to version "{{VERSION}}" file formats
 */
class Version{{VERSION}} extends Version {

	/**
	 * @var array FORMAT_FILES Mapping of subfile names to binary format files
	 */
	public const array FORMAT_FILES = array(
		{{MAPPING}}
	);

	public function version(): int {
		return {{VERSION}};
	}

}
