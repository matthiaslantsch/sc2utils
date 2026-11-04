<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\objects;

class Sc2CacheHandle {
	/**
	 * @var string $extension file extension of the resource
	 */
	public string $extension;

	/**
	 * @var string $hash Battle.net resource hash of the resource.
	 */
	public string $hash;

	/**
	 * @var string $region Battle.net region identifier the resource is registered on.
	 */
	public string $region;

	/**
	 * @var string $url Battle.net url the resource can be found at.
	 */
	public string $url;

	/**
	 * @param string $bytes Binary string with the cache handle
	 */
	public function __construct(string $bytes) {
		$this->extension = substr($bytes, 0, 4);
		$this->region = trim(substr($bytes, 4, 4));
		// There is no SEA server, use US instead
		if ($this->region === 'SEA') {
			$this->region = 'US';
		}

		$this->hash = bin2hex(substr($bytes, 8));
		$this->url = sprintf('http://%s.depot.battle.net:1119/%s.%s', $this->region, $this->hash, $this->extension);
	}
}
