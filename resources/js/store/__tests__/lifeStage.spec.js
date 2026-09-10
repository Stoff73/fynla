import { describe, it, expect, vi } from 'vitest';

vi.mock('@/services/lifeStageService', () => ({
  default: { getProgress: vi.fn(() => Promise.resolve({ success: true, data: {} })) },
}));

const lifeStage = (await import('../modules/lifeStage')).default;

/**
 * `users.life_stage` also holds the journey or focus area a user last started
 * (JourneyStateService, OnboardingService), so "goals" or "estate" can arrive
 * by every path that sets the stage. Treating that as a life stage put the
 * wizard into life-stage mode with no steps: a blank step, a null sidebar step
 * prop, Continue → dashboard.
 */
describe('lifeStage setCurrentStage', () => {
  it('adopts a real life stage', () => {
    const state = { currentStage: null };
    lifeStage.mutations.setCurrentStage(state, 'early_career');
    expect(state.currentStage).toBe('early_career');
  });

  it('drops a journey or focus-area name stored in the same column', () => {
    const state = { currentStage: 'peak' };
    lifeStage.mutations.setCurrentStage(state, 'goals');
    expect(state.currentStage).toBeNull();
    lifeStage.mutations.setCurrentStage(state, 'estate');
    expect(state.currentStage).toBeNull();
  });

  it('is what fetchStage goes through when it reads the user', async () => {
    const commits = [];
    await lifeStage.actions.fetchStage({
      commit: (type, payload) => commits.push([type, payload]),
      rootGetters: { 'auth/user': { life_stage: 'goals' } },
    });
    expect(commits.find(([t]) => t === 'setCurrentStage')).toEqual(['setCurrentStage', 'goals']);
  });
});
