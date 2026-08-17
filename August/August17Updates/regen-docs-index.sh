#!/usr/bin/env bash
# Regenerates docs/INDEX.md — the chronological pointer from the month-updates
# folders into the canonical dated doc trees under docs/.
#
# Run from the repo root:  bash August/August17Updates/regen-docs-index.sh > docs/INDEX.md
set -euo pipefail

cat <<'HDR'
# Documentation index — dated design docs, plans and evidence

Chronological pointer to every dated document under `docs/`. These trees are
**canonical** — do not move them into `{Month}/{Month}{D}Updates/` folders and
do not copy them there. `tests/Architecture/ClientParityLedgerTest.php:85` pins
the literal path of one of them, so a move breaks the test suite.

Session handovers and working notes live in `{Month}/{Month}{D}Updates/`.
This file is the bridge between the two conventions.

Regenerate after adding docs:

```bash
bash August/August17Updates/regen-docs-index.sh > docs/INDEX.md
```

| Date | Kind | Document |
|---|---|---|
HDR

rows() {
  find docs/superpowers docs/testing docs/plans docs/architecture \
       -type f -name '*.md' 2>/dev/null \
    | sed 's|^\./||' \
    | awk -F/ '{
        n = $NF
        kind = $2; if (kind == "superpowers") kind = $3
        link = $0; sub(/^docs\//, "", link)
        if (match(n, /^[0-9]{4}-[0-9]{2}-[0-9]{2}/)) d = substr(n, 1, 10); else d = "undated"
        title = n; sub(/^[0-9]{4}-[0-9]{2}-[0-9]{2}-/, "", title); sub(/\.md$/, "", title)
        printf "%s\t| %s | %s | [%s](%s) |\n", d, d, kind, title, link
      }'
}

# Dated rows newest-first, then undated.
rows | grep -v '^undated' | sort -r | cut -f2-
rows | grep '^undated'    | sort   | cut -f2-
