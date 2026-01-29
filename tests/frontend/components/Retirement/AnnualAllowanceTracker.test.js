import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import AnnualAllowanceTracker from '@/components/Retirement/AnnualAllowanceTracker.vue';

describe('AnnualAllowanceTracker', () => {
  const createMockStore = (annualAllowanceData = null) => {
    return createStore({
      modules: {
        retirement: {
          namespaced: true,
          state: {
            annualAllowance: annualAllowanceData,
          },
          actions: {
            fetchAnnualAllowance: () => Promise.resolve(),
          },
        },
      },
    });
  };

  const defaultAnnualAllowance = {
    standard_allowance: 60000,
    available_allowance: 60000,
    contributions_used: 15000,
    remaining_allowance: 45000,
    is_tapered: false,
    tapered_allowance: null,
    mpaa_triggered: false,
    carry_forward_available: 0,
  };

  it('renders with annual allowance data', () => {
    const store = createMockStore(defaultAnnualAllowance);
    const wrapper = mount(AnnualAllowanceTracker, {
      global: {
        plugins: [store],
      },
    });

    expect(wrapper.exists()).toBe(true);
  });

  it('displays standard allowance £60,000', () => {
    const store = createMockStore(defaultAnnualAllowance);
    const wrapper = mount(AnnualAllowanceTracker, {
      global: {
        plugins: [store],
      },
    });

    const html = wrapper.html();
    expect(html).toMatch(/60,?000|£60,?000/);
  });

  it('displays contributions used', () => {
    const store = createMockStore(defaultAnnualAllowance);
    const wrapper = mount(AnnualAllowanceTracker, {
      global: {
        plugins: [store],
      },
    });

    const html = wrapper.html();
    expect(html).toMatch(/15,?000|£15,?000/);
  });

  it('displays remaining allowance', () => {
    const store = createMockStore(defaultAnnualAllowance);
    const wrapper = mount(AnnualAllowanceTracker, {
      global: {
        plugins: [store],
      },
    });

    const html = wrapper.html();
    expect(html).toMatch(/45,?000|£45,?000/);
  });

  it('shows progress bar', () => {
    const store = createMockStore(defaultAnnualAllowance);
    const wrapper = mount(AnnualAllowanceTracker, {
      global: {
        plugins: [store],
      },
    });

    const progressBar = wrapper.find('.progress-bar, [role="progressbar"]');
    expect(progressBar.exists()).toBe(true);
  });

  it('calculates percentage correctly', () => {
    const store = createMockStore(defaultAnnualAllowance);
    const wrapper = mount(AnnualAllowanceTracker, {
      global: {
        plugins: [store],
      },
    });

    // 15,000 / 60,000 = 25%
    expect(wrapper.vm.progressPercent).toBe(25);
  });

  it('displays tapered allowance when applicable', () => {
    const taperedAllowance = {
      standard_allowance: 60000,
      available_allowance: 40000,
      contributions_used: 15000,
      remaining_allowance: 25000,
      is_tapered: true,
      tapered_allowance: 40000,
      mpaa_triggered: false,
      carry_forward_available: 0,
    };
    const store = createMockStore(taperedAllowance);
    const wrapper = mount(AnnualAllowanceTracker, {
      global: {
        plugins: [store],
      },
    });

    const html = wrapper.html();
    expect(html).toMatch(/tapered|reduced/i);
    expect(html).toMatch(/40,?000|£40,?000/);
  });

  it('displays MPAA status when triggered', () => {
    const mpaaAllowance = {
      ...defaultAnnualAllowance,
      available_allowance: 10000,
      mpaa_triggered: true,
    };
    const store = createMockStore(mpaaAllowance);
    const wrapper = mount(AnnualAllowanceTracker, {
      global: {
        plugins: [store],
      },
    });

    const html = wrapper.html();
    expect(html).toMatch(/MPAA|Money Purchase Annual Allowance/i);
    expect(html).toMatch(/10,?000|£10,?000/);
  });

  it('shows carry forward available', () => {
    const carryForwardAllowance = {
      ...defaultAnnualAllowance,
      carry_forward_available: 30000,
    };
    const store = createMockStore(carryForwardAllowance);
    const wrapper = mount(AnnualAllowanceTracker, {
      global: {
        plugins: [store],
      },
    });

    const html = wrapper.html();
    expect(html).toMatch(/carry forward/i);
    expect(html).toMatch(/30,?000|£30,?000/);
  });

  it('shows carry forward years (3 years)', () => {
    const carryForwardAllowance = {
      ...defaultAnnualAllowance,
      carry_forward_available: 30000,
    };
    const store = createMockStore(carryForwardAllowance);
    const wrapper = mount(AnnualAllowanceTracker, {
      global: {
        plugins: [store],
      },
    });

    const html = wrapper.html();
    expect(html).toMatch(/3 years|previous 3 years/i);
  });

  it('highlights when approaching limit (>80%)', () => {
    const highUsageAllowance = {
      standard_allowance: 60000,
      available_allowance: 60000,
      contributions_used: 50000, // 83%
      remaining_allowance: 10000,
      is_tapered: false,
      tapered_allowance: null,
      mpaa_triggered: false,
      carry_forward_available: 0,
    };
    const store = createMockStore(highUsageAllowance);
    const wrapper = mount(AnnualAllowanceTracker, {
      global: {
        plugins: [store],
      },
    });

    const html = wrapper.html();
    // Should have warning styling
    expect(html).toMatch(/warning|orange/i);
  });

  it('highlights when exceeding limit', () => {
    const exceededAllowance = {
      standard_allowance: 60000,
      available_allowance: 60000,
      contributions_used: 65000, // Exceeded
      remaining_allowance: -5000,
      is_tapered: false,
      tapered_allowance: null,
      mpaa_triggered: false,
      carry_forward_available: 0,
      excess_contributions: 5000,
    };
    const store = createMockStore(exceededAllowance);
    const wrapper = mount(AnnualAllowanceTracker, {
      global: {
        plugins: [store],
      },
    });

    const html = wrapper.html();
    // Should have danger/red styling
    expect(html).toMatch(/exceeded|excess|danger|red/i);
  });

  it('shows tax year selector', () => {
    const store = createMockStore(defaultAnnualAllowance);
    const wrapper = mount(AnnualAllowanceTracker, {
      global: {
        plugins: [store],
      },
    });

    const html = wrapper.html();
    // Should have tax year dropdown with current year
    expect(html).toMatch(/2024\/25/);
  });
});
