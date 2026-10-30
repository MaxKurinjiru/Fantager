#!/usr/bin/env bash
# Multi-season kingdom simulation as short-lived PHP processes.
# Each advance schedules one week (or season). Each process call consumes one
# bounded Messenger batch so PHP / Docker timeouts cannot kill an 8-season run.
#
# Examples:
#   bash scripts/simulate-kingdom.sh --init --seasons=8 -o /tmp/seasons.jsonl
#   bash scripts/simulate-kingdom.sh --kingdom-id=1 --seasons=8
#   bash scripts/simulate-kingdom.sh --init --dry-run

set -euo pipefail

SEASONS=8
STEP=week
NAME="Balance Sim"
PACE=classic
OUTPUT=""
LIMIT=200
TIME_LIMIT=45
MEMORY_LIMIT=128M
INIT=0
DRY_RUN=0
WITH_TEST_USERS=0
KINGDOM_ID=""

usage() {
  cat <<'EOF'
Usage: bash scripts/simulate-kingdom.sh [--init | --kingdom-id=ID] [options]

  --init                 Create an NPC-only kingdom, then simulate it
  --kingdom-id=ID        Continue an existing kingdom
  --name=NAME            Kingdom name for --init (default: Balance Sim)
  --pace=PRESET          Pace for --init (default: classic)
  --with-test-users      With --init, create player1–3@example.com
  --seasons=N            Stop after N frozen season reports (default: 8)
  --step=week|season     Advance chunk (default: week)
  -o, --output=PATH      Write season-report JSONL when finished
  --limit=N              Messenger messages per process invocation (default: 200)
  --time-limit=SEC       Messenger time limit per process invocation (default: 45)
  --memory-limit=SIZE    Messenger memory limit (default: 128M)
  --dry-run              Init (optional) + print status / next horizon, then exit
  -h, --help             Show this help

PHP commands (each is one process):
  app:kingdom:simulate:init
  app:kingdom:simulate:advance --kingdom-id= --step=
  app:kingdom:simulate:process --kingdom-id= --limit= --time-limit=
  app:kingdom:simulate:status  --kingdom-id= --json
EOF
}

console() {
  if [[ -f /.dockerenv ]] || [[ -n "${FANTAGER_IN_CONTAINER:-}" ]]; then
    php bin/console "$@"
  else
    docker exec -u apache fantager-web php bin/console "$@"
  fi
}

# Integer JSON field from a compact object (no extra parser dependency).
json_int() {
  local json=$1
  local field=$2
  printf '%s' "$json" | sed -n "s/.*\"${field}\":\\([0-9][0-9]*\\).*/\\1/p" | head -n1
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --init) INIT=1 ;;
    --dry-run) DRY_RUN=1 ;;
    --with-test-users) WITH_TEST_USERS=1 ;;
    --kingdom-id) KINGDOM_ID="$2"; shift ;;
    --kingdom-id=*) KINGDOM_ID="${1#*=}" ;;
    --name) NAME="$2"; shift ;;
    --name=*) NAME="${1#*=}" ;;
    --pace) PACE="$2"; shift ;;
    --pace=*) PACE="${1#*=}" ;;
    --seasons) SEASONS="$2"; shift ;;
    --seasons=*) SEASONS="${1#*=}" ;;
    --step) STEP="$2"; shift ;;
    --step=*) STEP="${1#*=}" ;;
    -o|--output) OUTPUT="$2"; shift ;;
    --output=*) OUTPUT="${1#*=}" ;;
    --limit) LIMIT="$2"; shift ;;
    --limit=*) LIMIT="${1#*=}" ;;
    --time-limit) TIME_LIMIT="$2"; shift ;;
    --time-limit=*) TIME_LIMIT="${1#*=}" ;;
    --memory-limit) MEMORY_LIMIT="$2"; shift ;;
    --memory-limit=*) MEMORY_LIMIT="${1#*=}" ;;
    -h|--help) usage; exit 0 ;;
    *)
      echo "Unknown argument: $1" >&2
      usage >&2
      exit 1
      ;;
  esac
  shift
done

if [[ "$INIT" -eq 1 && -n "$KINGDOM_ID" ]]; then
  echo "Pass either --init or --kingdom-id, not both." >&2
  exit 1
