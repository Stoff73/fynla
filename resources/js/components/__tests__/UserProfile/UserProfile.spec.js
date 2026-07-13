import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import { createMemoryHistory, createRouter } from 'vue-router';
import UserProfile from '../../../views/UserProfile.vue';

describe('UserProfile.vue', () => {
  let wrapper;
  let store;
  let router;
  let fetchProfile;

  const createTestStore = ({ subscriptionData = null, isPreviewMode = false } = {}) => {
    fetchProfile = vi.fn(() => Promise.resolve());

    return createStore({
      state: {
        aiFormFill: { pendingFill: null },
      },
      modules: {
        userProfile: {
          namespaced: true,
          state: () => ({
            loading: false,
            error: null,
          }),
          getters: {
            loading: (state) => state.loading,
            error: (state) => state.error,
          },
          actions: {
            fetchProfile,
          },
        },
        auth: {
          namespaced: true,
          state: () => ({ subscriptionData }),
        },
        preview: {
          namespaced: true,
          getters: {
            isPreviewMode: () => isPreviewMode,
          },
        },
      },
    });
  };

  const mountComponent = (testStore = store) => mount(UserProfile, {
    global: {
      plugins: [testStore, router],
      stubs: {
        AppLayout: {
          name: 'AppLayout',
          template: '<main data-test="app-layout"><slot /></main>',
        },
        ModuleStatusBar: {
          name: 'ModuleStatusBar',
          template: '<div class="module-status-stub" />',
        },
        PersonalInformation: {
          name: 'PersonalInformation',
          template: '<div class="personal-info-stub" />',
        },
        HealthInformation: {
          name: 'HealthInformation',
          template: '<div class="health-info-stub" />',
        },
        FamilyMembers: {
          name: 'FamilyMembers',
          template: '<div class="family-members-stub" />',
        },
        SubscriptionManagement: {
          name: 'SubscriptionManagement',
          template: '<div class="subscription-stub" />',
        },
      },
    },
  });

  beforeEach(async () => {
    window.history.replaceState({}, '', '/profile');
    store = createTestStore();
    router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/profile', component: { template: '<div />' } },
      ],
    });
    await router.push('/profile');
    await router.isReady();

    wrapper = mountComponent();
    await flushPromises();
  });

  it('renders the routed profile page inside AppLayout', () => {
    expect(wrapper.findComponent({ name: 'AppLayout' }).exists()).toBe(true);
    expect(wrapper.get('h1').text()).toBe('User Profile');
  });

  it('renders the current profile tabs for a full-plan user', () => {
    const tabs = wrapper.findAll('nav[aria-label="Tabs"] button');
    expect(tabs.map((tab) => tab.text())).toEqual([
      'Personal Info',
      'Health',
      'Family',
      'Subscription',
    ]);
  });

  it('shows personal information by default', () => {
    expect(wrapper.vm.activeTab).toBe('personal');
    expect(wrapper.findComponent({ name: 'PersonalInformation' }).isVisible()).toBe(true);
    expect(wrapper.findComponent({ name: 'HealthInformation' }).isVisible()).toBe(false);
  });

  it('switches between the current profile sections', async () => {
    const tabs = wrapper.findAll('nav[aria-label="Tabs"] button');

    await tabs[1].trigger('click');
    expect(wrapper.vm.activeTab).toBe('health');
    expect(wrapper.findComponent({ name: 'HealthInformation' }).isVisible()).toBe(true);

    await tabs[2].trigger('click');
    expect(wrapper.vm.activeTab).toBe('family');
    expect(wrapper.findComponent({ name: 'FamilyMembers' }).isVisible()).toBe(true);

    await tabs[3].trigger('click');
    expect(wrapper.vm.activeTab).toBe('subscription');
    expect(wrapper.findComponent({ name: 'SubscriptionManagement' }).isVisible()).toBe(true);
  });

  it('loads the profile on mount', () => {
    expect(fetchProfile).toHaveBeenCalledTimes(1);
  });

  it('shows the loading state while the profile is fetched', async () => {
    store.state.userProfile.loading = true;
    await wrapper.vm.$nextTick();

    expect(wrapper.text()).toContain('Loading profile...');
    expect(wrapper.findComponent({ name: 'PersonalInformation' }).exists()).toBe(false);
  });

  it('shows the store error and retries the profile request', async () => {
    store.state.userProfile.error = 'Failed to load profile';
    await wrapper.vm.$nextTick();

    expect(wrapper.text()).toContain('Error loading profile');
    expect(wrapper.text()).toContain('Failed to load profile');
    const retryButton = wrapper.findAll('button').find((button) => button.text() === 'Try Again');
    await retryButton.trigger('click');
    await flushPromises();
    expect(fetchProfile).toHaveBeenCalledTimes(2);
  });

  it('hides the family tab below the family plan', async () => {
    store = createTestStore({
      subscriptionData: { status: 'active', plan: 'standard' },
    });
    wrapper = mountComponent();
    await flushPromises();

    const labels = wrapper.findAll('nav[aria-label="Tabs"] button').map((tab) => tab.text());
    expect(labels).toEqual(['Personal Info', 'Health', 'Subscription']);
  });

  it('keeps the family tab available in preview mode', async () => {
    store = createTestStore({
      subscriptionData: { status: 'active', plan: 'student' },
      isPreviewMode: true,
    });
    wrapper = mountComponent();
    await flushPromises();

    const labels = wrapper.findAll('nav[aria-label="Tabs"] button').map((tab) => tab.text());
    expect(labels).toContain('Family');
  });

  it('opens a valid section supplied in the page query', async () => {
    window.history.replaceState({}, '', '/profile?section=health');
    wrapper.unmount();
    wrapper = mountComponent();
    await flushPromises();

    expect(wrapper.vm.activeTab).toBe('health');
    expect(wrapper.findComponent({ name: 'HealthInformation' }).isVisible()).toBe(true);
  });

  it('opens the family section for a family-member form-fill request', async () => {
    store.state.aiFormFill.pendingFill = {
      entityType: 'family_member',
      mode: 'add',
    };
    await wrapper.vm.$nextTick();

    expect(wrapper.vm.activeTab).toBe('family');
  });

  it('maintains the selected section across ordinary component updates', async () => {
    const familyTab = wrapper.findAll('nav[aria-label="Tabs"] button')
      .find((tab) => tab.text() === 'Family');
    await familyTab.trigger('click');
    await wrapper.vm.$forceUpdate();
    await wrapper.vm.$nextTick();

    expect(wrapper.vm.activeTab).toBe('family');
  });
});
