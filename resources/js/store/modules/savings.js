import savingsService from '@/services/savingsService';

import logger from '@/utils/logger';
const state = {
    accounts: [],
    expenditureProfile: null,
    analysis: null,
    isaAllowance: null,
    recommendations: [],
    lifeEvents: [],
    lifeEventImpact: null,
    goalStrategies: [],
    goalsSummary: null,
    canProceed: true,
    readinessChecks: null,
    loading: false,
    error: null,
};

const getters = {
    // The cash this viewer owns: `analysis.summary.total_savings`, which
    // `SavingsAgent` takes from `CrossModuleAssetAggregator::calculateCashTotal()`
    // — the same figure `/m`, iOS, the dashboard and `/net-worth` read (Rule 20;
    // 2026-10-01 one-figure audit item 21). Read as sent. The browser used to add
    // the accounts up itself; null here means the server did not answer, and the
    // page shows no figure rather than working one out.
    totalSavings: (state) => state.analysis?.summary?.total_savings ?? null,

    // The emergency fund block exactly as `SavingsAgent` computed it: runway,
    // target, target months, percentage of target, shortfall, the cash it is
    // measured on and the what-if targets for 3 to 12 months (audit items 22-23).
    emergencyFund: (state) => state.analysis?.emergency_fund ?? null,

    // The emergency fund is the same cash. `is_emergency_fund` is a DESIGNATION —
    // "which account has the user nominated" — not a definition of what the fund
    // contains (W-0271, W-0274), so this is the server's `current_amount`, which
    // is `total_savings`.
    emergencyFundTotal: (state) => state.analysis?.emergency_fund?.current_amount ?? null,

    // Months of runway: `SavingsAgent` divides the cash by RESOLVED monthly
    // expenditure, a priority chain the browser cannot reproduce. Null when it
    // cannot be worked out (W-0495) or the server did not answer — never a
    // division on the client.
    emergencyFundRunway: (state) => {
        const months = state.analysis?.emergency_fund?.runway_months;
        return months === undefined || months === null ? null : Number(months);
    },

    // The cash page's per-group totals, each at this viewer's share, from the
    // same aggregator as `total_savings` (so they add up to it).
    cashGroups: (state) => state.analysis?.summary?.cash_groups ?? [],

    // Get ISA allowance remaining
    // Note: Returns 0 if ISA data not loaded - ensure fetchISAAllowance is called on init
    isaAllowanceRemaining: (state) => {
        if (!state.isaAllowance) {
            // Return 0 instead of hardcoded fallback - forces proper API fetch
            console.warn('ISA allowance not loaded - call fetchISAAllowance first');
            return 0;
        }

        const cashISAUsed = state.isaAllowance.cash_isa_used || 0;
        const stocksISAUsed = state.isaAllowance.stocks_shares_isa_used || 0;
        const totalAllowance = state.isaAllowance.total_allowance || 0;

        return totalAllowance - cashISAUsed - stocksISAUsed;
    },

    // Get ISA usage percentage
    isaUsagePercent: (state, getters) => {
        if (!state.isaAllowance) return 0;

        const totalAllowance = state.isaAllowance.total_allowance || 0;
        if (totalAllowance === 0) return 0;
        const remaining = getters.isaAllowanceRemaining;

        return Math.round(((totalAllowance - remaining) / totalAllowance) * 100);
    },

    // Get current year ISA subscription (Cash ISA)
    currentYearISASubscription: (state) => {
        return state.isaAllowance?.cash_isa_used || 0;
    },

    // Get accounts by access type
    accountsByAccessType: (state) => {
        const grouped = {
            immediate: [],
            notice: [],
            fixed: [],
        };

        state.accounts.forEach(account => {
            const accessType = account.access_type || 'immediate';
            if (grouped[accessType]) {
                grouped[accessType].push(account);
            }
        });

        return grouped;
    },

    // The monthly spending the runway and target are measured against: the
    // RESOLVED figure from `SavingsAgent`, not the raw profile column, so the
    // figure shown beside the target is the one that produced it.
    monthlyExpenditure: (state) => state.analysis?.summary?.monthly_expenditure ?? null,

    // Life events relevant to savings module
    upcomingLifeEvents: (state) => state.lifeEvents,
    lifeEventNetImpact: (state) => state.lifeEventImpact?.net_impact || 0,

    // Goal strategies for savings module
    activeGoalStrategies: (state) => state.goalStrategies,
    totalGoalCommitment: (state) => state.goalsSummary?.total_monthly_commitment || 0,
    goalsOnTrackCount: (state) => {
        return state.goalStrategies.filter(s => s.goal?.is_on_track).length;
    },

    canProceed: (state) => state.canProceed,
    readinessChecks: (state) => state.readinessChecks,

    loading: (state) => state.loading,
    error: (state) => state.error,
};

