const state = {
  // The last CTA action dispatched (e.g. 'addAccount', 'uploadStatement')
  pendingAction: null,
  // Counter to ensure watchers fire even for repeated same-action clicks
  actionCounter: 0,
};

const mutations = {
  triggerAction(state, action) {
    state.pendingAction = action;
    state.actionCounter++;
  },
  clearAction(state) {
    state.pendingAction = null;
  },
};

const actions = {
  triggerCta({ commit }, action) {
    commit('triggerAction', action);
  },
  consumeCta({ commit }) {
    commit('clearAction');
  },
};

const getters = {
  pendingAction: (state) => state.pendingAction,
  actionCounter: (state) => state.actionCounter,
};

export default {
  namespaced: true,
  state,
  mutations,
  actions,
  getters,
};
