#!/usr/bin/env bash
set -euo pipefail

left="AOverview/includes/MetricMatcher.php"
right="ACharts/includes/MetricMatcher.php"

tmp_left="$(mktemp)"
tmp_right="$(mktemp)"
sed 's/Modules\\AOverview\\Includes/Modules\\__MODULE__/g' "$left" > "$tmp_left"
sed 's/Modules\\ACharts\\Includes/Modules\\__MODULE__/g' "$right" > "$tmp_right"

if ! diff -q "$tmp_left" "$tmp_right" >/dev/null 2>&1; then
  echo "MetricMatcher.php differs between AOverview and ACharts (ignoring namespace)."
  diff -u "$tmp_left" "$tmp_right" || true
  rm -f "$tmp_left" "$tmp_right"
  exit 1
fi

rm -f "$tmp_left" "$tmp_right"

echo "MetricMatcher.php is in sync."
