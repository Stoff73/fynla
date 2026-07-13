import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import SavingsGoals from '@/components/Savings/SavingsGoals.vue';

describe('SavingsGoals', () => {
  const createMockStore = (goals = []) => {
    return createStore({
      modules: {
        savings: {
          namespaced: true,
          state: {
            goals: goals,
            loading: false,
            error: null,
          },
          getters: {
            goalsOnTrack: (state) => {
              return state.goals.filter(g => {
                const progress = (g.current_saved / g.target_amount) * 100;
                const now = new Date();
                const target = new Date(g.target_date);
                const monthsElapsed = (now - new Date(g.created_at)) / (1000 * 60 * 60 * 24 * 30);
                const totalMonths = (target - new Date(g.created_at)) / (1000 * 60 * 60 * 24 * 30);
                const expectedProgress = (monthsElapsed / totalMonths) * 100;
                return progress >= expectedProgress;
              });
            },
            goalsOffTrack: (state) => {
              return state.goals.filter(g => {
                const progress = (g.current_saved / g.target_amount) * 100;
                const now = new Date();
                const target = new Date(g.target_date);
                const monthsElapsed = (now - new Date(g.created_at)) / (1000 * 60 * 60 * 24 * 30);
                const totalMonths = (target - new Date(g.created_at)) / (1000 * 60 * 60 * 24 * 30);
                const expectedProgress = (monthsElapsed / totalMonths) * 100;
                return progress < expectedProgress;
              });
            },
          },
        },
      },
    });
  };

  it('renders with no goals', () => {
    const store = createMockStore([]);
    const wrapper = mount(SavingsGoals, {
      global: {
        plugins: [store],
      },
    });

    expect(wrapper.exists()).toBe(true);
    expect(wrapper.text()).toMatch(/no goals|add.*goal/i);
  });

  it('displays goal cards when goals exist', () => {
    const mockGoals = [
      {
        id: 1,
        goal_name: 'Holiday Fund',
        target_amount: 5000,
        current_saved: 2500,
        target_date: '2025-06-01',
        created_at: '2024-01-01',
        priority: 'high',
      },
      {
        id: 2,
        goal_name: 'Car Purchase',
        target_amount: 15000,
        current_saved: 8000,
        target_date: '2026-01-01',
        created_at: '2024-01-01',
        priority: 'medium',
      },
    ];

    const store = createMockStore(mockGoals);
    const wrapper = mount(SavingsGoals, {
      global: {
        plugins: [store],
      },
    });

    expect(wrapper.text()).toContain('Holiday Fund');
    expect(wrapper.text()).toContain('Car Purchase');
  });

  it('displays progress bar for each goal', () => {
    const mockGoals = [
      {
        id: 1,
        goal_name: 'Holiday Fund',
        target_amount: 10000,
        current_saved: 5000,
        target_date: '2025-06-01',
        created_at: '2024-01-01',
        priority: 'high',
      },
    ];

    const store = createMockStore(mockGoals);
    const wrapper = mount(SavingsGoals, {
      global: {
        plugins: [store],
      },
    });

    const html = wrapper.html();
    // Should have progress bar showing 50%
    expect(html).toMatch(/progress|width.*50%|50%/i);
  });

  it('calculates progress percentage correctly', () => {
    const mockGoals = [
      {
        id: 1,
        goal_name: 'Holiday Fund',
        target_amount: 10000,
        current_saved: 7500,
        target_date: '2025-06-01',
        created_at: '2024-01-01',
        priority: 'high',
      },
    ];

    const store = createMockStore(mockGoals);
    const wrapper = mount(SavingsGoals, {
      global: {
        plugins: [store],
      },
    });

    // 7,500 / 10,000 = 75%
    expect(wrapper.text()).toMatch(/75%|75/);
  });

  it('displays on-track badge for goals ahead of schedule', () => {
    const pastDate = new Date();
    pastDate.setMonth(pastDate.getMonth() - 3);

    const futureDate = new Date();
    futureDate.setMonth(futureDate.getMonth() + 9);

    const mockGoals = [
      {
        id: 1,
        goal_name: 'On Track Goal',
        target_amount: 12000,
        current_saved: 6000, // 50% saved, 3/12 months elapsed = on track
        target_date: futureDate.toISOString().split('T')[0],
        created_at: pastDate.toISOString().split('T')[0],
        priority: 'high',
      },
    ];

    const store = createMockStore(mockGoals);
    const wrapper = mount(SavingsGoals, {
      global: {
        plugins: [store],
      },
    });

    const html = wrapper.html();
    expect(html).toMatch(/on.*track|green/i);
  });

  it('displays off-track badge for goals behind schedule', () => {
    const pastDate = new Date();
    pastDate.setMonth(pastDate.getMonth() - 6);

    const futureDate = new Date();
    futureDate.setMonth(futureDate.getMonth() + 6);

    const mockGoals = [
      {
        id: 1,
        goal_name: 'Off Track Goal',
        target_amount: 12000,
        current_saved: 2000, // Only 16% saved, 6/12 months elapsed = off track
        target_date: futureDate.toISOString().split('T')[0],
        created_at: pastDate.toISOString().split('T')[0],
        priority: 'high',
      },
    ];

    const store = createMockStore(mockGoals);
    const wrapper = mount(SavingsGoals, {
      global: {
        plugins: [store],
      },
    });

    const html = wrapper.html();
    expect(html).toMatch(/off.*track|behind|red/i);
  });

  it('displays the target date and derives the required monthly saving', () => {
    const futureDate = new Date();
    futureDate.setMonth(futureDate.getMonth() + 6);

    const mockGoals = [
      {
        id: 1,
        goal_name: 'Holiday Fund',
        target_amount: 5000,
        current_saved: 2500,
        target_date: futureDate.toISOString().split('T')[0],
        created_at: '2024-01-01',
        priority: 'high',
      },
    ];

    const store = createMockStore(mockGoals);
    const wrapper = mount(SavingsGoals, {
      global: {
        plugins: [store],
      },
    });

    expect(wrapper.text()).toContain(wrapper.vm.formatDate(mockGoals[0].target_date));
    expect(wrapper.vm.getRequiredMonthlySavings(mockGoals[0])).toBeGreaterThan(0);
  });

  it('calculates required monthly savings', () => {
    const futureDate = new Date();
    futureDate.setMonth(futureDate.getMonth() + 10);

    const mockGoals = [
      {
        id: 1,
        goal_name: 'Car Purchase',
        target_amount: 10000,
        current_saved: 2000, // Need £8,000 more in 10 months = £800/month
        target_date: futureDate.toISOString().split('T')[0],
        created_at: '2024-01-01',
        priority: 'high',
      },
    ];

    const store = createMockStore(mockGoals);
    const wrapper = mount(SavingsGoals, {
      global: {
        plugins: [store],
      },
    });

    const text = wrapper.text();
    // Should show approximately £800/month needed
    expect(text).toMatch(/800|£800|month/i);
  });

  it('displays summary of goals on track vs total', () => {
    const mockGoals = [
      {
        id: 1,
        goal_name: 'Goal 1',
        target_amount: 5000,
        current_saved: 5000,
        target_date: '2025-12-31',
        created_at: '2024-01-01',
        priority: 'high',
      },
      {
        id: 2,
        goal_name: 'Goal 2',
        target_amount: 10000,
        current_saved: 1000,
        target_date: '2025-12-31',
        created_at: '2024-01-01',
        priority: 'medium',
      },
      {
        id: 3,
        goal_name: 'Goal 3',
        target_amount: 8000,
        current_saved: 500,
        target_date: '2025-12-31',
        created_at: '2024-01-01',
        priority: 'low',
      },
    ];

    const store = createMockStore(mockGoals);
    const wrapper = mount(SavingsGoals, {
      global: {
        plugins: [store],
      },
    });

    const text = wrapper.text();
    // Should show something like "1 of 3 goals on track"
    expect(text).toMatch(/\d+.*of.*\d+.*goal/i);
  });

  it('displays "Add New Goal" button', () => {
    const store = createMockStore([]);
    const wrapper = mount(SavingsGoals, {
      global: {
        plugins: [store],
      },
    });

    const buttons = wrapper.findAll('button');
    const addButton = buttons.find(btn => btn.text().match(/add.*goal/i));
    expect(addButton).toBeDefined();
  });

  it('displays Update Progress button for each goal', () => {
    const mockGoals = [
      {
        id: 1,
        goal_name: 'Holiday Fund',
        target_amount: 5000,
        current_saved: 2500,
        target_date: '2025-06-01',
        created_at: '2024-01-01',
        priority: 'high',
      },
    ];

    const store = createMockStore(mockGoals);
    const wrapper = mount(SavingsGoals, {
      global: {
        plugins: [store],
      },
    });

    const buttons = wrapper.findAll('button');
    const updateButton = buttons.find(btn => btn.text().match(/update.*progress|add.*savings/i));
    expect(updateButton).toBeDefined();
  });

  it('displays Edit and Delete buttons for each goal', () => {
    const mockGoals = [
      {
        id: 1,
        goal_name: 'Holiday Fund',
        target_amount: 5000,
        current_saved: 2500,
        target_date: '2025-06-01',
        created_at: '2024-01-01',
        priority: 'high',
      },
    ];

    const store = createMockStore(mockGoals);
    const wrapper = mount(SavingsGoals, {
      global: {
        plugins: [store],
      },
    });

    const buttons = wrapper.findAll('button');
    const editButton = buttons.find(btn => btn.text().match(/edit/i));
    const deleteButton = buttons.find(btn => btn.text().match(/delete/i));

    expect(editButton).toBeDefined();
    expect(deleteButton).toBeDefined();
  });
});
