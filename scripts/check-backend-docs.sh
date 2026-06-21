#!/usr/bin/env bash
# check-backend-docs.sh — verify routes, entities, enums, and PHP translation keys are documented.
#
# Usage: bash scripts/check-backend-docs.sh
# Exit 0 = pass, 1 = violations found
#
# Requires: PHP CLI; ripgrep (rg).

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

VIOLATION_COUNT=0
ROUTE_MAP="$ROOT/docs/route-map.md"
ENTITY_REFERENCE="$ROOT/docs/entity-reference.md"
ENUMS_SECTION_START='## Enums (PHP backed enums)'
ENUMS_SECTION_END='## Entity Count Summary'

echo "Fantager backend docs check"
echo "==========================="
echo

if ! command -v php >/dev/null 2>&1; then
  echo -e "${RED}ERROR: PHP CLI is required.${NC}" >&2
  exit 1
fi

if ! command -v rg >/dev/null 2>&1; then
  echo -e "${RED}ERROR: ripgrep (rg) is required.${NC}" >&2
  exit 1
fi

normalize_path() {
  echo "$1" | sed -E 's/\{[a-zA-Z0-9_]+\}/\{\*\}/g'
}

yaml_has_key() {
  local yaml_file="$1"
  local key="$2"
  php "$ROOT/scripts/flatten-yaml-keys.php" "$yaml_file" | tr -d '\r' | rg -xF "$key" >/dev/null
}

# --- Build normalized path index from route-map.md ---
declare -A ROUTE_MAP_PATHS=()
while IFS= read -r doc_path; do
  ROUTE_MAP_PATHS["$(normalize_path "$doc_path")"]=1
done < <(rg -o '\`(/[^`]*)\`' "$ROUTE_MAP" | sed -E 's/^`|`$//g' | sort -u)

path_documented() {
  local norm
  norm="$(normalize_path "$1")"
  [[ -n "${ROUTE_MAP_PATHS[$norm]:-}" ]]
}

# --- Routes: code → route-map.md ---
MISSING_ROUTES=()
while IFS=$'\t' read -r _method path file; do
  [[ -z "$path" ]] && continue
  if ! path_documented "$path"; then
    MISSING_ROUTES+=("$_method $path ($file)")
  fi
done < <(php "$ROOT/scripts/extract-routes.php" | tr -d '\r')

