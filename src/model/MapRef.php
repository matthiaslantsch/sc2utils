<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\model;

/**
 * The map a replay was played on, as the replay names it: its title and its cache handle on the Battle.net depot.
 */
final class MapRef {
	private const string DEPOT_URL = 'https://%s-s2-depot.classic.blizzard.com/%s.%s';

	private const string DEPOT_URL_CN = 'https://cn-s2-depot.necdn.leihuo.netease.com/%s.%s';

	public ?string $depotUrl {
		get => match ($this->depotRegion) {
			'us', 'eu', 'kr' => sprintf(self::DEPOT_URL, $this->depotRegion, $this->hash, $this->type),
			'sea' => sprintf(self::DEPOT_URL, 'us', $this->hash, $this->type),
			'cn' => sprintf(self::DEPOT_URL_CN, $this->hash, $this->type),
			default => null,
		};
	}

	/**
	 * @param string $hash hex SHA-256 of the map file, which is also its name on the depot
	 * @param string $depotRegion the depot the cache handle names, lowercase (us, eu, kr, cn, sea, but also xx or bn)
	 * @param string $type the file type, s2ma for maps
	 */
	public function __construct(
		public readonly string $name,
		public readonly string $hash,
		public readonly string $depotRegion,
		public readonly string $type = 's2ma',
	) {}
}
