# Fynla Organisation Repository Migration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (- [ ]) syntax for tracking.

**Goal:** Create and verify the private 'Fynla/Fynla' organisation repository with useful Git history, every product surface and founder access, while permanently excluding the ProjectionLab export and three unsafe environment files and preserving knowledge in 'fynlaBrain'.

**Architecture:** Build a disposable full mirror from 'Stoff73/fynla', add the local migration-planning branch, filter only the four approved paths, and validate the mirror before creating or pushing the organisation repository. Hash-reconcile vault-only current-tree content with 'fynlaBrain', append clean migration commits to 'main' and 'dev' inside the disposable mirror, then publish and compare a fresh target clone against the validated source.

**Tech Stack:** Git 2.50.1 at '/usr/bin/git', Python 3.12, git-filter-repo, Gitleaks, GitHub CLI 2.86+, rg, jq, SHA-256 manifests and the GitHub REST API.

## Global Constraints

- Treat '/Users/CSJ/Desktop/fynla' and 'Stoff73/fynla' as read-only migration sources; do not rewrite, force-push, delete, archive or change their remotes.
- Use '/usr/bin/git' explicitly, or put '/usr/bin' before '/usr/local/bin'; the default '/usr/local/bin/git' is version 2.10.1 and is too old for current git-filter-repo.
- Target repository: 'Fynla/Fynla'; visibility: private; default branch: 'main'; integration branch: 'dev'.
- Permanently remove exactly these paths: '2026-02-27-projectionlab-account-data--slaterjoneschris-at-gmail-com.json', '.env.development', 'deploy/csjones-fynla/.env.production' and 'deploy/fynla-org/.env.production'.
- Keep safe templates including '.env.example' and '.env.production.example'.
- Ignore '.env*' at any depth except safe '*.example' templates; ignore root-level PNG files while retaining nested application PNG assets.
- Preserve all normal source branches and tags, including the local 'codex/fynla-org-repository-migration' branch.
- Never print secret values. Secret-scan output must use redaction and remain outside the repository.
- Do not remove current-tree knowledge until every candidate is hash-verified or copied into 'fynlaBrain'.
- Keep backend, web, '/m', Capacitor iOS, native iOS, tests, agents, skills, prompts, personas, workflows and deployment source.
- Do not use browser automation. GitHub work uses authenticated CLI/API calls; any later browser verification must use the user's installed Google Chrome.
- Stop before publication if the target already exists unexpectedly, the source/vault has unresolved changes, branch or tag inventories differ, a product surface disappears, or a credible secret remains.

## File and artifact map

| Path | Responsibility |
|---|---|
| 'docs/superpowers/specs/2026-07-20-fynla-organisation-repository-migration-design.md' | Approved migration contract |
| 'docs/superpowers/plans/2026-07-20-fynla-organisation-repository-migration.md' | Executable task sequence |
| '/private/tmp/fynla-org-migration-20260720/' | Disposable migration root; never committed |
| '/private/tmp/fynla-org-migration-20260720/fynla-sanitised.git' | Filtered bare mirror and publication source |
| '/private/tmp/fynla-org-migration-20260720/source-main' and 'source-dev' | Read-only branch-tip exports for knowledge comparison |
| '/private/tmp/fynla-org-migration-20260720/knowledge_manifest.py' | Hash-only vault comparison and lossless copy utility |
| '/private/tmp/fynla-org-migration-20260720/knowledge-*.tsv' | Current-tree-to-vault audit evidence |
| '/private/tmp/fynla-org-migration-20260720/filter-paths.txt' | Exact four-path history-filter input |
| '/private/tmp/fynla-org-migration-20260720/gitleaks-filtered.json' | Redacted pre-publication scan report |
| '/private/tmp/fynla-org-migration-20260720/target-verify.git' | Fresh post-publication verification mirror |
| 'docs/repository-migration/2026-07-20-migration-report.md' on the migration branch | Durable non-sensitive completion evidence |

---

### Task 1: Preflight, toolchain and immutable source inventory

**Files:**
- Create: '/private/tmp/fynla-org-migration-20260720/tool-versions.txt'
- Create: '/private/tmp/fynla-org-migration-20260720/source-local-status.txt'
- Read: '/Users/CSJ/Desktop/fynla/.git/config'
- Read: '/Users/CSJ/Desktop/fynlaBrain/.git/config'

**Interfaces:**
- Consumes: approved design commit '282289c' and an authenticated GitHub CLI session for 'Stoff73'.
- Produces: a clean disposable root, supported tools and a recorded source checkpoint.

- [ ] **Step 1: Verify the planning worktree and both source repositories are safe**

~~~bash
/usr/bin/git -C /Users/CSJ/Desktop/fynla status --short --branch
/usr/bin/git -C /Users/CSJ/Desktop/fynla/.worktrees/fynla-org-repository-migration status --short --branch
/usr/bin/git -C /Users/CSJ/Desktop/fynlaBrain status --short --branch
~~~

Expected: both Fynla worktrees have no unstaged or staged changes; the migration branch contains the approved design and this plan. If 'fynlaBrain' is dirty, stop before reconciliation and preserve its changes.

- [ ] **Step 2: Create the disposable root without deleting earlier evidence**

~~~bash
test ! -e /private/tmp/fynla-org-migration-20260720
mkdir -p /private/tmp/fynla-org-migration-20260720
~~~

Expected: both commands exit 0. If the path exists, inspect it and use a new suffixed path rather than deleting it.

- [ ] **Step 3: Verify supported Git and GitHub identity**

~~~bash
/usr/bin/git --version
gh --version | sed -n '1,2p'
gh auth status -h github.com
gh api user --jq .login
gh api orgs/Fynla --jq '{login: .login}'
~~~

