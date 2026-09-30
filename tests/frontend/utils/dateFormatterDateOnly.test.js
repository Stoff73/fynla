// A date-only value ("YYYY-MM-DD", how the API sends date_of_birth) must read
// the same in every time zone. new Date('1985-05-01') is UTC midnight, which is
// 30 April west of Greenwich — the display-side twin of the #1009 save bug.
import { afterAll, beforeAll, describe, expect, it } from 'vitest';
import { formatDateForInput, formatDateOnlyLong } from '../../../resources/js/utils/dateFormatter.js';

describe('formatDateOnlyLong', () => {
  const originalTz = process.env.TZ;
  beforeAll(() => { process.env.TZ = 'America/Los_Angeles'; });
  afterAll(() => { process.env.TZ = originalTz; });

  it('reads a date-only value as that calendar day west of Greenwich', () => {
    expect(new Date('1985-05-01').getDate()).toBe(30); // the trap it avoids
    expect(formatDateOnlyLong('1985-05-01')).toBe('1 May 1985');
  });

  it('ignores a time part and returns an empty string for nothing usable', () => {
    expect(formatDateOnlyLong('1980-04-12T00:00:00.000000Z')).toBe('12 April 1980');
    expect(formatDateOnlyLong('')).toBe('');
    expect(formatDateOnlyLong(null)).toBe('');
    expect(formatDateOnlyLong('not a date')).toBe('');
  });

  it('passes a date-only value to the date input unchanged west of Greenwich', () => {
    expect(formatDateForInput('1985-05-01')).toBe('1985-05-01');
    expect(formatDateForInput('')).toBe('');
  });
});
