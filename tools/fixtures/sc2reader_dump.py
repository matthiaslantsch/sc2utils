"""Dump sc2reader's view of the test replays into tests/fixtures/ (the "sc2reader" fixture kind).

Usage (from the repo root):
    tools/.venv/bin/python tools/fixtures/sc2reader_dump.py [replay or directory ...]

Default is all of tests/test_replays/. Arguments as for decoded.py.

Output per replay, in tests/fixtures/<key>/sc2reader/:

    meta.json      replay, sc2reader version and commit, per stream its file or the error
    summary.json   the library's Replay, see below

summary.json is one object shaped like the library's normalised `Replay` (tests/UsesFixtures.php
assertReplayMatchesFixture()):

    release_string, base_build, frames, expansion, speed, region, map_name / map_hash / map_file.server,
    unix_timestamp, time_zone, real_length, category, game_type, real_type, is_ladder;
    per player: pid, sid, name, clan_tag, the details toon (region_id, subregion, toon_id; null for computers),
    pick_race, play_race, result, is_human, color.name, colour RGBA, team_id;
    per observer: name, clan_tag, toon_handle; per team: number, pids, result, lineup

Every value is a sc2reader attribute as it is (load_level=2). This script picks and
renames, it computes nothing: the point of the fixture is an independent implementation to compare against.
Replays sc2reader can't load get only a meta.json with the error.
"""

import argparse
import shutil
import subprocess
import sys
from pathlib import Path

import sc2reader  # noqa: E402

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from common import jsonio  # noqa: E402
from common.replays import FIXTURES, REPLAYS, find_replays, replay_key  # noqa: E402

KIND = 'sc2reader'
SC2READER = Path(sc2reader.__file__).resolve().parents[1]


def head() -> str:
    return subprocess.run(['git', '-C', str(SC2READER), 'rev-parse', 'HEAD'], capture_output=True, text=True,
                          check=True).stdout.strip()


def summary(replay) -> dict:
    return {
        'version': replay.release_string,
        'baseBuild': replay.base_build,
        'gameloops': replay.frames,
        'expansion': replay.expansion,
        'speed': replay.speed,
        'region': replay.region,
        'map': {
            'name': replay.map_name,
            'hash': replay.map_hash,
            'depotRegion': replay.map_file.server,
        },
        'endTime': replay.unix_timestamp,
        'utcOffset': replay.time_zone,
        'realLength': replay.real_length.seconds,
        'gameMode': replay.category,
        'teamSize': replay.game_type,
        'realType': replay.real_type,
        'isLadder': replay.is_ladder,
        'players': [{
            'pid': p.pid,
            'slot': p.sid,
            'name': p.name,
            'clanTag': getattr(p, 'clan_tag', None),
            'toon': [p.region_id, p.subregion, p.toon_id] if p.is_human else None,
            'pickedRace': p.pick_race,
            'playedRace': p.play_race,
            'result': p.result,
            'human': p.is_human,
            'color': p.color.name,
            'rgba': [p.color.r, p.color.g, p.color.b, p.color.a],
            'team': p.team_id,
        } for p in replay.players],
        'observers': [{
            'name': o.name,
            'clanTag': o.clan_tag,
            'toon': o.toon_handle,
        } for o in replay.observers],
        'teams': [{
            'number': t.number,
            'players': [p.pid for p in t.players],
            'result': t.result,
            'lineup': t.lineup,
        } for t in replay.teams],
    }


def dump(path: Path, commit: str) -> dict:
    meta: dict = {
        'replay': path.relative_to(REPLAYS).as_posix(),
        'sc2reader': sc2reader.__version__,
        'sc2readerCommit': commit,
        'streams': {},
    }
    files = {}
    try:
        replay = sc2reader.load_replay(str(path), load_level=2)
        value = jsonio.clean(summary(replay))
    except Exception as e:
        meta['streams']['summary'] = {'error': f'{type(e).__name__}: {e}'}
    else:
        files['summary.json'] = value
        meta['streams']['summary'] = {'file': 'summary.json'}
    return {'meta': meta, 'files': files}


def write(key: str, result: dict) -> None:
    target = FIXTURES / key / KIND
    tmp = target.with_name(f'.{KIND}.tmp')
    if tmp.exists():
        shutil.rmtree(tmp)
    tmp.mkdir(parents=True)
    jsonio.write_json(tmp / 'meta.json', result['meta'])
    for filename, value in result['files'].items():
        jsonio.write_json(tmp / filename, value)
    if target.exists():
        shutil.rmtree(target)
    tmp.rename(target)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    parser.add_argument('paths', nargs='*', type=Path, default=[REPLAYS])
    args = parser.parse_args()

    commit = head()
    for path in find_replays(args.paths):
        key = replay_key(path)
        result = dump(path, commit)
        write(key, result)
        status = result['meta']['streams']['summary']
        print(f"{key}: {status.get('error', 'ok')[:100]}")
    return 0


if __name__ == '__main__':
    sys.exit(main())
