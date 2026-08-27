#!/usr/bin/env bash
set -euo pipefail

# Optional brrr notification helper for GitHub Actions.
# Preferred: BRRR_WEBHOOK_SECRET (sent in Authorization header).
# Compatibility: BRRR_WEBHOOK_URL (full webhook URL as documented by brrr).
# Notification delivery is advisory and never gates a Talvoro release.

title="${1:-Talvoro}"
message="${2:-GitHub Actions update}"
open_url="${3:-}"
interruption_level="${4:-active}"

if [ -z "${BRRR_WEBHOOK_SECRET:-}" ] && [ -z "${BRRR_WEBHOOK_URL:-}" ]; then
  printf 'brrr notification skipped: no BRRR_WEBHOOK_SECRET or BRRR_WEBHOOK_URL configured.\n'
  exit 0
fi

command -v curl >/dev/null 2>&1 || {
  printf 'WARNING: brrr notification skipped because curl is unavailable.\n' >&2
  exit 0
}
command -v python3 >/dev/null 2>&1 || {
  printf 'WARNING: brrr notification skipped because python3 is unavailable.\n' >&2
  exit 0
}

payload="$(python3 - "$title" "$message" "$open_url" "$interruption_level" <<'PY'
import json
import sys

title, message, open_url, interruption_level = sys.argv[1:5]
payload = {
    "title": title,
    "message": message,
    "thread_id": "talvoro-release",
    "interruption_level": interruption_level,
}
if open_url:
    payload["open_url"] = open_url
print(json.dumps(payload, ensure_ascii=False, separators=(",", ":")))
PY
)"

curl_args=(
  --fail
  --silent
  --show-error
  --retry 2
  --retry-all-errors
  --connect-timeout 5
  --max-time 15
  -X POST
  -H 'Content-Type: application/json'
  --data-binary "$payload"
)

set +e
if [ -n "${BRRR_WEBHOOK_SECRET:-}" ]; then
  curl "${curl_args[@]}" \
    -H "Authorization: Bearer ${BRRR_WEBHOOK_SECRET}" \
    'https://api.brrr.now/v1/send' >/dev/null
  status=$?
else
  curl "${curl_args[@]}" "${BRRR_WEBHOOK_URL}" >/dev/null
  status=$?
fi
set -e

if [ "$status" -ne 0 ]; then
  printf 'WARNING: brrr notification delivery failed (curl exit %s); release execution continues.\n' "$status" >&2
else
  printf 'brrr notification sent.\n'
fi

exit 0
