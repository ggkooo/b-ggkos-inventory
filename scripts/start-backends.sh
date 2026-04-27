#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
STATE_DIR="$PROJECT_ROOT/storage/framework/backend-servers"
STATE_FILE="$STATE_DIR/servers.json"
PORTS=(9001 9002 9003 9004 9005)
STOP_EXISTING=false
DRY_RUN=false

for arg in "$@"; do
  case "$arg" in
    --stop-existing-on-ports)
      STOP_EXISTING=true
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

mkdir -p "$STATE_DIR"

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

if [ ! -f "$PROJECT_ROOT/artisan" ]; then
  echo "Could not find artisan at: $PROJECT_ROOT/artisan" >&2
  exit 1
fi

if [ "$STOP_EXISTING" = true ]; then
  for port in "${PORTS[@]}"; do
    existing_pid="$(get_listening_pid "$port")"
    if [ -n "$existing_pid" ]; then
      if [ "$DRY_RUN" = true ]; then
        echo "[DRY-RUN] Would stop PID $existing_pid on port $port"
      else
        kill -9 "$existing_pid" 2>/dev/null || true
        echo "Stopped existing PID $existing_pid on port $port"
      fi
    fi
  done
fi

json_entries=()

for port in "${PORTS[@]}"; do
  stdout_log="$STATE_DIR/backend-$port.stdout.log"
  stderr_log="$STATE_DIR/backend-$port.stderr.log"
  existing_pid="$(get_listening_pid "$port")"

  if [ -n "$existing_pid" ]; then
    echo "Port $port already listening (PID $existing_pid)."
    json_entries+=("{\"port\":$port,\"pid\":$existing_pid,\"stdout_log\":\"$stdout_log\",\"stderr_log\":\"$stderr_log\",\"started_at\":\"$(date -Iseconds)\"}")
    continue
  fi

  cmd=(env BACKEND_SERVER_ROLE=backend php "$PROJECT_ROOT/artisan" serve --host=127.0.0.1 "--port=$port")

  if [ "$DRY_RUN" = true ]; then
    echo "[DRY-RUN] Would start: ${cmd[*]}"
    continue
  fi

  nohup "${cmd[@]}" >"$stdout_log" 2>"$stderr_log" &
  process_pid=$!
  disown "$process_pid" 2>/dev/null || true
  sleep 1

  if ! kill -0 "$process_pid" 2>/dev/null; then
    echo "Backend on port $port exited immediately. Check: $stdout_log and $stderr_log" >&2
    continue
  fi

  echo "Started backend on port $port (PID $process_pid)"
  json_entries+=("{\"port\":$port,\"pid\":$process_pid,\"stdout_log\":\"$stdout_log\",\"stderr_log\":\"$stderr_log\",\"started_at\":\"$(date -Iseconds)\"}")
done

if [ "$DRY_RUN" = false ]; then
  printf '[%s]\n' "$(IFS=,; echo "${json_entries[*]}")" > "$STATE_FILE"
  echo "Saved state file: $STATE_FILE"
fi

echo "Done."
