"""Loads the s2protocol version modules from the pinned submodule, without `imp`.

Three sources make up the full set:

- legacy: every module at `b41b369^`, the last commit of the old code generator (83 builds, 15405 to 57507).
  b41b369 (2017-10-27) deleted them all; a3d2066 regenerated 32 of them the same day with the new generator, and
  the other 51 (incl. all of 34784 to 51149) never came back. They are extracted into
  tools/.cache/s2protocol/<commit>/.
- current: the modules in the submodule's working tree (`s2protocol/versions/`), used for every build legacy
  doesn't have.
- supplement: hand-written modules in tools/supplements/ for builds s2protocol never had (the HotS beta 2.0.0.x), each
  derived from an s2protocol module as its docstring explains.

Legacy wins when a build exists in both. The regenerated tables lost what the old generator knew about the old formats.
"""

import importlib.util
import re
import subprocess
import sys
from dataclasses import dataclass
from functools import cache
from pathlib import Path
from types import ModuleType

TOOLS = Path(__file__).resolve().parents[1]
SUPPLEMENTS = TOOLS / 'supplements'
SUBMODULE = TOOLS / 's2protocol'
VERSIONS = 's2protocol/versions'
CACHE = TOOLS / '.cache' / 's2protocol'
LEGACY = 'b41b369^'

_NAME = re.compile(r'^protocol(\d+)\.py$')


class ProtocolError(Exception):
    pass


@dataclass(frozen=True)
class Source:
    build: int
    path: Path
    # None for modules in the current tree and supplements, otherwise the commit the file was extracted from
    commit: str | None
    supplement: bool = False

    @property
    def legacy(self) -> bool:
        return self.commit is not None

    @property
    def kind(self) -> str:
        return 'supplement' if self.supplement else 'legacy' if self.legacy else 'current'


def _git(*args: str) -> str:
    return subprocess.run(
        ['git', '-C', str(SUBMODULE), *args], check=True, capture_output=True, text=True
    ).stdout


def _check_submodule() -> None:
    if not (SUBMODULE / VERSIONS).is_dir():
        raise ProtocolError(
            f'{SUBMODULE} is not initialised, run `git submodule update --init` in the repository root'
        )


def head() -> str:
    """The pinned submodule commit, which records the source of everything generated from it."""
    _check_submodule()
    return _git('rev-parse', 'HEAD').strip()


def version() -> str:
    """The s2protocol release of the pinned commit, e.g. '5.0.17.98310.0' (from its commit subject)."""
    subject = _git('log', '-1', '--format=%s', 'HEAD')
    match = re.search(r'(\d+(?:\.\d+)+)', subject)
    return match.group(1) if match else head()[:12]


@cache
def sources() -> dict[int, Source]:
    """Every protocol build available from the pinned commit, sorted by build."""
    _check_submodule()
    found: dict[int, Source] = {}

    for file in (SUBMODULE / VERSIONS).iterdir():
        if match := _NAME.match(file.name):
            build = int(match.group(1))
            found[build] = Source(build, file, None)

    commit = _git('rev-parse', LEGACY).strip()
    for path in _git('ls-tree', '--name-only', commit, f'{VERSIONS}/').splitlines():
        if not (match := _NAME.match(Path(path).name)):
            continue
        build = int(match.group(1))
        target = CACHE / commit / Path(path).name
        if not target.exists():
            target.parent.mkdir(parents=True, exist_ok=True)
            content = subprocess.run(
                ['git', '-C', str(SUBMODULE), 'show', f'{commit}:{path}'], check=True, capture_output=True
            ).stdout
            tmp = target.with_suffix('.tmp')
            tmp.write_bytes(content)
            tmp.replace(target)
        found[build] = Source(build, target, commit)

    for file in SUPPLEMENTS.iterdir():
        if match := _NAME.match(file.name):
            build = int(match.group(1))
            if build in found:
                raise ProtocolError(f'{file} duplicates s2protocol build {build}')
            found[build] = Source(build, file, None, supplement=True)

    return dict(sorted(found.items()))


def builds() -> list[int]:
    return list(sources())


def nearest(base_build: int) -> int:
    """The protocol build a replay of `base_build` decodes with: the highest one not above it."""
    candidates = [build for build in sources() if build <= base_build]
    if not candidates:
        raise ProtocolError(f'No protocol for base build {base_build}, the oldest is {builds()[0]}')
    return candidates[-1]


