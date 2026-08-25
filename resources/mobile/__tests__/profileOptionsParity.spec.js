import { describe, it, expect } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import * as mobileOptions from '@m/constants/profileOptions.js';
import * as webOptions from '@/constants/profileOptions';

/**
 * `/m` is an isolated bundle and cannot import the desktop constants, so the
 * Health & Lifestyle option lists exist twice by architectural necessity. This
 * spec is what makes that safe: it pins the `/m` copy to the desktop copy AND
 * both to `App\Constants\ProfileEnums`, which
 * `tests/Unit/Database/ProfileEnumColumnsTest.php` in turn pins to the live
 * `users` columns.
 *
 * The full chain, so a value a select offers can never be one the column rejects:
 *
 *   users columns ─▶ ProfileEnums ─▶ resources/js constants ─▶ resources/mobile constants
 *
 * W-0006 and W-0031 were both breaks in that chain, and W-0031 reached a user as
 * an HTTP 500 from a live select.
 */
const LISTS = [
  ['HEALTH_STATUS_OPTIONS', 'HEALTH_STATUSES'],
  ['SMOKING_STATUS_OPTIONS', 'SMOKING_STATUSES'],
  ['EDUCATION_LEVEL_OPTIONS', 'EDUCATION_LEVELS'],
];

function phpConstantValues(name) {
  const source = readFileSync(resolve(__dirname, '../../../app/Constants/ProfileEnums.php'), 'utf8');
  const block = new RegExp(`const\\s+${name}\\s*=\\s*\\[([\\s\\S]*?)\\];`).exec(source);

  expect(block, `ProfileEnums::${name} not found`).not.toBeNull();

  const values = [...block[1].matchAll(/'([^']+)'/g)].map((m) => m[1]);

  expect(values.length, `ProfileEnums::${name} parsed as empty`).toBeGreaterThan(0);

  return values;
}

/**
 * Labels, not just values. `ComprehensiveProtectionPlanService` was a fourth
 * renderer nothing compared against, so it kept "Secondary (GCSE/O-Levels)" — an
 * acronym Rule 9 forbids — after both selects were fixed (W-0080).
 */
function phpLabelMap(name) {
  const source = readFileSync(resolve(__dirname, '../../../app/Constants/ProfileEnums.php'), 'utf8');
  const block = new RegExp(`const\\s+${name}\\s*=\\s*\\[([\\s\\S]*?)\\];`).exec(source);

  expect(block, `ProfileEnums::${name} not found`).not.toBeNull();

  const pairs = [...block[1].matchAll(/'([^']*)'\s*=>\s*'([^']*)'/g)].map((m) => [m[1], m[2]]);

  expect(pairs.length, `ProfileEnums::${name} parsed as empty`).toBeGreaterThan(0);

  return pairs;
}

describe('/m profile options parity', () => {
  it.each(LISTS)('%s is identical to the desktop list, values and labels', (jsName) => {
    expect(mobileOptions[jsName]).toEqual(webOptions[jsName]);
  });

  it.each(LISTS)('%s matches the backend constant %s, in order', (jsName, phpName) => {
    expect(mobileOptions[jsName].map((o) => o.value)).toEqual(phpConstantValues(phpName));
  });

  it('renders the education labels the backend authored, in order and word for word', () => {
    expect(mobileOptions.EDUCATION_LEVEL_OPTIONS.map((o) => [o.value, o.label]))
      .toEqual(phpLabelMap('EDUCATION_LEVEL_LABELS'));
  });

  it('spells out every education acronym — Rule 9 allows only ISA', () => {
    mobileOptions.EDUCATION_LEVEL_OPTIONS.forEach(({ label }) => {
      expect(label, `"${label}" contains an acronym`).not.toMatch(/\b[A-Z]{2,}\b/);
      expect(label, `"${label}" abbreviates a qualification level`).not.toMatch(/\b[A-Z]-Level/);
    });
  });

  it('offers no education level the column cannot hold', () => {
    const values = mobileOptions.EDUCATION_LEVEL_OPTIONS.map((o) => o.value);

    expect(values).not.toContain('doctorate');
    expect(values).not.toContain('foundation');
    expect(values).not.toContain('hnd');
  });

  it('formats stored values rather than showing "Not recorded"', () => {
    expect(mobileOptions.formatHealthStatus('yes')).toBe('Yes, good health');
    expect(mobileOptions.formatSmokingStatus('never')).toBe('Never smoked');
    expect(mobileOptions.formatEducationLevel('postgraduate')).toBe('Postgraduate Degree');
    expect(mobileOptions.formatEducationLevel(undefined)).toBe('Not recorded');
  });
});