const actions = {
    // Fetch all savings data
    async fetchSavingsData({ commit }) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await savingsService.getSavingsData();
            const data = response.data || response;

            // Guard: handle can_proceed: false
            if (data?.can_proceed === false) {
                commit('SET_CAN_PROCEED', false);
                commit('SET_READINESS_CHECKS', data?.readiness_checks || null);
                return response;
            }

            commit('SET_CAN_PROCEED', true);
            commit('SET_READINESS_CHECKS', null);
            commit('setAccounts', data.accounts || []);
            commit('setExpenditureProfile', data.expenditure_profile || null);
            commit('setAnalysis', data.analysis || null);
            commit('setISAAllowance', data.isa_allowance || null);
            commit('setLifeEvents', data.life_events || []);
            commit('setLifeEventImpact', data.life_event_impact || null);
            commit('setGoalStrategies', data.goal_strategies || []);
            commit('setGoalsSummary', data.goals_summary || null);
            return response;
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message || 'Failed to fetch savings data';
            commit('setError', errorMessage);
            logger.error('Savings data fetch error:', error);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    // The Strategy tab. Same endpoint and same items as Fyn's ranked list and the
    // dashboard aggregator (GET /savings/recommendations -> SavingsPlanService).
    async fetchRecommendations({ commit }) {
        const response = await savingsService.getRecommendations();
        commit('setRecommendations', response.data || []);
        return response;
    },

    /**
     * Load the ISA allowance if it is not already in the store.
     *
     * The allowance used to arrive ONLY as part of the big /api/savings payload,
     * so any screen that did not fetch that — the investment account modal —
     * read `cash_isa_used: 0` from the null state and silently withheld the
     * over-subscription warning on a statutory limit (W-0007). This is the ONE
     * place either modal loads it from; both read the same state and getters.
     */
    async ensureISAAllowance({ commit, state }, { force = false } = {}) {
        if (state.isaAllowance && !force) {
            return state.isaAllowance;
        }

        try {
            const response = await savingsService.getISAAllowance();
            const allowance = response?.data ?? null;
            commit('setISAAllowance', allowance);
            return allowance;
        } catch (error) {
            logger.error('ISA allowance fetch error:', error);
            return null;
        }
    },

    /**
     * Analyse savings against a supplied scenario.
     *
     * **No SPA caller, and that is correct, not a gap (W-0335).** The savings
     * analysis now arrives with `/api/savings` itself — `SavingsController::index()`
     * composes `emergency_fund` and the expenditure summary into the same payload
     * as the accounts — so the screens have the figures without a second round
     * trip, and dispatching this as well would be a second mechanism for one
     * number (Rule 20).
     *
     * `POST /savings/analyze` stays because it takes a SCENARIO: it answers "what
     * if my expenditure were X", which the index payload cannot. Recorded here
     * because an action with no callers reads exactly like an unwired capability
     * to the next sweep, and this one is not.
     */
    async analyseSavings({ commit }, data) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await savingsService.analyzeSavings(data);
            const responseData = response.data || response;

            // Guard: handle can_proceed: false
            if (responseData?.can_proceed === false) {
                commit('SET_CAN_PROCEED', false);
                commit('SET_READINESS_CHECKS', responseData?.readiness_checks || null);
                commit('setAnalysis', null);
                return response;
            }

            commit('SET_CAN_PROCEED', true);
            commit('SET_READINESS_CHECKS', null);
            // `responseData` IS the analysis — `/savings/analyze` returns it under
            // `data`, which `savingsService` has already unwrapped. Reading
            // `.analysis` off it committed `undefined` on every call, which the
            // guard three lines above proves: it reads `can_proceed` and
            // `readiness_checks` off `responseData` directly (W-0335).
            commit('setAnalysis', responseData);
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Analysis failed';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    // Account actions
    async createAccount({ commit, dispatch }, accountData) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await savingsService.createAccount(accountData);
            const account = response.data || response;
            commit('addAccount', account);
            // The totals and the emergency fund are the server's, so they are
            // fetched again rather than re-added in the browser (Rule 20).
            await dispatch('fetchSavingsData');
            // Refresh net worth and recommendations
            await dispatch('netWorth/refreshNetWorth', null, { root: true });
            dispatch('recommendations/fetchRecommendations', {}, { root: true });
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to create account';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    async fetchAccount({ commit }, id) {
        try {
            const response = await savingsService.getAccount(id);
            return response.data || response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to fetch account';
            commit('setError', errorMessage);
            throw error;
        }
    },

    async updateAccount({ commit, dispatch }, { id, accountData }) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await savingsService.updateAccount(id, accountData);
            const account = response.data || response;
            commit('updateAccount', account);
            // The totals and the emergency fund are the server's, so they are
            // fetched again rather than re-added in the browser (Rule 20).
            await dispatch('fetchSavingsData');
            // Refresh net worth and recommendations
            await dispatch('netWorth/refreshNetWorth', null, { root: true });
            dispatch('recommendations/fetchRecommendations', {}, { root: true });
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to update account';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    async deleteAccount({ commit, dispatch }, id) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await savingsService.deleteAccount(id);
            commit('removeAccount', id);
            // The totals and the emergency fund are the server's, so they are
            // fetched again rather than re-added in the browser (Rule 20).
            await dispatch('fetchSavingsData');
            // Refresh net worth and recommendations
            await dispatch('netWorth/refreshNetWorth', null, { root: true });
            dispatch('recommendations/fetchRecommendations', {}, { root: true });
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to delete account';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    // Expenditure profile actions
    async updateExpenditureProfile({ commit }, profileData) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await savingsService.updateExpenditureProfile(profileData);
            commit('setExpenditureProfile', response.data.profile);
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to update expenditure profile';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },
};

