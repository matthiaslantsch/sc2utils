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
common/          shared Python: protocol loader (nearest build, legacy generation from b41b369^, event loop),
                 JSON cleaning, gz JSON lines
generate/        protocols.sh = protocols.py (tables → JSON lines) | protocols.php (→ PHP data files), gamedata.php
fixtures/        decoded.py (s2protocol), sc2reader.py (summary/events/units/map), mpq.py (coverage scan for
                 tests/MpqArchiveTest.php), fetch_maps.py (depot download + SHA-256 check)
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
| sc2reader submodule bumped | `generate/gamedata.php`, `fixtures/sc2reader.py` | review the diff |
| replay or map added | `fixtures/decoded.py`, `fixtures/sc2reader.py`, `fixtures/mpq.py` | if `mpq.py` exits with 1, add the reported archive to `tests/MpqArchiveTest.php` (values once from mpyq) |
| map needed | `fixtures/fetch_maps.py` | curated maps go into `tests/test_maps/`, the rest stays in `.cache/` |

## Notes

- Upstream s2protocol has no protocols between builds 32283 and 51702 (deleted in `b41b369`, 2017), and the 32 old
  ones it regenerated afterwards are broken for old formats. `common/protocols.py` therefore takes every module
  that existed at `b41b369^` from there (extracted into `.cache/`) and only newer ones from the current tree.
- Map depot URL: `https://<region>-s2-depot.classic.blizzard.com/<hash>.<ext>` (`sea` → `us`, `cn` on
  `necdn.leihuo.netease.com`). The old `<region>.depot.battle.net:1119` hosts are gone. The file name is the
  SHA-256 of the content. `fetch_maps.py` falls back to the us/eu/kr depots when the replay's own region fails
  (HotS beta replays say `xx`, and their maps are on us).