Expected: Git 2.50.1 or newer, GitHub CLI 2.86.0 or newer, active login 'Stoff73' and organisation 'Fynla'.

- [ ] **Step 4: Confirm the target name is available**

~~~bash
if gh repo view Fynla/Fynla --json nameWithOwner >/dev/null 2>&1; then
  echo 'STOP: Fynla/Fynla already exists; inspect it before continuing.'
  exit 1
fi
echo 'Target name is available.'
~~~

Expected: 'Target name is available.' If the repository exists, stop rather than overwriting it.

- [ ] **Step 5: Install and verify the two purpose-built audit tools**

~~~bash
brew install git-filter-repo gitleaks
env PATH=/usr/bin:/bin:/usr/sbin:/sbin:/usr/local/bin git-filter-repo --version
gitleaks version
~~~

Expected: installation succeeds or reports the tools already installed; both version commands exit 0. Do not use git-filter-branch as a fallback.

- [ ] **Step 6: Record non-sensitive tool and source state**

~~~bash
{
  /usr/bin/git --version
  gh --version | sed -n '1,2p'
  env PATH=/usr/bin:/bin:/usr/sbin:/sbin:/usr/local/bin git-filter-repo --version
  gitleaks version
} > /private/tmp/fynla-org-migration-20260720/tool-versions.txt
/usr/bin/git -C /Users/CSJ/Desktop/fynla status --short --branch \
  > /private/tmp/fynla-org-migration-20260720/source-local-status.txt
~~~

Expected: both files exist, contain no tokens and identify the exact tools/source state.

---

### Task 2: Hash-based fynlaBrain reconciliation

**Files:**
- Create: '/private/tmp/fynla-org-migration-20260720/knowledge_manifest.py'
- Create: '/private/tmp/fynla-org-migration-20260720/knowledge-main.tsv'
- Create: '/private/tmp/fynla-org-migration-20260720/knowledge-dev.tsv'
- Potentially create: '/private/tmp/fynla-org-migration-20260720/vault-import/Imports/Fynla Code Repository/**'
- Potentially modify: 'Fynla/fynlaBrain' through branch 'codex/fynla-code-repository-import'

**Interfaces:**
- Consumes: tracked 'main' and 'dev' trees and clean 'fynlaBrain'.
- Produces: TSV proof that every cleanup candidate is already present or losslessly copied into the vault.

- [ ] **Step 1: Export both source tips without modifying the source**

~~~bash
/usr/bin/git -C /Users/CSJ/Desktop/fynlaBrain fetch origin
test "$(/usr/bin/git -C /Users/CSJ/Desktop/fynlaBrain rev-parse HEAD)" = \
  "$(/usr/bin/git -C /Users/CSJ/Desktop/fynlaBrain rev-parse origin/main)"
mkdir -p /private/tmp/fynla-org-migration-20260720/source-main
mkdir -p /private/tmp/fynla-org-migration-20260720/source-dev
/usr/bin/git -C /Users/CSJ/Desktop/fynla archive main \
  | tar -x -C /private/tmp/fynla-org-migration-20260720/source-main
/usr/bin/git -C /Users/CSJ/Desktop/fynla archive dev \
  | tar -x -C /private/tmp/fynla-org-migration-20260720/source-dev
~~~

Expected: the local vault exactly matches central 'origin/main'; both export directories contain tracked branch-tip files and no '.git' directory. Stop if the vault differs so unpushed knowledge is never mistaken for centralised knowledge.

- [ ] **Step 2: Create the deterministic hash/copy utility**

Create '/private/tmp/fynla-org-migration-20260720/knowledge_manifest.py' with:

~~~python
#!/usr/bin/env python3
import argparse
import csv
import hashlib
import shutil
from collections import defaultdict
from pathlib import Path

CANDIDATES = (
    Path('.obsidian'),
    Path('.goal'),
    Path('April'),
    Path('May'),
    Path('June'),
    Path('July'),
)


def sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open('rb') as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b''):
            digest.update(chunk)
    return digest.hexdigest()


def candidate_files(source: Path):
    for candidate in CANDIDATES:
        path = source / candidate
        if path.is_file():
            yield path
        elif path.is_dir():
            yield from sorted(item for item in path.rglob('*') if item.is_file())


def vault_hashes(vault: Path):
    by_hash = defaultdict(list)
    for path in sorted(item for item in vault.rglob('*') if item.is_file()):
        if '.git' in path.parts:
            continue
        by_hash[sha256(path)].append(path.relative_to(vault).as_posix())
    return by_hash


def unique_destination(vault: Path, relative: Path, digest: str) -> Path:
    destination = vault / 'Imports' / 'Fynla Code Repository' / relative
    if not destination.exists() or sha256(destination) == digest:
        return destination
    return destination.with_name(
        f'{destination.stem}.source-{digest[:8]}{destination.suffix}'
    )


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument('--source', type=Path, required=True)
    parser.add_argument('--vault', type=Path, required=True)
    parser.add_argument('--output', type=Path, required=True)
    parser.add_argument('--copy-missing', action='store_true')
    args = parser.parse_args()

    source = args.source.resolve()
    vault = args.vault.resolve()
    hashes = vault_hashes(vault)
    rows = []

    for path in candidate_files(source):
        relative = path.relative_to(source)
        digest = sha256(path)
        matches = hashes.get(digest, [])
        status = 'verified_in_fynlaBrain' if matches else 'copy_required'
        if not matches and args.copy_missing:
            destination = unique_destination(vault, relative, digest)
            destination.parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(path, destination)
            match = destination.relative_to(vault).as_posix()
            hashes[digest].append(match)
            matches = [match]
            status = 'copied_to_fynlaBrain'
        rows.append(
            (relative.as_posix(), digest, path.stat().st_size, status, '|'.join(matches))
        )

    args.output.parent.mkdir(parents=True, exist_ok=True)
    with args.output.open('w', newline='', encoding='utf-8') as handle:
        writer = csv.writer(handle, delimiter='\t')
        writer.writerow(('source_path', 'sha256', 'bytes', 'status', 'vault_paths'))
        writer.writerows(rows)

    unresolved = sum(1 for row in rows if row[3] == 'copy_required')
    print(f'files={len(rows)} unresolved={unresolved}')
    return 2 if unresolved else 0


