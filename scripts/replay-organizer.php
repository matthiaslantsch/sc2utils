<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * This script is a utility script that makes use of the sc2rep_parse library to parse sc2 replay files.
 * It can be used to organize replays by different criteria (even in subfolders)
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\scripts;

use Exception;
use holonet\sc2utils\objects\Player;
use holonet\sc2utils\ReplayParser;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

// procedural entry point
if (in_array('help', $argv) || in_array('--help', $argv)) {
	usage();
}

include_once dirname(__DIR__).\DIRECTORY_SEPARATOR.'vendor'.\DIRECTORY_SEPARATOR.'autoload.php';

new SC2ReplayOrganizer();

/**
 * Logic class used to wrap all the organizer functions to be able to work from an object context.
 *
 * @author  matthias.lantsch
 */
class SC2ReplayOrganizer {
	/**
	 * property containing an array with config options for the script
	 * this can be used to hardcode default values.
	 *
	 * @var array $config Associative array with config options
	 */
	public array $config = array(
		'dateformat' => 'Y-m-d H:i:s',
		'playerformat' => '{clantag}{name} ({race})'
	);

	/**
	 * constructor method overseeing the entire script process.
	 */
	public function __construct() {
		$this->constructConfig();
		$this->checkTokens();

		echo "Targeting {$this->config['target']}\n";
		$processed = 0;
		if (is_file($this->config['target'])) {
			$file = $this->config['target'];
			$this->config['target'] = dirname($this->config['target']);
			$this->processReplay($file);
			$processed++;
		} else {
			$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->config['target']));
			foreach ($rii as $file) {
				if (is_file($file) && (str_contains($file, '.sc2replay') || str_contains($file, '.SC2Replay'))) {
					$this->processReplay($file);
					$processed++;
				}
			}
		}
		echo "Processed {$processed} replay files\n";
	}

	/**
	 * method used to check all the requested tokens
	 * this method will specify the required loadlevel that the application needs to fullfill all tokens.
	 */
	private function checkTokens(): void {
		$tokenPattern = "/\{\w+\}/";
		$tokens = array();
		// extract from format string
		preg_match_all($tokenPattern, $this->config['format'], $tokens);
		$this->loadlevel = 1; // parse the header
		$this->tokens = $tokens[0];
		foreach ($this->tokens as $token) {
			if (in_array($token, array('{clantag}', '{vs}', '{matchup}', '{date}', '{gametype}', '{map}', '{identifier}', '{region}'))) {
				$this->loadlevel = 2; // do an entire identify run

				break;
			}
		}
	}

	/**
	 * private helper method used to ensure that all config options are set in one way or another.
	 */
	private function constructConfig(): void {
		$options = array(
			't:' => 'target:',
			'd:' => 'dateformat:',
			'f:' => 'format:',
			'p:' => 'playerformat:'
		);

		$rawoptions = getopt(implode('', array_keys($options)), array_values($options));

		foreach ($options as $shortopt => $longopt) {
			$saveName = str_replace(':', '', $longopt);
			$shortChar = str_replace(':', '', $shortopt);
			if (isset($rawoptions[$shortChar])) {
				$this->config[$saveName] = $rawoptions[$shortChar];
			} elseif (isset($rawoptions[$saveName])) {
				$this->config[$saveName] = $rawoptions[$saveName];
			} elseif ($saveName === 'target') {
				$this->config[$saveName] = getcwd();
			} else {
				// allow for default values to be hardcoded
				if (!isset($this->config[$saveName])) {
					$this->config[$saveName] = getNonEmptyInput("Please specify a {$longopt}");
				}
			}
		}

		if (!file_exists($this->config['target']) || !is_writable($this->config['target'])) {
			throwError("Cannot find target {$this->config['target']} or cannot write to it");
		}
	}

	/**
	 * method used to extract a token value out of the replay object.
	 *
	 * @param string $token The name of the token whose value should be extracted
	 * @param Replay $replay Replay object as returned from the parser
	 * @return string with the tokens value/the token itself if it's unknown
	 */
	private function extractTokenValue(string $token, Replay $replay): string {
		switch ($token) {
			case '{date}':
				return date($this->config['dateformat'], $replay->startTimestamp);

				break;
			case '{matchup}':
				return $replay->matchup;

				break;
			case '{gametype}':
				return $replay->gametype;

				break;
			case '{map}':
				return $replay->mapName;

				break;
			case '{region}':
				return $replay->region;

				break;
			case '{version}':
				return $replay->version;

				break;
			case '{basebuild}':
				return $replay->baseBuild;

				break;
			case '{gamelength}':
				return $replay->gamelength;

				break;
			case '{identifier}':
				return $replay->identifier;

				break;
			case '{expansion}':
				return $replay->expansion;

				break;
			case '{vs}':
				$teams = array();
				foreach ($replay->entities as $entity) {
					if ($entity instanceof Player) {
						$teams[$entity->teamId][] = $entity;
					}
				}

				$ret = array();
				foreach ($teams as $teamArr) {
					$teamStr = '';
					foreach ($teamArr as $pl) {
						$plString = $this->config['playerformat'];
						$plString = str_replace('{clantag}', $pl->clanTag, $plString);
						$plString = str_replace('{name}', $pl->name, $plString);
						$plString = str_replace('{fullrace}', $pl->pickRace, $plString);
						$plString = str_replace('{race}', $pl->pickRace[0], $plString);
						$plString = str_replace('{playrace}', $pl->playRace, $plString);
						if (!$pl->isComputer) {
							$plString = str_replace('{bnetId}', $pl->bnetId, $plString);
						}
						$teamStr .= " {$plString}";
					}
					$ret[] = trim($teamStr);
				}

				return implode(' vs ', $ret);

				break;
			default:
				return $token;

				break;
		}
	}

	/**
	 * method used to process a replay file
	 * the replay will be parsed as far as specified by the loadlevel integer in this object.
	 *
	 * @param string $file The path to the replay file to be processed
	 */
	private function processReplay(string $file): void {
		echo "Processing replay file {$file}...\n";

		try {
			$parser = new ReplayParser($file);
			if ($this->loadlevel === 2) {
				$parser->doIdentify();
			}
		} catch (Exception $e) {
			echo "\tError while processing: {$e->getMessage()}\n";
		}

		$newname = $this->config['format'];
		foreach ($this->tokens as $tkn) {
			$newname = str_replace($tkn, $this->extractTokenValue($tkn, $parser->replay), $newname);
		}

		echo "\t => {$newname}\n";
		$newname = $this->config['target'].\DIRECTORY_SEPARATOR.$newname.'.SC2Replay';
		if (!file_exists(dirname($newname))) {
			mkdir(dirname($newname), 0777, true);
		}

		rename($file, $newname);

		// just try to remove the directory
		@rmdir(dirname($file));
	}
}

