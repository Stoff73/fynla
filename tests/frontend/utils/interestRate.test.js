import { describe, it, expect } from 'vitest';
import { formatInterestRate, hasInterestRate } from '@/utils/interestRate';

// Regression walk 2026-10-09, R10: a rate the user never gave showed "0.00%".
describe('formatInterestRate', () => {
    it('shows a stored percentage as it is', () => {
        expect(formatInterestRate(4.25)).toBe('4.25%');
        expect(formatInterestRate('4.2500')).toBe('4.25%');
        expect(formatInterestRate(0)).toBe('0.00%');
    });

    it('says "Not recorded" for a rate never given', () => {
        expect(formatInterestRate(null)).toBe('Not recorded');
        expect(formatInterestRate(undefined)).toBe('Not recorded');
        expect(formatInterestRate('')).toBe('Not recorded');
        expect(hasInterestRate(null)).toBe(false);
        expect(hasInterestRate(0)).toBe(true);
    });
});