if __name__ == '__main__':
    raise SystemExit(main())
~~~

- [ ] **Step 3: Validate and run the utility without writes**

~~~bash
python3 -m py_compile /private/tmp/fynla-org-migration-20260720/knowledge_manifest.py
python3 /private/tmp/fynla-org-migration-20260720/knowledge_manifest.py \
  --source /private/tmp/fynla-org-migration-20260720/source-main \
  --vault /Users/CSJ/Desktop/fynlaBrain \
  --output /private/tmp/fynla-org-migration-20260720/knowledge-main.tsv || test $? -eq 2
python3 /private/tmp/fynla-org-migration-20260720/knowledge_manifest.py \
  --source /private/tmp/fynla-org-migration-20260720/source-dev \
  --vault /Users/CSJ/Desktop/fynlaBrain \
  --output /private/tmp/fynla-org-migration-20260720/knowledge-dev.tsv || test $? -eq 2
~~~

Expected: compilation passes; each run prints file and unresolved counts. Exit 2 means copies are required, not data loss.

- [ ] **Step 4: Create an isolated vault import only when required**

~~~bash
if rg -q $'\tcopy_required\t' \
  /private/tmp/fynla-org-migration-20260720/knowledge-main.tsv \
  /private/tmp/fynla-org-migration-20260720/knowledge-dev.tsv; then
  /usr/bin/git -C /Users/CSJ/Desktop/fynlaBrain fetch origin
  /usr/bin/git -C /Users/CSJ/Desktop/fynlaBrain worktree add \
    /private/tmp/fynla-org-migration-20260720/vault-import \
    -b codex/fynla-code-repository-import origin/main
fi
~~~

Expected: no action when all content exists; otherwise a clean vault worktree is created from 'origin/main'.

- [ ] **Step 5: Copy only hash-missing content and regenerate proof**

~~~bash
if test -d /private/tmp/fynla-org-migration-20260720/vault-import; then
  python3 /private/tmp/fynla-org-migration-20260720/knowledge_manifest.py \
    --source /private/tmp/fynla-org-migration-20260720/source-main \
    --vault /private/tmp/fynla-org-migration-20260720/vault-import \
    --output /private/tmp/fynla-org-migration-20260720/knowledge-main.tsv \
    --copy-missing
  python3 /private/tmp/fynla-org-migration-20260720/knowledge_manifest.py \
    --source /private/tmp/fynla-org-migration-20260720/source-dev \
    --vault /private/tmp/fynla-org-migration-20260720/vault-import \
    --output /private/tmp/fynla-org-migration-20260720/knowledge-dev.tsv \
    --copy-missing
fi
! rg -q $'\tcopy_required\t' \
  /private/tmp/fynla-org-migration-20260720/knowledge-main.tsv \
  /private/tmp/fynla-org-migration-20260720/knowledge-dev.tsv
~~~

Expected: only 'verified_in_fynlaBrain' or 'copied_to_fynlaBrain'; differing versions use a '.source-<hash8>' suffix.

- [ ] **Step 6: Publish any vault additions before code-tree deletion**

~~~bash
if test -d /private/tmp/fynla-org-migration-20260720/vault-import && \
   test -n "$(/usr/bin/git -C /private/tmp/fynla-org-migration-20260720/vault-import status --porcelain)"; then
  /usr/bin/git -C /private/tmp/fynla-org-migration-20260720/vault-import \
    add 'Imports/Fynla Code Repository'
  /usr/bin/git -C /private/tmp/fynla-org-migration-20260720/vault-import \
    diff --cached --check
  /usr/bin/git -C /private/tmp/fynla-org-migration-20260720/vault-import \
    commit -m 'docs: preserve code-repository vault content'
  /usr/bin/git -C /private/tmp/fynla-org-migration-20260720/vault-import \
    push -u origin codex/fynla-code-repository-import
  GH_REPO=Fynla/fynlaBrain gh pr create \
    --base main \
    --head codex/fynla-code-repository-import \
    --title 'Preserve knowledge from the legacy code repository' \
    --body 'Adds only hash-unique knowledge before its verified removal from the Fynla source tree.'
  GH_REPO=Fynla/fynlaBrain gh pr merge \
    codex/fynla-code-repository-import --merge --delete-branch
fi
~~~

Expected: no commit when nothing is missing; otherwise the vault PR merges before Task 4.

---

### Task 3: Construct and filter the disposable full-history mirror

**Files:**
- Create: '/private/tmp/fynla-org-migration-20260720/fynla-sanitised.git'
- Create: 'source-heads.txt', 'source-tags.txt', 'source-tip-oids.txt', 'source-objects.txt', 'signed-objects.txt' and 'filter-paths.txt' under the disposable root

**Interfaces:**
- Consumes: remote source branches/tags and the local migration branch.
- Produces: a bare mirror without the four approved historical paths and before/after ref inventories.

