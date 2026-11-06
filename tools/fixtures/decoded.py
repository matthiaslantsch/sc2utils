"""Dump s2protocol's decoded output of the test replays into tests/fixtures/ (the "decoded" fixture kind).

Usage (from the repo root):
    tools/.venv/bin/python tools/fixtures/decoded.py [replay or directory ...]

Default is all of tests/test_replays/. Arguments can be replay files or directories (searched recursively for
*.SC2Replay), all inside tests/test_replays/, since that path is the fixture key.

Output per replay, in tests/fixtures/<key>/decoded/:

    meta.json                 replay, base build, protocol build and source, s2protocol version and commit,
                              and per stream: its file and event count, `absent`, or the error
    header.json               replay header (MPQ user data)
    details.json              replay.details
    initdata.json             replay.initData
    attributes.json           replay.attributes.events (not typeinfo, see protocols.decode_attributes())
    message.events.json       replay.message.events, one event per line
    game.events.jsonl.gz      replay.game.events, gzipped JSON lines
    tracker.events.jsonl.gz   replay.tracker.events, gzipped JSON lines

Every value comes from s2protocol's decoders and the protocol tables picked by tools/common/protocols.py (legacy
generation first, nearest build). Header, details and initdata must be consumed to the end; event streams are
decoded until their data ends. The versioned streams (header, details, tracker) also record `skipped`: values
s2protocol skipped silently because the table doesn't know their tag.
Replays whose header s2protocol can't read get only a meta.json with the error.
"""

import argparse
import shutil
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

import mpyq  # noqa: E402
from s2protocol.decoders import BitPackedDecoder  # noqa: E402

from common import jsonio, protocols  # noqa: E402

REPO = Path(__file__).resolve().parents[2]
REPLAYS = REPO / 'tests' / 'test_replays'
FIXTURES = REPO / 'tests' / 'fixtures'
KIND = 'decoded'


class IncompleteError(Exception):
    pass


def find_replays(paths: list[Path]) -> list[Path]:
    found = []
    for path in paths:
        path = path.resolve()
        if path.is_dir():
            found += [p for p in path.rglob('*') if p.suffix.lower() == '.sc2replay']
        else:
            found.append(path)
    return sorted(set(found))


def replay_key(path: Path) -> str:
    try:
        parts = path.relative_to(REPLAYS).with_suffix('').parts
    except ValueError:
        raise SystemExit(f'{path} is not inside {REPLAYS}') from None
    if len(parts) >= 2 and parts[-1] == parts[-2]:
        parts = parts[:-1]
    return '/'.join(parts)


def decode_whole(decoder, data: bytes, typeid: int):
    value = decoder.instance(typeid)
    decoder.byte_align()
    if not decoder.done():
        left = len(data) - decoder.used_bits() // 8
        raise IncompleteError(f'{left} of {len(data)} bytes not consumed')
    return value


def skipped(decoder) -> dict:
    """{'skipped': n} when a versioned decoder skipped values the table doesn't know, else nothing."""
    count = getattr(decoder, 'skipped', 0)
    return {'skipped': count} if count else {}


def error(e: Exception) -> dict:
    return {'error': f'{type(e).__name__}: {e}'}


