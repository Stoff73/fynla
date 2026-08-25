import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import PolicyCard from '@/components/Protection/PolicyCard.vue';

describe('PolicyCard', () => {
  const lifePolicy = {
    id: 1,
    policy_type: 'life',
    policy_subtype: 'level_term',
    provider: 'Test Insurance Company',
    sum_assured: 500000,
    premium_amount: 50,
    premium_frequency: 'monthly',
    policy_start_date: '2020-01-01',
    policy_term_years: 30,
  };

  const mountCard = (policy = lifePolicy, push = vi.fn()) => ({
    push,
    wrapper: mount(PolicyCard, {
      props: { policy },
      global: { mocks: { $router: { push } } },
    }),
  });

  it('renders the current policy summary', () => {
    const { wrapper } = mountCard();

    expect(wrapper.text()).toContain('Life Insurance');
    expect(wrapper.text()).toContain('Test Insurance Company');
    expect(wrapper.text()).toContain('£500,000');
    expect(wrapper.text()).toContain('£50/monthly');
  });

  it('shows the full life-policy subtype', () => {
    const { wrapper } = mountCard();

    expect(wrapper.text()).toContain('Level Term');
    expect(wrapper.vm.lifePolicyTypeLabel).toBe('Level Term');
  });

  it('uses Sum Assured for lump-sum policy types', () => {
    const { wrapper } = mountCard({
      ...lifePolicy,
      policy_type: 'criticalIllness',
    });

    expect(wrapper.text()).toContain('Critical Illness');
    expect(wrapper.text()).toContain('Sum Assured');
  });

  it('uses Benefit Amount for income protection', () => {
    const { wrapper } = mountCard({
      id: 2,
      policy_type: 'incomeProtection',
      provider: 'Income Protection Provider',
      benefit_amount: 2500,
      premium_amount: 35,
      premium_frequency: 'monthly',
    });

    expect(wrapper.text()).toContain('Income Protection');
    expect(wrapper.text()).toContain('Benefit Amount');
    expect(wrapper.text()).toContain('£2,500');
  });

  it('falls back safely when provider and cover are missing', () => {
    const { wrapper } = mountCard({ id: 3, policy_type: 'disability' });

    expect(wrapper.text()).toContain('Unknown Provider');
    expect(wrapper.text()).toContain('£0');
  });

  it('navigates to the type-specific policy detail route', async () => {
    const push = vi.fn();
    const { wrapper } = mountCard(lifePolicy, push);

    await wrapper.trigger('click');

    expect(push).toHaveBeenCalledWith('/protection/policy/life/1');
  });

  it('identifies an in-force policy from its start date and term', () => {
    const { wrapper } = mountCard();

    expect(wrapper.vm.isActive).toBe(true);
  });

  it('does not mark a future policy as active', () => {
    const { wrapper } = mountCard({
      ...lifePolicy,
      policy_start_date: '2099-01-01',
    });

    expect(wrapper.vm.isActive).toBe(false);
  });

  it('parses policy conditions from arrays and stored JSON', () => {
    const { wrapper } = mountCard();

    expect(wrapper.vm.parseConditions(['Condition one'])).toEqual(['Condition one']);
    expect(wrapper.vm.parseConditions('["Condition one","Condition two"]')).toEqual([
      'Condition one',
      'Condition two',
    ]);
    expect(wrapper.vm.parseConditions('invalid')).toEqual([]);
  });

  // W-0186 — a joint-life policy covers both spouses and is recorded once, on the
  // account that entered it. It reaches the other life assured read-only.
  it('names the other life assured on a joint-life policy', () => {
    const { wrapper } = mountCard({
      ...lifePolicy,
      joint_life: true,
      joint_life_with: 'Sarah Jones',
      is_own_policy: true,
    });

    expect(wrapper.text()).toContain('Joint life with Sarah Jones');
    expect(wrapper.text()).not.toContain("Recorded on");
  });

  it('says whose record it is when the policy is not this account\'s', () => {
    const { wrapper } = mountCard({
      ...lifePolicy,
      joint_life: true,
      joint_life_with: 'David Jones',
      is_own_policy: false,
    });

    expect(wrapper.text()).toContain('Joint life with David Jones');
    expect(wrapper.text()).toContain("Recorded on David Jones's account");
  });

  it('says nothing about sharing on a single-life policy', () => {
    const { wrapper } = mountCard({ ...lifePolicy, joint_life: false, is_own_policy: true });

    expect(wrapper.text()).not.toContain('Joint life');
    expect(wrapper.text()).not.toContain('Recorded on');
  });
});
