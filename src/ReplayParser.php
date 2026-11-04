<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils;

use holonet\bitstream\StringStream;
use holonet\sc2utils\resources\Replay;
use MPQArchive;

/**
 * The ReplayParser class is used as a centerpiece for the replay parsing process
 * it executes four different routines that reveal different levels of data about the replay:
 *   - header decoding (in the constructor) => game version, protocol
 *   - replay identifying (in identify()) => identify the people that played the replay/the map/timestamps
 *   - events decoding (in decode()) => decodes the additional event files in the replay to create raw event objects
 *   - game engine simulation (in simulate()) => simulates the events in order and calls plugins to extract additional data.
 */
class ReplayParser {
	public Replay $replay;

	private MPQArchive $archive;

	/**
	 * constructor method starting the replay parsing process
	 * will always do the header decoding.
	 *
	 * @param string $path Path to the replay to be parsed
	 */
	public function __construct(string $path) {
		if (!file_exists($path) || !is_readable($path)) {
			throw new ParserException("The replay file '{$path}' could not be found/read", 10);
		}

		$this->archive = new MPQArchive($path);

		// decode the header file
		$this->decodeHeader();
	}

	/**
	 * static method used to compare two replays
	 * necessary because two computers recording the same match will never have the exact same timestamp on it.
	 *
	 * @param string $hashOne The replay hash of the first replay
	 * @param string $hashTwo The replay hash of the second replay
	 * @return bool determing wheter the two replays are from the same match
	 */
	public static function compare(string $hashOne, string $hashTwo): bool {
		if (str_starts_with($hashOne, 'BETA') || str_starts_with($hashTwo, 'BETA')) {
			return false;
		}

		if (strstr($hashOne, ':') !== strstr($hashTwo, ':')) {
			return false;
		}

		$timestampOne = strstr($hashOne, ':', true);
		$timestampTwo = strstr($hashTwo, ':', true);

		// 90 seconds tolerant
		return (bool)(abs($timestampOne - $timestampTwo) < 90);

	}

	/**
	 * static interface method to parse a replay, will identify it and then parse the game event files.
	 *
	 * @param string $path The path to the replay to parse
	 * @return Replay object resulting from the decoded replay file
	 */
	public static function decode(string $path): Replay {
		$me = new static($path);

		return $me->doParse();
	}

	/**
	 * dispatcher method calling a decoder on a certain replay subfile.
	 *
	 * @param string $file The name of the replay sub file that should be decoded
	 * @param string $decoder The name of the decoder class to be used to decode the data
	 */
	public function decodeFile(string $file, string $decoder): void {
		if (!in_array($file, array_keys($this->replay->rawdata))) {
			$data = $this->archive->readFile($file);
			$stream = new StringStream($data);
			// instantiate the decoder class
			$decoder = new $decoder($stream);
			// do the decoding
			$decoder->decode($this->replay);
		}
	}

	/**
	 * function used to increase the load level of the replay to a parsed level
	 * will decode init data and replay details and all event files.
	 *
	 * @return Replay object resulting from the decoded replay file
	 */
	public function doDecode(): Replay {
		$this->doIdentify();
		if ($this->replay->loadLevel < 3) {
			$this->decodeFile('replay.message.events', decoders\MessageEventsDecoder::class);
			$this->decodeFile('replay.game.events', decoders\GameEventsDecoder::class);

			$this->replay->loadLevel = 3;
		}

		return $this->replay;
	}

	/**
	 * function used to increase the load level of the replay to an identify level
	 * will decode init data and replay details.
	 *
	 * @return array with identify data
	 */
	public function doIdentify(): array {
		if ($this->replay->loadLevel < 2) {
			if ($this->replay->baseBuild < 15_097) {
				// replay version 1
				$this->versionedFormatDecode('replay.info');
			} else {
				// replay version 2
				$this->decodeFile('replay.initdata', decoders\InitdataDecoder::class);
				$this->decodeFile('replay.attributes.events', decoders\AttributeEventsDecoder::class);
				$this->decodeFile('replay.details', decoders\DetailsDecoder::class);
			}

			utils\PlayerLoader::loadPlayers($this->replay);

			$identifyString = $this->replay->region.$this->replay->peoplestring;

			// we'll allow 100 seconds of time in between to make sure we get replays of the same match
			$timestampSubString = isset($this->replay->startTimestamp) ? substr($this->replay->startTimestamp, 0, 8) : '';

			if ($this->replay->baseBuild < 16_195) {
				$this->replay->identifier = "BETA{$timestampSubString}:".md5($identifyString);
			} else {
				$this->replay->identifier = "{$timestampSubString}:".md5($identifyString);
			}

			$this->replay->loadLevel = 2;
		}

		return array(
			'gameloops' => $this->replay->gameloops,
			'repHash' => $this->replay->identifier,
			'mapHash' => $this->replay->mapHash,
			'mapUrl' => $this->replay->mapUrl,
			'version' => $this->replay->version
		);
	}

	/**
	 * static interface method to identify a replay, will decode init data and replay details.
	 *
	 * @param string $path The path to the replay to identify
	 * @return array with identify data
	 */
	public static function identify(string $path): array {
		$me = new static($path);

		return $me->doIdentify();
	}

	/**
	 * dispatcher method using the right version of a format file to parse a certain replay subfile.
	 *
	 * @param string $file The name of the replay sub file that should be decoded
	 */
	public function versionedFormatDecode(string $file): void {
		$stream = new StringStream($this->archive->readFile($file));
		$formatTree = require_once "format/{$this->replay->baseBuild}/{$file}.bformat.php";
		$parser = new BinaryFormatParser($stream, $formatTree);
		$data = $parser->parse();
	}

	/**
	 * method used to parse the information contained inside header of the replay file:
	 *  - game version
	 *  - game loop counter
	 */
	private function decodeHeader(): void {
		$header = new StringStream($this->archive->userData);
		$decoder = new decoders\HeaderDecoder($header);
		$headerData = $decoder->decode(null); // null since we do not have a replay object yet
		$this->replay = new Replay(
			$headerData['version']['baseBuild'],
			$headerData['versionString'],
			$headerData['elapsedGameLoops'],
			$headerData['expansion']
		);
	}
}
