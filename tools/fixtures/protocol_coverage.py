"""Which protocol builds the test replays cover, by decoding structure, and what a replay of each uncovered one adds.

Usage (from the repo root):
    tools/.venv/bin/python tools/fixtures/protocol_coverage.py

Many protocol builds have identical tables for everything decoding uses, so one replay covers all of them. This
groups consecutive builds into classes of identical structure and prints, per class, whether a test replay covers
it, what changed against the previous class, and the date window to search for an uncovered one. For uncovered
classes it also prints what is new: the parts whose structure no covered class has. That is what a replay of the
class would test that the corpus doesn't, and it is the measure to prioritise by (a class can change a lot against
its predecessor and still be almost entirely covered by its successor).

Compared per protocol: the header, details and initdata types, the user id type, and per event stream the event
id map, the event id type and every event struct. Types are compared resolved (by structure, not by type id); tags
only where decoding uses them (header, details, tracker events, which are versioned), not in bit-packed structs.

"Covered" reads the protocol each replay decoded with from tests/fixtures/*/decoded/meta.json, so run decoded.py
first after adding replays. The date window comes from the end times in the summary fixtures. Identical decoding
structure says nothing about per-build game data (unit and ability ids), which comes from sc2reader's tables.
"""

import datetime
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from common import protocols  # noqa: E402
from common.replays import FIXTURES  # noqa: E402

# the files the Replay is mapped from
REPLAY_FILES = ('header', 'details', 'initdata')

# event structs the library's model consumes (typed events, P3/P5); a change there costs more than elsewhere
CONSUMED = {
    'SCmdEvent', 'SSelectionDeltaEvent', 'SControlGroupUpdateEvent', 'SCameraUpdateEvent', 'SChatMessage',
    'SPingMessage', 'SPlayerLeaveEvent', 'SGameUserLeaveEvent', 'SUnitBornEvent', 'SUnitDiedEvent', 'SUnitInitEvent',
    'SUnitDoneEvent', 'SUnitTypeChangeEvent', 'SUpgradeEvent', 'SPlayerStatsEvent', 'SPlayerSetupEvent',
    'SUnitOwnerChangeEvent',
}


def resolve(typeinfos, typeid, tags, seen=()):
    kind, args = typeinfos[typeid]
    if typeid in seen:
        return ['<recursion>']
    seen = seen + (typeid,)
    if kind == '_struct':
        return ['struct', [[name, resolve(typeinfos, field, tags, seen)] + ([tag] if tags else [])
                           for name, field, tag in args[0]]]
    if kind == '_array':
        return ['array', list(args[0]), resolve(typeinfos, args[1], tags, seen)]
    if kind == '_optional':
        return ['optional', resolve(typeinfos, args[0], tags, seen)]
    if kind == '_choice':
        return ['choice', list(args[0]),
                {str(key): [name, resolve(typeinfos, choice, tags, seen)] for key, (name, choice) in sorted(args[1].items())}]
    return [kind, [list(a) if isinstance(a, tuple) else a for a in args]]


def structure(build: int) -> dict[str, str]:
    module = protocols.load(build)
    types = module.typeinfos
    parts = {
        'header': resolve(types, module.replay_header_typeid, True),
        'details': resolve(types, module.game_details_typeid, True),
        'initdata': resolve(types, module.replay_initdata_typeid, False),
        'userid': resolve(types, protocols.user_id_typeid(module), False),
    }
    for stream, versioned in (('game', False), ('message', False), ('tracker', True)):
        events = getattr(module, f'{stream}_event_types', None)
        if events is None:
            continue
        parts[f'{stream}:ids'] = sorted((eventid, name) for eventid, (_, name) in events.items())
        parts[f'{stream}:eventid'] = resolve(types, getattr(module, f'{stream}_eventid_typeid'), False)
        for typeid, name in events.values():
            parts[f'{stream}:{name.split(".")[-1]}'] = resolve(types, typeid, versioned)
    return {key: json.dumps(value, sort_keys=True) for key, value in parts.items()}


def covered() -> dict[int, list[datetime.date]]:
    """protocol build => end dates of the test replays that decode with it."""
    ret: dict[int, list] = {}
    for meta_path in FIXTURES.glob('**/decoded/meta.json'):
        meta = json.loads(meta_path.read_text())
        if 'protocol' not in meta:
            continue
        dates = ret.setdefault(meta['protocol'], [])
        key = meta_path.parent.parent
        for summary in (key / 'manual' / 'summary.json', key / 'sc2reader' / 'summary.json'):
            if summary.exists():
                end = json.loads(summary.read_text())['info'].get('endTime')
                if end is not None:
                    dates.append(datetime.datetime.fromtimestamp(end, datetime.timezone.utc).date())
                break
    return ret


def main() -> int:
    have = covered()
    builds = protocols.builds()
    structures = {build: structure(build) for build in builds}
    classes: list[list[int]] = []
    for build in builds:
        if classes and structures[classes[-1][-1]] == structures[build]:
            classes[-1].append(build)
        else:
            classes.append([build])

    dates = {i: sorted(d for b in c for d in have.get(b, [])) for i, c in enumerate(classes)}
    uncovered = [i for i, c in enumerate(classes) if not set(c) & set(have)]
    seen: dict[str, set[str]] = {}
    for i, builds_of_class in enumerate(classes):
        if i not in uncovered:
            for key, value in structures[builds_of_class[0]].items():
                seen.setdefault(key, set()).add(value)
    print(f'{len(builds)} protocol builds, {len(classes)} classes of identical decoding structure, '
          f'{len(classes) - len(uncovered)} covered, {len(uncovered)} uncovered\n')

    for i, builds_of_class in enumerate(classes):
        if i == 0:
            changes = []
        else:
            before, after = structures[classes[i - 1][-1]], structures[builds_of_class[0]]
            changes = sorted(key for key in set(before) | set(after) if before.get(key) != after.get(key))
        replay_files = [c for c in changes if c in REPLAY_FILES]
        consumed = [c.split(':')[-1] for c in changes if c.split(':')[-1] in CONSUMED]
        text = ', '.join(map(str, builds_of_class)) if len(builds_of_class) <= 4 \
            else f'{builds_of_class[0]} .. {builds_of_class[-1]} ({len(builds_of_class)})'
        if i in uncovered:
            earlier = next((dates[j][-1] for j in range(i - 1, -1, -1) if dates[j]), None)
            later = next((dates[j][0] for j in range(i + 1, len(classes)) if dates[j]), None)
            state = f'UNCOVERED, between {earlier or "?"} and {later or "?"}'
        else:
            state = 'covered'
        print(f'{text}: {state}')
        if changes:
            print(f'    {len(changes)} changes; replay files: {", ".join(replay_files) or "-"}; consumed events: '
                  f'{", ".join(consumed) or "-"}; other: {len(changes) - len(replay_files) - len(consumed)}')
        if i in uncovered:
            new = sorted(k for k, v in structures[builds_of_class[0]].items() if v not in seen.get(k, set()))
            print(f'    new against all covered classes: {len(new)}: {", ".join(n.split(":")[-1] for n in new) or "-"}')
    return 0


if __name__ == '__main__':
    sys.exit(main())