- [ ] **Step 1: Clone a fresh mirror and add the local migration branch**

~~~bash
env PATH=/usr/bin:/bin:/usr/sbin:/sbin:/usr/local/bin \
  git clone --mirror https://github.com/Stoff73/fynla.git \
  /private/tmp/fynla-org-migration-20260720/fynla-sanitised.git
/usr/bin/git -C /private/tmp/fynla-org-migration-20260720/fynla-sanitised.git fetch \
  /Users/CSJ/Desktop/fynla \
  refs/heads/codex/fynla-org-repository-migration:refs/heads/codex/fynla-org-repository-migration
~~~

Expected: the remote mirror and local planning branch exist without modifying the personal repository.

- [ ] **Step 2: Capture branches, tags and objects**

~~~bash
/usr/bin/git -C /private/tmp/fynla-org-migration-20260720/fynla-sanitised.git \
  for-each-ref --format='%(refname)' refs/heads | sort \
  > /private/tmp/fynla-org-migration-20260720/source-heads.txt
/usr/bin/git -C /private/tmp/fynla-org-migration-20260720/fynla-sanitised.git \
  for-each-ref --format='%(refname)' refs/tags | sort \
  > /private/tmp/fynla-org-migration-20260720/source-tags.txt
/usr/bin/git -C /private/tmp/fynla-org-migration-20260720/fynla-sanitised.git \
  count-objects -vH > /private/tmp/fynla-org-migration-20260720/source-objects.txt
for ref in refs/heads/main refs/heads/dev refs/heads/codex/fynla-org-repository-migration; do
  printf '%s\t%s\n' "$ref" \
    "$(/usr/bin/git -C /private/tmp/fynla-org-migration-20260720/fynla-sanitised.git rev-parse "$ref")"
done > /private/tmp/fynla-org-migration-20260720/source-tip-oids.txt
/usr/bin/git -C /private/tmp/fynla-org-migration-20260720/fynla-sanitised.git \
  log --all --format='%H %G?' | rg ' [GUXYRE]$' \
  > /private/tmp/fynla-org-migration-20260720/signed-objects.txt || true
/usr/bin/git -C /private/tmp/fynla-org-migration-20260720/fynla-sanitised.git \
  for-each-ref --format='%(refname)' refs/tags | while read -r ref; do
    if /usr/bin/git -C /private/tmp/fynla-org-migration-20260720/fynla-sanitised.git \
      cat-file -p "$ref" | rg -q 'BEGIN PGP SIGNATURE'; then
      printf '%s signed-tag\n' "$ref"
    fi
  done >> /private/tmp/fynla-org-migration-20260720/signed-objects.txt
rg -q '^refs/heads/main$' /private/tmp/fynla-org-migration-20260720/source-heads.txt
rg -q '^refs/heads/dev$' /private/tmp/fynla-org-migration-20260720/source-heads.txt
rg -q '^refs/heads/codex/fynla-org-repository-migration$' \
  /private/tmp/fynla-org-migration-20260720/source-heads.txt
~~~

Expected: inventories contain 'main', 'dev' and the migration branch.

- [ ] **Step 3: Create and verify the exact filter input**

Create '/private/tmp/fynla-org-migration-20260720/filter-paths.txt' with exactly:

~~~text
2026-02-27-projectionlab-account-data--slaterjoneschris-at-gmail-com.json
.env.development
deploy/csjones-fynla/.env.production
deploy/fynla-org/.env.production
~~~

Then run:

~~~bash
test "$(wc -l < /private/tmp/fynla-org-migration-20260720/filter-paths.txt | tr -d ' ')" = 4
~~~

Expected: exactly four paths.

- [ ] **Step 4: Analyze, then rewrite only the disposable mirror**

~~~bash
cd /private/tmp/fynla-org-migration-20260720/fynla-sanitised.git
env PATH=/usr/bin:/bin:/usr/sbin:/sbin:/usr/local/bin git-filter-repo --analyze
env PATH=/usr/bin:/bin:/usr/sbin:/sbin:/usr/local/bin git-filter-repo \
  --sensitive-data-removal \
  --invert-paths \
  --paths-from-file /private/tmp/fynla-org-migration-20260720/filter-paths.txt \
  --force
~~~

Expected: rewrite produces 'filter-repo/commit-map', removes the clone's source remote and does not touch the desktop or personal GitHub repository.

- [ ] **Step 5: Prove ref names survived**

~~~bash
/usr/bin/git for-each-ref --format='%(refname)' refs/heads | sort \
  > /private/tmp/fynla-org-migration-20260720/filtered-heads.txt
/usr/bin/git for-each-ref --format='%(refname)' refs/tags | sort \
  > /private/tmp/fynla-org-migration-20260720/filtered-tags.txt
diff -u /private/tmp/fynla-org-migration-20260720/source-heads.txt \
  /private/tmp/fynla-org-migration-20260720/filtered-heads.txt
diff -u /private/tmp/fynla-org-migration-20260720/source-tags.txt \
  /private/tmp/fynla-org-migration-20260720/filtered-tags.txt
~~~

Expected: both diffs are empty.

---

### Task 4: Harden current tips and remove verified vault-only content

**Files:**
- Modify on sanitised 'main' and 'dev': '.gitignore'
- Modify on sanitised 'main' and 'dev': 'README.md'
- Remove where tracked and manifest-verified: '.obsidian', '.goal', 'April', 'May', 'June', 'July'
- Create: '/private/tmp/fynla-org-migration-20260720/clean-work'

**Interfaces:**
- Consumes: filtered mirror and successful Task 2 manifests.
- Produces: append-only cleanup commits at 'main' and 'dev'; no extra history rewrite.

