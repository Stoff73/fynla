import { describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('../../api.js', () => ({ apiGet: vi.fn(), apiPut: vi.fn() }));

import { apiGet } from '../../api.js';
import PersonalInformation from '../PersonalInformation.vue';

// "Where you have lived" gives the same answers as the web page (the server's
// LongTermResidence explanation, else long-term / not yet / not known), never the
// raw status column ("non uk domiciled").
const mountWith = async (domicileInfo) => {
  apiGet.mockResolvedValue({ ok: true, status: 200, data: { data: {
    personal_info: { name: 'Alex Morgan', email: 'alex@example.com', address: {} },
    household: {}, income_occupation: {}, expenditure: {}, assets_summary: {}, liabilities_summary: {},
    domicile_info: domicileInfo,
  } } });
  const wrapper = mount(PersonalInformation, {
    global: {
      mocks: { $router: { push: vi.fn() }, $route: { path: '/personal-information', query: {} } },
      stubs: { MobileChrome: { template: '<main><slot /></main>' } },
    },
  });
  await flushPromises();
  return wrapper.get('[aria-labelledby="profile-domicile-heading"]').text();
};

describe('/m Where you have lived', () => {
  it('shows the server explanation when there is one', async () => {
    expect(await mountWith({ domicile_status: 'uk_domiciled', explanation: 'You are a long-term UK resident.' }))
      .toContain('You are a long-term UK resident.');
  });

  it('never shows the raw status column', async () => {
    const text = await mountWith({ domicile_status: 'non_uk_domiciled', is_long_term_uk_resident: false });

    expect(text).toContain('Not yet a long-term UK resident');
    expect(text).not.toContain('domiciled');
  });

  it('says not known yet when nothing is recorded', async () => {
    expect(await mountWith({})).toContain('Not known yet');
  });
});
