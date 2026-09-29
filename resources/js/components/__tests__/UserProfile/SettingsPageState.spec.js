import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';
// The service imports api.js, which builds the whole store; this spec needs
// the module's getters only.
vi.mock('../../../services/userProfileService', () => ({ default: {} }));

import userProfile from '../../../store/modules/userProfile';
import PersonalSettings from '../../../views/Settings/PersonalSettings.vue';

// Every userProfile save action sets `loading` and, when refused, `error`. The
// Settings pages used to gate on those, so a refused save replaced the form with
// "Error loading profile" (fynla.org, 2026-09-29). A page now waits only for the
// first load and shows a load error only when there is no profile to show.
describe('Settings page state', () => {
  const { pageLoading, pageLoadError } = userProfile.getters;

  it('keeps showing a loaded profile while a save is running', () => {
    expect(pageLoading({ loading: true, profile: { id: 1 } })).toBe(false);
    expect(pageLoading({ loading: true, profile: null })).toBe(true);
  });

  it('shows a load error only when no profile has loaded', () => {
    expect(pageLoadError({ error: 'The given data was invalid.', profile: { id: 1 } })).toBeNull();
    expect(pageLoadError({ error: 'Network Error', profile: null })).toBe('Network Error');
  });

  const mountWith = (state) => mount(PersonalSettings, {
    global: {
      plugins: [createStore({ modules: { userProfile: { namespaced: true, state: () => state, getters: userProfile.getters, actions: { fetchProfile: () => {} } } } })],
      stubs: {
        AppLayout: { template: '<div><slot /></div>' },
        SettingsTabBar: true,
        PersonalInformation: { name: 'PersonalInformation', template: '<div class="personal-information-stub" />' },
      },
    },
  });

  it('keeps the form on screen after a refused save', () => {
    const wrapper = mountWith({ profile: { id: 1 }, loading: false, error: 'The given data was invalid.' });

    expect(wrapper.find('.personal-information-stub').exists()).toBe(true);
    expect(wrapper.text()).not.toContain('Error loading profile');
  });

  it('shows the load error when the profile never arrived', () => {
    const wrapper = mountWith({ profile: null, loading: false, error: 'Network Error' });

    expect(wrapper.text()).toContain('Error loading profile');
    expect(wrapper.find('.personal-information-stub').exists()).toBe(false);
  });
});
