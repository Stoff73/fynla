import { execFileSync, spawnSync } from 'node:child_process';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const base = process.env.QUALITY_BASE || 'HEAD^';
const head = process.env.QUALITY_HEAD || 'HEAD';
const changed = execFileSync(
  'git',
  ['diff', '--name-only', '--diff-filter=ACM', base, head],
  { cwd: root, encoding: 'utf8' },
).trim().split('\n').filter(Boolean);

const files = changed.filter((file) => (
  /^(resources\/(js|mobile)|tests\/(frontend|E2E))\/.+\.(js|vue)$/.test(file)
  || /^scripts\/quality\/.+\.mjs$/.test(file)
  || /^[^/]+\.config\.js$/.test(file)
));

if (files.length === 0) {
  console.log('ESLint: no changed files');
  process.exit(0);
}

const eslint = path.join(root, 'node_modules/eslint/bin/eslint.js');
const result = spawnSync(
  process.execPath,
  [eslint, ...files, '--max-warnings=0'],
  { cwd: root, stdio: 'inherit' },
);

if (result.error) {
  throw result.error;
}

process.exit(result.status ?? 1);
