import { describe, it, expect } from 'vitest';
import fs from 'node:fs';
import path from 'node:path';
import { ASSET_TYPE_LABELS, ASSET_TYPES, formatAssetType } from '../assetTypes';

/**
 * W-0443. `formatAssetType` existed as ELEVEN private maps across four directories,
 * plus `HoldingForm`'s inline `<option>` list, `InlineHoldingsEditor`'s `ASSET_TYPES`
 * const, and `/m`'s `assetTypeLabel()` — fourteen renderings of ten values.
 *
 * **They did not agree.** `HoldingsTable` title-cased an unknown value so `uk_equity`
 * read "Uk Equity"; `PensionDetailInline` mapped it to "UK Equity"; `/m` ran
 * `capitalise('uk_equity')`, which is not a label at all. The same stored value read
 * differently depending on the screen.
 */
const REPO_ROOT = path.resolve(__dirname, '../../../../');

describe('one asset-type vocabulary (W-0443)', () => {
  /**
   * Acceptance 3. The authority is the `holdings.asset_type` column enum, mirrored
   * server-side in `StoreHoldingRequest::getAssetTypes()` and
   * `DCPensionHoldingsController`'s `in:` rules. This asserts the client mirror has not
   * drifted from it — a value added on one side and not the other is the failure this
   * catches.
   */
  it('mirrors the server vocabulary exactly', () => {
    const request = fs.readFileSync(
      path.join(REPO_ROOT, 'app/Http/Requests/Investment/StoreHoldingRequest.php'),
      'utf8'
    );
    const block = request.slice(request.indexOf('private function getAssetTypes'));
    const serverValues = [...block.matchAll(/'([a-z_]+)',/g)].map((m) => m[1]);

    expect(Object.keys(ASSET_TYPE_LABELS)).toEqual(serverValues.slice(0, 10));
  });

  /** Acceptance 2. "Uk Equity" is not a label, and neither is a bare acronym. */
  it('spells the labels per Rule 9', () => {
    expect(ASSET_TYPE_LABELS.uk_equity).toBe('UK Equity');
    expect(ASSET_TYPE_LABELS.us_equity).toBe('US Equity');
    expect(ASSET_TYPE_LABELS.etf).toBe('Exchange-Traded Fund');

    Object.values(ASSET_TYPE_LABELS).forEach((label) => {
      expect(label).not.toMatch(/\bUk\b/);
      expect(label).not.toMatch(/\bUs\b/);
    });
  });

  /**
   * An unknown value returns an em dash rather than a title-cased guess. Title-casing
   * is how "Uk Equity" reached a screen: it presents a stored string the vocabulary does
   * not contain as though it were a real label.
   */
  it('refuses to guess a label for a value it does not know', () => {
    expect(formatAssetType('some_new_type')).toBe('—');
    expect(formatAssetType(null)).toBe('—');
    expect(formatAssetType('')).toBe('—');
  });

  it('offers the values as options in the column enum order', () => {
    expect(ASSET_TYPES[0]).toEqual({ value: 'equity', label: 'Equity' });
    expect(ASSET_TYPES).toHaveLength(Object.keys(ASSET_TYPE_LABELS).length);
  });
});

/**
 * The guard is the item. Rule 20 does not say the copies must agree, it says there must
 * not be copies — and eleven agreeing-by-accident maps is exactly what shipped
 * "Uk Equity". No behavioural test of a single screen can see this: each renders a
 * plausible label from its own map.
 */
describe('no consumer keeps its own copy', () => {
  const consumers = [
    'resources/js/components/Estate/TrustPlanningStrategy.vue',
    'resources/js/components/Estate/GiftingStrategy.vue',
    'resources/js/components/Investment/AccountStrategyCard.vue',
    'resources/js/components/Investment/HoldingsTable.vue',
    'resources/js/components/Investment/InvestmentPerformance.vue',
    'resources/js/components/Investment/InlineHoldingsEditor.vue',
    'resources/js/components/Investment/HoldingForm.vue',
    'resources/js/components/NetWorth/InvestmentProjections.vue',
    'resources/js/components/NetWorth/PensionDetailInline.vue',
    'resources/js/components/NetWorth/JointAccountHistory.vue',
    'resources/js/views/Investment/AccountSummaryPanel.vue',
    'resources/js/views/Investment/AccountHoldingsPanel.vue',
    'resources/js/views/Investment/AccountPerformancePanel.vue',
    // Acceptance 4 — /m shares the vocabulary rather than mirroring it.
    'resources/mobile/views/modules/InvestmentAccountDetail.vue',
  ];

  it.each(consumers)('reads the shared vocabulary in %s', (file) => {
    const source = fs.readFileSync(path.join(REPO_ROOT, file), 'utf8');

    expect(source).toContain('constants/assetTypes');

    // No local map: a `uk_equity` key or an inline option label is a copy returning.
    const code = source
      .split('\n')
      .filter((line) => !line.trimStart().startsWith('//') && !line.trimStart().startsWith('*'));

    expect(code.filter((line) => /uk_equity['"]?\s*:/.test(line))).toEqual([]);
    expect(code.filter((line) => /<option value="uk_equity"/.test(line))).toEqual([]);
  });
});
