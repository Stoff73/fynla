import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('../../api.js', () => ({ apiGet: vi.fn() }));

import { apiGet } from '../../api.js';
import { store } from '../../store.js';
import Expenditure from '../Expenditure.vue';

const MobileChromeStub = { props: ['title', 'subtitle', 'loading', 'loadingLabel', 'contextualRequest'], template: '<main><h1>{{ title }}</h1><slot /></main>' };

/**
 * MB-50. The verify step sends the user here to check "does it look right?".
 * The screen showed only the composed total (£2,400 entered + £667 of
 * commitments = £3,067) and never the £2,400 the user had just said, so the
 * figure on screen was one they had never given.
 */
describe('MobileExpenditure — the entered figure is shown, not only the derived total (MB-50)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    store.setToken('tok');
    apiGet.mockResolvedValue({ ok: true, status: 200, data: { data: { expenditure: {
      categories: {},
      presentation: {
        entry_mode: 'summary', entry_mode_label: 'Monthly summary',
        active_monthly_total: 3066.67, active_annual_total: 36800,
        manual_monthly_total: 2400, commitments_monthly_total: 666.67,
        manual_annual_total: 28800, commitments_annual_total: 8000,
        has_recorded_expenditure: true,
        total_basis: 'Monthly summary plus financial commitments',
        detail_available: false, reconciles: true,
        summary_only_reason: 'Only a monthly summary has been entered. Add category details to improve your insights.',
      },
    } } } });
  });

  it('shows what the user entered and the auto-calculated commitments beside the total', async () => {
    const wrapper = mount(Expenditure, { global: { stubs: { MobileChrome: MobileChromeStub }, mocks: { $router: { push: vi.fn() } } } });
    await flushPromises();
    const text = wrapper.text();
    expect(text).toContain('£2,400');
    expect(text).toContain('£667');
    expect(text).toContain('£3,067');
    expect(text).toContain('Total monthly expenditure');
  });

  it('does not show a spending row when nothing has been recorded', async () => {
    apiGet.mockResolvedValueOnce({ ok: true, status: 200, data: { data: { expenditure: { categories: {}, presentation: {
      entry_mode: 'summary', entry_mode_label: 'Monthly summary', active_monthly_total: 517, active_annual_total: 6204,
      manual_monthly_total: 0, commitments_monthly_total: 517, has_recorded_expenditure: false,
      total_basis: 'Financial commitments only — no expenditure recorded', detail_available: false,
      summary_only_reason: 'No expenditure has been recorded. Add your spending to improve your insights.',
    } } } } });
    const wrapper = mount(Expenditure, { global: { stubs: { MobileChrome: MobileChromeStub }, mocks: { $router: { push: vi.fn() } } } });
    await flushPromises();
    expect(wrapper.text()).not.toContain('Monthly spending you entered');
    expect(wrapper.text()).toContain('£517');
  });
});
