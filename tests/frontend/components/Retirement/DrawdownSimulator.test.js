import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import DrawdownSimulator from '@/components/Retirement/DrawdownSimulator.vue';
import { SUCCESS_COLORS, ERROR_COLORS } from '@/constants/designSystem';

describe('DrawdownSimulator', () => {
  const mountSimulator = () => mount(DrawdownSimulator, {
    global: {
      stubs: {
        apexchart: {
          name: 'ApexChart',
          props: ['options', 'series', 'type', 'height'],
          template: '<div class="apexchart-stub" />',
        },
      },
    },
  });

  it('renders with the current default assumptions', () => {
    const wrapper = mountSimulator();

    expect(wrapper.text()).toContain('Drawdown Simulator');
    expect(wrapper.text()).toContain('Initial Pension Pot: £500,000');
    expect(wrapper.vm.simulatorData).toEqual({
      initialPot: 500000,
      withdrawalRate: 4,
      growthRate: 5,
      inflationRate: 2.5,
    });
  });

  it('provides controls for the pot, withdrawal, growth, and inflation assumptions', () => {
    const wrapper = mountSimulator();

    expect(wrapper.findAll('input[type="range"]')).toHaveLength(4);
  });

  it('updates the initial pension pot and reruns the simulation', async () => {
    const wrapper = mountSimulator();
    const initialKey = wrapper.vm.chartKey;

    await wrapper.findAll('input[type="range"]')[0].setValue(600000);

    expect(wrapper.vm.simulatorData.initialPot).toBe(600000);
    expect(wrapper.vm.simulationResults.portfolioValues[0]).toBe(600000);
    expect(wrapper.vm.chartKey).not.toBe(initialKey);
  });

  it('updates the annual withdrawal rate from its own slider', async () => {
    const wrapper = mountSimulator();

    await wrapper.findAll('input[type="range"]')[1].setValue(5);

    expect(wrapper.vm.simulatorData.withdrawalRate).toBe(5);
    expect(wrapper.vm.annualWithdrawal).toBe(25000);
  });

  it('updates the investment growth rate from its own slider', async () => {
    const wrapper = mountSimulator();

    await wrapper.findAll('input[type="range"]')[2].setValue(6);

    expect(wrapper.vm.simulatorData.growthRate).toBe(6);
  });

  it('updates the inflation rate from its own slider', async () => {
    const wrapper = mountSimulator();

    await wrapper.findAll('input[type="range"]')[3].setValue(3);

    expect(wrapper.vm.simulatorData.inflationRate).toBe(3);
    expect(wrapper.vm.simulationResults.realValueLoss).toBeGreaterThan(0);
  });

  it('runs a projection from age 67 through age 95 on mount', () => {
    const wrapper = mountSimulator();

    expect(wrapper.vm.simulationResults.ages[0]).toBe(67);
    expect(wrapper.vm.simulationResults.ages.at(-1)).toBe(95);
    expect(wrapper.vm.simulationResults.portfolioValues).toHaveLength(29);
  });

  it('calculates each year from the remaining pot using the chosen rates', () => {
    const wrapper = mountSimulator();

    expect(wrapper.vm.simulationResults.portfolioValues[1]).toBe(504000);
  });

  it('describes a surviving projection with specific remaining value', async () => {
    const wrapper = mountSimulator();
    await wrapper.vm.$nextTick();

    expect(wrapper.vm.simulationResults.depletes).toBe(false);
    expect(wrapper.text()).toContain('Sustainable Drawdown');
    expect(wrapper.text()).toContain('remaining at age 95');
  });

  it('uses the spring success colour for a surviving projection', async () => {
    const wrapper = mountSimulator();
    await wrapper.vm.$nextTick();

    expect(wrapper.vm.chartOptions.colours).toEqual([SUCCESS_COLORS[500]]);
    expect(wrapper.html()).toContain('text-spring-600');
  });

  it('records depletion when a scenario withdraws the whole remaining pot', async () => {
    const wrapper = mountSimulator();
    wrapper.vm.simulatorData.withdrawalRate = 100;
    wrapper.vm.runSimulation();
    await wrapper.vm.$nextTick();

    expect(wrapper.vm.simulationResults.depletes).toBe(true);
    expect(wrapper.vm.simulationResults.depletionAge).toBe(68);
    expect(wrapper.text()).toContain('Portfolio runs out');
    expect(wrapper.text()).toContain('Age 68');
  });

  it('uses the raspberry danger colour for a depleting projection', () => {
    const wrapper = mountSimulator();
    wrapper.vm.simulatorData.withdrawalRate = 100;
    wrapper.vm.runSimulation();

    expect(wrapper.vm.chartOptions.colours).toEqual([ERROR_COLORS[500]]);
  });

  it('exposes projected portfolio values as the chart series', () => {
    const wrapper = mountSimulator();

    expect(wrapper.vm.chartSeries).toEqual([{
      name: 'Portfolio Value',
      data: wrapper.vm.simulationResults.portfolioValues,
    }]);
  });

  it('formats chart values as pounds', () => {
    const wrapper = mountSimulator();

    expect(wrapper.vm.chartOptions.yaxis.labels.formatter(100000)).toBe('£100,000');
    expect(wrapper.vm.chartOptions.tooltip.y.formatter(25000)).toBe('£25,000');
  });

  it('changes the results when the withdrawal control changes', async () => {
    const wrapper = mountSimulator();
    const initialResults = [...wrapper.vm.simulationResults.portfolioValues];

    await wrapper.findAll('input[type="range"]')[1].setValue(6);

    expect(wrapper.vm.simulationResults.portfolioValues).not.toEqual(initialResults);
  });
});
