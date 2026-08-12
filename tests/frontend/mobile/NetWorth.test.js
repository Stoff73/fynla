import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import NetWorth from '../../../resources/mobile/views/modules/NetWorth.vue';
import { apiGet } from '../../../resources/mobile/api.js';

vi.mock('../../../resources/mobile/api.js', () => ({
  apiGet: vi.fn(),
}));

vi.mock('../../../resources/mobile/store.js', () => ({
  store: { token: 'test-token' },
}));

vi.mock('../../../resources/mobile/components/MobileChrome.vue', () => ({
  default: {
    template: '<main><slot /></main>',
    props: ['title', 'subtitle', 'loading', 'loadingLabel'],
  },
}));

vi.mock('../../../resources/mobile/components/NetWorthForecast.vue', () => ({
  default: { template: '<section>Projected net worth</section>' },
}));

describe('mobile Net Worth', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    apiGet.mockImplementation(async (path) => {
      if (path === '/api/net-worth/overview') {
        return { ok: true, data: { data: { net_worth: 350000, total_assets: 550000, total_liabilities: 200000 } } };
      }

      return { ok: true, data: { data: {} } };
    });
  });

  it('keeps recorded history separate from the projected forecast', async () => {
    const push = vi.fn();
    const wrapper = mount(NetWorth, {
      global: { mocks: { $router: { push } } },
    });
    await flushPromises();

    expect(wrapper.text()).toContain('Recorded balance history');
    expect(wrapper.text()).toContain('Projected net worth');
    expect(wrapper.text().indexOf('Recorded balance history'))
      .toBeLessThan(wrapper.text().indexOf('Projected net worth'));

    await wrapper.find('[data-testid="recorded-history-link"]').trigger('click');
    expect(push).toHaveBeenCalledWith('/net-worth/history');
  });
});
