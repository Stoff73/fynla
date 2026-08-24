import { describe, it, expect } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import {
  HEALTH_STATUS_OPTIONS,
  SMOKING_STATUS_OPTIONS,
  EDUCATION_LEVEL_OPTIONS,
  formatEducationLevel,
  formatHealthStatus,
} from '@/constants/profileOptions';

/**
 * Closes the chain that W-0006 and W-0031 both broke:
 *
 *   users column  ──(tests/Unit/Database/ProfileEnumColumnsTest.php)──▶  App\Constants\ProfileEnums
 *   App\Constants\ProfileEnums  ──(this spec)──▶  resources/js/constants/profileOptions.js
 *
 * With both links pinned, a select cannot offer a value the column will reject —
 * which is exactly what produced a 500 on the Personal Information page.
 *
 * The PHP constant is read as text rather than executed; that keeps the check in
 * the frontend suite where the JS side lives, at the cost of a regex that this
 * spec asserts actually matched something.
 */
function phpConstantValues(name) {
  const source = readFileSync(resolve(__dirname, '../../../../../app/Constants/ProfileEnums.php'), 'utf8');
  const block = new RegExp(`const\\s+${name}\\s*=\\s*\\[([\\s\\S]*?)\\];`).exec(source);

  expect(block, `ProfileEnums::${name} not found — has the constant been renamed?`).not.toBeNull();

  const values = [...block[1].matchAll(/'([^']+)'/g)].map((m) => m[1]);

  expect(values.length, `ProfileEnums::${name} parsed as empty`).toBeGreaterThan(0);

  return values;
}

/**
 * The education LABELS are pinned too, not just the values. They had four
 * renderers and only two of them agreed: `ComprehensiveProtectionPlanService`
 * held a private `match` and kept rendering "Secondary (GCSE/O-Levels)" — an
 * acronym Rule 9 forbids — after both selects were corrected. Nothing was
 * comparing copy, so nothing went red (W-0080).
 */
function phpLabelMap(name) {
  const source = readFileSync(resolve(__dirname, '../../../../../app/Constants/ProfileEnums.php'), 'utf8');
  const block = new RegExp(`const\\s+${name}\\s*=\\s*\\[([\\s\\S]*?)\\];`).exec(source);

  expect(block, `ProfileEnums::${name} not found — has the constant been renamed?`).not.toBeNull();

  const pairs = [...block[1].matchAll(/'([^']*)'\s*=>\s*'([^']*)'/g)].map((m) => [m[1], m[2]]);

  expect(pairs.length, `ProfileEnums::${name} parsed as empty`).toBeGreaterThan(0);

  return pairs;
}

describe('profileOptions ↔ ProfileEnums parity', () => {
  it.each([
    ['HEALTH_STATUSES', HEALTH_STATUS_OPTIONS],
    ['SMOKING_STATUSES', SMOKING_STATUS_OPTIONS],
    ['EDUCATION_LEVELS', EDUCATION_LEVEL_OPTIONS],
  ])('%s matches the backend constant, in order', (constName, options) => {
    expect(options.map((o) => o.value)).toEqual(phpConstantValues(constName));
  });

  it('renders the education labels the backend authored, in order and word for word', () => {
    expect(EDUCATION_LEVEL_OPTIONS.map((o) => [o.value, o.label])).toEqual(phpLabelMap('EDUCATION_LEVEL_LABELS'));
  });

  it('spells out every education acronym — Rule 9 allows only ISA', () => {
    // "GCSE", "O-Levels" and "A-Levels" all shipped here. The check is shape-based
    // rather than a denylist so a newly added acronym is caught too.
    EDUCATION_LEVEL_OPTIONS.forEach(({ label }) => {
      expect(label, `"${label}" contains an acronym`).not.toMatch(/\b[A-Z]{2,}\b/);
      expect(label, `"${label}" abbreviates a qualification level`).not.toMatch(/\b[A-Z]-Level/);
    });
  });

  it('offers no education level the column cannot hold', () => {
    const values = EDUCATION_LEVEL_OPTIONS.map((o) => o.value);

    // The three that produced HTTP 500 on save, live, from a real select.
    expect(values).not.toContain('doctorate');
    expect(values).not.toContain('foundation');
    expect(values).not.toContain('hnd');
  });

  it('gives every option a non-empty label', () => {
    [...HEALTH_STATUS_OPTIONS, ...SMOKING_STATUS_OPTIONS, ...EDUCATION_LEVEL_OPTIONS]
      .forEach((option) => expect(option.label.trim().length).toBeGreaterThan(0));
  });

  it('formats a stored value rather than falling back to "Not specified"', () => {
    expect(formatEducationLevel('postgraduate')).toBe('Postgraduate Degree');
    expect(formatHealthStatus('yes')).toBe('Yes, good health');
    expect(formatEducationLevel('doctorate')).toBe('Not specified');
  });
});
