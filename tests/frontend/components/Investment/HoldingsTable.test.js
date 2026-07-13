import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import HoldingsTable from '@/components/Investment/HoldingsTable.vue';
import { CHART_COLORS } from '@/constants/designSystem';

describe('HoldingsTable', () => {
  const holdings = [
    {
      id: 1,
      investment_account_id: 1,
      security_name: 'Vanguard FTSE All-World',
      ticker: 'VWRL',
      asset_type: 'international_equity',
      quantity: 100,
      purchase_price: 80.5,
      current_price: 95.25,
      current_value: 9525,
      return_percent: 18.32,
      ocf_percent: 0.22,
    },
    {
      id: 2,
      investment_account_id: 1,
      security_name: 'Vanguard UK Gilt',
      ticker: 'VGOV',
      asset_type: 'bond',
      quantity: 200,
      purchase_price: 50,
      current_price: 48.75,
      current_value: 9750,
      return_percent: -2.5,
      ocf_percent: 0.15,
    },
    {
      id: 3,
      investment_account_id: 2,
      security_name: 'Vanguard FTSE 100',
      ticker: 'VUKE',
      asset_type: 'uk_equity',
      quantity: 150,
      purchase_price: 60,
      current_price: 72,
      current_value: 10800,
      return_percent: 20,
      ocf_percent: 0.09,
    },
  ];

  const accounts = [
    { id: 1, provider: 'Provider One' },
    { id: 2, provider: 'Provider Two' },
  ];

  const mountTable = (props = {}) => mount(HoldingsTable, {
    props: { holdings, loading: false, accounts, ...props },
  });

  it('renders the allocation and breakdown views for current holdings', () => {
    const wrapper = mountTable();

    expect(wrapper.text()).toContain('Holdings Allocation');
    expect(wrapper.text()).toContain('Holdings Breakdown');
    expect(wrapper.text()).toContain('Vanguard FTSE All-World');
    expect(wrapper.text()).toContain('Vanguard UK Gilt');
    expect(wrapper.text()).toContain('Vanguard FTSE 100');
  });

  it('calculates the total portfolio value', () => {
    const wrapper = mountTable();

    expect(wrapper.vm.totalValue).toBe(30075);
    expect(wrapper.text()).toContain('£30,075');
  });

  it('sorts the visual breakdown from highest to lowest value', () => {
    const wrapper = mountTable();

    expect(wrapper.vm.sortedByValue.map(holding => holding.id)).toEqual([3, 2, 1]);
  });

  it('calculates each holding share from specific monetary values', () => {
    const wrapper = mountTable();

    expect(wrapper.vm.getHoldingPercentage(holdings[2])).toBe('35.9');
    expect(wrapper.vm.getHoldingPercentage(holdings[0])).toBe('31.7');
  });

  it('uses the canonical chart palette for allocation segments', () => {
    const wrapper = mountTable();

    expect(wrapper.vm.chartColours).toEqual(CHART_COLORS);
    expect(wrapper.vm.holdingsDonutSegments.map(segment => segment.color)).toEqual(
      CHART_COLORS.slice(0, 3),
    );
  });

  it('creates a donut segment for every holding', () => {
    const wrapper = mountTable();

    expect(wrapper.vm.holdingsDonutSegments).toHaveLength(3);
    expect(wrapper.vm.holdingsDonutSegments.every(segment => segment.arcLength > 0)).toBe(true);
  });

  it('returns no donut segments when every holding has zero value', () => {
    const wrapper = mountTable({
      holdings: holdings.map(holding => ({ ...holding, current_value: 0 })),
    });

    expect(wrapper.vm.holdingsDonutSegments).toEqual([]);
    expect(wrapper.vm.totalFilteredValue).toBe(0);
  });

  it('filters holdings by asset type', async () => {
    const wrapper = mountTable();

    await wrapper.setData({ selectedAssetType: 'uk_equity' });

    expect(wrapper.vm.filteredHoldings).toEqual([holdings[2]]);
    expect(wrapper.text()).toContain('Vanguard FTSE 100');
    expect(wrapper.text()).not.toContain('Vanguard UK Gilt');
    expect(wrapper.text()).toContain('Filtered View');
  });

  it('filters holdings by account identifier', async () => {
    const wrapper = mountTable();

    await wrapper.setData({ selectedAccountId: '1' });

    expect(wrapper.vm.filteredHoldings.map(holding => holding.id)).toEqual([1, 2]);
    expect(wrapper.text()).not.toContain('Vanguard FTSE 100');
  });

  it('combines account and asset-type filters', async () => {
    const wrapper = mountTable();

    await wrapper.setData({ selectedAccountId: '1', selectedAssetType: 'bond' });

    expect(wrapper.vm.filteredHoldings).toEqual([holdings[1]]);
  });

  it('renders account filter choices supplied by the parent', () => {
    const wrapper = mountTable();

    expect(wrapper.find('#account-filter').text()).toContain('Provider One');
    expect(wrapper.find('#account-filter').text()).toContain('Provider Two');
  });

  it('formats asset types as readable labels', () => {
    const wrapper = mountTable();

    expect(wrapper.vm.formatAssetType('international_equity')).toBe('International Equity');
    expect(wrapper.vm.formatAssetType(null)).toBe('N/A');
  });

  it('uses semantic palette classes for positive, negative, and neutral returns', () => {
    const wrapper = mountTable();

    expect(wrapper.vm.getReturnClass(18.32)).toBe('text-spring-600');
    expect(wrapper.vm.getReturnClass(-2.5)).toBe('text-raspberry-600');
    expect(wrapper.vm.getReturnClass(0)).toBe('text-neutral-500');
  });

  it('formats return percentages consistently', () => {
    const wrapper = mountTable();

    expect(wrapper.vm.formatReturn(18.32)).toBe('+18.32%');
    expect(wrapper.vm.formatReturn(-2.5)).toBe('-2.50%');
    expect(wrapper.vm.formatReturn(0)).toBe('+0.00%');
  });

  it('calculates the simple average return of filtered holdings', async () => {
    const wrapper = mountTable();
    expect(wrapper.vm.averageReturn).toBeCloseTo(11.94, 2);

    await wrapper.setData({ selectedAccountId: '1' });

    expect(wrapper.vm.averageReturn).toBeCloseTo(7.91, 2);
  });

  it('calculates unrealised gain or loss from quantity and purchase price', () => {
    const wrapper = mountTable();

    expect(wrapper.vm.getUnrealisedGainLoss(holdings[0])).toBe(1475);
    expect(wrapper.vm.getUnrealisedGainLoss(holdings[1])).toBe(-250);
  });

  it('provides a stable chart key based on the current values', async () => {
    const wrapper = mountTable();
    expect(wrapper.vm.chartKey).toBe('holdings-donut-3-30075');

    await wrapper.setData({ selectedAssetType: 'bond' });

    expect(wrapper.vm.chartKey).toBe('holdings-donut-1-9750');
  });

  it('emits add-holding from the primary action', async () => {
    const wrapper = mountTable();
    const addButton = wrapper.findAll('button').find(button => button.text().includes('Add Holding'));

    await addButton.trigger('click');

    expect(wrapper.emitted('add-holding')).toEqual([[]]);
  });

  it('displays the loading state without showing portfolio data', () => {
    const wrapper = mountTable({ holdings: [], loading: true });

    expect(wrapper.find('.animate-spin').exists()).toBe(true);
    expect(wrapper.text()).not.toContain('Holdings Allocation');
  });

  it('displays the empty state and provides an add action', async () => {
    const wrapper = mountTable({ holdings: [] });

    expect(wrapper.text()).toContain('No holdings found');
    expect(wrapper.text()).toContain('Get started by adding your first holding.');

    await wrapper.findAll('button').find(button => button.text().includes('Add Holding')).trigger('click');
    expect(wrapper.emitted('add-holding')).toEqual([[]]);
  });

  it('uses a filter-specific empty message when no holdings match', async () => {
    const wrapper = mountTable();

    await wrapper.setData({ selectedAssetType: 'cash' });

    expect(wrapper.text()).toContain('No holdings match the selected filters.');
  });
});