/**
 * function called to get an arbitrary non empty input string from the user.
 *
 * @param string $msg The message to display to the user as a prompt
 * @return string with non empty input
 */
function getNonEmptyInput(string $msg): string {
	do {
		echo $msg.\PHP_EOL;

		$input = trim(fgets(\STDIN));
	} while ($input === '');

	return $input;
}

/**
 * function used to print an error message
 * and stop the execution of the script with an error code.
 *
 * @param string $errmsg Error message to throw
 */
function throwError(string $errmsg): void {
	echo "ERROR {$errmsg}\n";
	exit(1);
}

/**
 * desc function used to print usage information to the user
 * and stop the execution of the script.
 */
function usage(): void {
	echo <<<DESC

	replay-organizer can be used to reorganize a single replay file or a folder of replay files into a certain format

	usage: replay-organizer.php [-t --target=value]
						  [-d --dateformat=value][-f --format=value]
						  [-p --playerformat=value]
						  [--help]

		target        Specify the path to either a single replay file or an entire directory of replays to reorganize.
		              The replays will be reorganized relative to the given target directory. If no target is provided, the script defaults to the current directory.
		dateformat    Specify the datetime format used for the {date} token in the name. Refer to the php documentation of date() to see valid codes.
		              Defaults to: "Y-m-d H:i:s"
		playerformat  Format used to represent a player in the {vs} token. Valid player information tokens include:
		                {clantag} => the players clan tag with [] around it
		                {name} => the player's name
		                {fullrace} => the player's race with the full name (e.g. Protoss)
		                {race} => the player's race with one letter (R for random)
		                {playrace} => the player's actual race with one letter (no random)
		                {bnetId} => the player's battle.net id number (will be left empty for AI players)
		                This will default to "{clantag}{name} ({race})"

		format        Build the format for the new filename of the replay files.
		              The name can contain any characters in addition to valid tokens except "/" is used as a path separator (For subfolders).
		              Unknown tokens will just be ignored.
		              Available tokens include:
		                {matchup} => Matchup of the played match (PvP, PvT usw...)
		                {date} => Date and time of the played match, format given by the dateformat argument
		                {gametype} => Gametype as in 1v1, 2v2, FFA usw...
		                {map} => Localized map name (with a german client, the german name for the map)
		                {vs} => String with all the teams seperated with a vs, where every player is outputted with the format specified in the playerformat argument
		               	    (e.g. [imba]DavidKimPls (T) vs Peter (P))
		               	{region} => the region the match was played on (e.g. EU)
		               	{version} => the full game version the match was played on (e.g. 3.4.0.44401)
		               	{basebuild} => the sc2 base build number of the version the match was played on (e.g. 44401)
		               	{gamelength} => the length of the game formatted in MM:SS
		               	{identifier} => the sc2rep unique identifier created from the timestamp of the match and a hash of the players
		               	{expansion} => the sc2 expansion the game was played on



		Example: sort replays in subfolders by the day the game was played on, then in subfolders per gametype and then in subfolders per matchup:
		    replay-organizer.php -t {$MYREPLAYFOLDER} -d "Y-m-d" -f "{date}/{gametype}/{matchup}/{vs} on {map}"

		help          Show this help text


	DESC;
	exit(0);
}
