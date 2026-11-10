"""The test replays: finding them and their fixture key."""

from pathlib import Path

REPO = Path(__file__).resolve().parents[2]
REPLAYS = REPO / 'tests' / 'test_replays'
FIXTURES = REPO / 'tests' / 'fixtures'


def find_replays(paths: list[Path]) -> list[Path]:
    """Replay files, directories searched recursively for *.SC2Replay."""
    found = []
    for path in paths:
        path = path.resolve()
        if path.is_dir():
            found += [p for p in path.rglob('*') if p.suffix.lower() == '.sc2replay']
        else:
            found.append(path)
    return sorted(set(found))


def replay_key(path: Path) -> str:
    """The path inside tests/test_replays/ without suffix; `x/x.SC2Replay` becomes `x`."""
    try:
        parts = path.relative_to(REPLAYS).with_suffix('').parts
    except ValueError:
        raise SystemExit(f'{path} is not inside {REPLAYS}') from None
    if len(parts) >= 2 and parts[-1] == parts[-2]:
        parts = parts[:-1]
    return '/'.join(parts)
