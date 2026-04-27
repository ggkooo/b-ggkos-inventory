#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
STATE_DIR="$PROJECT_ROOT/storage/framework/backend-servers"
STATE_FILE="$STATE_DIR/servers.json"
PORTS=(9001 9002 9003 9004 9005)
INCLUDE_UNKNOWN=false
DRY_RUN=false

for arg in "$@"; do
  case "$arg" in
    --include-unknown-port-listeners)
      INCLUDE_UNKNOWN=true
      ;;
    --dry-run)
      DRY_RUN=true
      ;;
    *)
      echo "Unknown argument: $arg" >&2
      exit 1
      ;;
  esac
done

get_listening_pid() {
  local port="$1"

  if command -v lsof >/dev/null 2>&1; then
    lsof -tiTCP:"$port" -sTCP:LISTEN 2>/dev/null | head -n 1
    return 0
  fi

  if command -v ss >/dev/null 2>&1; then
    ss -ltnp 2>/dev/null | awk -v p=":$port" '$4 ~ p {print $NF}' | sed -E 's/.*pid=([0-9]+).*/\1/' | head -n 1
    return 0
  fi

  if command -v netstat >/dev/null 2>&1; then
    netstat -ltnp 2>/dev/null | awk -v p=":$port" '$4 ~ p {print $7}' | cut -d/ -f1 | head -n 1
    return 0
  fi

  return 0
}

stop_pid() {
  local process_id="$1"
  kill -9 "$process_id" 2>/dev/null || true
}

declare -A SEEN_PIDS=()

if [ -f "$STATE_FILE" ]; then
  while IFS= read -r pid; do
    if [ -z "$pid" ]; then
      continue
    fi

    if [[ -n "${SEEN_PIDS[$pid]:-}" ]]; then
      continue
    fi

    SEEN_PIDS[$pid]=1

    if [ "$DRY_RUN" = true ]; then
      echo "[DRY-RUN] Would stop PID $pid (state file)"
    else
      stop_pid "$pid"
      echo "Stopped PID $pid (state file)"
    fi
  done < <(grep -o '"pid":[0-9]*' "$STATE_FILE" | cut -d: -f2)
fi

if [ "$INCLUDE_UNKNOWN" = true ]; then
  for port in "${PORTS[@]}"; do
    process_id_on_port="$(get_listening_pid "$port")"

    if [ -z "$process_id_on_port" ]; then
      continue
    fi

    if [[ -n "${SEEN_PIDS[$process_id_on_port]:-}" ]]; then
      continue
    fi

    SEEN_PIDS[$process_id_on_port]=1

    if [ "$DRY_RUN" = true ]; then
      echo "[DRY-RUN] Would stop PID $process_id_on_port listening on port $port"
    else
      stop_pid "$process_id_on_port"
      echo "Stopped PID $process_id_on_port listening on port $port"
    fi
  done
fi

if [ "$DRY_RUN" = false ] && [ -f "$STATE_FILE" ]; then
  rm -f "$STATE_FILE"
  echo "Removed state file: $STATE_FILE"
fi

echo "Done."
