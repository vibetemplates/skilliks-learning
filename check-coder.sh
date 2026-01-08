#!/usr/bin/env bash
# Usage: ./check-coder.sh <pid> <outfile>
# Returns JSON with either:
#   { "status": "running", "response_so_far": "...", "bytes": N }
# or
#   { "status": "done", "response": "...", "bytes": N }

set -euo pipefail

PID="${1:-}"
OUTFILE="${2:-}"
  
if [ -z "$PID" ] || [ -z "$OUTFILE" ]; then
  echo "Usage: $0 <pid> <outfile>" >&2
  exit 1
fi

if [ ! -f "$OUTFILE" ]; then
  echo '{ "status": "unknown", "error": "output file not found" }'
  exit 0
fi

# Current size (bytes) of the streamed file
BYTES=$(wc -c < "$OUTFILE" | tr -d ' ')
  
encode_json_file() {
  # Prefer jq, then python3, else sed escapes
  if command -v jq >/dev/null 2>&1; then
    jq -Rs . < "$OUTFILE"
  elif command -v python3 >/dev/null 2>&1; then
    python3 - <<PY
import json, sys
print(json.dumps(open("$OUTFILE","r",encoding="utf-8",errors="replace").read()))
PY
  else 
    # Minimal escaping: \, ", CR, LF, TAB
    sed -e 's/\\/\\\\/g' \
        -e 's/"/\\"/g' \
        -e $'s/\r/\\r/g' \
        -e $'s/\n/\\n/g' \
        -e $'s/\t/\\t/g' "$OUTFILE" | awk '{printf "%s", $0}'
  fi
}

if ps -p "$PID" > /dev/null 2>&1; then
  # Still running: return what we have so far
  if command -v jq >/dev/null 2>&1 || command -v python3 >/dev/null 2>&1; then
    RESP=$(encode_json_file)
    printf '{ "status": "running", "response_so_far": %s, "bytes": %s }\n' "$RESP" "$BYTES"
  else
    RESP=$(encode_json_file)
    printf '{ "status": "running", "response_so_far": "%s", "bytes": %s }\n' "$RESP" "$BYTES"
  fi
else
  # Done: return final output
  if command -v jq >/dev/null 2>&1 || command -v python3 >/dev/null 2>&1; then
    RESP=$(encode_json_file)
    printf '{ "status": "done", "response": %s, "bytes": %s }\n' "$RESP" "$BYTES"
  else
    RESP=$(encode_json_file)
    printf '{ "status": "done", "response": "%s", "bytes": %s }\n' "$RESP" "$BYTES"
  fi
  # (Optional) uncomment if you want auto-cleanup after completion:
  # rm -f "$OUTFILE"
fi
