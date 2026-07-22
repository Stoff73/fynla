import gamificationService from '@/services/gamification';

export default {
  namespaced: true,
  state: () => ({
    level: 1,
    levelName: 'Starter',
    progressPercent: 0,
    nextLevelName: null,
    nextActions: [],
    pendingCelebration: null, // { level, level_name, next_actions } | null
  }),
  mutations: {
    SET_STATUS(state, p) {
      state.level = p.level;
      state.levelName = p.level_name;
      state.progressPercent = p.progress_percent;
      state.nextLevelName = p.next_level_name;
      state.nextActions = p.next_actions || [];
      state.pendingCelebration = p.pending_celebration || null;
    },
    SET_CELEBRATION(state, c) { state.pendingCelebration = c; },
    CLEAR_CELEBRATION(state) { state.pendingCelebration = null; },
  },
  actions: {
    async fetchStatus({ commit }) {
      const { data } = await gamificationService.status();
      commit('SET_STATUS', data);
      return data;
    },
    // Called from the Fyn chat client when it receives a level_up SSE frame.
    queueCelebration({ commit }, frame) {
      commit('SET_CELEBRATION', { level: frame.level, level_name: frame.level_name, next_actions: frame.next_actions || [] });
    },
    async acknowledge({ commit }) {
      commit('CLEAR_CELEBRATION');
      try { await gamificationService.ackCelebration(); } catch (e) { /* non-fatal */ }
    },
  },
};
