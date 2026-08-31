import savingsService from '@/services/savingsService';

import logger from '@/utils/logger';
import { calculateTotalUserShare } from '@/utils/ownership';
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
    // The cash this viewer owns.
    //
    // `calculateTotalUserShare` adds up the `user_share` the API already put on
    // every account — the backend's own `calculateUserShare()` figure, one per
    // record — so this getter transports a total, it does not re-derive one
    // (Rule 20). The arithmetic it replaced applied `ownership_percentage`
    // whichever side of the record the viewer was on, so the CO-OWNER of a joint
    // account was charged the PRIMARY owner's share. On a 75/25 account the
    // co-owner saw 75% of money that was 25% theirs; only a 50/50 split hid it,
    // which is why this survived every earlier sweep (W-0274, F-0019 "fraction").
    totalSavings: (state) => calculateTotalUserShare(state.accounts),

    // The emergency fund.
    //
    // It is the same cash. `is_emergency_fund` is a DESIGNATION — "which account
    // has the user nominated" — not a definition of what the fund contains, and
    // filtering on it here was the fourth surviving answer to "how much emergency
    // fund does this household have" (W-0271, W-0274). A household with £130,780
    // of cash and no ticked boxes does not have a £0 emergency fund, but that is
    // exactly what `/savings` showed while the dashboard, `/m` and `/risk-profile`
    // all read the backend's answer and showed 79.8 and 25.3 months.
    //
    // Deliberately identical to `totalSavings` rather than aliased: the two
    // answer different questions that currently have the same answer, and a
    // future narrowing (W-0276 — cash the user cannot actually reach) belongs to
    // the backend's `CrossModuleAssetAggregator::calculateCashTotal()`, which
    // both figures ultimately come from.
    emergencyFundTotal: (state) => calculateTotalUserShare(state.accounts),

    // Months of runway.
    //
    // The backend's figure when the payload carries it: `SavingsAgent` divides
    // `calculateCashTotal()` by RESOLVED monthly expenditure — a priority chain,
    // not a single column — and that resolution is the part the browser cannot
    // reproduce. This household proves the chain is live: David's expenditure
    // resolves from `expenditure_profile` and Sarah's from `user_monthly`.
    //
    // Where the payload has no analysis block the division falls back to the
    // profile's own monthly figure. That keeps the fund value and the runway
    // consistent with each other; it can still differ from the dashboard if the
    // resolver took a different branch, which is why the backend figure wins
    // whenever it is present.
    emergencyFundRunway: (state, getters) => {
        const resolved = state.analysis?.emergency_fund?.runway_months;
        if (resolved !== undefined && resolved !== null) {
            return parseFloat(resolved) || 0;
        }

        // Null, not 0. With no monthly figure there is no runway to state, and
        // saying "0 months" to a household holding cash is the alarming error
        // W-0495 exists to remove. The backend now sends null for the same
        // reason, so both branches agree.
        const monthlyExpenditure = getters.monthlyExpenditure;
        if (!monthlyExpenditure) return null;

        return getters.emergencyFundTotal / monthlyExpenditure;
    },

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

    // Total ISA balances, at this viewer's share.
    //
    // The third copy of the same wrong-side arithmetic. A joint ISA does not
    // exist in UK law, so the split should never fire here — but the copy did,
    // and a rule with three implementations has three chances to be edited into
    // disagreement (Rule 20).
    totalISABalance: (state) => calculateTotalUserShare(state.accounts.filter(account => account.is_isa)),

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

    // Get monthly expenditure from profile
    monthlyExpenditure: (state) => {
        return state.expenditureProfile?.total_monthly_expenditure || 0;
    },

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

    // Analyse savings
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
