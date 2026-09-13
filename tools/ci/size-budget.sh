#!/usr/bin/env bash
# Frontend size budgets (docs/research/05): no artifact may exceed its
# gzip ceiling. Failing here means a page got heavier by design - raise
# the number only with a review, never silently.
#   vocabulary (L3)            <= 30 KB gz  (issue #6)
#   runtime entry JS           <= 12 KB gz  (spike budget)
#   runtime entry CSS          <=  2 KB gz
#   theme stylesheet           <= 18 KB gz
#   tokens L1                  <=  2 KB gz
set -euo pipefail
cd "$(dirname "$0")/../.."

fail=0
limit_kb=30

check() {
  local label="$1" path="$2" max="$3"
  if [ ! -f "$path" ]; then
    echo "SIZE-MISSING $label $path"
    fail=1
    return
  fi
  local gz
  gz=$(gzip -9c "$path" | wc -c)
  if [ "$gz" -gt "$max" ]; then
    echo "SIZE-FAIL $label: ${gz}B gz > ${max}B budget ($path)"
    fail=1
  else
    echo "SIZE-OK   $label: ${gz}B gz (budget ${max}B)"
  fi
}

runtime_js=$(ls packages/core-plugin/dist/assets/main-*.js 2>/dev/null | head -1 || true)
runtime_css=$(ls packages/core-plugin/dist/assets/main-*.css 2>/dev/null | head -1 || true)
theme_css=$(ls packages/theme/dist/assets/theme-*.css 2>/dev/null | head -1 || true)

check vocabulary  packages/core-plugin/resources/css/vocabulary.min.css 30720
check tokens      packages/core-plugin/resources/css/tokens.tailwind.css 2048
[ -n "$runtime_js" ]  && check runtime-js  "$runtime_js"  12288
[ -n "$runtime_css" ] && check runtime-css "$runtime_css" 2048
[ -n "$theme_css" ]   && check theme-css   "$theme_css"  18432

if [ "$fail" -ne 0 ]; then
  echo "size budget gate FAILED"
  exit 1
fi
echo "size budgets ok"
