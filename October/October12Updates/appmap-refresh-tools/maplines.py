#!/usr/bin/env python3
"""Track every `path:lines` citation in a map from its stamp commit to HEAD.

Usage: maplines.py <repo> <map.md> <stamp> [--apply]

For each citation with line numbers, the cited lines are mapped through a
line diff of the file (stamp -> HEAD):
  SAME     file unchanged; citation untouched
  SHIFTED  every cited line still exists with identical text; numbers moved
  CHANGED  at least one cited line was edited or removed -> claim must be re-read
  GONE     file deleted (or renamed away) -> claim must be re-read
With --apply, SHIFTED citations are rewritten in place to their new numbers.
CHANGED and GONE are printed with the sentence they sit in, for manual review.
"""
import difflib, re, subprocess, sys
from collections import defaultdict

repo, mapfile, stamp = sys.argv[1:4]
apply = '--apply' in sys.argv
run = lambda *a: subprocess.run(a, cwd=repo, capture_output=True, text=True).stdout

files_now = set(run('git', 'ls-files').split())
files_then = set(run('git', 'ls-tree', '-r', '--name-only', stamp).split())
by_suffix = defaultdict(set)
for f in files_now | files_then:
    parts = f.split('/')
    for i in range(len(parts)):
        by_suffix['/'.join(parts[i:])].add(f)

cache = {}
def linemap(path):
    if path in cache:
        return cache[path]
    old = run('git', 'show', f'{stamp}:{path}').split('\n')
    new = open(f'{repo}/{path}', encoding='utf-8', errors='ignore').read().split('\n') if path in files_now else None
    m = {}
    if new is not None:
        sm = difflib.SequenceMatcher(None, old, new, autojunk=False)
        for tag, i1, i2, j1, j2 in sm.get_opcodes():
            if tag == 'equal':
                for k in range(i2 - i1):
                    m[i1 + k + 1] = j1 + k + 1
    cache[path] = (m, new is not None, old == new if new is not None else False)
    return cache[path]

def parse(spec):
    out = []
    for part in spec.split(','):
        part = part.strip()
        if not part:
            continue
        if '-' in part:
            a, b = part.split('-', 1)
            if a.isdigit() and b.isdigit():
                out.append((int(a), int(b)))
        elif part.isdigit():
            out.append((int(part), int(part)))
    return out

def fmt(ranges):
    return ','.join(f'{a}' if a == b else f'{a}-{b}' for a, b in ranges)

text = open(f'{repo}/{mapfile}').read()
CITE = re.compile(r'`([A-Za-z0-9_./@-]+\.(?:php|vue|js|swift|md|json|yml|yaml))?:([0-9][0-9,\- ]*)`')
ANCHOR = re.compile(r'`([A-Za-z0-9_./@-]+\.(?:php|vue|js|swift|md|json|yml|yaml))(?::[0-9][0-9,\- ]*)?`|\b([A-Z][A-Za-z0-9]+)(?:::|\.php\b)')
basename_index = defaultdict(set)
for f in files_now | files_then:
    b = f.rsplit('/', 1)[-1]
    basename_index[b.rsplit('.', 1)[0]].add(f)
stats = defaultdict(int)
review = []

def resolve_key(key):
    key = key.lstrip('./').replace('@/', 'resources/js/')
    c = by_suffix.get(key, set())
    return next(iter(c)) if len(c) == 1 else None

FILECITE = re.compile(r'`([A-Za-z0-9_./@-]+\.(?:php|vue|js|swift|md|json|yml|yaml))(?::[0-9][0-9,\- ]*)?`|`(:[0-9][0-9,\- ]*)`')

def anchor_for(pos):
    """Same line: the nearest file citation or Class:: before pos.
    Earlier lines in the block: only each line's FIRST citation, and only if it
    names a file (a line that opens with a bare `:N` inherits, so skip it)."""
    line_start = text.rfind('\n', 0, pos) + 1
    best = None
    for am in ANCHOR.finditer(text[line_start:pos]):
        if am.group(1):
            r = resolve_key(am.group(1))
        else:
            c = {f for f in basename_index.get(am.group(2), set()) if f.endswith('.php')}
            r = next(iter(c)) if len(c) == 1 else None
        if r:
            best = r
    if best:
        return best, True
    block_start = text.rfind('\n\n', 0, pos)
    block_start = 0 if block_start < 0 else block_start + 2
    lines = text[block_start:line_start].split('\n')
    for ln in reversed(lines):
        fm = FILECITE.search(ln)
        if fm and fm.group(1):
            r = resolve_key(fm.group(1))
            if r:
                return r, False
    return None, False

def repl(mo):
    raw, spec = mo.group(1), mo.group(2)
    same = True
    if raw:
        path = resolve_key(raw)
    else:
        path, same = anchor_for(mo.start())
    if path is None or path not in files_then:
        stats['UNRESOLVED'] += 1
        if not raw:
            review.append(('BARE?', mo.group(0), mo.start()))
        return mo.group(0)
    m, exists, identical = linemap(path)
    tag = '' if raw else ' (bare->' + path.rsplit('/', 1)[-1] + ')'
    if not exists:
        stats['GONE'] += 1
        review.append(('GONE', mo.group(0) + tag, mo.start()))
        return mo.group(0)
    if identical:
        stats['SAME'] += 1
        return mo.group(0)
    ranges = parse(spec)
    new_ranges = []
    for a, b in ranges:
        mapped = [m.get(k) for k in range(a, b + 1)]
        if any(x is None for x in mapped) or mapped != list(range(mapped[0], mapped[0] + len(mapped))):
            stats['CHANGED'] += 1
            review.append(('CHANGED', mo.group(0) + tag, mo.start()))
            return mo.group(0)
        new_ranges.append((mapped[0], mapped[-1]))
    if new_ranges == ranges:
        stats['SAME'] += 1
        return mo.group(0)
    stats['SHIFTED'] += 1
    if not same:
        old_l = run('git', 'show', f'{stamp}:{path}').split('\n')[ranges[0][0]-1].strip()[:70]
        review.append(('SHIFT-X', f'{mo.group(0)} -> :{fmt(new_ranges)} in {path.rsplit("/",1)[-1]} | {old_l}', mo.start()))
    return f'`{raw or ""}:{fmt(new_ranges)}`'

new_text = CITE.sub(repl, text)
print(f'{mapfile}: ' + ', '.join(f'{k} {v}' for k, v in sorted(stats.items())))
for kind, cite, pos in review:
    line_no = text.count('\n', 0, pos) + 1
    print(f'  {kind:8} map line {line_no:4}  {cite}')
if apply:
    open(f'{repo}/{mapfile}', 'w').write(new_text)
    print('  applied SHIFTED rewrites')
