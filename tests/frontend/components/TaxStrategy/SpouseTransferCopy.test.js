import { describe, expect, it } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';

// Capital Gains Tax: TCGA 1992 s58 (no gain/no loss between spouses or civil
// partners living together; no domicile condition).
// https://www.legislation.gov.uk/ukpga/1992/12/section/58
// Inheritance Tax: from 6 April 2025 domicile was replaced by long-term UK
// residence; the spouse exemption is limited for a non-long-term-resident
// spouse (HMRC IHTM47030).
// https://www.gov.uk/hmrc-internal-manuals/inheritance-tax-manual/ihtm47030
// Wording shared with /m (resources/mobile/views/TaxStrategy.vue householdIntro).
const files = [
  'resources/js/components/TaxStrategy/HouseholdCoordinationPanel.vue',
  'resources/js/components/TaxStrategy/AssetShiftingPanel.vue',
];

describe('spouse transfer tax statements', () => {
  it.each(files)('%s never states the old domicile rule', (file) => {
    const src = readFileSync(resolve(process.cwd(), file), 'utf8');

    expect(src).not.toMatch(/domicil/i);
    expect(src).toContain('Inheritance Tax spouse-exemption conditions apply.');
    expect(src).not.toContain('only work because both spouses contribute');
  });
});

describe('household panel copy does not repeat its heading', () => {
  it.each([
    'resources/js/components/TaxStrategy/HouseholdCoordinationPanel.vue',
    'resources/mobile/views/TaxStrategy.vue',
  ])('%s', (file) => {
    const src = readFileSync(resolve(process.cwd(), file), 'utf8');

    expect(src).not.toContain('Coordinate as a household. ');
  });
});
