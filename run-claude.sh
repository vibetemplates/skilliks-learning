#!/usr/bin/env bash
# Usage:
#   ./run-claude.sh "your prompt here"
#   ./run-claude.sh c16c12a2-95ca-49e2-9d65-86fa1a3bf0a3 "your prompt here"

set -Eeuo pipefail

if (($# == 0)); then
  echo "Usage: $0 [session_id] \"your prompt here\"" >&2
  exit 1
fi

# Detect UUID-like session id
SESSION_ID=""
if [[ "${1-}" =~ ^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$ ]]; then
  SESSION_ID="$1"
  shift
fi

if (($# == 0)); then
  echo "Error: missing prompt" >&2
  exit 1
fi

PROMPT="$*"

OUTFILE="$(mktemp -t claude-output.XXXXXX)"
ERRFILE="${OUTFILE}.err"
: > "$OUTFILE"
: > "$ERRFILE"

export OUTFILE SESSION_ID

nohup bash -c '
  set -Eeuo pipefail
  if [[ -n "${SESSION_ID}" ]]; then
    RESUME_ARGS=(--resume "${SESSION_ID}")
  else
    RESUME_ARGS=()
  fi

  exec stdbuf -o0 -e0 \
    claude --dangerously-skip-permissions "${RESUME_ARGS[@]}" --print --verbose --output-format json "$1" 2>&1 \
    | stdbuf -o0 tr "\r" "\n" >> "${OUTFILE}"
' bash "$PROMPT" \
</dev/null >/dev/null 2>>"$ERRFILE" &

PID=$!

printf '{ "pid": %d, "outfile": "%s", "errfile": "%s" }\n' "$PID" "$OUTFILE" "$ERRFILE"

