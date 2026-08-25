import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import NRBRNRBTracker from '@/components/Estate/NRBRNRBTracker.vue';

const taxStore = () => createStore({
  modules: {
    taxConfig: {
      namespaced: true,
      getters: {
        ihtNilRateBand: () => 325000,
        ihtResidenceNilRateBand: () => 175000,
        ihtRnrbTaperThreshold: () => 2000000,
      },
    },
  },
});

const mountTracker = (props = {}) => mount(NRBRNRBTracker, {
  props: { estateValue: 800000, ...props },
  global: { plugins: [taxStore()] },
});

describe('NRBRNRBTracker', () => {
  it('renders with the estate value contract', () => {
    expect(mountTracker().exists()).toBe(true);
  });

  it('uses the configured nil-rate band', () => {
    expect(mountTracker().vm.nrbTotal).toBe(325000);
  });

  it('uses the configured residence nil-rate band for an eligible estate', () => {
    const wrapper = mountTracker({ hasMainResidence: true, hasDirectDescendants: true });
    expect(wrapper.vm.rnrbTotal).toBe(175000);
  });

  it('adds a transferred spouse nil-rate band', () => {
    const wrapper = mountTracker({ hasSpouseTransfer: true, spouseNrbTransfer: 325000 });
    expect(wrapper.vm.nrbTotal).toBe(650000);
  });

  it('tapers the residence nil-rate band above the configured threshold', () => {
    const wrapper = mountTracker({ estateValue: 2200000, hasMainResidence: true, hasDirectDescendants: true });
    expect(wrapper.vm.rnrbTotal).toBe(75000);
    expect(wrapper.vm.rnrbTapered).toBe(true);
  });

  it('does not allow a tapered residence band to become negative', () => {
    const wrapper = mountTracker({ estateValue: 2500000, hasMainResidence: true, hasDirectDescendants: true });
    expect(wrapper.vm.rnrbTotal).toBe(0);
  });

  it('returns no residence band when the estate is ineligible', () => {
    expect(mountTracker({ hasMainResidence: true, hasDirectDescendants: false }).vm.rnrbTotal).toBe(0);
  });

  it('combines the available nil-rate bands', () => {
    const wrapper = mountTracker({ hasMainResidence: true, hasDirectDescendants: true });
    expect(wrapper.vm.totalAllowance).toBe(500000);
  });

  it('calculates the taxable amount above available bands', () => {
    const wrapper = mountTracker({ hasMainResidence: true, hasDirectDescendants: true });
    expect(wrapper.vm.taxableAmount).toBe(300000);
  });

  it('calculates the taxable amount when only the standard band applies', () => {
    expect(mountTracker({ estateValue: 400000 }).vm.taxableAmount).toBe(75000);
  });

  it('calculates standard-band usage from the supplied used amount', () => {
    expect(mountTracker({ nrbUsed: 325000 }).vm.nrbPercentage).toBe(100);
  });

  it('calculates residence-band usage from the supplied used amount', () => {
    const wrapper = mountTracker({ hasMainResidence: true, hasDirectDescendants: true, rnrbUsed: 87500 });
    expect(wrapper.vm.rnrbPercentage).toBe(50);
  });

  it('exposes spouse-transfer status for the eligibility note', () => {
    const wrapper = mountTracker({ hasSpouseTransfer: true, spouseNrbTransfer: 100000 });
    expect(wrapper.vm.hasSpouseTransfer).toBe(true);
    expect(wrapper.vm.showEligibilityNotes).toBe(true);
  });

  it('does not taper an estate exactly at the configured threshold', () => {
    const wrapper = mountTracker({ estateValue: 2000000, hasMainResidence: true, hasDirectDescendants: true });
    expect(wrapper.vm.rnrbTotal).toBe(175000);
    expect(wrapper.vm.rnrbTapered).toBe(false);
  });

  it('combines a spouse transfer with the available residence band', () => {
    const wrapper = mountTracker({
      estateValue: 1200000,
      hasSpouseTransfer: true,
      spouseNrbTransfer: 325000,
      hasMainResidence: true,
      hasDirectDescendants: true,
    });
    expect(wrapper.vm.nrbTotal).toBe(650000);
    expect(wrapper.vm.rnrbTotal).toBe(175000);
    expect(wrapper.vm.totalAllowance).toBe(825000);
  });

  it('formats configured allowances as currency in the rendered summary', () => {
    const text = mountTracker({ hasMainResidence: true, hasDirectDescendants: true }).text();
    expect(text).toContain('£325,000');
    expect(text).toContain('£175,000');
  });
});
