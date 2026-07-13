import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import GoalCard from '@/components/Goals/GoalCard.vue';

describe('GoalCard', () => {
  const goal = {
    id: 1,
    goal_name: 'Retirement Fund',
    target_amount: 1000000,
    current_amount: 250000,
    monthly_contribution: 2000,
    days_remaining: 760,
    status: 'active',
    priority: 'high',
    assigned_module: 'investment',
    goal_type: 'retirement',
    is_on_track: true,
    contribution_streak: 0,
  };

  const mountCard = (props = {}) => mount(GoalCard, {
    props: { goal, ...props },
  });

  it('renders current goal data', () => {
    const wrapper = mountCard();

    expect(wrapper.text()).toContain('Retirement Fund');
    expect(wrapper.text()).toContain('Investment');
    expect(wrapper.text()).toContain('high');
  });

  it('displays current and target amounts as currency', () => {
    const wrapper = mountCard();

    expect(wrapper.text()).toContain('£250,000');
    expect(wrapper.text()).toContain('£1,000,000');
  });

  it('calculates progress from the goal amounts', () => {
    const wrapper = mountCard();

    expect(wrapper.vm.progressPercent).toBe(25);
    expect(wrapper.text()).toContain('25%');
  });

  it('caps the rendered progress-bar width at one hundred percent', () => {
    const wrapper = mountCard({ goal: { ...goal, current_amount: 1250000 } });
    const bar = wrapper.findAll('div').find(item => (
      item.classes().includes('h-2.5') && item.classes().includes('transition-all')
    ));

    expect(wrapper.vm.progressPercent).toBe(125);
    expect(bar.attributes('style')).toContain('width: 100%');
  });

  it('uses descriptive on-track status rather than a financial score', () => {
    const wrapper = mountCard();

    expect(wrapper.vm.statusText).toBe('On Track');
    expect(wrapper.text()).toContain('On Track');
    expect(wrapper.text()).not.toMatch(/\/100/);
  });

  it('shows behind-schedule status when the goal is not on track', () => {
    const wrapper = mountCard({ goal: { ...goal, is_on_track: false } });

    expect(wrapper.vm.statusText).toBe('Behind Schedule');
  });

  it('shows not-started status when no amount has been saved', () => {
    const wrapper = mountCard({ goal: { ...goal, current_amount: 0, is_on_track: false } });

    expect(wrapper.vm.statusText).toBe('Not Started');
    expect(wrapper.vm.progressBarClass).toBe('bg-horizon-300');
  });

  it('shows completion using the spring success palette', () => {
    const wrapper = mountCard({
      goal: { ...goal, current_amount: 1000000, status: 'completed' },
    });

    expect(wrapper.vm.statusText).toBe('Completed');
    expect(wrapper.vm.progressBarClass).toBe('bg-spring-500');
    expect(wrapper.vm.statusBadgeClass).toContain('bg-spring-100');
  });

  it.each([
    [-1, 'Overdue'],
    [0, 'Today'],
    [1, '1 day'],
    [20, '20 days'],
    [60, '2 months'],
    [760, '2y 1m'],
  ])('formats %s days remaining as %s', (days, expected) => {
    const wrapper = mountCard({ goal: { ...goal, days_remaining: days } });

    expect(wrapper.vm.timeRemaining).toBe(expected);
  });

  it('displays the monthly contribution', () => {
    const wrapper = mountCard();

    expect(wrapper.text()).toContain('Monthly contribution');
    expect(wrapper.text()).toContain('£2,000');
  });

  it('emits view when the card is clicked', async () => {
    const wrapper = mountCard();

    await wrapper.trigger('click');

    expect(wrapper.emitted('view')).toEqual([[goal]]);
  });

  it('emits edit without also emitting view', async () => {
    const wrapper = mountCard();

    await wrapper.find('button[title="Edit goal"]').trigger('click');

    expect(wrapper.emitted('edit')).toEqual([[goal]]);
    expect(wrapper.emitted('view')).toBeUndefined();
  });

  it('emits delete without also emitting view', async () => {
    const wrapper = mountCard();

    await wrapper.find('button[title="Delete goal"]').trigger('click');

    expect(wrapper.emitted('delete')).toEqual([[goal]]);
    expect(wrapper.emitted('view')).toBeUndefined();
  });

  it('emits add-contribution for active goals', async () => {
    const wrapper = mountCard();
    const button = wrapper.findAll('button').find(item => item.text().includes('Add Contribution'));

    await button.trigger('click');

    expect(wrapper.emitted('add-contribution')).toEqual([[goal]]);
  });

  it('updates a monthly contribution with the current goal identifier', async () => {
    const wrapper = mountCard();
    const editButton = wrapper.findAll('button').find(item => item.text() === 'Edit');
    await editButton.trigger('click');
    await wrapper.find('input[type="number"]').setValue(2500);
    const saveButton = wrapper.findAll('button').find(item => item.text() === 'Save');

    await saveButton.trigger('click');

    expect(wrapper.emitted('update-contribution')).toEqual([[
      { goalId: 1, monthly_contribution: 2500 },
    ]]);
  });

  it('cancels a contribution edit and restores the saved value', async () => {
    const wrapper = mountCard();
    await wrapper.findAll('button').find(item => item.text() === 'Edit').trigger('click');
    await wrapper.find('input[type="number"]').setValue(2500);

    await wrapper.findAll('button').find(item => item.text() === 'Cancel').trigger('click');

    expect(wrapper.vm.editingContribution).toBe(false);
    expect(wrapper.vm.contributionAmount).toBe(2000);
  });

  it('hides actions while retaining the contribution metric in read-only mode', () => {
    const wrapper = mountCard({ showActions: false });

    expect(wrapper.find('button[title="Edit goal"]').exists()).toBe(false);
    expect(wrapper.text()).toContain('Monthly Contribution');
    expect(wrapper.text()).toContain('£2,000');
  });

  it('shows dependency state supplied by the parent', () => {
    const blocked = mountCard({ isBlocked: true, dependencyCount: 2 });
    const dependent = mountCard({ dependencyCount: 2 });

    expect(blocked.text()).toContain('Blocked');
    expect(dependent.text()).toContain('2');
  });
});
