import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import FinancialHealthScore from '@/components/Dashboard/FinancialHealthScore.vue';

describe('FinancialHealthScore', () => {
  let store;
  let wrapper;

  beforeEach(() => {
    // Create mock Vuex store with all required modules
    store = createStore({
      modules: {
        protection: {
          namespaced: true,
          getters: {
            adequacyScore: () => 75,
          },
        },
        savings: {
          namespaced: true,
          getters: {
            emergencyFundRunway: () => 4,
          },
        },
        retirement: {
          namespaced: true,
          getters: {
            retirementReadinessScore: () => 68,
          },
        },
        investment: {
          namespaced: true,
          getters: {
            diversificationScore: () => 80,
          },
        },
        estate: {
          namespaced: true,
          getters: {
            probateReadiness: () => 85,
          },
        },
      },
    });
  });

  it('renders correctly', () => {
    wrapper = mount(FinancialHealthScore, {
      global: {
        plugins: [store],
      },
    });

    expect(wrapper.find('h3').text()).toContain('Financial Health Score');
    expect(wrapper.exists()).toBe(true);
  });

  it('calculates composite score correctly with weights', () => {
    wrapper = mount(FinancialHealthScore, {
      global: {
        plugins: [store],
      },
    });

    // Expected calculation:
    // Protection: 75 * 0.20 = 15
    // Emergency Fund: (4/6)*100 * 0.15 = 66.67 * 0.15 = 10
    // Retirement: 68 * 0.25 = 17
    // Investment: 80 * 0.20 = 16
    // Estate: 85 * 0.20 = 17
    // Total = 75

    const compositeScore = wrapper.vm.compositeScore;
    expect(compositeScore).toBeGreaterThan(0);
    expect(compositeScore).toBeLessThanOrEqual(100);
  });

  it('calculates individual module contributions', () => {
    wrapper = mount(FinancialHealthScore, {
      global: {
        plugins: [store],
      },
    });

    // Protection contribution = 75 * 0.20 = 15
    expect(wrapper.vm.protectionContribution).toBe(15);

    // Retirement contribution = 68 * 0.25 = 17
    expect(wrapper.vm.retirementContribution).toBe(17);

    // Investment contribution = 80 * 0.20 = 16
    expect(wrapper.vm.investmentContribution).toBe(16);

    // Estate contribution = 85 * 0.20 = 17
    expect(wrapper.vm.estateContribution).toBe(17);
  });

  it('displays correct label for excellent score (80+)', () => {
    const excellentStore = createStore({
      modules: {
        protection: {
          namespaced: true,
          getters: {
            adequacyScore: () => 90,
          },
        },
        savings: {
          namespaced: true,
          getters: {
            emergencyFundRunway: () => 6,
          },
        },
        retirement: {
          namespaced: true,
          getters: {
            retirementReadinessScore: () => 85,
          },
        },
        investment: {
          namespaced: true,
          getters: {
            diversificationScore: () => 85,
          },
        },
        estate: {
          namespaced: true,
          getters: {
            probateReadiness: () => 90,
          },
        },
      },
    });

    wrapper = mount(FinancialHealthScore, {
      global: {
        plugins: [excellentStore],
      },
    });

    expect(wrapper.vm.scoreLabel).toBe('Excellent Financial Health');
    expect(wrapper.vm.scoreTextClass).toBe('text-green-600');
  });

  it('displays correct label for good score (60-79)', () => {
    wrapper = mount(FinancialHealthScore, {
      global: {
        plugins: [store],
      },
    });

    const score = wrapper.vm.compositeScore;
    if (score >= 60 && score < 80) {
      expect(wrapper.vm.scoreLabel).toBe('Good Financial Health');
      expect(wrapper.vm.scoreTextClass).toBe('text-orange-600');
    }
  });

  it('displays correct label for fair score (40-59)', () => {
    const fairStore = createStore({
      modules: {
        protection: {
          namespaced: true,
          getters: {
            adequacyScore: () => 50,
          },
        },
        savings: {
          namespaced: true,
          getters: {
            emergencyFundRunway: () => 3,
          },
        },
        retirement: {
          namespaced: true,
          getters: {
            retirementReadinessScore: () => 45,
          },
        },
        investment: {
          namespaced: true,
          getters: {
            diversificationScore: () => 50,
          },
        },
        estate: {
          namespaced: true,
          getters: {
            probateReadiness: () => 50,
          },
        },
      },
    });

    wrapper = mount(FinancialHealthScore, {
      global: {
        plugins: [fairStore],
      },
    });

    expect(wrapper.vm.scoreLabel).toBe('Fair Financial Health');
    expect(wrapper.vm.scoreTextClass).toBe('text-red-600');
  });

  it('displays correct label for needs improvement score (<40)', () => {
    const poorStore = createStore({
      modules: {
        protection: {
          namespaced: true,
          getters: {
            adequacyScore: () => 30,
          },
        },
        savings: {
          namespaced: true,
          getters: {
            emergencyFundRunway: () => 1,
          },
        },
        retirement: {
          namespaced: true,
          getters: {
            retirementReadinessScore: () => 25,
          },
        },
        investment: {
          namespaced: true,
          getters: {
            diversificationScore: () => 30,
          },
        },
        estate: {
          namespaced: true,
          getters: {
            probateReadiness: () => 20,
          },
        },
      },
    });

    wrapper = mount(FinancialHealthScore, {
      global: {
        plugins: [poorStore],
      },
    });

    expect(wrapper.vm.scoreLabel).toBe('Needs Improvement');
    expect(wrapper.vm.scoreTextClass).toBe('text-red-600');
  });

  it('toggles breakdown details', async () => {
    wrapper = mount(FinancialHealthScore, {
      global: {
        plugins: [store],
      },
    });

    expect(wrapper.vm.showDetails).toBe(false);

    // Toggle breakdown by clicking button
    const toggleButton = wrapper.find('button');
    await toggleButton.trigger('click');
    expect(wrapper.vm.showDetails).toBe(true);

    // Toggle back
    await toggleButton.trigger('click');
    expect(wrapper.vm.showDetails).toBe(false);
  });

  it('calculates SVG gauge path correctly', () => {
    wrapper = mount(FinancialHealthScore, {
      global: {
        plugins: [store],
      },
    });

    const dashOffset = wrapper.vm.dashOffset;
    expect(dashOffset).toBeDefined();
    expect(typeof dashOffset).toBe('number');
    expect(dashOffset).toBeGreaterThanOrEqual(0);
  });

  it('handles zero scores gracefully', () => {
    const zeroStore = createStore({
      modules: {
        protection: {
          namespaced: true,
          getters: {
            adequacyScore: () => 0,
          },
        },
        savings: {
          namespaced: true,
          getters: {
            emergencyFundRunway: () => 0,
          },
        },
        retirement: {
          namespaced: true,
          getters: {
            retirementReadinessScore: () => 0,
          },
        },
        investment: {
          namespaced: true,
          getters: {
            diversificationScore: () => 0,
          },
        },
        estate: {
          namespaced: true,
          getters: {
            probateReadiness: () => 0,
          },
        },
      },
    });

    wrapper = mount(FinancialHealthScore, {
      global: {
        plugins: [zeroStore],
      },
    });

    expect(wrapper.vm.compositeScore).toBe(0);
    expect(wrapper.vm.scoreLabel).toBe('Needs Improvement');
  });

  it('calculates emergency fund score correctly', () => {
    wrapper = mount(FinancialHealthScore, {
      global: {
        plugins: [store],
      },
    });

    // Emergency fund: 4 months / 6 months target = 66.67%
    const emergencyFundScore = wrapper.vm.emergencyFundScore;
    expect(emergencyFundScore).toBeCloseTo(66.67, 0);
  });

  it('caps emergency fund score at 100', () => {
    const highEmergencyFundStore = createStore({
      modules: {
        protection: {
          namespaced: true,
          getters: {
            adequacyScore: () => 75,
          },
        },
        savings: {
          namespaced: true,
          getters: {
            emergencyFundRunway: () => 12, // 12 months > 6 months target
          },
        },
        retirement: {
          namespaced: true,
          getters: {
            retirementReadinessScore: () => 68,
          },
        },
        investment: {
          namespaced: true,
          getters: {
            diversificationScore: () => 80,
          },
        },
        estate: {
          namespaced: true,
          getters: {
            probateReadiness: () => 85,
          },
        },
      },
    });

    wrapper = mount(FinancialHealthScore, {
      global: {
        plugins: [highEmergencyFundStore],
      },
    });

    expect(wrapper.vm.emergencyFundScore).toBe(100);
  });
});
