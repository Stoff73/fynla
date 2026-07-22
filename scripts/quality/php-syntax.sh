#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

PHP_SYNTAX_JOBS="${PHP_SYNTAX_JOBS:-8}"

git ls-files -z 'app/*.php' 'app/**/*.php' 'config/*.php' \
  'database/*.php' 'database/**/*.php' 'routes/*.php' \
  'public/pages/*.php' 'public/pages/**/*.php' \
  | xargs -0 -n 20 -P "$PHP_SYNTAX_JOBS" bash -c '
      for file do
        if ! output="$(php -l "$file" 2>&1)"; then
          printf "PHP syntax failed: %s\n%s\n" "$file" "$output" >&2
          exit 1
        fi
      done
    ' _

echo "PHP syntax: clean"
