import { describe, expect, it } from 'vitest';
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';

/**
 * W-0453 — every `taxConfig` getter defaults to null, and `null.toLocaleString()`
 * throws.
 *
 * The store getters are all `state.config?.…?.x ?? null` by design: an
 * unconfigured value must not silently become a number. The consequence is that
 * any template calling `.toLocaleString()` on one directly throws a TypeError
 * before the configuration has hydrated — and a render error blanks the whole
 * component, so the user sees nothing rather than a wrong figure.
 *
 * The item named two sites. There were FIVE, while three others already used
 * `|| 0` — so the guard pattern existed and had simply been applied
 * inconsistently, which is the shape that always comes back.
 *
 * This fails on any tax getter reaching `.toLocaleString()` without a guard.
 */

// The nullable getters exposed by `store/modules/taxConfig.js` that templates
// most often print directly.
const NULLABLE_TAX_GETTERS = [
  'ihtNilRateBand',
  'ihtResidenceNilRateBand',
  'ihtRnrbTaperThreshold',
  'annualGiftExemption',
  'smallGiftExemption',
  'isaAnnualAllowance',
  'lifetimeIsaAllowance',
  'juniorIsaAllowance',
  'personalAllowance',
  'pensionAnnualAllowance',
  'pensionLifetimeAllowance',
  'cgtAnnualAllowance',
  'dividendAllowance',
  'marriageAllowance',
];

function sourceFiles(dir, acc = []) {
  for (const entry of readdirSync(dir)) {
    const path = join(dir, entry);
    if (statSync(path).isDirectory()) {
      if (entry !== 'node_modules') sourceFiles(path, acc);
    } else if (path.endsWith('.vue') || path.endsWith('.js')) {
      acc.push(path);
    }
  }
  return acc;
}

describe('a nullable tax getter is never dereferenced unguarded (W-0453)', () => {
  it('never calls .toLocaleString() straight off one', () => {
    const offenders = [];

    for (const file of [...sourceFiles('resources/js'), ...sourceFiles('resources/mobile')]) {
      const source = readFileSync(file, 'utf8');

      for (const getter of NULLABLE_TAX_GETTERS) {
        // `getter.toLocaleString()` with no `|| 0`, `??` or `Number()` in front.
        const unguarded = new RegExp(`(?<![|?)\\w.])\\b${getter}\\.toLocaleString\\(`, 'g');

        for (const _ of source.matchAll(unguarded)) {
          offenders.push(`${file}: ${getter}.toLocaleString()`);
        }
      }
    }

    // A throw here is not a wrong number on the page — it is a blank component,
    // which is why this went unnoticed until a console error was read.
    expect(offenders).toEqual([]);
  });
});
