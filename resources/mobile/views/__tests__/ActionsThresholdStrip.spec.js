import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('../../api.js', () => ({ apiGet: vi.fn(), apiPost: vi.fn(), apiStream: vi.fn() }));
vi.mock('../../navigation/webHandoff.js', () => ({ issueWebHandoff: vi.fn() }));

import { apiGet } from '../../api.js';
import { store } from '../../store.js';
import Actions from '../Actions.vue';

const line = { key: 'pa_taper', title: 'Personal Allowance taper', headline: 'You are £12,400 into the 60% band', body: 'The next £12,400 you earn costs 60p in the pound.', cost_total: 12620, lever: { title: 'Pay £12,400 into your pension', recovers: 12620, downside: 'The money is locked until you are 55.', action: { route: '/tax-strategy' } }, position: { unit: 'gbp' } };

const stubs = { MobileChrome: { template: '<div><slot /></div>' }, 'router-link': { template: '<a><slot /></a>' } };

describe('/m actions threshold strip', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    store.token = 'live-token';
    store.user = { id: 7, onboarding_completed: true };
    apiGet.mockImplementation(async (url) => {
      if (url === '/api/recommendations/actions') return { ok: true, status: 200, data: { data: { open: [], completed: [] } } };
      if (url === '/api/thresholds') return { ok: true, status: 200, data: { data: { strip: 'pa_taper', lines: [line] } } };
      return { ok: false, status: 404, data: {} };
    });
  });

  it('shows the headline and total, and the lever after a tap', async () => {
    const w = mount(Actions, { global: { stubs } });
    await flushPromises();
    expect(w.text()).toContain('You are £12,400 into the 60% band');
    expect(w.text()).toContain('£12,620');
    expect(w.text()).not.toContain('Pay £12,400');
    await w.get('.mt-toggle').trigger('click');
    expect(w.text()).toContain('Pay £12,400 into your pension');
  });

  it('renders nothing when no line applies', async () => {
    apiGet.mockImplementation(async (url) => (url === '/api/thresholds'
      ? { ok: true, status: 200, data: { data: { strip: null, lines: [] } } }
      : { ok: true, status: 200, data: { data: { open: [], completed: [] } } }));
    const w = mount(Actions, { global: { stubs } });
    await flushPromises();
    expect(w.find('.mt-strip').exists()).toBe(false);
  });
});
