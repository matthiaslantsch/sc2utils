<?php

return array(
	0 => new BinInteger(7),
	1 => new BinInteger(4),
	2 => new BinInteger(6),
	3 => new BinInteger(14),
	4 => new BinInteger(22),
	5 => new BinInteger(32),
	6 => new BinChoice(new BinInteger(2), array(
		0 => new BinInteger(6),
		1 => new BinInteger(14),
		2 => new BinInteger(22),
		3 => new BinInteger(32),
	)),
	7 => new BinBlob(new BinInteger(8)),
	8 => new BinInteger(8),
	9 => new BinStruct(array(
		'flags' => new BinInteger(8),
		'major' => new BinInteger(8),
		'minor' => new BinInteger(8),
		'revision' => new BinInteger(8),
		'build' => new BinInteger(32),
		'baseBuild' => new BinInteger(32),
	)),
	10 => new BinInteger(3),
	11 => new BinStruct(array(
		'signature' => new BinBlob(new BinInteger(8)),
		'version' => new BinStruct(array(
			'flags' => new BinInteger(8),
			'major' => new BinInteger(8),
			'minor' => new BinInteger(8),
			'revision' => new BinInteger(8),
			'build' => new BinInteger(32),
			'baseBuild' => new BinInteger(32),
		)),
		'type' => new BinInteger(3),
		'elapsedGameLoops' => new BinInteger(32),
	)),
	12 => new BinBlob(4),
	13 => new BinBlob(new BinInteger(7)),
	14 => new BinStruct(array(
		'region' => new BinInteger(8),
		'programId' => new BinBlob(4),
		'realm' => new BinInteger(32),
		'name' => new BinBlob(new BinInteger(7)),
	)),
	15 => new BinStruct(array(
		'a' => new BinInteger(8),
		'r' => new BinInteger(8),
		'g' => new BinInteger(8),
		'b' => new BinInteger(8),
	)),
	16 => new BinInteger(2),
	17 => new BinStruct(array(
		'name' => new BinBlob(new BinInteger(8)),
		'toon' => new BinStruct(array(
			'region' => new BinInteger(8),
			'programId' => new BinBlob(4),
			'realm' => new BinInteger(32),
			'name' => new BinBlob(new BinInteger(7)),
		)),
		'race' => new BinBlob(new BinInteger(8)),
		'color' => new BinStruct(array(
			'a' => new BinInteger(8),
			'r' => new BinInteger(8),
			'g' => new BinInteger(8),
			'b' => new BinInteger(8),
		)),
		'control' => new BinInteger(8),
		'teamId' => new BinInteger(4),
		'handicap' => new BinInteger(7),
		'observe' => new BinInteger(2),
		'result' => new BinInteger(2),
	)),
	18 => new BinArray(
		new BinInteger(5),
		new BinStruct(array(
			'name' => new BinBlob(new BinInteger(8)),
			'toon' => new BinStruct(array(
				'region' => new BinInteger(8),
				'programId' => new BinBlob(4),
				'realm' => new BinInteger(32),
				'name' => new BinBlob(new BinInteger(7)),
			)),
			'race' => new BinBlob(new BinInteger(8)),
			'color' => new BinStruct(array(
				'a' => new BinInteger(8),
				'r' => new BinInteger(8),
				'g' => new BinInteger(8),
				'b' => new BinInteger(8),
			)),
			'control' => new BinInteger(8),
			'teamId' => new BinInteger(4),
			'handicap' => new BinInteger(7),
			'observe' => new BinInteger(2),
			'result' => new BinInteger(2),
		))
	),
	19 => new BinOptional(new BinArray(
		new BinInteger(5),
		new BinStruct(array(
			'name' => new BinBlob(new BinInteger(8)),
			'toon' => new BinStruct(array(
				'region' => new BinInteger(8),
				'programId' => new BinBlob(4),
				'realm' => new BinInteger(32),
				'name' => new BinBlob(new BinInteger(7)),
			)),
			'race' => new BinBlob(new BinInteger(8)),
			'color' => new BinStruct(array(
				'a' => new BinInteger(8),
				'r' => new BinInteger(8),
				'g' => new BinInteger(8),
				'b' => new BinInteger(8),
			)),
			'control' => new BinInteger(8),
			'teamId' => new BinInteger(4),
			'handicap' => new BinInteger(7),
			'observe' => new BinInteger(2),
			'result' => new BinInteger(2),
		))
	), new BinInteger(1)),
	20 => new BinBlob(new BinInteger(10)),
	21 => new BinBlob(new BinInteger(11)),
	22 => new BinStruct(array(
		'file' => new BinBlob(new BinInteger(11)),
	)),
	23 => new BinInteger(1),
	24 => new BinInteger(64, -9_223_372_036_854_775_808),
	25 => new BinBlob(new BinInteger(12)),
	26 => new BinBlob(new BinInteger(0, 40)),
	27 => new BinArray(
		new BinInteger(4),
		new BinBlob(new BinInteger(0, 40))
	),
	28 => new BinOptional(new BinArray(
		new BinInteger(4),
		new BinBlob(new BinInteger(0, 40))
	), new BinInteger(1)),
	29 => new BinStruct(array(
		'playerList' => new BinOptional(new BinArray(
			new BinInteger(5),
			new BinStruct(array(
				'name' => new BinBlob(new BinInteger(8)),
				'toon' => new BinStruct(array(
					'region' => new BinInteger(8),
					'programId' => new BinBlob(4),
					'realm' => new BinInteger(32),
					'name' => new BinBlob(new BinInteger(7)),
				)),
				'race' => new BinBlob(new BinInteger(8)),
				'color' => new BinStruct(array(
					'a' => new BinInteger(8),
					'r' => new BinInteger(8),
					'g' => new BinInteger(8),
					'b' => new BinInteger(8),
				)),
				'control' => new BinInteger(8),
				'teamId' => new BinInteger(4),
				'handicap' => new BinInteger(7),
				'observe' => new BinInteger(2),
				'result' => new BinInteger(2),
			))
		), new BinInteger(1)),
		'title' => new BinBlob(new BinInteger(10)),
		'difficulty' => new BinBlob(new BinInteger(8)),
		'thumbnail' => new BinStruct(array(
			'file' => new BinBlob(new BinInteger(11)),
		)),
		'isBlizzardMap' => new BinInteger(1),
		'timeUTC' => new BinInteger(64, -9_223_372_036_854_775_808),
		'timeLocalOffset' => new BinInteger(64, -9_223_372_036_854_775_808),
		'description' => new BinBlob(new BinInteger(12)),
		'imageFilePath' => new BinBlob(new BinInteger(10)),
		'mapFileName' => new BinBlob(new BinInteger(10)),
		'cacheHandles' => new BinOptional(new BinArray(
			new BinInteger(4),
			new BinBlob(new BinInteger(0, 40))
		), new BinInteger(1)),
		'miniSave' => new BinInteger(1),
		'gameSpeed' => new BinInteger(3),
		'defaultDifficulty' => new BinInteger(6),
	)),
	30 => new BinOptional(new BinInteger(8), new BinInteger(1)),
	31 => new BinStruct(array(
		'race' => new BinOptional(new BinInteger(8), new BinInteger(1)),
	)),
	32 => new BinStruct(array(
		'name' => new BinBlob(new BinInteger(8)),
		'randomSeed' => new BinInteger(32),
		'racePreference' => new BinStruct(array(
			'race' => new BinOptional(new BinInteger(8), new BinInteger(1)),
		)),
		'testMap' => new BinInteger(1),
		'testAuto' => new BinInteger(1),
		'observe' => new BinInteger(2),
	)),
	33 => new BinArray(
		new BinInteger(5),
		new BinStruct(array(
			'name' => new BinBlob(new BinInteger(8)),
			'randomSeed' => new BinInteger(32),
			'racePreference' => new BinStruct(array(
				'race' => new BinOptional(new BinInteger(8), new BinInteger(1)),
			)),
			'testMap' => new BinInteger(1),
			'testAuto' => new BinInteger(1),
			'observe' => new BinInteger(2),
		))
	),
	34 => new BinStruct(array(
		'lockTeams' => new BinInteger(1),
		'teamsTogether' => new BinInteger(1),
		'advancedSharedControl' => new BinInteger(1),
		'randomRaces' => new BinInteger(1),
		'battleNet' => new BinInteger(1),
		'amm' => new BinInteger(1),
		'ranked' => new BinInteger(1),
		'noVictoryOrDefeat' => new BinInteger(1),
		'fog' => new BinInteger(2),
		'observers' => new BinInteger(2),
		'userDifficulty' => new BinInteger(2),
	)),
	35 => new BinInteger(5),
	36 => new BinInteger(4, 1),
	37 => new BinInteger(5, 1),
	38 => new BinInteger(8, 1),
	39 => new BinInteger(new BinInteger(6)),
	40 => new BinInteger(new BinInteger(8)),
	41 => new BinInteger(new BinInteger(2)),
	42 => new BinStruct(array(
		'allowedColors' => new BinInteger(new BinInteger(6)),
		'allowedRaces' => new BinInteger(new BinInteger(8)),
		'allowedDifficulty' => new BinInteger(new BinInteger(6)),
		'allowedControls' => new BinInteger(new BinInteger(8)),
		'allowedObserveTypes' => new BinInteger(new BinInteger(2)),
	)),
	43 => new BinArray(
		new BinInteger(5),
		new BinStruct(array(
			'allowedColors' => new BinInteger(new BinInteger(6)),
			'allowedRaces' => new BinInteger(new BinInteger(8)),
			'allowedDifficulty' => new BinInteger(new BinInteger(6)),
			'allowedControls' => new BinInteger(new BinInteger(8)),
			'allowedObserveTypes' => new BinInteger(new BinInteger(2)),
		))
	),
	44 => new BinStruct(array(
		'randomValue' => new BinInteger(32),
		'gameCacheName' => new BinBlob(new BinInteger(10)),
		'gameOptions' => new BinStruct(array(
			'lockTeams' => new BinInteger(1),
			'teamsTogether' => new BinInteger(1),
			'advancedSharedControl' => new BinInteger(1),
			'randomRaces' => new BinInteger(1),
			'battleNet' => new BinInteger(1),
			'amm' => new BinInteger(1),
			'ranked' => new BinInteger(1),
			'noVictoryOrDefeat' => new BinInteger(1),
			'fog' => new BinInteger(2),
			'observers' => new BinInteger(2),
			'userDifficulty' => new BinInteger(2),
		)),
		'gameSpeed' => new BinInteger(3),
		'gameType' => new BinInteger(3),
		'maxUsers' => new BinInteger(5),
		'maxObservers' => new BinInteger(5),
		'maxPlayers' => new BinInteger(4),
		'maxTeams' => new BinInteger(4, 1),
		'maxColors' => new BinInteger(5, 1),
		'maxRaces' => new BinInteger(8, 1),
		'maxControls' => new BinInteger(8, 1),
		'mapSizeX' => new BinInteger(8),
		'mapSizeY' => new BinInteger(8),
		'mapFileSyncChecksum' => new BinInteger(32),
		'mapFileName' => new BinBlob(new BinInteger(10)),
		'modFileSyncChecksum' => new BinInteger(32),
		'slotDescriptions' => new BinArray(
			new BinInteger(5),
			new BinStruct(array(
				'allowedColors' => new BinInteger(new BinInteger(6)),
				'allowedRaces' => new BinInteger(new BinInteger(8)),
				'allowedDifficulty' => new BinInteger(new BinInteger(6)),
				'allowedControls' => new BinInteger(new BinInteger(8)),
				'allowedObserveTypes' => new BinInteger(new BinInteger(2)),
			))
		),
		'defaultDifficulty' => new BinInteger(6),
		'cacheHandles' => new BinArray(
			new BinInteger(4),
			new BinBlob(new BinInteger(0, 40))
		),
		'isBlizzardMap' => new BinInteger(1),
	)),
	45 => new BinOptional(new BinInteger(4), new BinInteger(1)),
	46 => new BinOptional(new BinInteger(5), new BinInteger(1)),
	47 => new BinStruct(array(
		'color' => new BinOptional(new BinInteger(5), new BinInteger(1)),
	)),
	48 => new BinInteger(16),
	49 => new BinArray(
		new BinInteger(5),
		new BinInteger(16)
	),
	50 => new BinStruct(array(
		'control' => new BinInteger(8),
		'userId' => new BinOptional(new BinInteger(4), new BinInteger(1)),
		'teamId' => new BinInteger(4),
		'colorPref' => new BinStruct(array(
			'color' => new BinOptional(new BinInteger(5), new BinInteger(1)),
		)),
		'racePref' => new BinStruct(array(
			'race' => new BinOptional(new BinInteger(8), new BinInteger(1)),
		)),
		'difficulty' => new BinInteger(6),
		'handicap' => new BinInteger(7),
		'observe' => new BinInteger(2),
		'rewards' => new BinArray(
			new BinInteger(5),
			new BinInteger(16)
		),
	)),
	51 => new BinArray(
		new BinInteger(5),
		new BinStruct(array(
			'control' => new BinInteger(8),
			'userId' => new BinOptional(new BinInteger(4), new BinInteger(1)),
			'teamId' => new BinInteger(4),
			'colorPref' => new BinStruct(array(
				'color' => new BinOptional(new BinInteger(5), new BinInteger(1)),
			)),
			'racePref' => new BinStruct(array(
				'race' => new BinOptional(new BinInteger(8), new BinInteger(1)),
			)),
			'difficulty' => new BinInteger(6),
			'handicap' => new BinInteger(7),
			'observe' => new BinInteger(2),
			'rewards' => new BinArray(
				new BinInteger(5),
				new BinInteger(16)
			),
		))
	),
	52 => new BinStruct(array(
		'phase' => new BinInteger(3),
		'maxUsers' => new BinInteger(5),
		'maxObservers' => new BinInteger(5),
		'slots' => new BinArray(
			new BinInteger(5),
			new BinStruct(array(
				'control' => new BinInteger(8),
				'userId' => new BinOptional(new BinInteger(4), new BinInteger(1)),
				'teamId' => new BinInteger(4),
				'colorPref' => new BinStruct(array(
					'color' => new BinOptional(new BinInteger(5), new BinInteger(1)),
				)),
				'racePref' => new BinStruct(array(
					'race' => new BinOptional(new BinInteger(8), new BinInteger(1)),
				)),
				'difficulty' => new BinInteger(6),
				'handicap' => new BinInteger(7),
				'observe' => new BinInteger(2),
				'rewards' => new BinArray(
					new BinInteger(5),
					new BinInteger(16)
				),
			))
		),
		'randomSeed' => new BinInteger(32),
		'hostUserId' => new BinOptional(new BinInteger(4), new BinInteger(1)),
		'isSinglePlayer' => new BinInteger(1),
		'gameDuration' => new BinInteger(32),
		'defaultDifficulty' => new BinInteger(6),
	)),
	53 => new BinStruct(array(
		'userInitialData' => new BinArray(
			new BinInteger(5),
			new BinStruct(array(
				'name' => new BinBlob(new BinInteger(8)),
				'randomSeed' => new BinInteger(32),
				'racePreference' => new BinStruct(array(
					'race' => new BinOptional(new BinInteger(8), new BinInteger(1)),
				)),
				'testMap' => new BinInteger(1),
				'testAuto' => new BinInteger(1),
				'observe' => new BinInteger(2),
			))
		),
		'gameDescription' => new BinStruct(array(
			'randomValue' => new BinInteger(32),
			'gameCacheName' => new BinBlob(new BinInteger(10)),
			'gameOptions' => new BinStruct(array(
				'lockTeams' => new BinInteger(1),
				'teamsTogether' => new BinInteger(1),
				'advancedSharedControl' => new BinInteger(1),
				'randomRaces' => new BinInteger(1),
				'battleNet' => new BinInteger(1),
				'amm' => new BinInteger(1),
				'ranked' => new BinInteger(1),
				'noVictoryOrDefeat' => new BinInteger(1),
				'fog' => new BinInteger(2),
				'observers' => new BinInteger(2),
				'userDifficulty' => new BinInteger(2),
			)),
			'gameSpeed' => new BinInteger(3),
			'gameType' => new BinInteger(3),
			'maxUsers' => new BinInteger(5),
			'maxObservers' => new BinInteger(5),
			'maxPlayers' => new BinInteger(4),
			'maxTeams' => new BinInteger(4, 1),
			'maxColors' => new BinInteger(5, 1),
			'maxRaces' => new BinInteger(8, 1),
			'maxControls' => new BinInteger(8, 1),
			'mapSizeX' => new BinInteger(8),
			'mapSizeY' => new BinInteger(8),
			'mapFileSyncChecksum' => new BinInteger(32),
			'mapFileName' => new BinBlob(new BinInteger(10)),
			'modFileSyncChecksum' => new BinInteger(32),
			'slotDescriptions' => new BinArray(
				new BinInteger(5),
				new BinStruct(array(
					'allowedColors' => new BinInteger(new BinInteger(6)),
					'allowedRaces' => new BinInteger(new BinInteger(8)),
					'allowedDifficulty' => new BinInteger(new BinInteger(6)),
					'allowedControls' => new BinInteger(new BinInteger(8)),
					'allowedObserveTypes' => new BinInteger(new BinInteger(2)),
				))
			),
			'defaultDifficulty' => new BinInteger(6),
			'cacheHandles' => new BinArray(
				new BinInteger(4),
				new BinBlob(new BinInteger(0, 40))
			),
			'isBlizzardMap' => new BinInteger(1),
		)),
		'lobbyState' => new BinStruct(array(
			'phase' => new BinInteger(3),
			'maxUsers' => new BinInteger(5),
			'maxObservers' => new BinInteger(5),
			'slots' => new BinArray(
				new BinInteger(5),
				new BinStruct(array(
					'control' => new BinInteger(8),
					'userId' => new BinOptional(new BinInteger(4), new BinInteger(1)),
					'teamId' => new BinInteger(4),
					'colorPref' => new BinStruct(array(
						'color' => new BinOptional(new BinInteger(5), new BinInteger(1)),
					)),
					'racePref' => new BinStruct(array(
						'race' => new BinOptional(new BinInteger(8), new BinInteger(1)),
					)),
					'difficulty' => new BinInteger(6),
					'handicap' => new BinInteger(7),
					'observe' => new BinInteger(2),
					'rewards' => new BinArray(
						new BinInteger(5),
						new BinInteger(16)
					),
				))
			),
			'randomSeed' => new BinInteger(32),
			'hostUserId' => new BinOptional(new BinInteger(4), new BinInteger(1)),
			'isSinglePlayer' => new BinInteger(1),
			'gameDuration' => new BinInteger(32),
			'defaultDifficulty' => new BinInteger(6),
		)),
	)),
	54 => new BinStruct(array(
		'syncLobbyState' => new BinStruct(array(
			'userInitialData' => new BinArray(
				new BinInteger(5),
				new BinStruct(array(
					'name' => new BinBlob(new BinInteger(8)),
					'randomSeed' => new BinInteger(32),
					'racePreference' => new BinStruct(array(
						'race' => new BinOptional(new BinInteger(8), new BinInteger(1)),
					)),
					'testMap' => new BinInteger(1),
					'testAuto' => new BinInteger(1),
					'observe' => new BinInteger(2),
				))
			),
			'gameDescription' => new BinStruct(array(
				'randomValue' => new BinInteger(32),
				'gameCacheName' => new BinBlob(new BinInteger(10)),
				'gameOptions' => new BinStruct(array(
					'lockTeams' => new BinInteger(1),
					'teamsTogether' => new BinInteger(1),
					'advancedSharedControl' => new BinInteger(1),
					'randomRaces' => new BinInteger(1),
					'battleNet' => new BinInteger(1),
					'amm' => new BinInteger(1),
					'ranked' => new BinInteger(1),
					'noVictoryOrDefeat' => new BinInteger(1),
					'fog' => new BinInteger(2),
					'observers' => new BinInteger(2),
					'userDifficulty' => new BinInteger(2),
				)),
				'gameSpeed' => new BinInteger(3),
				'gameType' => new BinInteger(3),
				'maxUsers' => new BinInteger(5),
				'maxObservers' => new BinInteger(5),
				'maxPlayers' => new BinInteger(4),
				'maxTeams' => new BinInteger(4, 1),
				'maxColors' => new BinInteger(5, 1),
				'maxRaces' => new BinInteger(8, 1),
				'maxControls' => new BinInteger(8, 1),
				'mapSizeX' => new BinInteger(8),
				'mapSizeY' => new BinInteger(8),
				'mapFileSyncChecksum' => new BinInteger(32),
				'mapFileName' => new BinBlob(new BinInteger(10)),
				'modFileSyncChecksum' => new BinInteger(32),
				'slotDescriptions' => new BinArray(
					new BinInteger(5),
					new BinStruct(array(
						'allowedColors' => new BinInteger(new BinInteger(6)),
						'allowedRaces' => new BinInteger(new BinInteger(8)),
						'allowedDifficulty' => new BinInteger(new BinInteger(6)),
						'allowedControls' => new BinInteger(new BinInteger(8)),
						'allowedObserveTypes' => new BinInteger(new BinInteger(2)),
					))
				),
				'defaultDifficulty' => new BinInteger(6),
				'cacheHandles' => new BinArray(
					new BinInteger(4),
					new BinBlob(new BinInteger(0, 40))
				),
				'isBlizzardMap' => new BinInteger(1),
			)),
			'lobbyState' => new BinStruct(array(
				'phase' => new BinInteger(3),
				'maxUsers' => new BinInteger(5),
				'maxObservers' => new BinInteger(5),
				'slots' => new BinArray(
					new BinInteger(5),
					new BinStruct(array(
						'control' => new BinInteger(8),
						'userId' => new BinOptional(new BinInteger(4), new BinInteger(1)),
						'teamId' => new BinInteger(4),
						'colorPref' => new BinStruct(array(
							'color' => new BinOptional(new BinInteger(5), new BinInteger(1)),
						)),
						'racePref' => new BinStruct(array(
							'race' => new BinOptional(new BinInteger(8), new BinInteger(1)),
						)),
						'difficulty' => new BinInteger(6),
						'handicap' => new BinInteger(7),
						'observe' => new BinInteger(2),
						'rewards' => new BinArray(
							new BinInteger(5),
							new BinInteger(16)
						),
					))
				),
				'randomSeed' => new BinInteger(32),
				'hostUserId' => new BinOptional(new BinInteger(4), new BinInteger(1)),
				'isSinglePlayer' => new BinInteger(1),
				'gameDuration' => new BinInteger(32),
				'defaultDifficulty' => new BinInteger(6),
			)),
		)),
	)),
	55 => new BinStruct(array(
		'name' => new BinBlob(new BinInteger(7)),
	)),
	56 => new BinBlob(new BinInteger(6)),
	57 => new BinStruct(array(
		'name' => new BinBlob(new BinInteger(6)),
	)),
	58 => new BinStruct(array(
		'name' => new BinBlob(new BinInteger(6)),
		'type' => new BinInteger(32),
		'data' => new BinBlob(new BinInteger(7)),
	)),
	59 => new BinStruct(array(
		'type' => new BinInteger(32),
		'name' => new BinBlob(new BinInteger(6)),
		'data' => new BinBlob(new BinInteger(12)),
	)),
	60 => new BinStruct(array(
		'developmentCheatsEnabled' => new BinInteger(1),
		'multiplayerCheatsEnabled' => new BinInteger(1),
		'syncChecksummingEnabled' => new BinInteger(1),
		'isMapToMapTransition' => new BinInteger(1),
	)),
	61 => new BinStruct(array(
	)),
	62 => new BinStruct(array(
		'fileName' => new BinBlob(new BinInteger(10)),
		'automatic' => new BinInteger(1),
		'overwrite' => new BinInteger(1),
		'name' => new BinBlob(new BinInteger(8)),
		'description' => new BinBlob(new BinInteger(10)),
	)),
	63 => new BinInteger(32, -2_147_483_648),
	64 => new BinStruct(array(
		'x' => new BinInteger(32, -2_147_483_648),
		'y' => new BinInteger(32, -2_147_483_648),
	)),
	65 => new BinStruct(array(
		'point' => new BinStruct(array(
			'x' => new BinInteger(32, -2_147_483_648),
			'y' => new BinInteger(32, -2_147_483_648),
		)),
		'time' => new BinInteger(32, -2_147_483_648),
		'verb' => new BinBlob(new BinInteger(10)),
		'arguments' => new BinBlob(new BinInteger(10)),
	)),
	66 => new BinStruct(array(
		'data' => new BinStruct(array(
			'point' => new BinStruct(array(
				'x' => new BinInteger(32, -2_147_483_648),
				'y' => new BinInteger(32, -2_147_483_648),
			)),
			'time' => new BinInteger(32, -2_147_483_648),
			'verb' => new BinBlob(new BinInteger(10)),
			'arguments' => new BinBlob(new BinInteger(10)),
		)),
	)),
	67 => new BinStruct(array(
		'x' => new BinInteger(32, -2_147_483_648),
		'y' => new BinInteger(32, -2_147_483_648),
		'z' => new BinInteger(32, -2_147_483_648),
	)),
	68 => new BinStruct(array(
		'cmdFlags' => new BinInteger(32),
		'abilLink' => new BinInteger(16),
		'abilCmdIndex' => new BinInteger(8),
		'abilCmdData' => new BinInteger(8),
		'targetUnitFlags' => new BinInteger(8),
		'targetUnitTimer' => new BinInteger(8),
		'otherUnit' => new BinInteger(32),
		'targetUnitTag' => new BinInteger(32),
		'targetUnitSnapshotUnitLink' => new BinInteger(16),
		'targetUnitSnapshotPlayerId' => new BinOptional(new BinInteger(4), new BinInteger(1)),
		'targetPoint' => new BinStruct(array(
			'x' => new BinInteger(32, -2_147_483_648),
			'y' => new BinInteger(32, -2_147_483_648),
			'z' => new BinInteger(32, -2_147_483_648),
		)),
	)),
	69 => new BinStruct(array(
		'__parent' => new BinInteger(new BinInteger(8)),
	)),
	70 => new BinStruct(array(
		'unitLink' => new BinInteger(16),
		'intraSubgroupPriority' => new BinInteger(8),
		'count' => new BinInteger(8),
	)),
	71 => new BinArray(
		new BinInteger(8),
		new BinStruct(array(
			'unitLink' => new BinInteger(16),
			'intraSubgroupPriority' => new BinInteger(8),
			'count' => new BinInteger(8),
		))
	),
	72 => new BinArray(
		new BinInteger(8),
		new BinInteger(32)
	),
	73 => new BinStruct(array(
		'subgroupIndex' => new BinInteger(8),
		'removeMask' => new BinStruct(array(
			'__parent' => new BinInteger(new BinInteger(8)),
		)),
		'addSubgroups' => new BinArray(
			new BinInteger(8),
			new BinStruct(array(
				'unitLink' => new BinInteger(16),
				'intraSubgroupPriority' => new BinInteger(8),
				'count' => new BinInteger(8),
			))
		),
		'addUnitTags' => new BinArray(
			new BinInteger(8),
			new BinInteger(32)
		),
	)),
	74 => new BinStruct(array(
		'controlGroupId' => new BinInteger(4),
		'delta' => new BinStruct(array(
			'subgroupIndex' => new BinInteger(8),
			'removeMask' => new BinStruct(array(
				'__parent' => new BinInteger(new BinInteger(8)),
			)),
			'addSubgroups' => new BinArray(
				new BinInteger(8),
				new BinStruct(array(
					'unitLink' => new BinInteger(16),
					'intraSubgroupPriority' => new BinInteger(8),
					'count' => new BinInteger(8),
				))
			),
			'addUnitTags' => new BinArray(
				new BinInteger(8),
				new BinInteger(32)
			),
		)),
	)),
	75 => new BinStruct(array(
		'controlGroupIndex' => new BinInteger(4),
		'controlGroupUpdate' => new BinInteger(2),
		'mask' => new BinStruct(array(
			'__parent' => new BinInteger(new BinInteger(8)),
		)),
	)),
	76 => new BinStruct(array(
		'count' => new BinInteger(8),
		'subgroupCount' => new BinInteger(8),
		'activeSubgroupIndex' => new BinInteger(8),
		'unitTagsChecksum' => new BinInteger(32),
		'subgroupIndicesChecksum' => new BinInteger(32),
		'subgroupsChecksum' => new BinInteger(32),
	)),
	77 => new BinStruct(array(
		'controlGroupId' => new BinInteger(4),
		'selectionSyncData' => new BinStruct(array(
			'count' => new BinInteger(8),
			'subgroupCount' => new BinInteger(8),
			'activeSubgroupIndex' => new BinInteger(8),
			'unitTagsChecksum' => new BinInteger(32),
			'subgroupIndicesChecksum' => new BinInteger(32),
			'subgroupsChecksum' => new BinInteger(32),
		)),
	)),
	78 => new BinArray(
		new BinInteger(3),
		new BinInteger(32, -2_147_483_648)
	),
	79 => new BinStruct(array(
		'recipientId' => new BinInteger(4),
		'resources' => new BinArray(
			new BinInteger(3),
			new BinInteger(32, -2_147_483_648)
		),
	)),
	80 => new BinStruct(array(
		'chatMessage' => new BinBlob(new BinInteger(10)),
	)),
	81 => new BinInteger(8, -128),
	82 => new BinStruct(array(
		'beacon' => new BinInteger(8, -128),
		'ally' => new BinInteger(8, -128),
		'autocast' => new BinInteger(8, -128),
		'targetUnitTag' => new BinInteger(32),
		'targetUnitSnapshotUnitLink' => new BinInteger(16),
		'targetUnitSnapshotPlayerId' => new BinOptional(new BinInteger(4), new BinInteger(1)),
		'targetPoint' => new BinStruct(array(
			'x' => new BinInteger(32, -2_147_483_648),
			'y' => new BinInteger(32, -2_147_483_648),
			'z' => new BinInteger(32, -2_147_483_648),
		)),
	)),
	83 => new BinStruct(array(
		'speed' => new BinInteger(3),
	)),
	84 => new BinStruct(array(
		'delta' => new BinInteger(8, -128),
	)),
	85 => new BinStruct(array(
		'verb' => new BinBlob(new BinInteger(10)),
		'arguments' => new BinBlob(new BinInteger(10)),
	)),
	86 => new BinStruct(array(
		'alliance' => new BinInteger(32),
		'control' => new BinInteger(32),
	)),
	87 => new BinStruct(array(
		'unitTag' => new BinInteger(32),
	)),
	88 => new BinStruct(array(
		'unitTag' => new BinInteger(32),
		'flags' => new BinInteger(8),
	)),
	89 => new BinStruct(array(
		'conversationId' => new BinInteger(32, -2_147_483_648),
		'replyId' => new BinInteger(32, -2_147_483_648),
	)),
	90 => new BinStruct(array(
		'purchaseItemId' => new BinInteger(32, -2_147_483_648),
	)),
	91 => new BinStruct(array(
		'difficultyLevel' => new BinInteger(32, -2_147_483_648),
	)),
	92 => null,
	93 => new BinChoice(new BinInteger(3), array(
		0 => null,
		1 => new BinInteger(1),
		2 => new BinInteger(32),
		3 => new BinInteger(32, -2_147_483_648),
		4 => new BinBlob(new BinInteger(11)),
	)),
	94 => new BinStruct(array(
		'controlId' => new BinInteger(32, -2_147_483_648),
		'eventType' => new BinInteger(32, -2_147_483_648),
		'eventData' => new BinChoice(new BinInteger(3), array(
			0 => null,
			1 => new BinInteger(1),
			2 => new BinInteger(32),
			3 => new BinInteger(32, -2_147_483_648),
			4 => new BinBlob(new BinInteger(11)),
		)),
	)),
	95 => new BinStruct(array(
		'soundHash' => new BinInteger(32),
		'length' => new BinInteger(32),
	)),
	96 => new BinStruct(array(
		'soundHash' => new BinArray(
			new BinInteger(8),
			new BinInteger(32)
		),
		'length' => new BinArray(
			new BinInteger(8),
			new BinInteger(32)
		),
	)),
	97 => new BinStruct(array(
		'syncInfo' => new BinStruct(array(
			'soundHash' => new BinArray(
				new BinInteger(8),
				new BinInteger(32)
			),
			'length' => new BinArray(
				new BinInteger(8),
				new BinInteger(32)
			),
		)),
	)),
	98 => new BinStruct(array(
		'sound' => new BinInteger(32),
	)),
	99 => new BinStruct(array(
		'transmissionId' => new BinInteger(32, -2_147_483_648),
	)),
	100 => new BinStruct(array(
		'target' => new BinStruct(array(
			'x' => new BinInteger(32, -2_147_483_648),
			'y' => new BinInteger(32, -2_147_483_648),
		)),
		'distance' => new BinInteger(32, -2_147_483_648),
		'pitch' => new BinInteger(32, -2_147_483_648),
		'yaw' => new BinInteger(32, -2_147_483_648),
	)),
	101 => new BinInteger(1),
	102 => new BinStruct(array(
		'skipType' => new BinInteger(1),
	)),
	103 => new BinStruct(array(
		'button' => new BinInteger(32),
		'down' => new BinInteger(1),
		'posXUI' => new BinInteger(32),
		'posYUI' => new BinInteger(32),
		'posXWorld' => new BinInteger(32, -2_147_483_648),
		'posYWorld' => new BinInteger(32, -2_147_483_648),
		'posZWorld' => new BinInteger(32, -2_147_483_648),
	)),
	104 => new BinStruct(array(
		'soundtrack' => new BinInteger(32),
	)),
	105 => new BinStruct(array(
		'planetId' => new BinInteger(32, -2_147_483_648),
	)),
	106 => new BinStruct(array(
		'key' => new BinInteger(8, -128),
		'flags' => new BinInteger(8, -128),
	)),
	107 => new BinStruct(array(
		'resources' => new BinArray(
			new BinInteger(3),
			new BinInteger(32, -2_147_483_648)
		),
	)),
	108 => new BinStruct(array(
		'fulfillRequestId' => new BinInteger(32, -2_147_483_648),
	)),
	109 => new BinStruct(array(
		'cancelRequestId' => new BinInteger(32, -2_147_483_648),
	)),
	110 => new BinStruct(array(
		'researchItemId' => new BinInteger(32, -2_147_483_648),
	)),
	111 => new BinStruct(array(
		'laggingPlayerId' => new BinInteger(4),
	)),
	112 => new BinStruct(array(
		'mercenaryId' => new BinInteger(32, -2_147_483_648),
	)),
	113 => new BinStruct(array(
		'battleReportId' => new BinInteger(32, -2_147_483_648),
		'difficultyLevel' => new BinInteger(32, -2_147_483_648),
	)),
	114 => new BinStruct(array(
		'battleReportId' => new BinInteger(32, -2_147_483_648),
	)),
	115 => new BinStruct(array(
		'decrementMs' => new BinInteger(32),
	)),
	116 => new BinStruct(array(
		'portraitId' => new BinInteger(32, -2_147_483_648),
	)),
	117 => new BinStruct(array(
		'functionName' => new BinBlob(new BinInteger(7)),
	)),
	118 => new BinStruct(array(
		'result' => new BinInteger(32, -2_147_483_648),
	)),
	119 => new BinStruct(array(
		'gameMenuItemIndex' => new BinInteger(32, -2_147_483_648),
	)),
	120 => new BinStruct(array(
		'reason' => new BinInteger(8, -128),
	)),
	121 => new BinStruct(array(
		'purchaseCategoryId' => new BinInteger(32, -2_147_483_648),
	)),
	122 => new BinStruct(array(
		'button' => new BinInteger(16),
	)),
	123 => new BinStruct(array(
		'recipient' => new BinInteger(2),
		'string' => new BinBlob(new BinInteger(11)),
	)),
	124 => new BinStruct(array(
		'recipient' => new BinInteger(2),
		'point' => new BinStruct(array(
			'x' => new BinInteger(32, -2_147_483_648),
			'y' => new BinInteger(32, -2_147_483_648),
		)),
	)),
	125 => new BinStruct(array(
		'progress' => new BinInteger(32, -2_147_483_648),
	)),
);
