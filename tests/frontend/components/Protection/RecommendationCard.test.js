import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import RecommendationCard from '@/components/Protection/RecommendationCard.vue';

describe('RecommendationCard', () => {
  const recommendation = {
    priority: 'high',
    category: 'Life Insurance',
    action: 'Increase Life Insurance Cover',
    rationale: 'Your current cover leaves a financial shortfall for your family.',
    impact: 'Reduces the identified family protection shortfall.',
    estimated_cost: 50,
    personalised_context: ['Your outstanding mortgage is not fully covered.'],
    details: 'Review the amount and term with a regulated adviser.',
  };

  const mountCard = (value = recommendation) => mount(RecommendationCard, {
    props: { recommendation: value },
  });

  it('renders the actionable summary while collapsed', () => {
    const wrapper = mountCard();

    expect(wrapper.text()).toContain('HIGH');
    expect(wrapper.text()).toContain('Life Insurance');
    expect(wrapper.text()).toContain('Increase Life Insurance Cover');
    expect(wrapper.text()).toContain(recommendation.rationale);
    expect(wrapper.vm.isExpanded).toBe(false);
  });

  it.each([
    ['high', 'bg-raspberry-500', 'border-raspberry-300'],
    ['medium', 'bg-violet-500', 'border-violet-300'],
    ['low', 'bg-spring-500', 'border-spring-300'],
  ])('uses the canonical palette for %s priority', (priority, badge, border) => {
    const wrapper = mountCard({ ...recommendation, priority });

    expect(wrapper.vm.priorityBadgeClass).toContain(badge);
    expect(wrapper.vm.borderColourClass).toBe(border);
  });

  it('expands from the card header to show supporting detail', async () => {
    const wrapper = mountCard();

    await wrapper.find('.p-4.cursor-pointer').trigger('click');

    expect(wrapper.vm.isExpanded).toBe(true);
    expect(wrapper.text()).toContain('Expected Impact');
    expect(wrapper.text()).toContain(recommendation.impact);
  });

  it('formats the estimated monthly cost with pence', async () => {
    const wrapper = mountCard({ ...recommendation, estimated_cost: 125.5 });

    await wrapper.find('.p-4.cursor-pointer').trigger('click');

    expect(wrapper.text()).toContain('£125.50');
    expect(wrapper.text()).toContain('per month');
  });

  it('shows personalised context only when a non-empty list is supplied', async () => {
    const wrapper = mountCard();
    const withoutContext = mountCard({ ...recommendation, personalised_context: [] });

    await wrapper.find('.p-4.cursor-pointer').trigger('click');
    await withoutContext.find('.p-4.cursor-pointer').trigger('click');

    expect(wrapper.text()).toContain('Why this matters for you');
    expect(wrapper.text()).toContain('Your outstanding mortgage is not fully covered.');
    expect(withoutContext.text()).not.toContain('Why this matters for you');
  });

  it('shows optional additional details after expansion', async () => {
    const wrapper = mountCard();

    await wrapper.find('.p-4.cursor-pointer').trigger('click');

    expect(wrapper.text()).toContain('Additional Details');
    expect(wrapper.text()).toContain(recommendation.details);
  });

  it('emits mark-done from the expanded action', async () => {
    const wrapper = mountCard();
    await wrapper.find('.p-4.cursor-pointer').trigger('click');
    const button = wrapper.findAll('button').find(item => item.text() === 'Mark as Done');

    await button.trigger('click');

    expect(wrapper.emitted('mark-done')).toEqual([[]]);
  });

  it('can collapse again using the disclosure control', async () => {
    const wrapper = mountCard();
    const disclosure = wrapper.find('.ml-4');

    await disclosure.trigger('click');
    expect(wrapper.vm.isExpanded).toBe(true);
    await disclosure.trigger('click');

    expect(wrapper.vm.isExpanded).toBe(false);
  });
});
