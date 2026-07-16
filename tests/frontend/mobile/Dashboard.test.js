import { describe, expect, it } from 'vitest';
import Dashboard from '../../../resources/mobile/views/Dashboard.vue';

describe('mobile Dashboard retirement summary', () => {
  it('shows the known pension pot without inventing target progress when retirement goals are not configured', () => {
    const finances = Dashboard.computed.finances.call({
      data: {
        modules: {
          retirement: {
            status: 'not_configured',
            message: 'Retirement profile not yet set up.',
          },
        },
        net_worth: {
          total: 84250,
          breakdown: {
            assets: {
              savings: 36750,
              pensions: 47500,
            },
            total_assets: 84250,
            total_liabilities: 0,
          },
        },
      },
      fmt: Dashboard.methods.fmt,
    });

    expect(finances.find(({ key }) => key === 'retirement')).toMatchObject({
      value: '£47,500',
      barFill: 0,
      barValue: 'Target not set',
      barUnit: '',
      caption: 'Your pension pot',
    });
  });

  it('prefers the retirement summary pot when the full analysis is available', () => {
    const finances = Dashboard.computed.finances.call({
      data: {
        modules: {
          retirement: {
            status: 'active',
            pot_value: 60000,
            projected_income: 24000,
            target_income: 30000,
          },
        },
        net_worth: {
          total: 60000,
          breakdown: {
            assets: { pensions: 60000 },
            total_assets: 60000,
            total_liabilities: 0,
          },
        },
      },
      fmt: Dashboard.methods.fmt,
    });

    expect(finances.find(({ key }) => key === 'retirement')).toMatchObject({
      value: '£60,000',
      barFill: 80,
      barValue: '80%',
      barUnit: 'of target',
      caption: 'Towards your target',
    });
  });

  it('ignores additive dashboard and module fields from the shared API', () => {
    const finances = Dashboard.computed.finances.call({
      data: {
        future_dashboard_field: { enabled: true },
        modules: {
          savings: {
            value: 12000,
            emergency_fund_months: 4,
            future_module_field: 'ignored',
          },
          retirement: {
            pot_value: 50000,
            projected_income: 20000,
            target_income: 25000,
            future_module_field: ['ignored'],
          },
          investment: {
            portfolio_value: 8000,
            accounts_count: 1,
            holdings_count: 3,
            future_module_field: 42,
          },
        },
        net_worth: {
          total: 70000,
          breakdown: {
            assets: { savings: 12000, pensions: 50000 },
            total_assets: 70000,
            total_liabilities: 0,
          },
        },
      },
      fmt: Dashboard.methods.fmt,
    });

    expect(finances.find(({ key }) => key === 'savings')).toMatchObject({
      value: '£12,000',
      caption: 'Building your fund',
    });
    expect(finances.find(({ key }) => key === 'retirement')).toMatchObject({
      value: '£50,000',
      barValue: '80%',
    });
    expect(finances.find(({ key }) => key === 'investment')).toMatchObject({
      value: '£8,000',
      caption: '3 holdings',
    });
  });
});
