# sc2utils

sc2utils (`holonet/sc2utils`) is a PHP 8.4 library for extracting information from Starcraft II resources.

sc2utils requires PHP 8.4+ with the `bz2` and `zlib` extensions.

It is essentially a PHP port of two Python libraries:

- **[s2protocol](https://github.com/Blizzard/s2protocol)** is Blizzard's reference implementation for decoding the
  binary files inside a replay (header, details, init data, game, message and tracker events). The bit-packed and
  versioned decoders were ported from it, and its per-build protocol are consumed.

- **[sc2reader](https://github.com/ggtracker/sc2reader)** builds a meaningful model on top of that raw data:
  players, teams, units, abilities, the game engine with its plugins (APM, …) and map parsing. The domain layer,
  the game simulation and the map reader follow its design.

### Replays

Replays can be parsed for the following general types of information:

- Replay details (map, length, version, expansion, date/time, game type/speed, ladder season, ...)
- Player details (name, clan tag, race, team, color, toon/bnet id, result, ...)
- Observers and computer players
- Message details (chat text, time, sender, recipient, pings)
- Selection and control group (hotkey) events
- Unit commands (attack, move, train, build, abilities, ...)
- Camera movements
- Unit states: creation and completion times, positions, type changes, deaths
- Player resource stats: collection rates, unspent totals, resources spent and lost
- Upgrades

Further game state information is computed by running a replay through the engine (`simulate()`):

- The unit model: every unit with its owner, type changes (morphs) and lifetime
- APM and SPM per user
- Ability usage per user
- Mining bases and expansion timings

Every replay with a replay header opens, back to the Wings of Liberty betas. Builds without a protocol (or with
broken event streams) throw an `UnsupportedBuildException` for the parts that don't decode.

### Maps

Maps (`.s2ma`) can be parsed for the following information:

- Localized name (enUS, enGB, then any other locale the map has)
- Map dimensions, camera bounds and tileset
- Minimap image (tga)
- The raw MapInfo file and the map script (`MapScript.galaxy`)

Maps referenced by a replay can be downloaded from the Battle.net depot; the file is checked against its SHA-256
hash before it is written.

## Basic Usage

> For example scripts, see `scripts/replay-dump.php` and `scripts/replay-organiser.php`.

### Loading Replays

Opening a replay only reads the header and picks the protocol. Everything else is decoded on demand:

```php
use holonet\sc2utils\ReplayParser;

$parser = new ReplayParser();
$file = $parser->open('MyReplay.SC2Replay');

// details, players, teams, map reference, ...
$replay = $parser->replay($file);
echo $replay->map->name, ' ', $replay->header->version, "\n";
foreach ($replay->players as $player) {
    echo $player->name, ' ', $player->playedRace?->value, ' ', $player->result?->value, "\n";
}

// chat messages and pings
$messages = $parser->messages($replay, $file);

// player stats samples from the tracker events (2.0.8+)
$stats = $parser->playerStats($replay, $file);
```

### Loading Maps

If you have a replay and want its map file as well, the `MapDownloader` fetches it from the depot the replay names
(falling back to the us/eu/kr depots):

```php
use holonet\sc2utils\MapDownloader;
use holonet\sc2utils\MapFile;

new MapDownloader()->download($replay->map, '/path/to/map.s2ma');

$map = MapFile::open('MyMap.s2ma')->map();
echo $map->name, " ({$map->width}x{$map->height})\n";
```

### Using Plugins

`simulate()` runs the replay's events through the engine, the counterpart of sc2reader's `GameEngine`. Without
arguments it registers the default plugins (`ApmTracker`, `SpmTracker`, `AbilityTracker`, `BaseTracker`):

```php
use holonet\sc2utils\simulate\plugin\ApmTracker;

$simulation = $parser->simulate($file);

foreach ($simulation->replay->users as $user) {
    echo $user->name, ': ', $simulation->result(ApmTracker::class)?->for($user)?->average, " APM\n";
}
```

To pick the plugins yourself, pass them in registration order (dependencies first):

```php
$simulation = $parser->simulate($file, [new ApmTracker(), new MyPlugin()]);
```

A plugin implements `holonet\sc2utils\simulate\Plugin`: it subscribes to event classes, keeps its own state and
returns a result.

## Testing

```
composer test
```

runs PHPUnit, php-cs-fixer (dry run) and Psalm. To run only the tests:

```
vendor/bin/phpunit -c phpunit.xml
vendor/bin/phpunit -c phpunit.xml --filter ReplaySummaryTest
```

The expected results in `tests/fixtures/` are produced by s2protocol and sc2reader, so the test suite itself needs no
Python. `composer fix` applies the code style.


## Acknowledgements

- Thanks to the developers of [sc2reader](https://github.com/ggtracker/sc2reader), whose design and data files this library is built on.
- Thanks to Blizzard for supporting the development of 3rd party tools and releasing their
  [s2protocol](https://github.com/Blizzard/s2protocol) reference implementation.
- Thanks to the abandoned [phpsc2replay](http://code.google.com/p/phpsc2replay/) project that  sc2reader itself started from.
