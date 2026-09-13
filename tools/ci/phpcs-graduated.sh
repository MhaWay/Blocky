#!/usr/bin/env bash
# Graduated PHPCS gate: files listed here must stay WordPress-Coding-Standards
# clean (errors only; warnings are tolerated). Add a file to the list in the
# same PR that cleans it — never remove one without a plan.
set -euo pipefail
cd "$(dirname "$0")/../../packages/core-plugin"

GRADUATED=(#
  "src/Compiler/SiteStylesheet.php"
  "src/Compiler/TailwindBinary.php"
  "src/Support/Access.php"
  "src/Support/ApiAudit.php"
  "src/Support/ApiAuth.php"
  "src/Support/ApiKeyCodec.php"
  "src/Support/CssSanitizer.php"
  "src/Support/PropsValidator.php"
  "src/Cli/KeyCommand.php"
  "tests/Unit/ApiKeyCodecTest.php"
)

fail=0
for file in "${GRADUATED[@]}"; do
  rc=0
  # rc 0 = clean, rc 1 = warnings (tolerated); rc >= 2 = errors.
  vendor/bin/phpcs --standard=phpcs.xml --warning-severity=0 "$file" > /dev/null 2>&1 || rc=$?
  if [ "$rc" -ge 2 ]; then
    echo "PHPCS regression: $file (rc=$rc)"
    vendor/bin/phpcs --standard=phpcs.xml "$file" | sed -n '/FILE\|ERROR/p' | head -8
    fail=1
  fi
done
exit $fail
