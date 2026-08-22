import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import RetirementTargetCard from '../../Retirement/RetirementTargetCard.vue';

/**
 * W-0035. `retirement_profiles.target_retirement_income` is the figure every
 * retirement projection is built on — required capital, the income projection,
 * decumulation, capital adequacy, the income gap and Monte Carlo. No form on any
 * surface could write it; only Fyn's `capture_retirement_goals` tool could.
 *
 * So every user who had not chatted to Fyn had the lot built on
 * RequiredCapitalCalculator's fallback of (gross income − pension contributions)
 * × 75%, presented as their own target. For the persona household that meant Sarah
 * being told she needed £116,250 a year when she had said £55,000 — 111% too high,
 * with nothing on screen saying the figure was not hers.
 *
 * The card therefore does two jobs, and the second matters as much as the first.
 */
const previewDisabled = { mounted() {}, updated() {} };

function mountCard(props = {}) {
  return mount(RetirementTargetCard, {
    props: { profile: null, requiredCapital: null, ...props },
    global: { directives: { 'preview-disabled': previewDisabled } },
  });
}

describe('RetirementTargetCard', () => {
  it('shows a stated target as the user’s own figure', () => {
    const wrapper = mountCard({
      profile: { target_retirement_income: '55000.00', target_retirement_age: 60 },
      requiredCapital: { required_income: 55000, income_source: 'profile' },
    });

    expect(wrapper.get('[data-testid="retirement-target-income"]').text()).toContain('55,000');
    expect(wrapper.get('[data-testid="retirement-target-age"]').text()).toBe('60');
    expect(wrapper.text()).toContain('The figure you told us you want.');
    expect(wrapper.get('[data-testid="retirement-target-edit"]').text()).toBe('Change');
  });

  it('says the figure was worked out when the user has not stated one', () => {
    const wrapper = mountCard({
      requiredCapital: { required_income: 116250, income_source: 'calculated' },
    });

    expect(wrapper.get('[data-testid="retirement-target-income"]').text()).toContain('116,250');
    expect(wrapper.text()).toContain('because you have not set a target yet');
    expect(wrapper.get('[data-testid="retirement-target-edit"]').text()).toBe('Set your target');
  });

  it('reads as unset when there is neither a stated nor a derived figure', () => {
    const wrapper = mountCard();

    expect(wrapper.get('[data-testid="retirement-target-income"]').text()).toBe('Not set');
    expect(wrapper.get('[data-testid="retirement-target-age"]').text()).toBe('Not set');
  });

  it('emits only the values the user answered', async () => {
    const wrapper = mountCard({ requiredCapital: { required_income: 90000, income_source: 'calculated' } });

    await wrapper.get('[data-testid="retirement-target-edit"]').trigger('click');
    await wrapper.get('[data-testid="retirement-target-income-input"]').setValue('55000');
    await wrapper.get('form').trigger('submit');

    expect(wrapper.emitted('save')[0][0]).toEqual({ target_retirement_income: 55000 });
  });

  it('does not pre-fill the box with the derived figure', async () => {
    // Pre-filling would turn "we worked this out" into "you chose this" the moment
    // the user saved without touching it — the exact confusion this card removes.
    const wrapper = mountCard({ requiredCapital: { required_income: 116250, income_source: 'calculated' } });

    await wrapper.get('[data-testid="retirement-target-edit"]').trigger('click');

    expect(wrapper.get('[data-testid="retirement-target-income-input"]').element.value).toBe('');
  });

  it('pre-fills a target the user did state', async () => {
    const wrapper = mountCard({
      profile: { target_retirement_income: '55000.00', target_retirement_age: 60 },
      requiredCapital: { required_income: 55000, income_source: 'profile' },
    });

    await wrapper.get('[data-testid="retirement-target-edit"]').trigger('click');

    expect(wrapper.get('[data-testid="retirement-target-income-input"]').element.value).toBe('55000');
    expect(wrapper.get('[data-testid="retirement-target-age-input"]').element.value).toBe('60');
  });

  it('refuses an empty submission rather than emitting one', async () => {
    const wrapper = mountCard();

    await wrapper.get('[data-testid="retirement-target-edit"]').trigger('click');
    await wrapper.get('form').trigger('submit');

    expect(wrapper.emitted('save')).toBeUndefined();
    expect(wrapper.get('[data-testid="retirement-target-error"]').text())
      .toBe('Enter a target income, a target retirement age, or both.');
  });

  it('bounds the retirement age the way the endpoint does', async () => {
    const wrapper = mountCard();

    await wrapper.get('[data-testid="retirement-target-edit"]').trigger('click');
    await wrapper.get('[data-testid="retirement-target-age-input"]').setValue('40');
    await wrapper.get('form').trigger('submit');

    expect(wrapper.emitted('save')).toBeUndefined();
    expect(wrapper.get('[data-testid="retirement-target-error"]').text())
      .toContain('between 50 and 100');
  });

  it('keeps the form open when the parent reports a failure (Rule 3)', async () => {
    const wrapper = mountCard();

    await wrapper.get('[data-testid="retirement-target-edit"]').trigger('click');
    await wrapper.get('[data-testid="retirement-target-income-input"]').setValue('55000');
    await wrapper.get('form').trigger('submit');

    wrapper.vm.saveFailed('Set your target retirement age first.');
    await wrapper.vm.$nextTick();

    expect(wrapper.get('[data-testid="retirement-target-error"]').text())
      .toBe('Set your target retirement age first.');
    expect(wrapper.find('[data-testid="retirement-target-save"]').exists()).toBe(true);
  });

  it('closes the form when the parent reports success', async () => {
    const wrapper = mountCard();

    await wrapper.get('[data-testid="retirement-target-edit"]').trigger('click');
    await wrapper.get('[data-testid="retirement-target-income-input"]').setValue('55000');
    await wrapper.get('form').trigger('submit');

    wrapper.vm.saveSucceeded();
    await wrapper.vm.$nextTick();

    expect(wrapper.find('[data-testid="retirement-target-save"]').exists()).toBe(false);
    expect(wrapper.find('[data-testid="retirement-target-income"]').exists()).toBe(true);
  });
});