const mutations = {
    setAccounts(state, accounts) {
        state.accounts = accounts;
    },

    setRecommendations(state, recommendations) {
        state.recommendations = recommendations;
    },

    setExpenditureProfile(state, profile) {
        state.expenditureProfile = profile;
    },

    setAnalysis(state, analysis) {
        state.analysis = analysis;
    },

    setISAAllowance(state, allowance) {
        state.isaAllowance = allowance;
    },

    setLifeEvents(state, events) {
        state.lifeEvents = events;
    },

    setLifeEventImpact(state, impact) {
        state.lifeEventImpact = impact;
    },

    setGoalStrategies(state, strategies) {
        state.goalStrategies = strategies;
    },

    setGoalsSummary(state, summary) {
        state.goalsSummary = summary;
    },

    addAccount(state, account) {
        state.accounts.push(account);
    },

    updateAccount(state, account) {
        const index = state.accounts.findIndex(a => a.id === account.id);
        if (index !== -1) {
            state.accounts.splice(index, 1, account);
        }
    },

    removeAccount(state, id) {
        const index = state.accounts.findIndex(a => a.id === id);
        if (index !== -1) {
            state.accounts.splice(index, 1);
        }
    },

    SET_CAN_PROCEED(state, canProceed) {
        state.canProceed = canProceed;
    },

    SET_READINESS_CHECKS(state, checks) {
        state.readinessChecks = checks;
    },

    setLoading(state, loading) {
        state.loading = loading;
    },

    setError(state, error) {
        state.error = error;
    },
};

export default {
    namespaced: true,
    state,
    getters,
    actions,
    mutations,
};
