#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

cd "${PLUGIN_DIR}"

bash bin/setup-e2e.sh
npx playwright install chromium
npx playwright test