- [ ] **Step 1: Clone the filtered mirror for normal commits**

~~~bash
/usr/bin/git clone /private/tmp/fynla-org-migration-20260720/fynla-sanitised.git \
  /private/tmp/fynla-org-migration-20260720/clean-work
/usr/bin/git -C /private/tmp/fynla-org-migration-20260720/clean-work \
  config user.name 'Chris Slater-Jones'
/usr/bin/git -C /private/tmp/fynla-org-migration-20260720/clean-work \
  config user.email "$(/usr/bin/git -C /Users/CSJ/Desktop/fynla config user.email)"
~~~

Expected: a clean clone whose 'origin' is only the disposable mirror.

- [ ] **Step 2: Apply the hardened policy to 'main'**

~~~bash
/usr/bin/git -C /private/tmp/fynla-org-migration-20260720/clean-work checkout main
~~~

Replace these three lines in '.gitignore':

~~~gitignore
.env
.env.backup
.env.production
~~~

with:

~~~gitignore
# Environment files are local; only non-secret example templates are versioned.
.env*
!.env.example
!.env.*.example
~~~

Add under the planning/local-document section:

~~~gitignore
# Obsidian vault and monthly project journals live in fynlaBrain.
/.obsidian/
/.goal
/January/
/February/
/March/
/April/
/May/
/June/
/July/
/August/
/September/
/October/
/November/
/December/
~~~

Add immediately after the opening product-description paragraph in 'README.md':

~~~markdown
This is Fynla's canonical organisation-owned application repository. 'main' is production-oriented and 'dev' is the integration branch. Founder knowledge, conversations and Obsidian content live in the private 'Fynla/fynlaBrain' repository; do not commit real environment files or credentials here.
~~~

- [ ] **Step 3: Test 'main' ignore behavior before deletion**

~~~bash
cd /private/tmp/fynla-org-migration-20260720/clean-work
/usr/bin/git check-ignore -q --no-index .env
/usr/bin/git check-ignore -q --no-index nested/.env.staging
! /usr/bin/git check-ignore -q --no-index .env.example
! /usr/bin/git check-ignore -q --no-index deploy/sample/.env.production.example
/usr/bin/git check-ignore -q --no-index root-test.png
! /usr/bin/git check-ignore -q --no-index resources/images/app-icon.png
/usr/bin/git diff --check
~~~

Expected: real env variants and root PNGs are ignored; safe templates and nested app PNGs are not.

- [ ] **Step 4: Remove only manifest-covered candidates from 'main' and commit**

~~~bash
! rg -q $'\tcopy_required\t' \
  /private/tmp/fynla-org-migration-20260720/knowledge-main.tsv
! /usr/bin/git grep -n -E '(\.obsidian/|(^|/)\.goal($|/)|(^|/)(April|May|June|July)/)' -- \
  app bootstrap config database routes resources public scripts deploy \
  composer.json composer.lock package.json package-lock.json vite.config.js \
  vite.mobile.config.js playwright.config.js 2>/dev/null
/usr/bin/git rm -r --ignore-unmatch .obsidian .goal April May June July
/usr/bin/git add .gitignore README.md
/usr/bin/git diff --cached --check
/usr/bin/git commit -m 'chore: separate source code from vault content'
/usr/bin/git push origin main
~~~

Expected: one append-only commit; only verified paths are removed.

- [ ] **Step 5: Apply the identical policy and cleanup to 'dev'**

~~~bash
/usr/bin/git checkout dev
~~~

Apply the exact '.env*' replacement, 'fynlaBrain' block and canonical-repository README paragraph from Step 2, then run:

~~~bash
! rg -q $'\tcopy_required\t' \
  /private/tmp/fynla-org-migration-20260720/knowledge-dev.tsv
! /usr/bin/git grep -n -E '(\.obsidian/|(^|/)\.goal($|/)|(^|/)(April|May|June|July)/)' -- \
  app bootstrap config database routes resources public scripts deploy \
  composer.json composer.lock package.json package-lock.json vite.config.js \
  vite.mobile.config.js playwright.config.js 2>/dev/null
/usr/bin/git check-ignore -q --no-index .env
/usr/bin/git check-ignore -q --no-index nested/.env.staging
! /usr/bin/git check-ignore -q --no-index .env.example
! /usr/bin/git check-ignore -q --no-index deploy/sample/.env.production.example
/usr/bin/git check-ignore -q --no-index root-test.png
! /usr/bin/git check-ignore -q --no-index resources/images/app-icon.png
/usr/bin/git rm -r --ignore-unmatch .obsidian .goal April May June July
/usr/bin/git add .gitignore README.md
/usr/bin/git diff --cached --check
/usr/bin/git commit -m 'chore: separate source code from vault content'
/usr/bin/git push origin dev
~~~

Expected: one append-only 'dev' commit; personal source remains unchanged.

---

### Task 5: Pre-publication security and completeness gate

**Files:**
- Create: '/private/tmp/fynla-org-migration-20260720/gitleaks-filtered.json'
- Create: '/private/tmp/fynla-org-migration-20260720/product-surface-evidence.txt'
- Create: '/private/tmp/fynla-org-migration-20260720/filtered-objects.txt'
- Create: '/private/tmp/fynla-org-migration-20260720/metadata-spotcheck.txt'

**Interfaces:**
- Consumes: filtered mirror with cleaned tips.
- Produces: publish/no-publish decision. Task 6 must not run if an assertion fails.

- [ ] **Step 1: Prove the four paths are absent from all reachable history**

