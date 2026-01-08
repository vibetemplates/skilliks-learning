#!/usr/bin/env bash
# Usage: ./run-coder-json.sh "Your prompt here"

if [ -z "$1" ]; then
  echo "Usage: $0 \"your prompt here\"" >&2
  exit 1
fi  
    
PROMPT="$*"
OUTFILE=$(mktemp)
  
# Start the process in background, redirect stdout+stderr to OUTFILE
( skilliks-coder-cli -p "$PROMPT" --yolo >"$OUTFILE" 2>&1 ) &
PID=$!
  
# Immediately return PID + file path in JSON
echo "{ \"pid\": $PID, \"outfile\": \"$OUTFILE\" }"


