import { describe, it, expect } from 'vitest';
import { dashboardCard } from '@/utils/dashboardCards';

// The dashboard cards are built on the server (DashboardCards) and rendered as
// sent on web, /m and iOS (CSJ 2026-10-01). This helper only maps the block.
describe('dashboardCard', () => {
  const payload = {
    cards: {
      savings: { value: 12000, value_is_income: false, caption: 'Building your fund', visual: { type: 'bar', progress: 50, number: '3', label: '/ 6 months' } },
      retirement: { value: 30000, value_is_income: true, caption: 'Guaranteed retirement income', visual: { type: 'bar', progress: 0, number: 'Target not set', label: '' } },
    },
  };

  it('passes the server card through as sent', () => {
    expect(dashboardCard(payload, 'savings')).toEqual({
      value: 12000,
      valueIsIncome: false,
      caption: 'Building your fund',
      viz: 'bar',
      progress: 50,
      vizNum: '3',
      vizCap: '/ 6 months',
      barFill: 50,
      barValue: '3',
      barUnit: '/ 6 months',
    });
  });

  it('carries the income flag the server set', () => {
    expect(dashboardCard(payload, 'retirement').valueIsIncome).toBe(true);
  });

  it('shows an empty card, not a made-up figure, when the server sent none', () => {
    expect(dashboardCard({}, 'investment')).toMatchObject({ value: 0, caption: '', progress: 0, vizNum: '' });
  });
});
