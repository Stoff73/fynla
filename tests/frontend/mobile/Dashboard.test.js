import { describe, expect, it } from 'vitest';
import Dashboard from '../../../resources/mobile/views/Dashboard.vue';

// The retirement card's rules (pot, secured income, target progress, someone
// drawing) are decided on the server (DashboardCards, tested in
// tests/Unit/Services/Mobile/DashboardCardsTest.php). /m renders the card as sent
// (CSJ 2026-10-01: one figure, every surface).
describe('mobile Dashboard retirement summary', () => {
  const finances = (cards, extra = {}) => Dashboard.computed.finances.call({
    data: { cards, ...extra },
    fmt: Dashboard.methods.fmt,
  });

  it('shows the server card for a saver with a target', () => {
    const card = finances({
      retirement: { value: 60000, value_is_income: false, caption: 'Towards your target', visual: { type: 'bar', progress: 80, number: '80%', label: 'of target' } },
    }).find(({ key }) => key === 'retirement');

    expect(card).toMatchObject({ value: '£60,000', barFill: 80, barValue: '80%', barUnit: 'of target', caption: 'Towards your target' });
  });

  it('labels an income per year when the server says it is one', () => {
    const card = finances({
      retirement: { value: 26514, value_is_income: true, caption: 'Your income this year', visual: { type: 'bar', progress: 0, number: 'runs out by about age 76', label: '' } },
    }).find(({ key }) => key === 'retirement');

    expect(card).toMatchObject({ value: '£26,514/year', caption: 'Your income this year', barValue: 'runs out by about age 76' });
  });

  it('ignores module fields and works the card out from nothing else', () => {
    const card = finances({}, {
      modules: { retirement: { card_value: 50000, progress_percent: 80, target_income: 25000 } },
    }).find(({ key }) => key === 'retirement');

    expect(card).toMatchObject({ value: '£0', barValue: '', caption: '' });
  });
});
