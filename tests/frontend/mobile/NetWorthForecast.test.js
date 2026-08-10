import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import NetWorthForecast from '../../../resources/mobile/components/NetWorthForecast.vue';
import { apiDelete, apiGet, apiPut } from '../../../resources/mobile/api.js';

vi.mock('../../../resources/mobile/api.js', () => ({
  apiDelete: vi.fn(),
  apiGet: vi.fn(),
  apiPut: vi.fn(),
}));

vi.mock('../../../resources/mobile/store.js', () => ({
  store: { token: 'test-token' },
}));

const categories = [
  'property',
  'investments',
  'pensions',
  'cash',
  'business',
  'valuables',
  'mortgages',
  'other_liabilities',
];

function forecast(overrides = {}) {
  const assumptions = Object.fromEntries(categories.map((category, index) => [category, {
    rate_percent: index + 1,
    source: index === 0 ? 'user_override' : 'system_default',
    effective_from: `2026-08-${String(index + 1).padStart(2, '0')}`,
    basis: index === 0 ? 'real' : 'nominal',
  }]));

  return {
    contract_version: 'net_worth_forecast_v1',
    recorded_as_of: '2026-08-10',
    current: { net_worth: 350000 },
    points: [
      { year: 0, calendar_year: 2026, net_worth: 350000, source: 'recorded' },
      { year: 1, calendar_year: 2027, net_worth: 371200, source: 'projected' },
      { year: 2, calendar_year: 2028, net_worth: 393218, source: 'projected' },
    ],
    assumptions,
    warnings: [],
    ...overrides,
  };
}

function okForecast(value = forecast()) {
  return { ok: true, status: 200, data: { success: true, data: value } };
}

describe('mobile NetWorthForecast', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    apiGet.mockResolvedValue(okForecast());
  });

  it('plots only the canonical forecast points and discloses every assumption', async () => {
    const wrapper = mount(NetWorthForecast, {
      global: {
        stubs: {
          apexchart: {
            name: 'apexchart',
            template: '<div data-testid="forecast-chart" />',
            props: ['options', 'series', 'type', 'height'],
          },
        },
      },
    });
    await flushPromises();

    expect(apiGet).toHaveBeenCalledWith('/api/net-worth/forecast', 'test-token');
    expect(wrapper.text()).toContain('Projected net worth');
    expect(wrapper.findComponent({ name: 'apexchart' }).props('series')).toEqual([{
      name: 'Projected net worth',
      data: [
        { x: '2026', y: 350000 },
        { x: '2027', y: 371200 },
        { x: '2028', y: 393218 },
      ],
    }]);
    expect(wrapper.text()).toContain('Recorded starting point');
    expect(wrapper.text()).toContain('Projected from 2027');

    for (const [index, category] of categories.entries()) {
      expect(wrapper.find(`[data-testid="forecast-rate-${category}"]`).element.value)
        .toBe(String(index + 1));
      expect(wrapper.find(`[data-testid="forecast-meta-${category}"]`).text())
        .toContain(index === 0 ? 'Your assumption' : 'Fynla default');
      expect(wrapper.find(`[data-testid="forecast-meta-${category}"]`).text())
        .toContain(`2026-08-${String(index + 1).padStart(2, '0')}`);
      expect(wrapper.find(`[data-testid="forecast-meta-${category}"]`).text())
        .toContain(index === 0 ? 'Real' : 'Nominal');
    }
  });

  it('saves the displayed category values exactly and refreshes the projection', async () => {
    apiPut.mockResolvedValue({ ok: true, status: 200, data: { data: forecast().assumptions } });
    apiGet
      .mockResolvedValueOnce(okForecast())
      .mockResolvedValueOnce(okForecast({ points: [
        { year: 0, calendar_year: 2026, net_worth: 350000, source: 'recorded' },
        { year: 1, calendar_year: 2027, net_worth: 375000, source: 'projected' },
      ] }));

    const wrapper = mount(NetWorthForecast);
    await flushPromises();
    await wrapper.find('[data-testid="forecast-rate-property"]').setValue('4.25');
    await wrapper.find('[data-testid="forecast-basis"]').setValue('real');
    await wrapper.find('form').trigger('submit');
    await flushPromises();

    expect(apiPut).toHaveBeenCalledWith('/api/net-worth/forecast/assumptions', {
      property: 4.25,
      investments: 2,
      pensions: 3,
      cash: 4,
      business: 5,
      valuables: 6,
      mortgages: 7,
      other_liabilities: 8,
      basis: 'real',
    }, 'test-token');
    expect(apiGet).toHaveBeenCalledTimes(2);
    expect(wrapper.text()).toContain('Assumptions saved.');
  });

  it('renders server validation errors beside the relevant assumption', async () => {
    apiPut.mockResolvedValue({
      ok: false,
      status: 422,
      data: { errors: { property: ['The property field must not be greater than 30.'] } },
    });

    const wrapper = mount(NetWorthForecast);
    await flushPromises();
    await wrapper.find('[data-testid="forecast-rate-property"]').setValue('31');
    await wrapper.find('form').trigger('submit');
    await flushPromises();

    expect(wrapper.find('[data-testid="forecast-error-property"]').text())
      .toContain('must not be greater than 30');
  });

  it('resets assumptions to server defaults and refreshes the projection', async () => {
    apiDelete.mockResolvedValue({ ok: true, status: 200, data: { data: forecast().assumptions } });
    const wrapper = mount(NetWorthForecast);
    await flushPromises();
    await wrapper.find('[data-testid="forecast-reset"]').trigger('click');
    await flushPromises();

    expect(apiDelete).toHaveBeenCalledWith('/api/net-worth/forecast/assumptions', 'test-token');
    expect(apiGet).toHaveBeenCalledTimes(2);
    expect(wrapper.text()).toContain('Assumptions reset to Fynla defaults.');
  });
});
