"""Report MPQ archives (replays, maps) with a variant that tests/archive/MpqArchiveTest.php doesn't cover yet.

Each file in an archive has a variant: single-unit or sectored, encrypted, and the compression of its first sector
(0x02 zlib, 0x10 bzip2, ...). An archive is covered when every variant it has also occurs in an archive of the
test with the same MPQ format version and sector size shift. The test's archives are read from the test file
itself, so that list exists only once, and they are always scanned, whatever paths are given.

Usage (from the repo root):
    tools/.venv/bin/python tools/fixtures/mpq.py tests/test_replays tests/test_maps tools/.cache/maps

Exit code 1 if an archive isn't covered. Then add one such archive to the test's data provider, with its expected
values taken once from mpyq.
"""

import argparse
import collections
import os
import re
import struct
import sys

import mpyq

REPO = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
TEST_FILE = os.path.join(REPO, 'tests', 'archive', 'MpqArchiveTest.php')

FLAG_SINGLE_UNIT = 0x01000000
FLAG_SECTOR_CRC = 0x04000000
FLAG_ENCRYPTED = 0x00010000
FLAG_COMPRESSED = 0x00000200


def find_archives(paths):
    for path in paths:
        if os.path.isdir(path):
            for root, _, files in os.walk(path):
                for name in sorted(files):
                    if name.lower().endswith(('.sc2replay', '.s2ma')):
                        yield os.path.join(root, name)
        else:
            yield path


def covered_archives():
    """Absolute paths of the archives in MpqArchiveTest's data providers."""
    with open(TEST_FILE, encoding='utf8') as f:
        source = f.read()
    tests_dir = os.path.join(REPO, 'tests')
    return {
        os.path.realpath(os.path.join(tests_dir, p))
        for p in re.findall(r"'(test_(?:replays|maps)/[^']+\.(?:SC2Replay|s2ma))'", source)
    }


def sector_kinds(archive, block):
    """Per sector of a compressed sectored file: 'raw' or its compression byte, e.g. {'c02', 'raw'}.

    A sector is compressed only when it's stored smaller than its expected size; otherwise it's raw, whatever its
    first byte is. Files with sector checksums have one extra offset table entry (the CRC table).
    """
    sector_size = 512 << archive.header['sector_size_shift']
    count = (block.size + sector_size - 1) // sector_size
    entries = count + (2 if block.flags & FLAG_SECTOR_CRC else 1)
    start = block.offset + archive.header['offset']
    archive.file.seek(start)
    offsets = struct.unpack('<%dI' % entries, archive.file.read(4 * entries))

    kinds = set()
    for i in range(count):
        expected = min(sector_size, block.size - i * sector_size)
        if offsets[i + 1] - offsets[i] >= expected:
            kinds.add('raw')
        else:
            archive.file.seek(start + offsets[i])
            kinds.add('c%02x' % archive.file.read(1)[0])
    return kinds


def file_variant(archive, name):
    """Storage/compression variant of one file, e.g. 'single+c10', 'sectored+c02+raw' or 'sectored+crc+c02'.

    'sectored' alone is an uncompressed sectored file, which has no sector offset table.
    """
    entry = archive.get_hash_table_entry(name)
    if entry is None:
        return None
    block = archive.block_table[entry.block_table_index]
    if block.size == 0:
        return 'empty'

    single = block.flags & FLAG_SINGLE_UNIT
    parts = ['single' if single else 'sectored']
    if block.flags & FLAG_SECTOR_CRC:
        parts.append('crc')
    if block.flags & FLAG_ENCRYPTED:
        parts.append('enc')
    elif block.flags & FLAG_COMPRESSED:
        if single:
            if block.archived_size < block.size:
                archive.file.seek(block.offset + archive.header['offset'])
                parts.append('c%02x' % archive.file.read(1)[0])
        else:
            parts.extend(sorted(sector_kinds(archive, block)))
    return '+'.join(parts)


def scan(path):
    """Return ((format version, sector shift), set of file variants) of an archive."""
    archive = mpyq.MPQArchive(path, listfile=True)
    # latin-1, not utf-8: the MPQ hash works on bytes, and mpyq hashes ord() per character
    variants = {file_variant(archive, n.decode('latin-1')) for n in archive.files if n}
    variants.discard(None)
    # sector checksums only add one entry at the end of the sector offset table; decoding reads the first n + 1
    # entries either way, so 'crc' doesn't make a file variant different for coverage
    variants = {v.replace('+crc', '') for v in variants}
    header = archive.header
    # mpyq's format_version is zero-based: 1 = MPQ format v2, 3 = v4
    return (f"MPQ v{header['format_version'] + 1}", f"sector shift {header['sector_size_shift']}"), variants


def main():
    parser = argparse.ArgumentParser(description='Report MPQ archives with variants MpqArchiveTest misses.')
    parser.add_argument('paths', nargs='+', help='archives or directories containing *.SC2Replay / *.s2ma')
    args = parser.parse_args()

    # what the test covers: per (format version, sector shift), the variants of its archives
    covered = collections.defaultdict(set)
    for path in sorted(covered_archives()):
        layout, variants = scan(path)
        covered[layout] |= variants
    print('MpqArchiveTest covers:')
    for layout, variants in sorted(covered.items()):
        print(f"  {', '.join(layout)}: {', '.join(sorted(variants))}")

    missing = collections.defaultdict(list)  # (layout, missing variants) -> archives
    count = 0
    for path in find_archives(args.paths):
        try:
            layout, variants = scan(path)
        except Exception as e:
            print(f'ERROR  {path}: {type(e).__name__}: {e}')
            continue
        count += 1
        gap = variants - covered.get(layout, set())
        if gap or layout not in covered:
            missing[(layout, ', '.join(sorted(gap)))].append(path)

    print(f'\n{count} archive(s) scanned.')
    if not missing:
        print('All covered.')
        return 0
    for (layout, gap), paths in sorted(missing.items()):
        print(f"NOT COVERED  {', '.join(layout)}, missing [{gap}]: {len(paths)} archive(s)")
        for p in paths[:5]:
            print(f'    {os.path.relpath(p, REPO)}')
    return 1


if __name__ == '__main__':
    sys.exit(main())