~~~bash
cd /private/tmp/fynla-org-migration-20260720/fynla-sanitised.git
for path in \
  '2026-02-27-projectionlab-account-data--slaterjoneschris-at-gmail-com.json' \
  '.env.development' \
  'deploy/csjones-fynla/.env.production' \
  'deploy/fynla-org/.env.production'; do
  ! /usr/bin/git log --all --format= --name-only -- "$path" | rg -q .
  ! /usr/bin/git rev-list --objects --all | rg -F -q " $path"
done
~~~

Expected: every negative assertion passes.

- [ ] **Step 2: Prove safe templates and historical root screenshots remain**

~~~bash
/usr/bin/git rev-list --all -- .env.example | rg -q .
/usr/bin/git rev-list --all -- .env.production.example | rg -q .
/usr/bin/git rev-list --all -- accTypes.png | rg -q .
~~~

Expected: all pass, proving the filter did not overreach.

- [ ] **Step 3: Verify required product surfaces across preserved refs**

~~~bash
{
  for path in app routes resources/mobile ios tests .claude/agents .claude/skills \
    prompts personas fyn-memory; do
    commit=$(/usr/bin/git rev-list --all -n 1 -- "$path")
    test -n "$commit"
    printf '%s\t%s\n' "$path" "$commit"
  done
  commit=$(/usr/bin/git rev-list --all -n 1 -- ios-native)
  test -n "$commit"
  printf 'ios-native\t%s\n' "$commit"
  /usr/bin/git ls-tree -d --name-only \
    refs/heads/codex/savetax-allowance-ctas -- ios-native | rg -q '^ios-native$'
} > /private/tmp/fynla-org-migration-20260720/product-surface-evidence.txt
~~~

Expected: every path has a reachable commit; native iOS remains on its active development history rather than being falsely promoted into 'main' or 'dev'.

- [ ] **Step 4: Run Git integrity checks**

~~~bash
/usr/bin/git fsck --full --strict
/usr/bin/git count-objects -vH \
  > /private/tmp/fynla-org-migration-20260720/filtered-objects.txt
test -s /private/tmp/fynla-org-migration-20260720/fynla-sanitised.git/filter-repo/commit-map
~~~

Expected: no Git errors and both evidence files exist.

- [ ] **Step 5: Spot-check author, committer, dates and messages through the rewrite map**

~~~bash
while IFS=$'\t' read -r ref old; do
  new=$(awk -v oid="$old" '$1 == oid { print $2 }' \
    /private/tmp/fynla-org-migration-20260720/fynla-sanitised.git/filter-repo/commit-map)
  test -n "$new"
  test "$new" != '0000000000000000000000000000000000000000'
  old_meta=$(/usr/bin/git -C /Users/CSJ/Desktop/fynla show -s \
    --format='%an%x1f%ae%x1f%aI%x1f%cn%x1f%ce%x1f%cI%x1f%s' "$old")
  new_meta=$(/usr/bin/git -C /private/tmp/fynla-org-migration-20260720/fynla-sanitised.git \
    show -s --format='%an%x1f%ae%x1f%aI%x1f%cn%x1f%ce%x1f%cI%x1f%s' "$new")
  test "$old_meta" = "$new_meta"
  printf '%s\t%s\t%s\n' "$ref" "$old" "$new"
done < /private/tmp/fynla-org-migration-20260720/source-tip-oids.txt \
  > /private/tmp/fynla-org-migration-20260720/metadata-spotcheck.txt
if test -s /private/tmp/fynla-org-migration-20260720/signed-objects.txt; then
  echo 'Signed source objects exist; their old cryptographic signatures cannot validate rewritten identifiers.'
fi
~~~

Expected: all three branch-tip metadata comparisons pass. Any signed-object warning is included in the handoff.

- [ ] **Step 6: Scan all filtered history with values redacted**

~~~bash
gitleaks git \
  --redact \
  --no-banner \
  --report-format json \
  --report-path /private/tmp/fynla-org-migration-20260720/gitleaks-filtered.json \
  /private/tmp/fynla-org-migration-20260720/fynla-sanitised.git
~~~

Expected: exit 0 and no unresolved credible findings. If exit 1, inspect only redacted path/rule metadata and obtain CSJ approval before removing anything else.

---

### Task 6: Create and publish Fynla/Fynla

**Files:**
- Modify external state: create private 'Fynla/Fynla'
- Modify: remotes in the disposable mirror only

**Interfaces:**
- Consumes: a fully passing Task 5 mirror.
- Produces: a private organisation repository containing every sanitised normal branch and tag; GitHub-internal pull-request refs are never pushed.

- [ ] **Step 1: Reconfirm the target is absent**

~~~bash
! gh repo view Fynla/Fynla --json nameWithOwner >/dev/null 2>&1
~~~

Expected: exit 0. Otherwise stop and inspect.

- [ ] **Step 2: Create the empty private repository**

~~~bash
gh repo create Fynla/Fynla \
  --private \
  --description 'Canonical Fynla application source: web, mobile, iOS, Fyn AI, tests and agent tooling.' \
  --disable-wiki
~~~

Expected: GitHub returns the target URL and visibility 'PRIVATE'.

- [ ] **Step 3: Push only the validated normal branches and tags**

~~~bash
/usr/bin/git -C /private/tmp/fynla-org-migration-20260720/fynla-sanitised.git \
  remote add target https://github.com/Fynla/Fynla.git
/usr/bin/git -C /private/tmp/fynla-org-migration-20260720/fynla-sanitised.git \
  push --force --prune target 'refs/heads/*:refs/heads/*'
/usr/bin/git -C /private/tmp/fynla-org-migration-20260720/fynla-sanitised.git \
  push --force --prune target 'refs/tags/*:refs/tags/*'