if [[ ${#MISSING_ROUTES[@]} -gt 0 ]]; then
  echo -e "${RED}FAIL: Routes in code but not found in docs/route-map.md${NC}"
  printf '  %s\n' "${MISSING_ROUTES[@]}"
  echo
  VIOLATION_COUNT=$((VIOLATION_COUNT + 1))
fi

# --- Entities: src/Entity → entity-reference.md ---
MISSING_ENTITIES=()
while IFS= read -r entity_file; do
  base="$(basename "$entity_file" .php)"
  rel="${entity_file#$ROOT/src/Entity/}"
  rel="${rel%.php}"
  fqcn="App\\Entity\\${rel//\//\\}"

  if rg -qF "**$base**" "$ENTITY_REFERENCE" 2>/dev/null; then
    continue
  fi
  if rg -qF "$fqcn" "$ENTITY_REFERENCE" 2>/dev/null; then
    continue
  fi

  MISSING_ENTITIES+=("src/Entity/$rel.php")
done < <(find "$ROOT/src/Entity" -name '*.php' -type f | sort)

if [[ ${#MISSING_ENTITIES[@]} -gt 0 ]]; then
  echo -e "${RED}FAIL: Entity classes not referenced in docs/entity-reference.md${NC}"
  printf '  %s\n' "${MISSING_ENTITIES[@]}"
  echo
  VIOLATION_COUNT=$((VIOLATION_COUNT + 1))
fi

# --- Enums: src/Enum → entity-reference.md § Enums ---
ENUMS_SECTION="$(sed -n "/${ENUMS_SECTION_START}/,/${ENUMS_SECTION_END}/p" "$ENTITY_REFERENCE")"
MISSING_ENUMS=()
while IFS= read -r enum_file; do
  base="$(basename "$enum_file" .php)"
  if echo "$ENUMS_SECTION" | rg -qF "\`${base}\`"; then
    continue
  fi
  MISSING_ENUMS+=("src/Enum/$base.php")
done < <(find "$ROOT/src/Enum" -name '*.php' -type f | sort)

if [[ ${#MISSING_ENUMS[@]} -gt 0 ]]; then
  echo -e "${RED}FAIL: Enum classes not listed in docs/entity-reference.md (Enums section)${NC}"
  printf '  %s\n' "${MISSING_ENUMS[@]}"
  echo
  VIOLATION_COUNT=$((VIOLATION_COUNT + 1))
fi

# --- Translation keys: PHP → messages.*.yaml / validators.*.yaml ---
MISSING_I18N=()
while IFS=$'\t' read -r domain key; do
  [[ -z "$domain" || -z "$key" ]] && continue

  case "$domain" in
    messages)
      en_file="$ROOT/translations/messages.en.yaml"
      cs_file="$ROOT/translations/messages.cs.yaml"
      ;;
    validators)
      en_file="$ROOT/translations/validators.en.yaml"
      cs_file="$ROOT/translations/validators.cs.yaml"
      ;;
    *)
      continue
      ;;
  esac

  if ! yaml_has_key "$en_file" "$key"; then
    MISSING_I18N+=("$domain $key missing in $(basename "$en_file")")
  fi
  if ! yaml_has_key "$cs_file" "$key"; then
    MISSING_I18N+=("$domain $key missing in $(basename "$cs_file")")
  fi
done < <(php "$ROOT/scripts/extract-translation-keys.php" | tr -d '\r')

if [[ ${#MISSING_I18N[@]} -gt 0 ]]; then
  echo -e "${RED}FAIL: PHP translation keys missing from YAML catalogs${NC}"
  printf '  %s\n' "${MISSING_I18N[@]}"
  echo
  VIOLATION_COUNT=$((VIOLATION_COUNT + 1))
fi

# --- Stale route-map paths (warn only) ---
CODE_PATHS_NORM=()
while IFS=$'\t' read -r _m path _f; do
  CODE_PATHS_NORM+=("$(normalize_path "$path")")
done < <(php "$ROOT/scripts/extract-routes.php" | tr -d '\r')

STALE=()
while IFS= read -r doc_path; do
  norm_doc="$(normalize_path "$doc_path")"
  matched=0
  for code_norm in "${CODE_PATHS_NORM[@]}"; do
    [[ "$code_norm" == "$norm_doc" ]] && matched=1 && break
  done
  [[ "$matched" -eq 1 ]] && continue

  map_line="$(rg -nF "\`$doc_path\`" "$ROUTE_MAP" | head -1 || true)"
  [[ -z "$map_line" ]] && continue
  [[ "$map_line" == *planned* || "$map_line" == *Symfony* ]] && continue

  STALE+=("$doc_path")
done < <(rg -o '\`(/[^`]*)\`' "$ROUTE_MAP" | sed -E 's/^`|`$//g' | sort -u)

if [[ ${#STALE[@]} -gt 0 ]]; then
  echo -e "${YELLOW}WARN: route-map.md paths with no matching controller Route attribute:${NC}"
  printf '  %s\n' "${STALE[@]}" | head -15
  [[ ${#STALE[@]} -gt 15 ]] && echo "  ... and $((${#STALE[@]} - 15)) more"
  echo
fi

# --- Summary ---
if [[ "$VIOLATION_COUNT" -gt 0 ]]; then
  echo -e "${RED}Backend docs check FAILED: ${VIOLATION_COUNT} category/categories with violations.${NC}"
  echo "See docs/backend-agent-cheatsheet.md and docs/screen-code-map.md"
  exit 1
fi

echo -e "${GREEN}Backend docs check PASSED.${NC}"
exit 0
