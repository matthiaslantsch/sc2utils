"""Download the maps of SC2 replays from the Battle.net depot.

For each replay, sc2reader reads the map's cache handle from replay.details and turns it into the depot URL
(https://<region>-s2-depot.classic.blizzard.com/<hash>.s2ma). The map is saved as <hash>.s2ma in the output
directory. Its file name is the SHA-256 of its content, which is checked after every download.

Usage (from the repo root):
    tools/.venv/bin/python tools/fixtures/fetch_maps.py --out tools/.cache/maps tests/test_replays

Arguments can be replay files or directories (searched recursively for *.SC2Replay). Maps already present are
skipped, so the script can be rerun at any time. Choosing the curated maps for tests/test_maps/ stays manual.

If the replay's own depot fails, the same hash is tried on the us/eu/kr depots (the hash check makes that safe).
Observed in the test replays: HotS beta replays have region 'xx' (no such host; their maps are on the us depot),
and a cn map gone from the NetEase CDN was still on us/eu/kr.
"""

import argparse
import hashlib
import os
import sys
import tempfile
import urllib.request

import sc2reader

FALLBACK_URL = 'https://{region}-s2-depot.classic.blizzard.com/{hash}.{ext}'
FALLBACK_REGIONS = ('us', 'eu', 'kr')


def find_replays(paths):
    for path in paths:
        if os.path.isdir(path):
            for root, _, files in os.walk(path):
                for name in sorted(files):
                    if name.lower().endswith('.sc2replay'):
                        yield os.path.join(root, name)
        else:
            yield path


def map_handle(replay_path):
    """Return (url, sha256, extension) of the replay's map, read via sc2reader at load level 1 (details)."""
    replay = sc2reader.load_replay(replay_path, load_level=1)
    handle = replay.map_file
    if handle is None:
        raise ValueError('replay has no map cache handle')
    return handle.url, handle.hash, handle.type


def download(url, expected_sha256, target, timeout):
    """Download url to target via a temporary file. The file only gets its final name if the hash matches."""
    directory = os.path.dirname(target)
    with urllib.request.urlopen(url, timeout=timeout) as response:
        fd, tmp = tempfile.mkstemp(dir=directory, suffix='.part')
        digest = hashlib.sha256()
        size = 0
        try:
            with os.fdopen(fd, 'wb') as out:
                while chunk := response.read(1 << 16):
                    digest.update(chunk)
                    out.write(chunk)
                    size += len(chunk)
            if digest.hexdigest() != expected_sha256:
                raise ValueError(f'SHA-256 mismatch: got {digest.hexdigest()}')
            # mkstemp creates the file with mode 0600; give it the normal umask-based mode instead
            umask = os.umask(0)
            os.umask(umask)
            os.chmod(tmp, 0o666 & ~umask)
            os.replace(tmp, target)
        finally:
            if os.path.exists(tmp):
                os.remove(tmp)
    return size


def main():
    parser = argparse.ArgumentParser(description='Download the maps of SC2 replays from the Battle.net depot.')
    parser.add_argument('paths', nargs='+', help='replay files or directories containing replays')
    parser.add_argument('--out', required=True, help='directory to save the maps in (created if missing)')
    parser.add_argument('--timeout', type=int, default=120, help='download timeout per map in seconds')
    args = parser.parse_args()

    os.makedirs(args.out, exist_ok=True)
    seen = set()
    counts = {'downloaded': 0, 'present': 0, 'skipped': 0, 'failed': 0}

    for replay_path in find_replays(args.paths):
        try:
            url, sha256, ext = map_handle(replay_path)
        except Exception as e:
            # e.g. phase-1 beta replays, which sc2reader cannot read
            print(f'skip      {replay_path}: {type(e).__name__}: {e}')
            counts['skipped'] += 1
            continue

        if sha256 in seen:
            continue
        seen.add(sha256)

        target = os.path.join(args.out, f'{sha256}.{ext}')
        if os.path.exists(target):
            print(f'present   {sha256[:12]}  {replay_path}')
            counts['present'] += 1
            continue

        candidates = [url] + [
            u for u in (FALLBACK_URL.format(region=r, hash=sha256, ext=ext) for r in FALLBACK_REGIONS) if u != url
        ]
        errors = []
        for candidate in candidates:
            try:
                size = download(candidate, sha256, target, args.timeout)
            except Exception as e:
                errors.append(f'{candidate}: {type(e).__name__}: {e}')
                continue
            via = '' if candidate == url else f'  (fallback {candidate.split("/")[2]})'
            print(f'download  {sha256[:12]}  {size / 1e6:.1f} MB  {replay_path}{via}')
            counts['downloaded'] += 1
            break
        else:
            print(f'FAILED    {sha256[:12]}  {replay_path}')
            for error in errors:
                print(f'          {error}')
            counts['failed'] += 1

    print(', '.join(f'{n} {k}' for k, n in counts.items()))
    return 1 if counts['failed'] else 0


if __name__ == '__main__':
    sys.exit(main())
