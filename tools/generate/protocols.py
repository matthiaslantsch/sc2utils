"""Dump every s2protocol protocol module as JSON lines on stdout, for tools/generate/protocols.php.

Usage (from the repo root; tools/generate/protocols.sh runs the whole pipe):
    tools/.venv/bin/python tools/generate/protocols.py | php tools/generate/protocols.php

Line 1 describes the source: {"s2protocol": version, "commit": submodule commit, "legacy": rev, "legacyCommit": sha}.
Every further line is one protocol, in build order, with the module's tables as s2protocol has them (tuples become
JSON lists, int dict keys become strings) and nothing interpreted:

    build, source ("legacy", "current" or "supplement"), file (path in the submodule, or in tools/ for
    supplements), notice (the module's licence header),
    typeinfos, svaruint32_typeid, user_id_typeid, replay_header_typeid, game_details_typeid,
    replay_initdata_typeid, game_eventid_typeid, game_event_types, message_eventid_typeid, message_event_types,
    tracker_eventid_typeid, tracker_event_types

user_id_typeid is replay_userid_typeid, or replay_playerid_typeid in the oldest legacy modules (see
common.protocols.user_id_typeid()). The tracker fields are null for protocols without tracker events.
"""

import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from common import protocols  # noqa: E402


def notice(path: Path) -> str:
    """The comment block the module starts with (Blizzard's MIT notice), without the comment markers."""
    lines = []
    for line in path.read_text(encoding='utf-8').splitlines():
        if not line.startswith('#'):
            break
        lines.append(line[1:].removeprefix(' ').rstrip())
    return '\n'.join(lines).strip('\n')


def protocol_line(source: protocols.Source) -> dict:
    module = protocols.load(source.build)
    tracker = hasattr(module, 'tracker_event_types')
    return {
        'build': source.build,
        'source': source.kind,
        'file': f'tools/supplements/{source.path.name}' if source.supplement else f'{protocols.VERSIONS}/{source.path.name}',
        'notice': notice(source.path),
        'typeinfos': module.typeinfos,
        'svaruint32_typeid': module.svaruint32_typeid,
        'user_id_typeid': protocols.user_id_typeid(module),
        'replay_header_typeid': module.replay_header_typeid,
        'game_details_typeid': module.game_details_typeid,
        'replay_initdata_typeid': module.replay_initdata_typeid,
        'game_eventid_typeid': module.game_eventid_typeid,
        'game_event_types': module.game_event_types,
        'message_eventid_typeid': module.message_eventid_typeid,
        'message_event_types': module.message_event_types,
        'tracker_eventid_typeid': module.tracker_eventid_typeid if tracker else None,
        'tracker_event_types': module.tracker_event_types if tracker else None,
    }


def main() -> int:
    out = sys.stdout
    head = {
        's2protocol': protocols.version(),
        'commit': protocols.head(),
        'legacy': protocols.LEGACY,
        'legacyCommit': protocols._git('rev-parse', protocols.LEGACY).strip(),
    }
    out.write(json.dumps(head) + '\n')
    for source in protocols.sources().values():
        out.write(json.dumps(protocol_line(source), separators=(',', ':')) + '\n')
    return 0


if __name__ == '__main__':
    sys.exit(main())
