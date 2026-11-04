<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\objects;

class Player extends Entity {
	/**
	 * property containing the color the player used as an array with rbga values.
	 * @var array $color Array with rgba values with r, g, b and a as keys
	 */
	public array $color;

	/**
	 * @var string $difficulty difficulty setting for this player (always Medium for real players)
	 */
	public string $difficulty;

	/**
	 * @var bool $isComputer Boolean determing wheter this player is an AI computer or not
	 */
	public bool $isComputer;

	/**
	 * one of the following: Protoss, Zerg, Terran, Random.
	 * @var string $pickRace The race the player picked prior to the game start
	 */
	public string $pickRace;

	/**
	 * one of the following: Protoss, Zerg, Terran.
	 * @var string $pickRace The race the player played in the game
	 */
	public string $playRace;

	/**
	 * one of the following: Win, Loss, Unknown.
	 * @var string $result Outcome of the map for this specific player
	 */
	public string $result = 'Unknown';
}
