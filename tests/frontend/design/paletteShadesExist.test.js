import { describe, expect, it } from 'vitest';
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';

/**
 * W-0503 — a Tailwind class for a shade that does not exist emits NOTHING.
 *
 * `text-light-blue-700` was on the "Platform updates" insight tag. The
 * `light-blue` scale has 100 and 500 and nothing else, so Tailwind produced no
 * rule and the tag rendered with whatever colour it inherited. Nobody noticed,
 * because a missing colour is not an error — it is silence.
 *
 * The item named one instance. A sweep found THIRTY-ONE across thirteen files:
 * 600, 200, 300, 800, 50 and 700, none of which the config defines. This guard
 * is what makes the fix stick, because the next one will be just as invisible.
 *
 * Scoped to the scales that are genuinely sparse. The full scales (violet,
 * spring, raspberry, neutral, horizon) define the usual range.
 */

const SPARSE_SCALES = {
  'light-blue': ['100', '500'],
};

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

describe('every palette shade used is one the config defines (W-0503)', () => {
  for (const [scale, defined] of Object.entries(SPARSE_SCALES)) {
    it(`uses only the ${scale} shades that exist: ${defined.join(', ')}`, () => {
      const pattern = new RegExp(`${scale}-(\\d+)`, 'g');
      const offenders = [];

      for (const file of [...sourceFiles('resources/js'), ...sourceFiles('resources/mobile')]) {
        const source = readFileSync(file, 'utf8');
        for (const match of source.matchAll(pattern)) {
          if (!defined.includes(match[1])) {
            offenders.push(`${file}: ${scale}-${match[1]}`);
          }
        }
      }

      // A shade the config does not define produces no CSS at all, so this
      // cannot be caught by looking at the page — only by looking here.
      expect(offenders).toEqual([]);
    });
  }
});
