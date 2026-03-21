const ENTITY_LABELS = {
  savings_account: 'savings account',
  investment_account: 'investment account',
  dc_pension: 'pension',
  db_pension: 'pension',
  property: 'property',
  mortgage: 'mortgage',
  protection_policy: 'protection policy',
  goal: 'goal',
  life_event: 'life event',
  family_member: 'family member',
  trust: 'trust',
  business_interest: 'business interest',
  chattel: 'valuable item',
  estate_asset: 'estate asset',
  estate_liability: 'liability',
  estate_gift: 'gift',
};

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

  completeFill({ commit, state: s, rootCommit }) {
    // Add confirmation message to chat
    if (s.pendingFill) {
      const entityType = s.pendingFill.entityType;
      const mode = s.pendingFill.mode || 'create';
      const label = ENTITY_LABELS[entityType] || entityType.replace(/_/g, ' ');
      const verb = mode === 'edit' ? 'updated' : 'added';
      const name = s.pendingFill.fields?.institution
        || s.pendingFill.fields?.account_name
        || s.pendingFill.fields?.goal_name
        || s.pendingFill.fields?.trust_name
        || s.pendingFill.fields?.business_name
        || s.pendingFill.fields?.description
        || s.pendingFill.fields?.first_name
        || '';
      const suffix = name ? ` "${name}"` : '';

      commit('aiChat/ADD_MESSAGE', {
        id: 'fill_confirm_' + Date.now(),
        role: 'assistant',
        content: `Done — your ${label}${suffix} has been ${verb} successfully.`,
        created_at: new Date().toISOString(),
      }, { root: true });
    }

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
