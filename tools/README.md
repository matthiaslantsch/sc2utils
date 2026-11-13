# tools

Dev tooling (`export-ignore`). It has two jobs:

- **Generators** produce data the library ships: protocol tables in `src/protocol/data/` and game data in
  `src/gamedata/data/`. Their output is committed.
- **Fixture dumpers** run the Python oracles (s2protocol, sc2reader, mpyq) over the test replays and maps and write
  the expected results to `tests/fixtures/`. Their output is committed too, so the PHP test suite needs no Python.

The pinned submodule commits record exactly which upstream state produced the generated files and fixtures.

## Layout

```
s2protocol/      submodule (Blizzard/s2protocol), oracle for decoding, source of the protocol tables
sc2reader/       submodule (ggtracker/sc2reader), oracle for the semantic layer, source of the game data
supplements/     hand-written protocol modules for builds s2protocol never had
common/          shared Python: protocol loader (nearest build, legacy generation from b41b369^, supplements,
                 event loop), JSON cleaning, gz JSON lines
generate/        protocols.sh = protocols.py (tables → JSON lines) | protocols.php (→ PHP data files),
                 gamedata.php (sc2reader's data files → src/gamedata/data/)
fixtures/        decoded.py (s2protocol), sc2reader_dump.py (summary/events/units/map), mpq.py (coverage scan for
                 tests/archive/MpqArchiveTest.php), fetch_maps.php (depot download),
                 protocol_coverage.py (which protocol classes the test replays cover, see the plan's Version gaps)
.venv/  .cache/  local only (gitignored): Python environment; extracted legacy protocols,
                 downloaded maps outside the curated set
```

## Setup

```
git submodule update --init
cd tools
python3 -m venv .venv
.venv/bin/pip install -r requirements.txt
```

## When to run what

| Change | Run | Then |
|---|---|---|
| s2protocol submodule bumped | `generate/protocols.sh`, `fixtures/decoded.py` | review the diff of `src/protocol/data/` and the fixtures |
| sc2reader submodule bumped | `generate/gamedata.php`, `fixtures/sc2reader_dump.py` | review the diff |
| replay or map added | `fixtures/decoded.py`, `fixtures/sc2reader_dump.py`, `fixtures/mpq.py`, `fixtures/protocol_coverage.py` | if `mpq.py` exits with 1, extend `tests/archive/MpqArchiveTest.php` |
| map needed | `php fixtures/fetch_maps.php` | curated maps go into `tests/test_maps/`, the rest stays in `.cache/` |

## Notes

- `supplements/` holds hand-written protocol modules for builds s2protocol never had.
  `common/protocols.py` loads them as a third source next to legacy and
  current, so the generator and `fixtures/decoded.py` treat them like any other protocol.

- `fixtures/sc2reader_dump.py` writes `tests/fixtures/<key>/sc2reader/summary.json`: the library's domain layer
  as sc2reader computes it. For replays sc2reader can't read, `tests/fixtures/<key>/manual/summary.json` is hand-written instead.
- `generate/gamedata.php` writes `attributes.php`, `abilities/<expansion>_<version>.php` (one per sc2reader ability
  table) and `units.php` (`unit_lookup.csv` + `unit_info.json`, keyed by Blizzard's unit id). Which ability table a
  replay uses, and how unit type changes relate (modes, morphs), are hand-written in `src/gamedata/` because
  sc2reader has them in code or not at all.

- Upstream s2protocol has no protocols between builds 32283 and 51702 (deleted in `b41b369`, 2017), and the 32 old
  ones it regenerated afterwards are broken for old formats. `common/protocols.py` therefore takes every module
  that existed at `b41b369^` from there (extracted into `.cache/`) and only newer ones from the current tree.
- Map depot URL: `https://<region>-s2-depot.classic.blizzard.com/<hash>.<ext>` (`sea` → `us`, `cn` on
  `necdn.leihuo.netease.com`). The old `<region>.depot.battle.net:1119` hosts are gone. The file name is the
  SHA-256 of the content. `MapDownloader` (and so `fetch_maps.php`) falls back to the us/eu/kr depots when the replay's own region fails
  (HotS beta replays say `xx`, and their maps are on us).
