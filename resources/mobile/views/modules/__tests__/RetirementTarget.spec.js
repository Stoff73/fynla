import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('../../../api.js', () => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
  apiPut: vi.fn(),
}));

import { apiGet, apiPost, apiPut } from '../../../api.js';
import Retirement from '../Retirement.vue';

/**
 * W-0035. `retirement_profiles.target_retirement_income` is the figure every
 * retirement projection is built on, and no form on any surface could write it —
 * only Fyn's `capture_retirement_goals` tool. On `/m` it was worse than on the web:
 * the analysis reads the profile directly with no fallback, so a user who had never
 * stated a target saw "—" where the web app showed a derived figure.
 *
 * The write goes to the SAME endpoint and the SAME store as the desktop card
 * (PUT /api/retirement/goals -> RetirementProfileStore). `/m` does not get its own
 * write path, its own validation, or its own idea of what the target means (Rule 20).
 */
const MobileChromeStub = {
  props: ['title', 'subtitle', 'loading', 'loadingLabel', 'contextualRequest'],
  template: '<main><slot /></main>',
};

const retirementPayload = (profile = null) => ({
  data: {
    profile,
    dc_pensions: [],
    db_pensions: [],
    state_pension: null,
    account_count: 0,
    account_limit: null,
  },
});

function stubLoad({ profile = null, requiredCapital = null } = {}) {
  apiGet.mockImplementation((path) => {
    if (path === '/api/retirement') {
      return Promise.resolve({ ok: true, status: 200, data: retirementPayload(profile) });
    }
    if (path === '/api/retirement/required-capital') {
      return Promise.resolve({ ok: true, status: 200, data: { data: requiredCapital } });
    }
    return Promise.resolve({ ok: false, status: 200, data: {} });
  });
  apiPost.mockResolvedValue({ ok: false, status: 200, data: {} });
  apiPut.mockResolvedValue({ ok: true, status: 200, data: { success: true } });
}

async function mountView() {
  const wrapper = mount(Retirement, {
    global: {
      mocks: { $router: { push: vi.fn() }, $route: { path: '/retirement', query: {} } },
      stubs: { MobileChrome: MobileChromeStub },
    },
  });
  await flushPromises();
  return wrapper;
}

describe('/m retirement target', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows a stated target as the user’s own figure', async () => {
    stubLoad({
      profile: { target_retirement_income: '55000.00', target_retirement_age: 60 },
      requiredCapital: { required_income: 55000, income_source: 'profile' },
    });

    const wrapper = await mountView();

    expect(wrapper.get('[data-testid="retirement-target-income"]').text()).toContain('55,000');
    expect(wrapper.get('[data-testid="retirement-target-age"]').text()).toBe('60');
    expect(wrapper.text()).toContain('The figure you told us you want.');
  });

  /**
   * The whole defect in one assertion: the household was shown £116,250 a year as
   * its target when it had said £55,000, with nothing on screen admitting the
   * figure had been worked out for them.
   */
  it('says out loud when the figure was worked out rather than chosen', async () => {
    stubLoad({
      profile: null,
      requiredCapital: { required_income: 116250, income_source: 'calculated' },
    });

    const wrapper = await mountView();

    expect(wrapper.get('[data-testid="retirement-target-income"]').text()).toContain('116,250');
    expect(wrapper.text()).toContain('Worked out from your income, because you have not set a target yet.');
  });

  it('offers to set a target when nothing has one', async () => {
    stubLoad({ profile: null, requiredCapital: null });

    const wrapper = await mountView();

    expect(wrapper.get('[data-testid="retirement-target-income"]').text()).toBe('Not set');
    expect(wrapper.get('[data-testid="retirement-target-edit"]').text()).toBe('Set');
  });

  it('writes to the shared goals endpoint, sending only what was answered', async () => {
    stubLoad({ profile: null, requiredCapital: { required_income: 90000, income_source: 'calculated' } });

    const wrapper = await mountView();
    await wrapper.get('[data-testid="retirement-target-edit"]').trigger('click');
    await wrapper.get('[data-testid="retirement-target-income-input"]').setValue('55000');
    await wrapper.get('form.mr-target__form').trigger('submit');
    await flushPromises();

    // The age was left blank, so it is not sent — the endpoint leaves an omitted
    // value alone rather than clearing it, and nothing on screen offers "unset".
    expect(apiPut.mock.calls[0][0]).toBe('/api/retirement/goals');
    expect(apiPut.mock.calls[0][1]).toEqual({ target_retirement_income: 55000 });
  });

  it('does not pre-fill the box with the derived figure', async () => {
    // Pre-filling would turn "we worked this out" into "you chose this" the moment
    // the user pressed save without touching it.
    stubLoad({ profile: null, requiredCapital: { required_income: 116250, income_source: 'calculated' } });

    const wrapper = await mountView();
    await wrapper.get('[data-testid="retirement-target-edit"]').trigger('click');

    expect(wrapper.get('[data-testid="retirement-target-income-input"]').element.value).toBe('');
  });

  it('keeps the form open and says so when the save fails', async () => {
    stubLoad({ profile: null, requiredCapital: null });
    apiPut.mockResolvedValue({ ok: false, status: 422, data: { message: 'Set your target retirement age first.' } });

    const wrapper = await mountView();
    await wrapper.get('[data-testid="retirement-target-edit"]').trigger('click');
    await wrapper.get('[data-testid="retirement-target-income-input"]').setValue('55000');
    await wrapper.get('form.mr-target__form').trigger('submit');
    await flushPromises();

    expect(wrapper.get('[data-testid="retirement-target-error"]').text())
      .toBe('Set your target retirement age first.');
    expect(wrapper.find('[data-testid="retirement-target-save"]').exists()).toBe(true);
  });

  it('refuses an empty submission rather than calling the endpoint', async () => {
    stubLoad({ profile: null, requiredCapital: null });

    const wrapper = await mountView();
    await wrapper.get('[data-testid="retirement-target-edit"]').trigger('click');
    await wrapper.get('form.mr-target__form').trigger('submit');
    await flushPromises();

    expect(apiPut).not.toHaveBeenCalled();
    expect(wrapper.get('[data-testid="retirement-target-error"]').text())
      .toBe('Enter a target income, a target retirement age, or both.');
  });
});
