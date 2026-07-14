import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import AnnualAllowanceTracker from '@/components/Retirement/AnnualAllowanceTracker.vue';

describe('AnnualAllowanceTracker', () => {
  const annualAllowance = {
    contributions_used: 15000,
    is_tapered: false,
    tapered_allowance: null,
    mpaa_triggered: false,
  };

  const createMockStore = ({
    allowance = annualAllowance,
    definedContributionPensions = [],
    profile = { current_income: 50000 },
    standardAllowance = 60000,
    moneyPurchaseAnnualAllowance = 10000,
  } = {}) => createStore({
    modules: {
      retirement: {
        namespaced: true,
        state: {
          annualAllowance: allowance,
          dcPensions: definedContributionPensions,
          profile,
        },
        actions: { fetchAnnualAllowance: () => Promise.resolve() },
      },
      taxConfig: {
        namespaced: true,
        getters: {
          pensionAnnualAllowance: () => standardAllowance,
          moneyPurchaseAnnualAllowance: () => moneyPurchaseAnnualAllowance,
        },
      },
    },
  });

  const mountTracker = (options = {}) => mount(AnnualAllowanceTracker, {
    global: { plugins: [createMockStore(options)] },
  });

  it('renders the current Annual Allowance', () => {
    const wrapper = mountTracker();

    expect(wrapper.text()).toContain('Annual Allowance Tracker');
    expect(wrapper.text()).toContain('£15,000');
    expect(wrapper.text()).toContain('/ £60,000');
  });

  it('calculates and displays the remaining allowance', () => {
    const wrapper = mountTracker();

    expect(wrapper.vm.remainingAllowance).toBe(45000);
    expect(wrapper.text()).toContain('£45,000');
  });

  it('calculates usage as a percentage of the configured allowance', () => {
    const wrapper = mountTracker();

    expect(wrapper.vm.progressPercent).toBe(25);
    expect(wrapper.text()).toContain('25% used');
  });

  it('renders progress width and the spring on-track palette', () => {
    const wrapper = mountTracker();
    const progress = wrapper.find('.h-4.rounded-full.transition-all');

    expect(progress.attributes('style')).toContain('width: 25%');
    expect(progress.classes()).toContain('bg-spring-500');
    expect(wrapper.vm.statusText).toBe('On Track');
  });

  it('uses a tapered allowance supplied by the backend', () => {
    const wrapper = mountTracker({
      allowance: {
        ...annualAllowance,
        is_tapered: true,
        tapered_allowance: 40000,
      },
    });

    expect(wrapper.vm.currentAllowance).toBe(40000);
    expect(wrapper.text()).toContain('Tapered Annual Allowance');
    expect(wrapper.text()).toContain('£40,000');
  });

  it('uses the Money Purchase Annual Allowance after flexible access', () => {
    const wrapper = mountTracker({
      allowance: { ...annualAllowance, mpaa_triggered: true },
    });

    expect(wrapper.vm.currentAllowance).toBe(10000);
    expect(wrapper.text()).toContain('Money Purchase Annual Allowance Triggered');
    expect(wrapper.text()).toContain('reduced to £10,000');
    expect(wrapper.vm.carryForwardYears).toEqual([]);
  });

  it('shows the three eligible carry-forward years dynamically', () => {
    const wrapper = mountTracker();

    expect(wrapper.vm.carryForwardYears).toHaveLength(3);
    expect(wrapper.findAll('.space-y-3 > div')).toHaveLength(3);
  });

  it('makes clear when historical carry-forward data is not yet tracked', () => {
    const wrapper = mountTracker();

    expect(wrapper.vm.hasCarryForwardData).toBe(false);
    expect(wrapper.findAll('.space-y-3 .italic').map(item => item.text())).toEqual([
      'Not yet tracked',
      'Not yet tracked',
      'Not yet tracked',
    ]);
    expect(wrapper.vm.totalAvailableWithCarryForward).toBe(45000);
  });

  it('calculates personal pension contributions from monthly amounts', () => {
    const wrapper = mountTracker({
      allowance: null,
      definedContributionPensions: [
        { scheme_type: 'personal', monthly_contribution_amount: 500 },
      ],
    });

    expect(wrapper.vm.calculatedContributions).toBe(6000);
    expect(wrapper.vm.contributionsUsed).toBe(6000);
  });

  it('calculates workplace contributions from employee and employer percentages', () => {
    const wrapper = mountTracker({
      allowance: null,
      definedContributionPensions: [{
        scheme_type: 'workplace',
        employee_contribution_percent: 5,
        employer_contribution_percent: 3,
        annual_salary: 50000,
      }],
    });

    expect(wrapper.vm.calculatedContributions).toBe(4000);
  });

  it('uses profile income when a workplace pension has no salary field', () => {
    const wrapper = mountTracker({
      allowance: null,
      profile: { current_income: 80000 },
      definedContributionPensions: [{
        scheme_type: 'workplace',
        employee_contribution_percent: 5,
        employer_contribution_percent: 5,
      }],
    });

    expect(wrapper.vm.calculatedContributions).toBe(8000);
  });

  it('uses violet caution styling when usage approaches the limit', () => {
    const wrapper = mountTracker({
      allowance: { ...annualAllowance, contributions_used: 50000 },
    });

    expect(wrapper.vm.progressPercent).toBe(83);
    expect(wrapper.vm.progressBarColour).toBe('bg-violet-500');
    expect(wrapper.vm.statusTextColour).toBe('text-violet-600');
    expect(wrapper.vm.statusText).toBe('Approaching Limit');
  });

  it('caps progress and uses raspberry danger styling after the limit is exceeded', () => {
    const wrapper = mountTracker({
      allowance: { ...annualAllowance, contributions_used: 65000 },
    });

    expect(wrapper.vm.progressPercent).toBe(100);
    expect(wrapper.vm.progressBarColour).toBe('bg-raspberry-500');
    expect(wrapper.vm.statusText).toBe('Allowance Exceeded');
    expect(wrapper.text()).toContain('£0');
  });

  it('offers the current tax year and three previous tax years', () => {
    const wrapper = mountTracker();

    expect(wrapper.vm.taxYearOptions).toHaveLength(4);
    expect(wrapper.find('select').findAll('option')).toHaveLength(4);
    expect(wrapper.vm.taxYearOptions[0]).toBe(wrapper.vm.currentTaxYear);
  });
});
