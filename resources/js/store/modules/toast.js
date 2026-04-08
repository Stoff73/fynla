const state = {
  message: '',
  type: 'success',
  visible: false,
  timerId: null,
};

const mutations = {
  SHOW(state, { message, type = 'success' }) {
    state.message = message;
    state.type = type;
    state.visible = true;
  },
  HIDE(state) {
    state.visible = false;
  },
  SET_TIMER(state, id) {
    state.timerId = id;
  },
};

const actions = {
  show({ commit, state }, { message, type = 'success', duration = 3000 }) {
    if (state.timerId) clearTimeout(state.timerId);
    commit('SHOW', { message, type });
    const id = setTimeout(() => commit('HIDE'), duration);
    commit('SET_TIMER', id);
  },
  success({ dispatch }, message) {
    dispatch('show', { message, type: 'success' });
  },
  error({ dispatch }, message) {
    dispatch('show', { message, type: 'error' });
  },
};

export default {
  namespaced: true,
  state,
  mutations,
  actions,
};
