import { describe, it, expect } from 'vitest';
import fs from 'node:fs';
import path from 'node:path';
import { projectionRiskMessage, VOLATILITY_DRAG_NOTE } from '../projectionCaption';

/**
 * W-0258. The risk profile's expected return is an ARITHMETIC mean; the line the chart
 * draws is the MEDIAN of a simulated distribution, which compounds at the geometric
 * mean — lower by roughly half the variance. On David's portfolio a stated 7.07%
 * produced a median implying 5.36%-6.09% a year, a gap of 1.42%.
 *
 * Both numbers are right. A user checking one against the other concludes the
 * projection is broken, because nothing said they measure different things.
 */
describe('the projection caption discloses volatility drag', () => {
  it('states why the median compounds below the quoted rate', () => {
    const message = projectionRiskMessage({
      levelDisplay: 'Balanced',
      expectedReturn: 7.07,
    });

    expect(message).toContain('7.07% expected return');
    expect(message).toContain(VOLATILITY_DRAG_NOTE);
  });

  it('keeps the charges disclosure alongside it', () => {
    const message = projectionRiskMessage({
      levelDisplay: 'Balanced',
      expectedReturn: 7.07,
      feeDragPercent: 0.45,
    });

    expect(message).toContain('less 0.45% in charges');
    expect(message).toContain(VOLATILITY_DRAG_NOTE);
  });

  it('omits the charges clause when there are none', () => {
    const message = projectionRiskMessage({
      levelDisplay: 'Cautious',
      expectedReturn: 4.0,
      feeDragPercent: 0,
    });

    expect(message).not.toContain('charges');
  });

  /**
   * The guard. Two components built this string independently and identically, which
   * is why the disclosure would otherwise have to be added twice — and why a third
   * site (PensionDetailInline) had it missing entirely until this item.
   */
  it('is built in one place, not rebuilt per component', () => {
    const root = path.resolve(__dirname, '../../');
    const consumers = [
      'components/Investment/InvestmentProjectionChart.vue',
      'components/Retirement/PensionPotProjectionChart.vue',
      'components/NetWorth/PensionDetailInline.vue',
    ];

    consumers.forEach((file) => {
      const source = fs.readFileSync(path.join(root, file), 'utf8');

      expect(source).toContain('utils/projectionCaption');
      // No component reassembles the sentence for itself.
      expect(source).not.toMatch(/`Using \$\{levelDisplay\} risk profile/);
    });
  });
});
