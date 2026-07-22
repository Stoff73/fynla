import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';

const serviceMocks = vi.hoisted(() => ({
  investment: vi.fn(),
  protection: vi.fn(),
  estate: vi.fn(),
}));

vi.mock('@/services/investmentService', () => ({
  default: { getPortfolioStrategy: serviceMocks.investment },
}));
vi.mock('@/services/protectionService', () => ({
  default: { getRecommendations: serviceMocks.protection },
}));
vi.mock('@/services/estateService', () => ({
  default: { calculateIHTPlanning: serviceMocks.estate },
}));

import ActionsOverviewCard from '@/components/Dashboard/ActionsOverviewCard.vue';

describe('ActionsOverviewCard', () => {
  let router;

  const mountCard = (props = {}) => mount(ActionsOverviewCard, {
    props,
    global: { mocks: { $router: router } },
  });

  beforeEach(() => {
    router = { push: vi.fn() };
    serviceMocks.investment.mockReset().mockResolvedValue({ recommendations: [] });
    serviceMocks.protection.mockReset().mockResolvedValue({ data: { recommendations: [] } });
    serviceMocks.estate.mockReset().mockResolvedValue({ success: true, data: {} });
  });

  it('renders the current review-actions heading', async () => {
    const wrapper = mountCard();
    await flushPromises();
    expect(wrapper.get('h3').text()).toBe('Items for Review');
  });

  it('loads investment, protection, and estate actions in parallel', async () => {
    mountCard();
    await flushPromises();
    expect(serviceMocks.investment).toHaveBeenCalledOnce();
    expect(serviceMocks.protection).toHaveBeenCalledOnce();
    expect(serviceMocks.estate).toHaveBeenCalledOnce();
  });

  it('normalises investment strategies', async () => {
    serviceMocks.investment.mockResolvedValue({ recommendations: [{ title: 'Rebalance holdings', priority: 2 }] });
    const wrapper = mountCard();
    await flushPromises();
    expect(wrapper.vm.allActions[0]).toMatchObject({ module: 'investment', title: 'Rebalance holdings', priority: 2 });
  });

  it('normalises protection recommendations and string priorities', async () => {
    serviceMocks.protection.mockResolvedValue({ data: { recommendations: [{ action: 'Review cover', priority: 'high' }] } });
    const wrapper = mountCard();
    await flushPromises();
    expect(wrapper.vm.allActions[0]).toMatchObject({ module: 'protection', title: 'Review cover', priority: 1 });
  });

  it('normalises estate gifting strategies', async () => {
    serviceMocks.estate.mockResolvedValue({ success: true, data: { gifting_strategy: { strategies: [{ strategy_name: 'Use annual exemption' }] } } });
    const wrapper = mountCard();
    await flushPromises();
    expect(wrapper.vm.allActions[0]).toMatchObject({ module: 'estate', title: 'Use annual exemption', priority: 2 });
  });

  it('adds the estate life-cover recommendation when present', async () => {
    serviceMocks.estate.mockResolvedValue({ success: true, data: { life_cover_strategy: { recommendation: 'Consider cover', cover_required: 125000 } } });
    const wrapper = mountCard();
    await flushPromises();
    expect(wrapper.vm.allActions[0]).toMatchObject({ module: 'estate', potential_saving: 125000 });
  });

  it('sorts displayed actions by ascending priority', async () => {
    const wrapper = mountCard();
    await flushPromises();
    await wrapper.setData({
      investmentStrategies: [{ title: 'Medium', priority: 3 }, { title: 'Urgent', priority: 1 }],
    });
    expect(wrapper.vm.displayedActions.map(action => action.title)).toEqual(['Urgent', 'Medium']);
  });

  it('applies the configured display limit', async () => {
    const wrapper = mountCard({ limit: 1 });
    await flushPromises();
    await wrapper.setData({ investmentStrategies: [{ title: 'One', priority: 1 }, { title: 'Two', priority: 2 }] });
    expect(wrapper.vm.displayedActions).toHaveLength(1);
  });

  it('totals actions across modules', async () => {
    const wrapper = mountCard();
    await flushPromises();
    await wrapper.setData({
      investmentStrategies: [{ title: 'One' }],
      protectionRecommendations: [{ action: 'Two' }],
      estateStrategies: [{ strategy_name: 'Three' }],
    });
    expect(wrapper.vm.totalActions).toBe(3);
  });

  it('totals available potential savings', async () => {
    const wrapper = mountCard();
    await flushPromises();
    await wrapper.setData({ investmentStrategies: [{ title: 'One', potential_saving: 1000 }], estateStrategies: [{ strategy_name: 'Two', iht_saved: 2500 }] });
    expect(wrapper.vm.totalPotentialSavings).toBe(3500);
  });

  it.each([
    ['investment', '/net-worth/investments'],
    ['protection', '/protection'],
    ['estate', '/estate'],
  ])('navigates %s actions to their module', async (module, route) => {
    const wrapper = mountCard();
    await flushPromises();
    wrapper.vm.navigateToModule(module);
    expect(router.push).toHaveBeenCalledWith(route);
  });

  it('falls back to the dashboard for an unknown module', async () => {
    const wrapper = mountCard();
    await flushPromises();
    wrapper.vm.navigateToModule('unknown');
    expect(router.push).toHaveBeenCalledWith('/dashboard');
  });

  it('uses functional priority dots and module badges', async () => {
    serviceMocks.investment.mockResolvedValue({ recommendations: [{ title: 'Review portfolio', priority: 1 }] });
    const wrapper = mountCard();
    await flushPromises();
    expect(wrapper.get('.priority-dot').classes()).toContain('dot-critical');
    expect(wrapper.get('.module-badge').classes()).toContain('module-investment');
  });

  it('shows the empty state after successful empty responses', async () => {
    const wrapper = mountCard();
    await flushPromises();
    expect(wrapper.text()).toContain('All caught up!');
    expect(wrapper.vm.loading).toBe(false);
  });
});
