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
  investment_holding: 'investment holding',
};

// Multi-step form: map logical step numbers to field keys
const STEP_FIELD_MAP = {
  property: {
    1: ['property_type', 'address_line_1', 'address_line_2', 'city', 'county', 'postcode', 'current_value', 'purchase_price', 'purchase_date'],
    2: ['ownership_type', 'ownership_percentage'],
    3: ['mortgage_provider', 'mortgage_type', 'outstanding_balance', 'mortgage_rate', 'rate_type', 'monthly_payment', 'mortgage_term_remaining', 'mortgage_start_date'],
    4: ['council_tax_band', 'council_tax_annual', 'annual_insurance', 'annual_service_charge', 'annual_ground_rent', 'annual_maintenance'],
    5: ['monthly_rental_income', 'annual_letting_agent_fees', 'annual_void_period_weeks'],
  },
};

const state = {
  pendingFill: null,      // { entityType, fields, route, mode, entityId }
  filling: false,
  formReady: false,       // Set to true when the form component acknowledges receipt
  currentFieldIndex: 0,
  fieldOrder: [],
  highlightedField: null,
  currentStep: 0,
  queue: [],              // Queued fills to process sequentially
};

const getters = {
  isFillingForm: (state) => state.filling,
  currentHighlight: (state) => state.highlightedField,
  fillDataForField: (state) => (key) => state.pendingFill?.fields?.[key] ?? null,
  stepFieldMap: () => (entityType) => STEP_FIELD_MAP[entityType] || null,
};

const mutations = {
  SET_PENDING_FILL(state, fill) { state.pendingFill = fill; },
  SET_FILLING(state, filling) { state.filling = filling; },
  SET_FORM_READY(state, ready) { state.formReady = ready; },
  SET_FIELD_ORDER(state, order) { state.fieldOrder = order; },
  SET_CURRENT_FIELD_INDEX(state, index) { state.currentFieldIndex = index; },
  SET_HIGHLIGHTED_FIELD(state, field) { state.highlightedField = field; },
  SET_CURRENT_STEP(state, step) { state.currentStep = step; },
  CLEAR(state) {
    state.pendingFill = null;
    state.filling = false;
    state.formReady = false;
    state.currentFieldIndex = 0;
    state.fieldOrder = [];
    state.highlightedField = null;
    state.currentStep = 0;
  },
  ENQUEUE_FILL(state, fill) {
    state.queue.push(fill);
  },
  DEQUEUE_FILL(state) {
    state.queue.shift();
  },
  CLEAR_QUEUE(state) {
    state.queue = [];
  },
};

let fallbackTimer = null;

const actions = {
  startFill({ commit, state: s, dispatch }, { entityType, fields, route, mode, entityId }) {
    const fillData = {
      entityType,
      fields,
      route,
      mode: mode || 'create',
      entityId: entityId || null,
    };

    // If a fill is already in progress, queue this one for sequential processing
    if (s.pendingFill || s.filling) {
      commit('ENQUEUE_FILL', fillData);
      return;
    }

    clearTimeout(fallbackTimer);

    commit('SET_PENDING_FILL', fillData);
    commit('SET_FORM_READY', false);

    // Fallback: if the form hasn't acknowledged within 30 seconds, clear state
    // and inform the user. This is a safety net — normally the form acknowledges
    // within 1-2 seconds via acknowledgeFormReady().
    fallbackTimer = setTimeout(() => {
      if (!s.formReady && !s.filling) {
        const label = ENTITY_LABELS[entityType] || entityType.replace(/_/g, ' ');
        commit('aiChat/ADD_MESSAGE', {
          id: 'fill_timeout_' + Date.now(),
          role: 'assistant',
          content: `The form for your ${label} didn't load in time. Please try again, or add it manually using the form on the page.`,
          created_at: new Date().toISOString(),
        }, { root: true });
        commit('CLEAR');
        dispatch('processNextInQueue');
      }
    }, 30000);
  },

  /**
   * Called by form components when they detect a pendingFill and are ready to process it.
   * This confirms the form has mounted and the pendingFill watcher has fired.
   */
  acknowledgeFormReady({ commit }) {
    commit('SET_FORM_READY', true);
    clearTimeout(fallbackTimer);
  },

  beginFieldSequence({ commit, state: s, dispatch }, fieldOrder) {
    // Acknowledge form is ready — centralised here so every form component
    // gets the handshake automatically when they call beginFieldSequence
    commit('SET_FORM_READY', true);
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

  /**
   * For multi-step forms: fill fields for one step, then signal step complete.
   * The form component calls this per step and advances its own wizard.
   */
  fillStepFields({ commit, state: s, dispatch }, { stepNumber, fieldOrder }) {
    // Acknowledge form is ready (multi-step forms use this instead of beginFieldSequence)
    commit('SET_FORM_READY', true);
    clearTimeout(fallbackTimer);
    // Only fill fields that have AI data
    const fieldsWithData = fieldOrder.filter(k => {
      const val = s.pendingFill?.fields?.[k];
      return val !== null && val !== undefined && val !== '';
    });

    if (fieldsWithData.length === 0) {
      // No fields for this step — signal step complete immediately
      commit('SET_CURRENT_STEP', stepNumber);
      return;
    }

    commit('SET_FIELD_ORDER', fieldsWithData);
    commit('SET_CURRENT_FIELD_INDEX', 0);
    commit('SET_FILLING', true);
    commit('SET_CURRENT_STEP', stepNumber);
    dispatch('fillNextField');
  },

  /**
   * Get the field keys for a specific step of a multi-step entity type.
   */
  getStepFields(_, { entityType, stepNumber }) {
    const map = STEP_FIELD_MAP[entityType];
    return map?.[stepNumber] || [];
  },

  completeFill({ commit, state: s, dispatch }) {
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
    dispatch('processNextInQueue');
  },

  cancelFill({ commit, dispatch }) {
    // Cancels only the current fill; queued fills still proceed
    clearTimeout(fallbackTimer);
    commit('CLEAR');
    dispatch('processNextInQueue');
  },

  cancelAll({ commit }) {
    // Cancels the current fill AND clears the entire queue
    clearTimeout(fallbackTimer);
    commit('CLEAR');
    commit('CLEAR_QUEUE');
  },

  processNextInQueue({ commit, state: s, dispatch }) {
    if (s.queue.length === 0) return;
    const next = s.queue[0];
    commit('DEQUEUE_FILL', next);
    // Kick off the next fill via startFill, which will set pendingFill and the fallback timer
    dispatch('startFill', next);
  },
};

export default {
  namespaced: true,
  state,
  getters,
  mutations,
  actions,
};