~~~

Expected: all normal branches and tags push; no GitHub-internal ref and no push to 'Stoff73/fynla'. The target is empty, so the explicit force applies only to the newly created repository.

- [ ] **Step 4: Set metadata and default branch**

~~~bash
gh repo edit Fynla/Fynla --default-branch main --enable-issues --disable-wiki
gh repo view Fynla/Fynla --json nameWithOwner,visibility,defaultBranchRef,url
~~~

Expected: owner 'Fynla', visibility 'PRIVATE', default branch 'main'.

---

### Task 7: Founder access and safe repository rules

**Files:**
- Modify external state: 'Founders' team, repository permission and 'main'/'dev' protection
- Create: '/private/tmp/fynla-org-migration-20260720/founder-access.json'
- Create: '/private/tmp/fynla-org-migration-20260720/main-protection.json'
- Create: '/private/tmp/fynla-org-migration-20260720/dev-protection.json'

**Interfaces:**
- Consumes: published target and usernames 'Stoff73', 'Phailanx', 'Icecube-acc'.
- Produces: maintainable founder access, honest pending-invitation reporting and branch safety where supported.

- [ ] **Step 1: Create or reuse the closed Founders team**

~~~bash
if ! gh api orgs/Fynla/teams/founders --silent >/dev/null 2>&1; then
  gh api --method POST orgs/Fynla/teams \
    -f name='Founders' \
    -f description='Fynla founding team' \
    -f privacy='closed'
fi
gh api orgs/Fynla/teams/founders --jq '{name: .name, slug: .slug, privacy: .privacy}'
~~~

Expected: slug 'founders', privacy 'closed'.

- [ ] **Step 2: Add active founders and report pending invitations**

~~~bash
for login in Stoff73 Phailanx Icecube-acc; do
  if gh api "orgs/Fynla/members/$login" --silent >/dev/null 2>&1; then
    gh api --method PUT "orgs/Fynla/teams/founders/memberships/$login" -f role='member'
  else
    printf '%s\tpending organisation invitation or unavailable\n' "$login"
  fi
done
gh api orgs/Fynla/invitations \
  > /private/tmp/fynla-org-migration-20260720/founder-access.json
~~~

Expected: active members are added; pending users remain explicitly pending.

- [ ] **Step 3: Grant Maintain permission**

~~~bash
gh api --method PUT orgs/Fynla/teams/founders/repos/Fynla/Fynla \
  -f permission='maintain'
gh api orgs/Fynla/teams/founders/repos --paginate \
  --jq '.[] | select(.full_name == "Fynla/Fynla") | {full_name, permission: .permissions}'
~~~

Expected: permission includes 'maintain: true'.

- [ ] **Step 4: Observe copied workflows before requiring check names**

~~~bash
gh run list --repo Fynla/Fynla --limit 10
~~~

Expected: workflow state is recorded. Do not require a status-check name until that exact check has succeeded in the new repository.

- [ ] **Step 5: Protect main and dev against force-push/deletion**

~~~bash
jq -n '{
  required_status_checks: null,
  enforce_admins: false,
  required_pull_request_reviews: {
    dismiss_stale_reviews: true,
    require_code_owner_reviews: false,
    required_approving_review_count: 1
  },
  restrictions: null,
  allow_force_pushes: false,
  allow_deletions: false
}' > /private/tmp/fynla-org-migration-20260720/main-protection.json
gh api --method PUT repos/Fynla/Fynla/branches/main/protection \
  --input /private/tmp/fynla-org-migration-20260720/main-protection.json
jq -n '{
  required_status_checks: null,
  enforce_admins: false,
  required_pull_request_reviews: null,
  restrictions: null,
  allow_force_pushes: false,
  allow_deletions: false
}' > /private/tmp/fynla-org-migration-20260720/dev-protection.json
gh api --method PUT repos/Fynla/Fynla/branches/dev/protection \
  --input /private/tmp/fynla-org-migration-20260720/dev-protection.json
~~~

Expected: 'main' requires one review and both branches reject force-push/deletion. If unavailable for the organisation plan, record the exact non-secret response and continue verification without inventing a weaker rule.

---

### Task 8: Fresh-clone verification, report and founder handoff

**Files:**
- Create: '/private/tmp/fynla-org-migration-20260720/target-verify.git'
- Create on target migration branch: 'docs/repository-migration/2026-07-20-migration-report.md'
- Do not modify: '/Users/CSJ/Desktop/fynla' remotes/worktrees or 'Stoff73/fynla'

**Interfaces:**
- Consumes: published repository and pre-publication inventories.
- Produces: independent target proof, non-sensitive audit report and safe cutover instructions.

- [ ] **Step 1: Fresh-clone target and compare ref names and identifiers**

~~~bash
env PATH=/usr/bin:/bin:/usr/sbin:/sbin:/usr/local/bin \
  git clone --mirror https://github.com/Fynla/Fynla.git \
  /private/tmp/fynla-org-migration-20260720/target-verify.git
/usr/bin/git -C /private/tmp/fynla-org-migration-20260720/fynla-sanitised.git \
  for-each-ref --format='%(refname) %(objectname)' refs/heads refs/tags | sort \
  > /private/tmp/fynla-org-migration-20260720/published-ref-oids.txt
/usr/bin/git -C /private/tmp/fynla-org-migration-20260720/target-verify.git \
  for-each-ref --format='%(refname) %(objectname)' refs/heads refs/tags | sort \
  > /private/tmp/fynla-org-migration-20260720/target-ref-oids.txt
diff -u /private/tmp/fynla-org-migration-20260720/published-ref-oids.txt \
  /private/tmp/fynla-org-migration-20260720/target-ref-oids.txt
