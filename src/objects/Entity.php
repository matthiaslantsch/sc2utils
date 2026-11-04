<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\objects;

/**
 * the abstract Entity base class is the base class for any user in the replay
 * meaning it can be any of: observer, referee, player, computer.
 */
class Entity {
	/**
	 * @var int $archonLeaderId Player id of the archon team leader
	 */
	public int $archonLeaderId;

	/**
	 * @var string $bnetId Battle.net id identifier
	 */
	public string $bnetId;

	/**
	 * @var string $clanTag Clan tag for this entity
	 */
	public string $clanTag;

	/**
	 * @var int $combinedRaceLevels Player's combined level across all races
	 */
	public int $combinedRaceLevels;

	/**
	 * @var int $handicap Integer indicating how much handicap this entity has, ranges from 50-100
	 */
	public int $handicap;

	/**
	 * @var string $highestLeague Player's highest league archieved this season
	 */
	public string $highestLeague;

	/**
	 * @var string $name Battle.net name for this entity
	 */
	public string $name;

	/**
	 * @var int $playerId Integer containing the player id of this entity
	 */
	public int $playerId;

	/**
	 * @var string $region Battle.net region identifier
	 */
	public string $region;

	/**
	 * @var string $subregion Battle.net subregion identifier
	 */
	public string $subregion;

	/**
	 * @var int $teamId The id of the team the entity is on (null for observers)
	 */
	public int $teamId;

	/**
	 * @param int $pid Player id for this entity
	 * @param string $name The name for this entity
	 */
	public function __construct(int $pid, string $name) {
		$this->playerId = $pid;
		$this->name = $name;
	}

	/**
	 * @return string with the battle.net url or false if not applicable (e.g. battle.net 1 entity)
	 */
	public function bnetUrl(): string {
		if (isset($this->realId) || $this->region === false || $this->subregion === false || $this->bnetId === false || $this->name === false) {
			return false;
		}

		return sprintf('http://%s.battle.net/sc2/en/profile/%d/%d/%s/', $this->region, $this->bnetId, $this->subregion, $this->name);
	}
}
