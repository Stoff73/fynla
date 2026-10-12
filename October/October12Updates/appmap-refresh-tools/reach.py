#!/usr/bin/env python3
"""Reachability of resources/<root> .vue/.js files from entry points.

Follows static imports, dynamic import(), require(), re-exports, and component
tags matched to file basenames. HTML and JS comments are stripped first.
Usage: reach.py <repo> <root-dir> <entry> [<entry> ...]
"""
import os, re, subprocess, sys

repo, root = sys.argv[1], sys.argv[2]
entries = sys.argv[3:]
os.chdir(repo)
files = subprocess.run(['git', 'ls-files', root], capture_output=True, text=True).stdout.split()
files = [f for f in files if f.endswith(('.vue', '.js'))
         and '__tests__' not in f and '.spec.' not in f and '.test.' not in f]
fileset = set(files)

def pascal(name):
    return ''.join(p[:1].upper() + p[1:] for p in re.split(r'[-_]', name))

by_base = {}
for f in files:
    if f.endswith('.vue'):
        by_base.setdefault(os.path.splitext(os.path.basename(f))[0], []).append(f)

IMP = re.compile(r"""(?:import\s[^'"]*?from\s*|import\s*\(\s*|import\s+|require\s*\(\s*|export\s[^'"]*?from\s*)['"]([^'"]+)['"]""")
TAG = re.compile(r'<([A-Za-z][\w-]*)')

def strip(src):
    src = re.sub(r'<!--.*?-->', '', src, flags=re.S)
    src = re.sub(r'/\*.*?\*/', '', src, flags=re.S)
    src = re.sub(r'(?<![:"\'\\])//[^\n]*', '', src)
    return src

def resolve(spec, frm):
    if spec.startswith('@/'):
        base = os.path.join('resources/js', spec[2:])
    elif spec.startswith('@mobile/'):
        base = os.path.join('resources/mobile', spec[8:])
    elif spec.startswith('.'):
        base = os.path.normpath(os.path.join(os.path.dirname(frm), spec))
    else:
        return None
    for cand in (base, base + '.js', base + '.vue', base + '/index.js', base + '/index.vue'):
        if cand in fileset:
            return cand
    return None

seen, stack = set(), [e for e in entries if e in fileset]
while stack:
    f = stack.pop()
    if f in seen:
        continue
    seen.add(f)
    try:
        src = strip(open(f, encoding='utf-8', errors='ignore').read())
    except OSError:
        continue
    for spec in IMP.findall(src):
        r = resolve(spec, f)
        if r and r not in seen:
            stack.append(r)
    if f.endswith('.vue'):
        tmpl = src.split('<script', 1)[0]
        for tag in TAG.findall(tmpl):
            for t in {tag, pascal(tag)}:
                for r in by_base.get(t, []):
                    if r not in seen:
                        stack.append(r)

dead = sorted(fileset - seen)
print(f'TOTAL {len(files)} REACHABLE {len(seen)} UNREACHABLE {len(dead)}')
for d in dead:
    print(d)