fi
if [[ "$INIT" -eq 0 && -z "$KINGDOM_ID" ]]; then
  echo "Pass --init or --kingdom-id=." >&2
  usage >&2
  exit 1
fi

if [[ "$INIT" -eq 1 ]]; then
  init_args=(app:kingdom:simulate:init --name="$NAME" --pace="$PACE" --json)
  if [[ "$WITH_TEST_USERS" -eq 1 ]]; then
    init_args+=(--with-test-users)
  fi
  INIT_JSON="$(console "${init_args[@]}")"
  echo "$INIT_JSON"
  KINGDOM_ID="$(json_int "$INIT_JSON" kingdom_id)"
  if [[ -z "$KINGDOM_ID" ]]; then
    echo "Init did not return kingdom_id." >&2
    exit 1
  fi
fi

if [[ "$DRY_RUN" -eq 1 ]]; then
  console app:kingdom:simulate:status --kingdom-id="$KINGDOM_ID" --json || true
  console app:kingdom:simulate:advance --kingdom-id="$KINGDOM_ID" --step="$STEP" --dry-run --json || true
  echo "kingdom_id=$KINGDOM_ID"
  exit 0
fi

process_until_idle() {
  local rc=0
  while true; do
    set +e
    console app:kingdom:simulate:process \
      --kingdom-id="$KINGDOM_ID" \
      --limit="$LIMIT" \
      --time-limit="$TIME_LIMIT" \
      --memory-limit="$MEMORY_LIMIT"
    rc=$?
    set -e
    case "$rc" in
      0) return 0 ;;
      2) ;;
      *)
        echo "Process failed (exit $rc). Inspect app:ticks:status / app:kingdom:diagnose, then app:ticks:retry --kingdom-id=$KINGDOM_ID" >&2
        return "$rc"
        ;;
    esac
  done
}

completed_seasons() {
  local status_json rc=0
  set +e
  status_json="$(console app:kingdom:simulate:status --kingdom-id="$KINGDOM_ID" --json)"
  rc=$?
  set -e
  if [[ "$rc" -eq 1 ]]; then
    echo "$status_json" >&2
    echo "Pipeline failed. Use app:ticks:retry --kingdom-id=$KINGDOM_ID after fixing the cause." >&2
    exit 1
  fi
  json_int "$status_json" completed_seasons
}

echo "Simulating kingdom_id=$KINGDOM_ID until $SEASONS season report(s), step=$STEP"

process_until_idle
COMPLETED="$(completed_seasons)"
echo "Completed seasons: ${COMPLETED:-0}/$SEASONS"

empty_advances=0
while [[ "${COMPLETED:-0}" -lt "$SEASONS" ]]; do
  set +e
  console app:kingdom:simulate:advance --kingdom-id="$KINGDOM_ID" --step="$STEP"
  adv_rc=$?
  set -e
  if [[ "$adv_rc" -eq 2 ]]; then
    echo "Advance skipped (busy); processing remaining work first."
    process_until_idle
    continue
  fi
  if [[ "$adv_rc" -ne 0 ]]; then
    echo "Advance failed (exit $adv_rc)." >&2
    exit "$adv_rc"
  fi

  process_until_idle
  NEXT="$(completed_seasons)"
  if [[ "$NEXT" == "$COMPLETED" ]]; then
    empty_advances=$((empty_advances + 1))
    if [[ "$empty_advances" -ge 3 ]]; then
      echo "Three steps produced no new season report. Stopping. Check app:kingdom:simulate:status --kingdom-id=$KINGDOM_ID" >&2
      exit 1
    fi
  else
    empty_advances=0
  fi
  COMPLETED="$NEXT"
  echo "Completed seasons: ${COMPLETED:-0}/$SEASONS"
done

if [[ -n "$OUTPUT" ]]; then
  console app:season:report:export --kingdom-id="$KINGDOM_ID" -o "$OUTPUT"
else
  console app:season:report:export --kingdom-id="$KINGDOM_ID" --alerts-only
fi
console app:kingdom:diagnose "$KINGDOM_ID" || true
echo "Done. kingdom_id=$KINGDOM_ID"
