<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils;

use RuntimeException;

class ParserException extends RuntimeException {
	public function __construct(string $msg, int $code) {
		parent::__construct($msg, $code);
	}
}
