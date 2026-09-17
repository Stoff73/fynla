import gamificationService from '@/services/gamification';

export default {
  namespaced: true,
  state: () => ({
    level: 1,
    levelName: 'Starter',
    progressPercent: 0,
    nextLevelName: null,
    nextActions: [],
    // The banked climb: every level above celebrateFrom, up to celebrateTo,
    // is owed and will be spent on the dashboard hero circle. The full-screen
    // celebration this replaced could only ever hold one level (CSJ 2026-09-17).
    celebrateFrom: 1,
    celebrateTo: 1,
  }),
  mutations: {
    SET_STATUS(state, p) {
      state.level = p.level;
      state.levelName = p.level_name;
      state.progressPercent = p.progress_percent;
      state.nextLevelName = p.next_level_name;
      state.nextActions = p.next_actions || [];
      state.celebrateFrom = p.celebrate_from ?? p.level ?? 1;
      state.celebrateTo = p.celebrate_to ?? p.level ?? 1;
    },
    SETTLE_CELEBRATION(state, level) {
      state.celebrateFrom = level;
      state.celebrateTo = Math.max(level, state.celebrateTo);
    },
  },
  actions: {
    async fetchStatus({ commit }) {
      const { data } = await gamificationService.status();
      commit('SET_STATUS', data);
      return data;
    },
    async acknowledge({ commit }, level) {
      commit('SETTLE_CELEBRATION', level);
      try { await gamificationService.ackCelebration(level); } catch { /* non-fatal */ }
    },
  },
};