@cache
def load(build: int) -> ModuleType:
    """The protocol module of exactly `build`."""
    try:
        source = sources()[build]
    except KeyError:
        raise ProtocolError(f'No protocol module for build {build}') from None

    import s2protocol.decoders

    name = f'_sc2utils_protocols.protocol{build:05d}'
    spec = importlib.util.spec_from_file_location(name, source.path)
    assert spec is not None and spec.loader is not None
    module = importlib.util.module_from_spec(spec)

    alias = source.legacy and 'decoders' not in sys.modules
    if alias:
        sys.modules['decoders'] = s2protocol.decoders
    try:
        spec.loader.exec_module(module)
    finally:
        if alias:
            del sys.modules['decoders']

    return module


# kind => (decoder class name, event id type attribute, event map attribute, whether events carry a user id)
EVENT_STREAMS = {
    'game': ('BitPackedDecoder', 'game_eventid_typeid', 'game_event_types', True),
    'message': ('BitPackedDecoder', 'message_eventid_typeid', 'message_event_types', True),
    'tracker': ('VersionedDecoder', 'tracker_eventid_typeid', 'tracker_event_types', False),
}


def decode_attributes(protocol_build: int, contents: bytes) -> dict:
    """
    replay.attributes.events, like upstream's `decode_replay_attributes_events()`.
    """
    from s2protocol.decoders import BitPackedBuffer

    buffer = BitPackedBuffer(contents, 'little')
    attributes = {}
    if not buffer.done():
        attributes['source'] = buffer.read_bits(8) if protocol_build >= 17326 else None
        attributes['mapNamespace'] = buffer.read_bits(32)
        buffer.read_bits(32)  # count
        attributes['scopes'] = {}
        while not buffer.done():
            value = {}
            value['namespace'] = buffer.read_bits(32)
            value['attrid'] = attrid = buffer.read_bits(32)
            scope = buffer.read_bits(8)
            value['value'] = buffer.read_aligned_bytes(4)[::-1].strip(b'\x00')
            attributes['scopes'].setdefault(scope, {}).setdefault(attrid, []).append(value)
    return attributes


def user_id_typeid(protocol: ModuleType) -> int | None:
    """The type prefixing each game/message event: `m_userId`, or `m_playerId` in the oldest modules."""
    for name in ('replay_userid_typeid', 'replay_playerid_typeid'):
        if getattr(protocol, name, None) is not None:
            return getattr(protocol, name)
    return None


def decode_events(protocol: ModuleType, kind: str, contents: bytes, decoder=None):
    """
    Yields the events of one stream, like upstream's `_decode_event_stream()` (5.0.17), from any module's tables.
    """
    import s2protocol.decoders as decoders

    decoder_class, eventid_attr, types_attr, with_user = EVENT_STREAMS[kind]
    if not hasattr(protocol, types_attr):
        raise ProtocolError(f'Protocol has no {kind} events')
    event_types = getattr(protocol, types_attr)
    eventid_typeid = getattr(protocol, eventid_attr)
    userid_typeid = user_id_typeid(protocol) if with_user else None
    if with_user and userid_typeid is None:
        raise ProtocolError('Protocol has no user id type')

    if decoder is None:
        decoder = getattr(decoders, decoder_class)(contents, protocol.typeinfos)
    gameloop = 0
    while not decoder.done():
        start_bits = decoder.used_bits()
        delta = decoder.instance(protocol.svaruint32_typeid)
        gameloop += next(iter(delta.values()), 0)
        if with_user:
            userid = decoder.instance(userid_typeid)

        eventid = decoder.instance(eventid_typeid)
        typeid, typename = event_types.get(eventid, (None, None))
        if typeid is None:
            raise decoders.CorruptedError(f'eventid({eventid}) at {decoder}')

        event = decoder.instance(typeid)
        event['_event'] = typename
        event['_eventid'] = eventid
        event['_gameloop'] = gameloop
        if with_user:
            event['_userid'] = userid
        decoder.byte_align()
        event['_bits'] = decoder.used_bits() - start_bits
        yield event


def counting_versioned_decoder(contents: bytes, typeinfos):
    """
    s2protocol's VersionedDecoder, counting the values it skips because the table doesn't know their tag.
    """
    from s2protocol.decoders import VersionedDecoder

    class CountingVersionedDecoder(VersionedDecoder):
        skipped = 0
        _depth = 0

        def _skip_instance(self):
            if self._depth == 0:
                self.skipped += 1
            self._depth += 1
            try:
                super()._skip_instance()
            finally:
                self._depth -= 1

    return CountingVersionedDecoder(contents, typeinfos)
