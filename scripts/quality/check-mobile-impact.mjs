import { execFileSync } from 'node:child_process';

const base = process.env.QUALITY_BASE || 'HEAD^';
const head = process.env.QUALITY_HEAD || 'HEAD';
const body = process.env.PR_BODY || '';
const files = execFileSync('git', ['diff', '--name-only', base, head], { encoding: 'utf8' })
  .trim().split('\n').filter(Boolean);

const webChanged = files.some((file) => /^resources\/js\/(views|components)\//.test(file));
const mobileChanged = files.some((file) => file.startsWith('resources/mobile/'));
const declaration = /Mobile impact:\s*(mobile-changed|shared-backend|no-counterpart-approved)/i.test(body);

if (webChanged && !mobileChanged && !declaration) {
  console.error('Desktop UI changed without /m files or a valid Mobile impact declaration.');
  process.exit(1);
}
