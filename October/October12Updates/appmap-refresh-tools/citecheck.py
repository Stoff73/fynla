#!/usr/bin/env python3
"""For a map file, list each cited source file and whether it changed since the stamp.

Usage: citecheck.py <repo> <map.md> <stamp-commit> [--detail]
A citation is a backticked token ending in a source extension, optionally :lines.
Bare basenames are resolved against `git ls-files` at HEAD (unique match only).
"""
import re, subprocess, sys
from collections import defaultdict

repo, mapfile, stamp = sys.argv[1:4]
detail = '--detail' in sys.argv
run = lambda *a: subprocess.run(a, cwd=repo, capture_output=True, text=True).stdout

files_now = run('git', 'ls-files').split()
files_then = set(run('git', 'ls-tree', '-r', '--name-only', stamp).split())
by_suffix = defaultdict(list)
for f in set(files_now) | files_then:
    parts = f.split('/')
    for i in range(len(parts)):
        by_suffix['/'.join(parts[i:])].append(f)

text = open(f'{repo}/{mapfile}').read()
cites = re.findall(r'`([A-Za-z0-9_./@-]+\.(?:php|vue|js|swift|md|json|yml|yaml))(?::([0-9,\- ]+))?`', text)
per_file = defaultdict(int)
unresolved = set()
for path, _lines in cites:
    path = path.lstrip('./').replace('@/', 'resources/js/')
    cands = sorted(set(by_suffix.get(path, [])))
    if len(cands) != 1:
        unresolved.add(path if not cands else f'{path} (ambiguous: {len(cands)})')
        continue
    per_file[cands[0]] += 1

changed, same, gone = [], [], []
for f, n in sorted(per_file.items()):
    if f not in set(files_now):
        gone.append((f, n))
    elif f not in files_then:
        changed.append((f, n, 'new'))
    else:
        d = run('git', 'diff', '--shortstat', stamp, 'HEAD', '--', f).strip()
        (changed if d else same).append((f, n, d) if d else (f, n))

print(f'{mapfile}: {len(cites)} citations, {len(per_file)} files resolved, '
      f'{len(same)} unchanged, {len(changed)} changed, {len(gone)} deleted, {len(unresolved)} unresolved')
if detail:
    for f, n, d in sorted(changed, key=lambda x: -x[1]):
        print(f'  CHANGED x{n:3} {f}  [{d}]')
    for f, n in gone:
        print(f'  DELETED x{n:3} {f}')
    for u in sorted(unresolved):
        print(f'  UNRESOLVED {u}')
