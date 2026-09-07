import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('../../../api.js', () => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
  apiPut: vi.fn(),
}));

vi.mock('../../../navigation/webHandoff.js', () => ({
  issueWebHandoff: vi.fn(),
}));

import { apiGet } from '../../../api.js';
import { issueWebHandoff } from '../../../navigation/webHandoff.js';
import Estate from '../Estate.vue';

/**
 * W-0110. `create_power_of_attorney` is in both Fyn tool catalogues and stripped only
 * from the read-only advice surface, so a `/m` user could tell Fyn about their Lasting
 * Power of Attorney, have the row created — and then have no screen on that device
 * that showed it. A write with no read.
 *
 * The instrument itself stays on web; `/m` reads the same endpoint the web store
 * reads, prints the labels the record carries rather than a fifth copy of the
 * vocabulary, and hands off to the screen that holds the document.
 */
const MobileChromeStub = {
  props: ['title', 'subtitle', 'loading', 'loadingLabel', 'contextualRequest'],
  template: '<main><slot /></main>',
};

function stubLoad(lpas) {
  apiGet.mockImplementation((path) => {
    if (path === '/api/estate') {
      return Promise.resolve({
        ok: true,
        status: 200,
        data: { mode: 'full', data: { gifts: [], trusts: [], will_info: null } },
      });
    }
    if (path === '/api/estate/net-worth') {
      return Promise.resolve({ ok: true, status: 200, data: { data: {} } });
    }
    if (path === '/api/estate/bequests') {
      return Promise.resolve({ ok: true, status: 200, data: { data: [] } });
    }
    if (path === '/api/estate/lpa') {
      return Promise.resolve({ ok: true, status: 200, data: { data: lpas } });
    }
    return Promise.resolve({ ok: true, status: 200, data: { data: null } });
  });
}

async function mountEstate() {
  const wrapper = mount(Estate, {
    global: {
      stubs: { MobileChrome: MobileChromeStub },
      mocks: { $router: { push: vi.fn() } },
    },
  });
  await flushPromises();
  await flushPromises();
  return wrapper;
}

describe('/m estate — Lasting Powers of Attorney', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('reads back an instrument Fyn recorded from this device', async () => {
    stubLoad([
      { id: 4, lpa_type: 'property_financial', status: 'registered', type_label: 'Property & Financial Affairs', status_label: 'Registered' },
    ]);

    const wrapper = await mountEstate();

    expect(apiGet.mock.calls.map((call) => call[0])).toContain('/api/estate/lpa');
    expect(wrapper.text()).toContain('Property & Financial Affairs');
    expect(wrapper.text()).toContain('Registered');
  });

  it('says plainly when none is recorded, rather than showing nothing', async () => {
    stubLoad([]);

    const wrapper = await mountEstate();

    expect(wrapper.text()).toContain('You have not recorded a Lasting Power of Attorney yet.');
  });

  it('hands off to the web screen that holds the document', async () => {
    stubLoad([]);
    const wrapper = await mountEstate();

    const button = wrapper.findAll('button').find((b) => b.text() === 'Open on the web app'
      && b.element.closest('.m-card')?.textContent.includes('Lasting Powers of Attorney'));
    await button.trigger('click');
    await flushPromises();

    expect(issueWebHandoff).toHaveBeenCalledWith('estate_lpa');
  });
});
