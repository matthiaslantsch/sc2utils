<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * class file for the Replay ressource object class
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\resources;

use holonet\sc2utils\format\Version;
use holonet\sc2utils\ParserException;

/**
 * The Replay class represents a parsed replay file and contains it's data,
 * raw and ordered.
 *
 * @author  matthias.lantsch
 */
class Replay {
	/**
	 * property containing the base build number for the game (used for version specific code).
	 *
	 * @var int $baseBuild Base build number
	 */
	public int $baseBuild;

	/**
	 * property containing the game expansion as a string
	 * one of [WoL Beta, WoL, HotS Beta, HotS, LotV Beta, LotV].
	 *
	 * @var string $expansion Expansion string determing the game version
	 */
	public string $expansion;

	/**
	 * property containing the number of ingame engine game loops in the played game.
	 *
	 * @var int $gameloops Gameloop number for the entire game
	 */
	public int $gameloops;

	/**
	 * property containing an integer marking how far this replay has been parsed yet.
	 *
	 * @var int $loadLevel Integer with the load level
	 */
	public int $loadLevel;

	/**
	 * property containing arrays of raw data, as parsed from the replay subfiles.
	 *
	 * @var array $rawdata Array with rawdata
	 */
	public array $rawdata = array();

	/**
	 * property containing the full game version string for the current replay.
	 *
	 * @var string $version Full game version string
	 */
	public string $version;

	/**
	 * constructor initialising the replay object with the inital data from the header.
	 *
	 * @param int $baseBuild The base build number of the game at that point
	 * @param string $versionString A string identifying the version and build of the game in detail
	 * @param int $gameloops The counter of ingame engine game loops inside the replay
	 * @param string $expansion Expansion string determing the game version
	 */
	public function __construct(int $baseBuild, string $versionString, int $gameloops, string $expansion) {
		$this->baseBuild = $baseBuild;
		$this->version = $versionString;
		$this->gameloops = $gameloops;
		$this->expansion = $expansion;
		$this->loadLevel = 1;
	}

	/**
	 * getter method for an array of events.
	 *
	 * @param string $type String specifying the kind of events requested
	 * @return array with all the events of the requested type in it
	 */
	public function eventArray(string $type): array {
		if ($this->loadLevel < 3) {
			// not all event files have been parsed yet
			// check if the requested type has been parsed
			if (!isset($this->rawdata["{$type}.events"])) {
				throw new ParserException('Cannot list events without parsing event files first (load level 3 => decode)', 100);
			}
		}

		switch ($type) {
			case 'message':
				$events = $this->rawdata['message.events'];

				break;
			case 'game':
				$events = $this->rawdata['game.events'];

				break;
			default:
				throw new ParserException("Cannot list events for unknown event type: '{$type}'", 100);
		}

		$ret = array();
		foreach ($events as $ev) {
			$ret[$ev['gameloop']][] = $ev;
		}

		return $ret;
	}

	/**
	 * getter method for the version class
	 * returns either the matching version or the last older one that exists
	 * so we can still attempt to parse unsupported replays with older versions.
	 *
	 * @return string with the version class for the given version
	 */
	public function versionClass(): string {
		$actualClass = "holonet\\Sc2repParser\\format\\Version{$this->baseBuild}";
		if (class_exists($actualClass)) {
			return $actualClass;
		}

		return Version::previous($this->baseBuild);
	}
}
