#!/usr/bin/env bash
set -euo pipefail

errors=0

for legacy in \
  AOverview/includes/MetricMatcher.php \
  AOverview/includes/RequestRateLimiter.php \
  ACharts/includes/MetricMatcher.php \
  ACharts/includes/RequestRateLimiter.php
do
  if [[ -f "$legacy" ]]; then
    echo "Remove duplicate (use ZbxCommon): $legacy"
    errors=1
  fi
done

if [[ ! -f ZbxCommon/includes/MetricMatcher.php ]]; then
  echo "Missing ZbxCommon/includes/MetricMatcher.php"
  errors=1
fi

if [[ ! -f ZbxCommon/includes/RequestRateLimiter.php ]]; then
  echo "Missing ZbxCommon/includes/RequestRateLimiter.php"
  errors=1
fi

for widget in AOverview ACharts; do
  if grep -rq 'Modules\\AOverview\\Includes\\MetricMatcher\|Modules\\ACharts\\Includes\\MetricMatcher' "$widget" --include='*.php' 2>/dev/null; then
    echo "$widget still references local MetricMatcher namespace"
    errors=1
  fi
  if ! grep -rq 'Modules\\ZbxCommon\\Includes\\MetricMatcher' "$widget" --include='*.php' 2>/dev/null; then
    echo "$widget should use Modules\\ZbxCommon\\Includes\\MetricMatcher"
    errors=1
  fi
done

if [[ "$errors" -ne 0 ]]; then
  exit 1
fi

echo "ZbxCommon usage OK."
