"""JSON output for fixtures: one lossless mapping of oracle values, deterministic files.

Value mapping:
- bytes: a string if they are valid UTF-8, otherwise {"hex": "..."};
- tuples are s2protocol bitarrays, `(length, value)`, up to 256 bits wide: {"bits": length, "hex": value as
  big-endian hex, zero-padded to ceil(length / 4) digits, so '' for zero bits}. The versioned decoder's variant
  returns bytes as the value: {"bits": length, "bytes": hex};
- dict keys become strings (JSON has no others);
- ints must fit into a signed 64-bit integer (PHP's int), anything else is an error.

Files:
- write_json(): pretty-printed (tab indent), for small structures;
- write_json_list(): a JSON array with one compact element per line, so diffs show whole events;
- write_jsonl_gz(): one compact element per line, gzipped with a fixed header (no name, mtime 0). The bytes
  can still differ between zlib builds, so compare these files by content, never by bytes.
"""

import gzip
import json
from pathlib import Path
from typing import Any, Iterable

INT64_MIN = -(1 << 63)
INT64_MAX = (1 << 63) - 1


def clean(value: Any, path: str = '$') -> Any:
    if isinstance(value, bool) or value is None or isinstance(value, (str, float)):
        return value
    if isinstance(value, int):
        if not INT64_MIN <= value <= INT64_MAX:
            raise ValueError(f'{path}: {value} does not fit into a signed 64-bit integer')
        return value
    if isinstance(value, (bytes, bytearray)):
        try:
            return bytes(value).decode('utf-8')
        except UnicodeDecodeError:
            return {'hex': bytes(value).hex()}
    if isinstance(value, dict):
        return {str(k): clean(v, f'{path}.{k}') for k, v in value.items()}
    if isinstance(value, tuple):
        match value:
            case (int() as length, int() as bits):
                return {'bits': length, 'hex': format(bits, f'0{(length + 3) // 4}x') if length else ''}
            case (int() as length, bytes() as data):
                return {'bits': length, 'bytes': data.hex()}
        raise TypeError(f'{path}: unexpected tuple {value!r}')
    if isinstance(value, list):
        return [clean(v, f'{path}[{i}]') for i, v in enumerate(value)]
    raise TypeError(f'{path}: cannot write {type(value).__name__} to JSON')


def _compact(value: Any) -> str:
    return json.dumps(clean(value), ensure_ascii=False, allow_nan=False, separators=(',', ':'))


def write_json(path: Path, value: Any) -> None:
    path.write_text(json.dumps(clean(value), ensure_ascii=False, allow_nan=False, indent='\t') + '\n', encoding='utf-8')


def write_json_list(path: Path, values: Iterable[Any]) -> None:
    lines = [_compact(v) for v in values]
    body = '[\n' + ',\n'.join(lines) + '\n]\n' if lines else '[]\n'
    path.write_text(body, encoding='utf-8')


def write_jsonl_gz(path: Path, values: Iterable[Any]) -> None:
    with open(path, 'wb') as raw, gzip.GzipFile(filename='', mode='wb', fileobj=raw, mtime=0, compresslevel=9) as gz:
        for value in values:
            gz.write((_compact(value) + '\n').encode('utf-8'))


def read_jsonl_gz(path: Path) -> Iterable[Any]:
    with gzip.open(path, 'rt', encoding='utf-8') as gz:
        for line in gz:
            yield json.loads(line)
