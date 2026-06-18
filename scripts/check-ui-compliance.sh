#!/usr/bin/env bash
# check-ui-compliance.sh — detect Tailwind utilities and other UI guideline violations in Fantager.
#
# Usage: bash scripts/check-ui-compliance.sh
# Exit 0 = pass, 1 = violations found

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

VIOLATION_COUNT=0

if ! command -v rg >/dev/null 2>&1; then
  echo -e "${RED}ERROR: ripgrep (rg) is required but not installed.${NC}" >&2
  exit 1
fi

TWIG_PATHS=(templates)
TWIG_GLOBS=(--glob '*.twig' --glob '!templates/email/**')

echo "Fantager UI compliance check"
echo "============================"
echo

run_check() {
  local category="$1"
  local pattern="$2"
  shift 2
  local extra_args=("$@")

  local matches
  matches="$(rg -n "${extra_args[@]}" "$pattern" "${TWIG_PATHS[@]}" 2>/dev/null || true)"

  if [[ -n "$matches" ]]; then
    echo -e "${RED}FAIL: ${category}${NC}"
    echo "$matches"
    echo
    VIOLATION_COUNT=$((VIOLATION_COUNT + 1))
  fi
}

# --- Twig: layout & spacing utilities in class attributes ---
run_check "Twig flex layout utility" \
  'class="[^"]*\bflex ' \
  "${TWIG_GLOBS[@]}"

run_check "Twig grid-cols utility" \
  'class="[^"]*\bgrid grid-cols' \
  "${TWIG_GLOBS[@]}"

run_check "Twig gap-* utility" \
  'class="[^"]*\bgap-[0-9]' \
  "${TWIG_GLOBS[@]}"

run_check "Twig space-y/x utility" \
  'class="[^"]*\bspace-[xy]-' \
  "${TWIG_GLOBS[@]}"

run_check "Twig justify-* / items-* utility" \
  'class="[^"]*\b(justify-|items-)' \
  "${TWIG_GLOBS[@]}"

run_check "Twig margin/padding utility (m*/p* + number)" \
  'class="[^"]*\b[mp][trblxy]?-[0-9]' \
  "${TWIG_GLOBS[@]}"

run_check "Twig responsive prefix (sm:/md:/lg:)" \
  'class="[^"]*\b(sm|md|lg|xl|2xl):' \
  "${TWIG_GLOBS[@]}"

run_check "Twig col-span utility" \
  'class="[^"]*\bcol-span' \
  "${TWIG_GLOBS[@]}"

run_check "Twig typography utility" \
  'class="[^"]*\btext-(xs|sm|base|lg|xl|2xl|3xl|4xl|5xl|center|left|right)' \
  "${TWIG_GLOBS[@]}"

run_check "Twig font-* utility" \
  'class="[^"]*\bfont-(semibold|bold|medium|extrabold|normal|black)' \
  "${TWIG_GLOBS[@]}"

run_check "Twig Tailwind palette color utility" \
  'class="[^"]*\b(text|bg|border)-(red|green|blue|emerald|amber|gray|grey|slate|zinc|neutral|stone|white|black|yellow|purple|pink|indigo|cyan|teal|orange|lime|fuchsia|violet|rose|sky)-' \
  "${TWIG_GLOBS[@]}"

run_check "Twig overflow utility" \
  'class="[^"]*\boverflow-(x|y)-' \
  "${TWIG_GLOBS[@]}"

run_check "Twig shadow utility" \
  'class="[^"]*\bshadow-(sm|md|lg|xl|2xl|inner|none)' \
  "${TWIG_GLOBS[@]}"

run_check "Twig width/height utility" \
  'class="[^"]*\b[wh]-[0-9]' \
  "${TWIG_GLOBS[@]}"

run_check "Twig arbitrary min/max height" \
  'class="[^"]*\b(min|max)-[hw]-\[' \
  "${TWIG_GLOBS[@]}"

run_check "Twig whitespace utility" \
  'class="[^"]*\bwhitespace-' \
  "${TWIG_GLOBS[@]}"

run_check "Twig cursor-pointer (use .form-check or semantic class)" \
  'class="[^"]*\bcursor-pointer' \
  "${TWIG_GLOBS[@]}"

run_check "Twig flex-1 utility" \
  'class="[^"]*\bflex-1\b' \
  "${TWIG_GLOBS[@]}"

run_check "Twig shrink-0 utility" \
  'class="[^"]*\bshrink-0\b' \
  "${TWIG_GLOBS[@]}"

run_check "Twig rounded utility (bare)" \
  'class="[^"]*\brounded\b' \
  "${TWIG_GLOBS[@]}"

run_check "Twig uppercase utility" \
  'class="[^"]*(?<![-\w])uppercase(?!\w)' \
  "${TWIG_GLOBS[@]}" \
  --pcre2

run_check "Twig italic utility" \
  'class="[^"]*(?<![-\w])italic(?!\w)' \
  "${TWIG_GLOBS[@]}" \
  --pcre2

run_check "Twig select-none utility" \
  'class="[^"]*\bselect-none\b' \
  "${TWIG_GLOBS[@]}"

run_check "Twig inline style attribute (non-progress)" \
  'style="' \
  "${TWIG_GLOBS[@]}" \
  --glob '!templates/components/ui/progress_bar.html.twig'

# --- SCSS: raw rgba outside tokens (heuristic) ---
SCSS_MATCHES="$(rg -n 'rgba\(' assets/styles/components/ --glob '*.scss' 2>/dev/null || true)"
if [[ -n "$SCSS_MATCHES" ]]; then
  echo -e "${YELLOW}WARN: rgba() in component SCSS (prefer tokens / color-mix):${NC}"
  echo "$SCSS_MATCHES"
  echo
fi

# --- Summary ---
if [[ "$VIOLATION_COUNT" -gt 0 ]]; then
  echo -e "${RED}UI compliance FAILED: ${VIOLATION_COUNT} category/categories with violations.${NC}"
  echo "See docs/ui-agent-cheatsheet.md and .cursor/rules/twig-ui.mdc"
  exit 1
fi

echo -e "${GREEN}UI compliance PASSED.${NC}"
exit 0
