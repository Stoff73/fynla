#!/usr/bin/env python3
"""Recount the overview map's inventory figures at the worktree's HEAD.
Usage: overview_counts.py <repo> <routes.json>
"""
import json, os, re, subprocess, sys
from collections import Counter

repo, routes_json = sys.argv[1], sys.argv[2]
os.chdir(repo)
sh = lambda c: subprocess.run(c, shell=True, capture_output=True, text=True).stdout
ls = lambda p: [f for f in sh(f"git ls-files {p}").split() if f]
R = json.load(open(routes_json))

def mw(r):
    m = r.get('middleware') or []
    return m if isinstance(m, list) else [m]

out = {}
out['routes_total'] = len(R)
out['routes_api_group'] = sum(1 for r in R if 'api' in mw(r))
out['routes_sanctum'] = sum(1 for r in R if any('Authenticate:sanctum' in x or x == 'auth:sanctum' for x in mw(r)))
out['routes_web_group'] = sum(1 for r in R if 'web' in mw(r))
out['routes_closure'] = sum(1 for r in R if r.get('action') == 'Closure')
out['routes_redirect_authed_closures'] = sum(1 for r in R if r.get('action') == 'Closure' and any('RedirectAuthenticatedToDashboard' in x or x == 'redirect.authed' for x in mw(r)))
for key, pat in [('estate.full', 'estate.full|EnsureEstateFull|RequireEstate'), ('holistic.full', 'holistic.full|Holistic'),
                 ('permission:admin.access', 'admin.access'), ('permission:admin.tax_config', 'admin.tax_config'),
                 ('advisor', r'^advisor$|IsAdvisor|EnsureAdvisor')]:
    out[f'routes_mw_{key}'] = sum(1 for r in R if any(re.search(pat, x) for x in mw(r)))
# controllers
ctrls = [f for f in ls('app/Http/Controllers') if f.endswith('.php')]
actions = {r['action'].split('@')[0] for r in R if '@' in r.get('action', '')} | {r['action'] for r in R if '\\' in r.get('action', '') and '@' not in r['action']}
routed = [f for f in ctrls if 'App\\' + f[4:-4].replace('/', '\\') in actions]
out['controllers_on_disk'] = len(ctrls)
out['controllers_routed'] = len(routed)
out['controllers_not_routed'] = sorted(set(ctrls) - set(routed))
out['form_requests'] = len([f for f in ls('app/Http/Requests') if f.endswith('.php')])
out['api_resources'] = len([f for f in ls('app/Http/Resources') if f.endswith('.php')])
# services
svc = [f for f in ls('app/Services') if f.endswith('.php')]
groups = Counter(f.split('/')[2] for f in svc if f.count('/') >= 3)
out['services_files'] = len(svc)
out['services_groups'] = len(groups)
out['services_largest'] = groups.most_common(10)
out['coordination_files'] = groups.get('Coordination')
out['tax_files'] = groups.get('Tax')
out['agents'] = [os.path.basename(f) for f in ls('app/Agents')]
# data
out['models'] = len([f for f in ls('app/Models') if f.endswith('.php')])
out['migrations'] = len([f for f in ls('database/migrations') if f.endswith('.php')])
out['seeders'] = len([f for f in ls('database/seeders') if f.endswith('.php')])
out['observers_on_disk'] = len([f for f in ls('app/Observers') if f.endswith('.php')])
esp = open('app/Providers/EventServiceProvider.php').read()
asp = open('app/Providers/AppServiceProvider.php').read()
obs_esp = set(re.findall(r'(\w+Observer)::class', esp.split('$observers', 1)[1] if '$observers' in esp else ''))
obs_asp = set(re.findall(r'observe\((\w+Observer)::class', asp))
out['observers_registered_esp'] = len(obs_esp)
out['observers_registered_asp'] = sorted(obs_asp)
out['userdatacache_models'] = len(re.findall(r'UserDataCacheObserver::class', esp))
out['events'] = len([f for f in ls('app/Events') if f.endswith('.php')])
out['erds'] = len([f for f in ls('docs/diagrams') if os.path.basename(f).startswith('erd-')])
# SPA
router = open('resources/js/router/index.js').read()
out['spa_router_paths'] = len(re.findall(r"path: '", router))
out['spa_layouts'] = [os.path.basename(f) for f in ls('resources/js/layouts') if f.endswith('.vue')]
out['spa_views'] = len(ls('resources/js/views'))
out['spa_components'] = len(ls('resources/js/components'))
out['spa_services'] = len([f for f in ls('resources/js/services') if f.endswith('.js') and '__tests__' not in f])
out['spa_store_modules'] = len([f for f in ls('resources/js/store/modules') if f.endswith('.js') and '__tests__' not in f])
# /m
mob = ls('resources/mobile')
out['m_files'] = len(mob)
out['m_router_paths'] = len(re.findall(r"path: '", open('resources/mobile/router.js').read()))
out['m_views_top'] = len([f for f in mob if re.match(r'resources/mobile/views/[^/]+\.vue$', f)])
out['m_views_modules'] = sorted(os.path.basename(f)[:-4] for f in mob if re.match(r'resources/mobile/views/modules/[^/]+\.vue$', f))
# iOS
out['ios_files'] = len(ls('ios-native'))
out['ios_features'] = sorted({f.split('/')[3] for f in ls('ios-native/Fynla/Features') if f.count('/') >= 4})
out['ios_core'] = sorted({f.split('/')[3] for f in ls('ios-native/Fynla/Core') if f.count('/') >= 4})
swift = ' '.join(open(f, errors='ignore').read() for f in ls('ios-native/Fynla') if f.endswith('.swift'))
out['ios_path_literals'] = len(set(re.findall(r'path: "([^"]+)"', swift)))
# background
cmds = [f for f in ls('app/Console/Commands') if f.endswith('.php')]
out['commands_on_disk'] = len(cmds)
kernel = open('app/Console/Kernel.php').read()
out['schedule_command_calls'] = len(re.findall(r"\$schedule->command\(", kernel))
out['schedule_job_calls'] = len(re.findall(r"\$schedule->job\(", kernel))
out['jobs'] = len([f for f in ls('app/Jobs') if f.endswith('.php')])
out['mailables'] = len([f for f in ls('app/Mail') if f.endswith('.php')])
out['notifications'] = len([f for f in ls('app/Notifications') if f.endswith('.php')])
# tests
t = ls('tests')
out['tests_architecture'] = len([f for f in t if f.startswith('tests/Architecture/') and f.endswith('Test.php')])
out['tests_unit'] = len([f for f in t if f.startswith('tests/Unit/') and f.endswith('Test.php')])
out['tests_feature'] = len([f for f in t if f.startswith('tests/Feature/') and f.endswith('Test.php')])
out['tests_feature_fyn_eval'] = len([f for f in t if f.startswith('tests/Feature/Fyn/Eval/') and f.endswith('Test.php')])
out['tests_integration'] = len([f for f in t if f.startswith('tests/Integration/') and f.endswith('Test.php')])
out['tests_browser_scenarios'] = len([f for f in t if f.startswith('tests/Browser/scenarios/BS-')])
out['tests_e2e_specs'] = len([f for f in t if f.startswith('tests/E2E/') and f.endswith('.spec.js')])
vit = [f for f in sh("git ls-files 'resources/**/__tests__/*' 'tests/frontend/*'").split() if re.search(r'\.(spec|test)\.js$', f)]
out['vitest_files'] = len(vit)
for k, v in out.items():
    print(f'{k}: {v}')
