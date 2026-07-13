import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import Dashboard from '@/views/Dashboard.vue';

describe('Dashboard', () => {
  let store;
  let router;
  let refreshCompleteness;
  let fetchGamificationStatus;

  const mountDashboard = () => mount(Dashboard, {
    global: {
      plugins: [store],
      mocks: {
        $route: { query: {} },
        $router: router,
      },
      stubs: {
        AppLayout: { template: '<main class="app-layout"><slot /></main>' },
        GamifiedDashboard: { template: '<section class="gamified-dashboard">Dashboard</section>' },
        GamificationCelebration: true,
        RouterLink: true,
      },
    },
  });

  beforeEach(() => {
    sessionStorage.clear();
    localStorage.clear();
    router = { push: vi.fn(), replace: vi.fn() };
    refreshCompleteness = vi.fn(() => Promise.resolve());
    fetchGamificationStatus = vi.fn(() => Promise.resolve());

    store = createStore({
      modules: {
        auth: {
          namespaced: true,
          state: () => ({ user: null }),
          getters: {
            currentUser: state => state.user,
            isAdmin: () => false,
          },
        },
        gamification: {
          namespaced: true,
          state: () => ({ pendingCelebration: null }),
          actions: { fetchStatus: fetchGamificationStatus },
        },
        lifeStage: {
          namespaced: true,
          getters: { currentStage: () => null, dashboardCards: () => [] },
          actions: { refreshCompleteness },
        },
        preview: {
          namespaced: true,
          getters: { effectivePersonaData: () => ({}) },
        },
        plans: {
          namespaced: true,
          getters: { getPlan: () => () => null },
        },
        taxConfig: {
          namespaced: true,
          getters: { isaAnnualAllowance: () => 20000, pensionAnnualAllowance: () => 60000 },
        },
        netWorth: {
          namespaced: true,
          getters: {
            netWorth: () => 0,
            totalAssets: () => 0,
            totalLiabilities: () => 0,
            overview: () => ({}),
          },
        },
      },
    });
  });

  it('renders inside the authenticated application layout', () => {
    const wrapper = mountDashboard();
    expect(wrapper.get('.app-layout').exists()).toBe(true);
  });

  it('renders the current gamified dashboard surface', () => {
    const wrapper = mountDashboard();
    expect(wrapper.get('.gamified-dashboard').text()).toBe('Dashboard');
  });

  it('refreshes journey completeness on mount', () => {
    mountDashboard();
    expect(refreshCompleteness).toHaveBeenCalledOnce();
  });

  it('refreshes gamification status on mount', () => {
    mountDashboard();
    expect(fetchGamificationStatus).toHaveBeenCalledOnce();
  });

  it('does not show the security reminder without a signed-in user', () => {
    const wrapper = mountDashboard();
    expect(wrapper.vm.showMFABanner).toBe(false);
  });

  it('shows the security reminder for an eligible user', () => {
    expect(Dashboard.computed.showMFABanner.call({
      currentUser: { is_preview_user: false, mfa_enabled: false },
      mfaBannerDismissed: false,
    })).toBe(true);
  });

  it('does not show the security reminder to preview users', () => {
    expect(Dashboard.computed.showMFABanner.call({
      currentUser: { is_preview_user: true, mfa_enabled: false },
      mfaBannerDismissed: false,
    })).toBe(false);
  });

  it('persists dismissal of the security reminder for the session', () => {
    const wrapper = mountDashboard();
    wrapper.vm.dismissMFABanner();
    expect(wrapper.vm.mfaBannerDismissed).toBe(true);
    expect(sessionStorage.getItem('mfaBannerDismissed')).toBe('true');
  });

  it('navigates through the shared dashboard navigation helper', () => {
    const wrapper = mountDashboard();
    wrapper.vm.navigateTo('/estate');
    expect(router.push).toHaveBeenCalledWith('/estate');
  });

  it('uses current palette bands for allowance progress', () => {
    const wrapper = mountDashboard();
    expect(wrapper.vm.allowanceBarClass(50, false)).toContain('horizon');
    expect(wrapper.vm.allowanceBarClass(80, false)).toContain('violet');
    expect(wrapper.vm.allowanceBarClass(96, false)).toContain('raspberry');
  });

  it('derives the net-worth summary from current store getters', () => {
    expect(Dashboard.computed.netWorthData.call({
      netWorthValue: 475000,
      netWorthAssets: 600000,
      netWorthLiabilities: 125000,
    })).toEqual({ netWorth: 475000, totalAssets: 600000, totalLiabilities: 125000 });
  });

  it('identifies the student preview persona from the current user contract', () => {
    expect(Dashboard.computed.isStudentPersona.call({
      currentUser: { preview_persona_id: 'student', is_preview_user: true },
    })).toBe(true);
    expect(Dashboard.computed.isStudentPersona.call({
      currentUser: { preview_persona_id: 'student', is_preview_user: false },
    })).toBe(false);
  });

  it('does not request the Pro-only trust list from the general dashboard', async () => {
    const dispatch = vi.fn(() => Promise.resolve());

    await Dashboard.methods.loadAllData.call({
      currentUser: { marital_status: 'single' },
      isStudentPersona: false,
      loading: {},
      errors: {},
      loadFinancialCommitments: vi.fn(),
      fetchProjection: vi.fn(() => Promise.resolve()),
      $store: { dispatch },
    });

    expect(dispatch).not.toHaveBeenCalledWith('trusts/fetchTrusts', undefined);
  });

  it('removes its resize listener when unmounted', () => {
    const removeEventListener = vi.spyOn(window, 'removeEventListener');
    const wrapper = mountDashboard();
    wrapper.unmount();
    expect(removeEventListener).toHaveBeenCalledWith('resize', expect.any(Function));
  });
});
