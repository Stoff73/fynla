import { describe, it, expect } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import UnifiedPensionForm from '@/components/Retirement/UnifiedPensionForm.vue';

describe('UnifiedPensionForm', () => {
  const definedContributionPension = {
    id: 1,
    scheme_name: 'Workplace Pension',
    provider: 'Aviva',
    current_fund_value: 50000,
  };

  const definedBenefitPension = {
    id: 2,
    scheme_name: 'Public Sector Pension',
    accrued_annual_pension: 15000,
  };

  const statePension = {
    id: 3,
    forecast_annual_amount: 11502,
  };

  const mountForm = (props = {}) => shallowMount(UnifiedPensionForm, { props });

  it('uses the defined contribution form for a new pension', () => {
    const wrapper = mountForm();

    expect(wrapper.findComponent({ name: 'DCPensionForm' }).exists()).toBe(true);
    expect(wrapper.findComponent({ name: 'DBPensionForm' }).exists()).toBe(false);
    expect(wrapper.findComponent({ name: 'StatePensionForm' }).exists()).toBe(false);
  });

  it('uses the defined contribution form when editing that pension type', () => {
    const wrapper = mountForm({
      pension: definedContributionPension,
      initialPensionType: 'dc',
    });

    const form = wrapper.findComponent({ name: 'DCPensionForm' });
    expect(form.exists()).toBe(true);
    expect(form.props('pension')).toEqual(definedContributionPension);
  });

  it('uses the defined benefit form when editing that pension type', () => {
    const wrapper = mountForm({
      pension: definedBenefitPension,
      initialPensionType: 'db',
    });

    const form = wrapper.findComponent({ name: 'DBPensionForm' });
    expect(form.exists()).toBe(true);
    expect(form.props('pension')).toEqual(definedBenefitPension);
    expect(wrapper.findComponent({ name: 'DCPensionForm' }).exists()).toBe(false);
  });

  it('uses the state pension form when editing state pension data', () => {
    const wrapper = mountForm({
      statePension,
      initialPensionType: 'state',
    });

    const form = wrapper.findComponent({ name: 'StatePensionForm' });
    expect(form.exists()).toBe(true);
    expect(form.props('statePension')).toEqual(statePension);
  });

  it('forwards saves from the defined contribution form unchanged', async () => {
    const wrapper = mountForm({
      pension: definedContributionPension,
      initialPensionType: 'dc',
    });
    const payload = { scheme_name: 'Updated Workplace Pension', current_fund_value: 55000 };

    wrapper.findComponent({ name: 'DCPensionForm' }).vm.$emit('save', payload);
    await wrapper.vm.$nextTick();

    expect(wrapper.emitted('save')).toEqual([[payload]]);
  });

  it('tags a defined benefit save for the parent dispatch boundary', async () => {
    const wrapper = mountForm({
      pension: definedBenefitPension,
      initialPensionType: 'db',
    });
    const payload = { scheme_name: 'Updated Public Sector Pension', accrued_annual_pension: 16000 };

    wrapper.findComponent({ name: 'DBPensionForm' }).vm.$emit('save', payload);
    await wrapper.vm.$nextTick();

    expect(wrapper.emitted('save')).toEqual([[
      { ...payload, _pensionType: 'db' },
    ]]);
  });

  it('tags a state pension save for the parent dispatch boundary', async () => {
    const wrapper = mountForm({ statePension, initialPensionType: 'state' });
    const payload = { forecast_annual_amount: 12000 };

    wrapper.findComponent({ name: 'StatePensionForm' }).vm.$emit('save', payload);
    await wrapper.vm.$nextTick();

    expect(wrapper.emitted('save')).toEqual([[
      { ...payload, _pensionType: 'state' },
    ]]);
  });

  it.each([
    ['dc', 'DCPensionForm'],
    ['db', 'DBPensionForm'],
    ['state', 'StatePensionForm'],
  ])('forwards close from the %s edit flow', async (initialPensionType, componentName) => {
    const wrapper = mountForm({
      pension: definedBenefitPension,
      statePension,
      initialPensionType,
    });

    wrapper.findComponent({ name: componentName }).vm.$emit('close');
    await wrapper.vm.$nextTick();

    expect(wrapper.emitted('close')).toEqual([[]]);
  });

  it('falls back to the unified add form for an unknown initial type', () => {
    const wrapper = mountForm({ initialPensionType: 'occupational' });

    expect(wrapper.findComponent({ name: 'DCPensionForm' }).exists()).toBe(true);
  });
});
