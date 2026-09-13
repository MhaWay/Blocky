#!/usr/bin/env bash
# Optional PageSpeed Insights probe for a PUBLIC Blocky URL (the local
# sandbox is unreachable from PSI). Needs no API key for casual use; set
# PSI_KEY for real quotas. Not part of CI - run it against staging:
#   BLOCKY_PSI_URL=https://staging.example.com/ bash tools/ci/psi.sh
set -euo pipefail

url="${BLOCKY_PSI_URL:?set BLOCKY_PSI_URL to a public URL}"
strategy="${BLOCKY_PSI_STRATEGY:-mobile}"
key_arg=""
[ -n "${PSI_KEY:-}" ] && key_arg="&key=${PSI_KEY}"

json=$(curl -s "https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url=${url}&strategy=${strategy}&category=performance${key_arg}")

python3 - "$json" <<'PYEOF'
import json, sys
d = json.load(sys.stdin)
lh = d.get('lighthouseResult') or {}
score = (lh.get('categories', {}).get('performance', {}).get('score') or 0) * 100
audits = lh.get('audits', {})
def val(k):
    return audits.get(k, {}).get('displayValue', 'n/a')
print(f"PSI {score:.0f} | FCP {val('first-contentful-paint')} | LCP {val('largest-contentful-paint')} | CLS {val('cumulative-layout-shift')} | TBT {val('total-blocking-time')}")
if score < 90:
    raise SystemExit(f'PSI performance {score:.0f} < 90')
PYEOF
