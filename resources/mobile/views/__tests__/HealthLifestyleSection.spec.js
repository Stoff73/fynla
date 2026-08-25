import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('../../api.js', () => ({
  apiGet: vi.fn(),
  apiPut: vi.fn(),
}));

import { apiGet, apiPut } from '../../api.js';
import PersonalInformation from '../PersonalInformation.vue';

/**
 * W-0034. `/m` rendered no health, smoking or education field anywhere — zero
 * grep hits across resources/mobile — even after W-0006 corrected the
 * getCompleteProfile payload that this screen already fetches.
 *
 * The write goes to the SAME endpoint and the SAME validator as desktop
 * (PUT /api/user/profile/personal -> UpdatePersonalInfoRequest), which is what
 * keeps W-0006's empty-select trap from being reintroduced here: '' becomes null
 * and prepareForValidation drops the key, rather than /m inventing its own rules.
 */
const profileWith = (overrides = {}) => ({
  personal_info: {
    name: 'Alex Morgan',
    email: 'alex@example.com',
    address: {},
    ...overrides,
  },
  household: {},
  income_occupation: {},
  expenditure: {},
  assets_summary: {},
  liabilities_summary: {},
});

const MobileChromeStub = {
  props: ['title', 'subtitle', 'loading', 'loadingLabel', 'contextualRequest'],
  template: '<main><slot /></main>',
};

function mountView() {
  return mount(PersonalInformation, {
    global: {
      mocks: { $router: { push: vi.fn() }, $route: { path: '/personal-information', query: {} } },
      stubs: { MobileChrome: MobileChromeStub },
    },
  });
}

describe('/m Health & Lifestyle section', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    apiGet.mockResolvedValue({ ok: true, status: 200, data: { data: profileWith() } });
    apiPut.mockResolvedValue({ ok: true, status: 200, data: {} });
  });

  it('renders the three stored values', async () => {
    apiGet.mockResolvedValue({
      ok: true,
      status: 200,
      data: {
        data: profileWith({
          health_status: 'yes',
          smoking_status: 'never',
          education_level: 'postgraduate',
        }),
      },
    });

    const wrapper = mountView();
    await flushPromises();

    expect(wrapper.get('[data-testid="health-status-value"]').text()).toBe('Yes, good health');
    expect(wrapper.get('[data-testid="smoking-status-value"]').text()).toBe('Never smoked');
    expect(wrapper.get('[data-testid="education-level-value"]').text()).toBe('Postgraduate Degree');
  });

  it('says "Not recorded" rather than rendering a blank when nothing is stored', async () => {
    const wrapper = mountView();
    await flushPromises();

    expect(wrapper.get('[data-testid="health-status-value"]').text()).toBe('Not recorded');
  });

  it('writes to the same endpoint the desktop form uses', async () => {
    apiGet.mockResolvedValue({
      ok: true,
      status: 200,
      data: { data: profileWith({ health_status: 'yes', smoking_status: 'never' }) },
    });

    const wrapper = mountView();
    await flushPromises();

    await wrapper.get('[data-testid="health-edit"]').trigger('click');
    await wrapper.get('[data-testid="education-level-input"]').setValue('postgraduate');
    await wrapper.get('[data-testid="health-save"]').trigger('submit');
    await flushPromises();

    expect(apiPut).toHaveBeenCalledTimes(1);
    const [path, body] = apiPut.mock.calls[0];

    expect(path).toBe('/api/user/profile/personal');
    expect(body).toEqual({
      health_status: 'yes',
      smoking_status: 'never',
      education_level: 'postgraduate',
    });
  });

  it('seeds the form from the stored values rather than blanking them', async () => {
    apiGet.mockResolvedValue({
      ok: true,
      status: 200,
      data: { data: profileWith({ health_status: 'no_existing', smoking_status: 'quit_recent' }) },
    });

    const wrapper = mountView();
    await flushPromises();
    await wrapper.get('[data-testid="health-edit"]').trigger('click');

    expect(wrapper.get('[data-testid="health-status-input"]').element.value).toBe('no_existing');
    expect(wrapper.get('[data-testid="smoking-status-input"]').element.value).toBe('quit_recent');
  });

  it('surfaces a failed save instead of closing as though it worked', async () => {
    apiPut.mockResolvedValue({ ok: false, status: 422, data: { message: 'Validation failed' } });

    const wrapper = mountView();
    await flushPromises();

    await wrapper.get('[data-testid="health-edit"]').trigger('click');
    await wrapper.get('[data-testid="health-save"]').trigger('submit');
    await flushPromises();

    expect(wrapper.get('[data-testid="health-error"]').text()).toBe('Validation failed');
    // Still in edit mode — the user's unsaved answers are not thrown away.
    expect(wrapper.find('[data-testid="health-save"]').exists()).toBe(true);
  });

  it('re-reads the profile after a successful save', async () => {
    const wrapper = mountView();
    await flushPromises();
    expect(apiGet).toHaveBeenCalledTimes(1);

    await wrapper.get('[data-testid="health-edit"]').trigger('click');
    await wrapper.get('[data-testid="health-save"]').trigger('submit');
    await flushPromises();

    expect(apiGet).toHaveBeenCalledTimes(2);
    expect(wrapper.find('[data-testid="health-save"]').exists()).toBe(false);
  });

  it('carries no icons, emoji or glyphs into the section (Rule 15)', async () => {
    const wrapper = mountView();
    await flushPromises();

    const section = wrapper.get('[aria-labelledby="profile-health-heading"]');

    expect(section.findAll('svg')).toHaveLength(0);
    expect(section.findAll('i')).toHaveLength(0);

    // Emoji, pictographs and the Unicode-as-icon glyphs Rule 15 names by hand.
    const glyphs = /\p{Extended_Pictographic}|[\u2190-\u21FF\u2600-\u27BF\u2B00-\u2BFF\u2705\u2714\u2716\u26A0\u2139\u2605\u2606]/u;

    expect(section.text()).not.toMatch(glyphs);
  });
});