~~~

Expected: empty diff.

- [ ] **Step 2: Repeat security and integrity assertions against GitHub**

~~~bash
cd /private/tmp/fynla-org-migration-20260720/target-verify.git
/usr/bin/git fsck --full --strict
for path in \
  '2026-02-27-projectionlab-account-data--slaterjoneschris-at-gmail-com.json' \
  '.env.development' \
  'deploy/csjones-fynla/.env.production' \
  'deploy/fynla-org/.env.production'; do
  ! /usr/bin/git log --all --format= --name-only -- "$path" | rg -q .
  ! /usr/bin/git rev-list --objects --all | rg -F -q " $path"
done
~~~

Expected: integrity passes and all four paths remain absent.

- [ ] **Step 3: Verify clean tips and product coverage**

~~~bash
for ref in main dev; do
  ! /usr/bin/git ls-tree -r --name-only "$ref" \
    | rg -q '^(\.obsidian/|\.goal$|April/|May/|June/|July/)'
  ! /usr/bin/git ls-tree -r --name-only "$ref" \
    | rg -q '^(node_modules/|vendor/|public/(build|m-build|m-e2e-build)/|ios/App/Pods/|ios/App/App/public/)'
  ! /usr/bin/git ls-tree -r --name-only "$ref" | rg '(^|/)\.env' | rg -v '\.example$' | rg -q .
  ! /usr/bin/git ls-tree -r --name-only "$ref" | rg -q '^[^/]+\.png$'
  /usr/bin/git ls-tree -r --name-only "$ref" | rg -q '/[^/]+\.png$'
  /usr/bin/git show "$ref":.gitignore | rg -q '^\.env\*$'
  /usr/bin/git show "$ref":.gitignore | rg -q '^/\*\.png$'
done
for path in app routes resources/mobile ios ios-native tests .claude/agents \
  .claude/skills prompts personas fyn-memory; do
  test -n "$(/usr/bin/git rev-list --all -n 1 -- "$path")"
done
~~~

Expected: vault candidates, real env files, root PNGs, dependencies and generated builds are absent from 'main' and 'dev'; a nested application PNG remains; ignore rules are present; all required surfaces remain reachable.

- [ ] **Step 4: Write the non-sensitive migration report**

Create 'docs/repository-migration/2026-07-20-migration-report.md' on 'codex/fynla-org-repository-migration' with:

~~~markdown
# Fynla Organisation Repository Migration Report

**Date:** 2026-07-20
**Source:** Stoff73/fynla (left unchanged)
**Target:** Fynla/Fynla (private)
**Default branch:** main

## Completed controls

- Preserved all inventoried source branches and tags.
- Permanently removed the approved ProjectionLab export and three unsafe environment files from all published history.
- Confirmed safe environment templates remain.
- Confirmed root PNG files are ignored while nested application assets remain permitted.
- Verified backend, web, /m, Capacitor iOS, native iOS, tests, agents, skills, prompts, personas and Fyn memory content remain reachable.
- Hash-verified or copied vault-only tip content into fynlaBrain before source-tree removal.
- Re-cloned the GitHub target and matched every published branch and tag tip.
- Ran full Git integrity and redacted secret-scan checks.

## Founder access

- Founders team repository permission: Maintain.
- Active organisation members were added.
- Unaccepted organisation invitations remain explicitly pending.

## Cutover note

The migration rewrote commit identifiers. Existing clones must not force-push old history into Fynla/Fynla; use a fresh clone for normal work. The personal repository remains the rollback source until founder validation finishes.
~~~

- [ ] **Step 5: Commit the report only to the target migration branch**

~~~bash
/usr/bin/git clone https://github.com/Fynla/Fynla.git \
  /private/tmp/fynla-org-migration-20260720/report-work
cd /private/tmp/fynla-org-migration-20260720/report-work
/usr/bin/git checkout codex/fynla-org-repository-migration
/usr/bin/git add docs/repository-migration/2026-07-20-migration-report.md
/usr/bin/git diff --cached --check
/usr/bin/git commit -m 'docs: record Fynla organisation repository migration'
/usr/bin/git push origin codex/fynla-org-repository-migration
~~~

Expected: report is visible on the migration branch; 'main' and 'dev' remain at their validated tips.

- [ ] **Step 6: Prove the original source remains unchanged**

~~~bash
/usr/bin/git -C /Users/CSJ/Desktop/fynla status --short --branch
/usr/bin/git -C /Users/CSJ/Desktop/fynla remote -v
gh repo view Stoff73/fynla --json nameWithOwner,url,visibility,defaultBranchRef
~~~

Expected: active desktop branch/remotes and the personal GitHub repository remain unchanged.

- [ ] **Step 7: Deliver exact cutover guidance**

~~~text
Canonical repository: https://github.com/Fynla/Fynla

Private data was removed from historical commits, so commit identifiers changed. Use a fresh clone for normal work. Do not force-push an older Stoff73/fynla clone into the organisation repository.

The personal repository remains available during validation. Archiving or retiring it is a separate founder decision after access and the first normal pull-request cycle are confirmed.
~~~

- [ ] **Step 8: Mark complete only with this evidence**

~~~text
repository_created=true
visibility=private
default_branch=main
branches_and_tags_match=true
approved_paths_absent_from_all_history=true
secret_scan_unresolved_findings=0
product_surfaces_preserved=true
main_and_dev_cleaned=true
founders_team_permission=maintain
original_repository_unchanged=true
~~~

If Azlan or Brett has not accepted the organisation invitation, report 'founder_access=pending acceptance'; do not misstate a human action as completed.