def dump(path: Path, latest) -> dict:
    archive = mpyq.MPQArchive(str(path))
    header_data = archive.header['user_data_header']['content']
    meta: dict = {
        'replay': path.relative_to(REPLAYS).as_posix(),
        's2protocol': protocols.version(),
        's2protocolCommit': protocols.head(),
    }
    files: dict[str, tuple] = {}

    try:
        base_build = latest.decode_replay_header(header_data)['m_version']['m_baseBuild']
        build = protocols.nearest(base_build)
    except Exception as e:
        meta['error'] = f'header: {type(e).__name__}: {e}'
        return {'meta': meta, 'files': files}

    source = protocols.sources()[build]
    protocol = protocols.load(build)
    meta['baseBuild'] = base_build
    meta['protocol'] = build
    meta['protocolSource'] = f'legacy {protocols.LEGACY}' if source.legacy else 'current'

    def read(name):
        return header_data if name is None else archive.read_file(name)

    versioned = lambda d: protocols.counting_versioned_decoder(d, protocol.typeinfos)  # noqa: E731
    bitpacked = lambda d: BitPackedDecoder(d, protocol.typeinfos)  # noqa: E731
    single = [
        ('header', None, versioned, lambda dec, d: decode_whole(dec, d, protocol.replay_header_typeid)),
        ('details', 'replay.details', versioned, lambda dec, d: decode_whole(dec, d, protocol.game_details_typeid)),
        ('initdata', 'replay.initData', bitpacked, lambda dec, d: decode_whole(dec, d, protocol.replay_initdata_typeid)),
        ('attributes', 'replay.attributes.events', None, lambda dec, d: protocols.decode_attributes(build, d)),
    ]
    streams = [
        ('message', 'replay.message.events', bitpacked, 'message.events.json', jsonio.write_json_list),
        ('game', 'replay.game.events', bitpacked, 'game.events.jsonl.gz', jsonio.write_jsonl_gz),
        ('tracker', 'replay.tracker.events', versioned, 'tracker.events.jsonl.gz', jsonio.write_jsonl_gz),
    ]

    meta['streams'] = {}
    for name, archived, make_decoder, decode in single:
        data = read(archived)
        if data is None:
            meta['streams'][name] = {'absent': True}
            continue
        decoder = make_decoder(data) if make_decoder else None
        try:
            value = decode(decoder, data)
            jsonio.clean(value)
        except Exception as e:
            meta['streams'][name] = error(e)
            continue
        files[f'{name}.json'] = (jsonio.write_json, value)
        meta['streams'][name] = {'file': f'{name}.json', **skipped(decoder)}

    for name, archived, make_decoder, filename, writer in streams:
        data = read(archived)
        if data is None:
            meta['streams'][name] = {'absent': True}
            continue
        decoder = make_decoder(data)
        try:
            events = list(protocols.decode_events(protocol, name, data, decoder))
            for event in events:
                jsonio.clean(event)
        except Exception as e:
            meta['streams'][name] = error(e)
            continue
        files[filename] = (writer, events)
        meta['streams'][name] = {'file': filename, 'events': len(events), **skipped(decoder)}

    return {'meta': meta, 'files': files}


def write(key: str, result: dict) -> Path:
    target = FIXTURES / key / KIND
    tmp = target.with_name(f'.{KIND}.tmp')
    if tmp.exists():
        shutil.rmtree(tmp)
    tmp.mkdir(parents=True)
    jsonio.write_json(tmp / 'meta.json', result['meta'])
    for filename, (writer, value) in result['files'].items():
        writer(tmp / filename, value)
    if target.exists():
        shutil.rmtree(target)
    tmp.rename(target)
    return target


def summary(meta: dict) -> str:
    if 'error' in meta:
        return meta['error']
    parts = [f"{meta['baseBuild']} -> {meta['protocol']}"]
    for name, info in meta['streams'].items():
        if 'error' in info:
            parts.append(f'{name}: {info["error"][:70]}')
        elif 'absent' in info:
            parts.append(f'{name}: -')
        elif 'events' in info:
            parts.append(f'{name}: {info["events"]}')
    return ' | '.join(parts)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    parser.add_argument('paths', nargs='*', type=Path, default=[REPLAYS])
    args = parser.parse_args()

    latest = protocols.load(protocols.builds()[-1])
    failed = False
    for path in find_replays(args.paths):
        key = replay_key(path)
        result = dump(path, latest)
        write(key, result)
        meta = result['meta']
        failed |= 'error' not in meta and any('error' in s for s in meta['streams'].values())
        print(f'{key}: {summary(meta)}')
    # stream errors are recorded, not fatal; the exit code only flags them for review
    return 1 if failed else 0


if __name__ == '__main__':
    sys.exit(main())
