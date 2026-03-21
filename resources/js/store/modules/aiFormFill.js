const state = {
  pendingFill: null,      // { entityType, fields, route, mode, entityId }
  filling: false,
  currentFieldIndex: 0,
  fieldOrder: [],
  highlightedField: null,
  currentStep: 0,
};

const getters = {
  isFillingForm: (state) => state.filling,
  currentHighlight: (state) => state.highlightedField,
  fillDataForField: (state) => (key) => state.pendingFill?.fields?.[key] ?? null,
};

const mutations = {
  SET_PENDING_FILL(state, fill) { state.pendingFill = fill; },
  SET_FILLING(state, filling) { state.filling = filling; },
  SET_FIELD_ORDER(state, order) { state.fieldOrder = order; },
  SET_CURRENT_FIELD_INDEX(state, index) { state.currentFieldIndex = index; },
  SET_HIGHLIGHTED_FIELD(state, field) { state.highlightedField = field; },
  SET_CURRENT_STEP(state, step) { state.currentStep = step; },
  CLEAR(state) {
    state.pendingFill = null;
    state.filling = false;
    state.currentFieldIndex = 0;
    state.fieldOrder = [];
    state.highlightedField = null;
    state.currentStep = 0;
  },
};

let fallbackTimer = null;

const actions = {
  startFill({ commit, state: s }, { entityType, fields, route, mode, entityId }) {
    commit('SET_PENDING_FILL', {
      entityType,
      fields,
      route,
      mode: mode || 'create',
      entityId: entityId || null,
    });

    // If the fill hasn't started within 3 seconds (page didn't load / modal didn't open),
    // clear state so the user can retry or Fyn can fall back
    clearTimeout(fallbackTimer);
    fallbackTimer = setTimeout(() => {
      if (!s.filling) {
        commit('CLEAR');
      }
    }, 3000);
  },

  beginFieldSequence({ commit, state: s, dispatch }, fieldOrder) {
    clearTimeout(fallbackTimer);
    commit('SET_FIELD_ORDER', fieldOrder);
    commit('SET_CURRENT_FIELD_INDEX', 0);
    commit('SET_FILLING', true);
    dispatch('fillNextField');
  },

  fillNextField({ commit, state: s, dispatch }) {
    const index = s.currentFieldIndex;
    if (index >= s.fieldOrder.length) {
      // All fields filled — pause then signal complete
      setTimeout(() => {
        commit('SET_HIGHLIGHTED_FIELD', null);
        commit('SET_FILLING', false);
      }, 250);
      return;
    }

    const fieldKey = s.fieldOrder[index];
    commit('SET_HIGHLIGHTED_FIELD', fieldKey);

    setTimeout(() => {
      commit('SET_CURRENT_FIELD_INDEX', index + 1);
      dispatch('fillNextField');
    }, 250);
  },

  advanceStep({ commit, state: s }) {
    commit('SET_CURRENT_STEP', s.currentStep + 1);
  },

  completeFill({ commit }) {
    clearTimeout(fallbackTimer);
    commit('CLEAR');
  },

  cancelFill({ commit }) {
    clearTimeout(fallbackTimer);
    commit('CLEAR');
  },
};

export default {
  namespaced: true,
  state,
  getters,
  mutations,
  actions,
};
