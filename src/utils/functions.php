<?php
/**
 * This file is part of the sc2rep replay parser project
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\utils;

/**
 * lookup from region id to get a region string (e.g. NA).
 *
 * @param int $regionId The id of the region to look up
 * @return string short string representing the region (e.g. NA)
 */
function gatewayLookup(int $regionId): string {
	switch ($regionId) {
		case 1:
			return 'NA';
		case 2:
			return 'EU';
		case 3:
			return 'KR';
		case 5:
			return 'CN';
		case 6:
			return 'SEA';
		default:
			return 'Unknown';
	}
}

/**
 * lookup from league number to real league name (e.g. 1 => Bronze).
 *
 * @param int $league The number of the league to look up
 * @return string representing the league (e.g. Silver)
 */
function leagueLookup(int $league): string {
	switch ($league) {
		case 1:
			return 'Bronze';
		case 2:
			return 'Silver';
		case 3:
			return 'Gold';
		case 4:
			return 'Platinum';
		case 5:
			return 'Diamond';
		case 6:
			return 'Master';
		case 7:
			return 'Grandmaster';
		default:
			return 'None';
	}
}

/**
 * lookup for known localized race names, hardcoded in here
 * if no translation is available, we will return the original name.
 *
 * @param string $localizedRace The race name in another language, hopefully there is a hardcode for it
 * @return string with the delocalized race (if possible)
 */
function delocalizeRace(string $localizedRace): string {
	$LOCALIZEDRACES = array(
		// enUS
		'Terran' => 'Terran',
		'Protoss' => 'Protoss',
		'Zerg' => 'Zerg',

		// ruRU
		'Терран' => 'Terran',
		'Протосс' => 'Protoss',
		'Зерг' => 'Zerg',

		// koKR
		'테란' => 'Terran',
		'프로토스' => 'Protoss',
		'저그' => 'Zerg',

		// plPL
		'Terranie' => 'Terran',
		'Protosi' => 'Protoss',
		'Zergi' => 'Zerg',

		// zhCH
		'人类' => 'Terran',
		'星灵' => 'Protoss',
		'异虫' => 'Zerg',

		// zhTW
		'人類' => 'Terran',
		'神族' => 'Protoss',
		'蟲族' => 'Zerg',

		// ???
		'Terrano' => 'Terran',

		// deDE
		'Terraner' => 'Terran',

		// esES - Spanish
		// esMX - Latin American
		// frFR - French - France
		// plPL - Polish Polish
		// ptBR - Brazilian Portuguese
	);

	if (isset($LOCALIZEDRACES[$localizedRace])) {
		return $LOCALIZEDRACES[$localizedRace];
	}

	return $localizedRace;

}

/**
 * method calculating the real second count out of an ingame engine loop counter.
 *
 * @param int $loopCount The number of loops passed
 * @param string $gameSpeed A string despicting the game speed of the replay
 * @return int how many real life seconds have passed
 */
function loopsToRealTime(int $loopCount, string $gameSpeed): int {
	$GAMESPEEDFACTOR = array(
		'Slower' => 0.6,
		'Slow' => 0.8,
		'Normal' => 1.0,
		'Fast' => 1.2,
		'Faster' => 1.4
	);

	return floor(floor($loopCount / 16) / $GAMESPEEDFACTOR[$gameSpeed]);
}

/**
 * method used to create a MM:SS time string out of seconds number.
 *
 * @param int $seconds The number of seconds passed
 * @return string how many minutes and seconds have passed
 */
function createTimeString(int $seconds): string {
	if ($seconds === null) {
		return '-';
	}

	if ($seconds >= 60) {
		if ($seconds % 60 === 0) {
			return twoDigitNumber($seconds / 60).' : 00';
		}

		return twoDigitNumber((int)($seconds / 60)).' : '.twoDigitNumber((int)($seconds % 60));
	}

	return '00 : '.twoDigitNumber($seconds);

}

/**
 * small helper function making sure the number always has two digits
 * e.g. 1 => 01, 10 => 10, 4 => 04.
 *
 * @param  int number The number to format
 * @return string a two digit number
 */
function twoDigitNumber($number): string {
	if ($number < 10) {
		return '0'.$number;
	}

	return $number;

}
