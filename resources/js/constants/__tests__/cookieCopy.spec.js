import { describe, it, expect } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { COOKIE_DECLINE_TITLE, COOKIE_DECLINE_TEXT } from '../cookieCopy';

/**
 * W-0546. The SPA banner and the server-rendered pages' vanilla banner each
 * carry the decline copy; the vanilla script cannot import the constant, so
 * this pins the two to one wording and fails the moment they drift.
 */
describe('cookie decline copy', () => {
  const vanilla = readFileSync(resolve(__dirname, '../../../../public/pages/js/cookie-consent.js'), 'utf8');

  it('states only what declining costs', () => {
    expect(COOKIE_DECLINE_TEXT).not.toMatch(/unavailable/);
    expect(COOKIE_DECLINE_TEXT).toMatch(/work as normal/);
  });

  it('is the same copy on the server-rendered pages', () => {
    expect(vanilla).toContain(COOKIE_DECLINE_TITLE);
    expect(vanilla).toContain(COOKIE_DECLINE_TEXT);
    expect(vanilla).not.toMatch(/registration will be unavailable/);
  });
});
