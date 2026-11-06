#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../.."
"${PYTHON:-tools/.venv/bin/python}" tools/generate/protocols.py | php tools/generate/protocols.php "$@"
