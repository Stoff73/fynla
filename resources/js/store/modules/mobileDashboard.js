/**
 * Mobile Dashboard Store Module
 *
 * Manages the mobile dashboard state including net worth summary,
 * module overviews, alerts, and daily insights. Supports staleness
 * checking to avoid unnecessary API calls.
 */

import api from '@/services/api';
import { formatCurrency } from '@/utils/currency';

const MODULE_LABELS = {
    protection: 'Protection',
    savings: 'Savings',
    investment: 'Investment',
    retirement: 'Retirement',
    estate: 'Estate Planning',
    goals: 'Goals',
    coordination: 'Coordination',
};

/**
 * Transform raw API module data into the normalised shape
 * expected by ModuleSummaryCard and ModuleSummary components.
 */
function normaliseModule(name, raw) {
    const base = { name, label: MODULE_LABELS[name] || name, ...raw };

    if (raw.status === 'not_configured' || raw.status === 'unavailable') {
        return {
            ...base,
            metric_value: raw.message || 'Not set up',
            metric_type: 'text',
            status: 'not_configured',
            hero_metric: null,
            fyn_summary: raw.message || null,
            details: [],
        };
    }

    switch (name) {
    case 'protection': {
        const gaps = raw.critical_gaps || 0;
        const policyCount = raw.policy_count || 0;
        return {
            ...base,
            metric_type: 'text',
            metric_value: `${policyCount} ${policyCount === 1 ? 'policy' : 'policies'}`,
            subtitle: gaps > 0 ? `${gaps} gap${gaps > 1 ? 's' : ''} to review` : 'No gaps identified',
            status: gaps > 0 ? 'action_needed' : 'good',
            hero_metric: {
                formatted: formatCurrency(raw.total_coverage || 0),
                value: raw.total_coverage || 0,
                label: 'Total life cover',
            },
            fyn_summary: gaps > 0
                ? `You have ${gaps} protection gap${gaps > 1 ? 's' : ''} that may need attention.`
                : 'Your protection cover looks solid.',
            details: [
                { label: 'Policies', value: String(policyCount) },
                { label: 'Total life cover', value: formatCurrency(raw.total_coverage || 0) },
                { label: 'Critical gaps', value: String(gaps) },
                { label: 'Income protection', value: raw.has_income_protection ? 'Yes' : 'No' },
            ],
        };
    }
    case 'savings': {
        const months = raw.emergency_fund_months || 0;
        return {
            ...base,
            metric_type: 'currency',
            metric_value: raw.total_savings || 0,
            subtitle: `${raw.total_accounts || 0} account${(raw.total_accounts || 0) !== 1 ? 's' : ''}`,
            status: months < 3 ? 'warning' : 'good',
            hero_metric: {
                formatted: formatCurrency(raw.total_savings || 0),
                value: raw.total_savings || 0,
                label: 'Total savings',
            },
            fyn_summary: months < 3
                ? `Your emergency fund covers ${months.toFixed(1)} months — building towards 3-6 months is recommended.`
                : `Your emergency fund covers ${months.toFixed(1)} months of expenses.`,
            details: [
                { label: 'Total savings', value: formatCurrency(raw.total_savings || 0) },
                { label: 'Accounts', value: String(raw.total_accounts || 0) },
                { label: 'Emergency fund', value: `${months.toFixed(1)} months` },
                { label: 'Emergency fund status', value: raw.emergency_fund_status || '—' },
            ],
        };
    }
    case 'investment': {
        return {
            ...base,
            metric_type: 'currency',
            metric_value: raw.portfolio_value || 0,
            subtitle: `${raw.accounts_count || 0} account${(raw.accounts_count || 0) !== 1 ? 's' : ''}`,
            status: 'good',
            hero_metric: {
                formatted: formatCurrency(raw.portfolio_value || 0),
                value: raw.portfolio_value || 0,
                label: 'Portfolio value',
            },
            fyn_summary: 'Your investment portfolio is working to grow your wealth over time.',
            details: [
                { label: 'Portfolio value', value: formatCurrency(raw.portfolio_value || 0) },
                { label: 'Accounts', value: String(raw.accounts_count || 0) },
                { label: 'Holdings', value: String(raw.holdings_count || 0) },
            ],
        };
    }
    case 'retirement': {
        const gap = raw.income_gap || 0;
        return {
            ...base,
            metric_type: 'currency',
            metric_value: raw.projected_income || 0,
            subtitle: `${raw.years_to_retirement || 0} years to retirement`,
            status: gap > 0 ? 'warning' : 'good',
            hero_metric: {
                formatted: formatCurrency(raw.projected_income || 0),
                value: raw.projected_income || 0,
                label: 'Projected retirement income',
            },
            fyn_summary: gap > 0
                ? `Your projected retirement income is ${formatCurrency(gap)} below your target.`
                : 'Your projected retirement income meets your target.',
            details: [
                { label: 'Projected income', value: formatCurrency(raw.projected_income || 0) },
                { label: 'Target income', value: formatCurrency(raw.target_income || 0) },
                { label: 'Income gap', value: gap > 0 ? formatCurrency(gap) : 'None' },
                { label: 'Years to retirement', value: String(raw.years_to_retirement || 0) },
                { label: 'Pensions', value: String(raw.total_pensions || 0) },
            ],
        };
    }
    case 'estate': {
        const iht = raw.iht_liability || 0;
        return {
            ...base,
            metric_type: 'currency',
            metric_value: raw.net_estate || 0,
            subtitle: iht > 0 ? `Inheritance tax: ${formatCurrency(iht)}` : 'No inheritance tax liability',
            status: iht > 0 ? 'warning' : 'good',
            hero_metric: {
                formatted: formatCurrency(raw.net_estate || 0),
                value: raw.net_estate || 0,
                label: 'Net estate value',
            },
            fyn_summary: iht > 0
                ? `Your estate has an estimated inheritance tax liability of ${formatCurrency(iht)}.`
                : 'Your estate currently has no inheritance tax liability.',
            details: [
                { label: 'Net estate', value: formatCurrency(raw.net_estate || 0) },
                { label: 'Inheritance tax liability', value: formatCurrency(iht) },
                { label: 'Effective tax rate', value: `${(raw.effective_tax_rate || 0).toFixed(1)}%` },
            ],
        };
    }
    case 'goals': {
        const total = raw.total_goals || 0;
        const completed = raw.completed_goals || 0;
        return {
            ...base,
            metric_type: 'text',
            metric_value: `${completed} of ${total} completed`,
            subtitle: total > 0 ? `${formatCurrency(raw.total_saved || 0)} saved` : null,
            status: total === 0 ? 'not_configured' : (completed === total ? 'good' : 'on_track'),
            hero_metric: {
                formatted: `${completed} / ${total}`,
                value: completed,
                label: 'Goals completed',
            },
            fyn_summary: completed > 0
                ? `Well done — you have completed ${completed} of your ${total} financial goals.`
                : `You have ${total} financial goals in progress.`,
            details: [
                { label: 'Total goals', value: String(total) },
                { label: 'Completed', value: String(completed) },
                { label: 'Target total', value: formatCurrency(raw.total_target || 0) },
                { label: 'Saved so far', value: formatCurrency(raw.total_saved || 0) },
            ],
        };
    }
    default:
        return { ...base, metric_value: '—', metric_type: 'text', status: raw.status || 'unknown' };
    }
}

const state = {
    summary: null,
    netWorth: null,
    modules: [],
    alerts: [],
    insight: null,
    loading: false,
    error: null,
    lastFetched: null,
};

const getters = {
    summary: (state) => state.summary,
    netWorth: (state) => state.netWorth,
    modules: (state) => state.modules,
    alerts: (state) => state.alerts,
    insight: (state) => state.insight,
    loading: (state) => state.loading,
    error: (state) => state.error,
    lastFetched: (state) => state.lastFetched,

    isStale: (state) => {
        if (!state.lastFetched) return true;
        const fiveMinutes = 5 * 60 * 1000;
        return Date.now() - state.lastFetched > fiveMinutes;
    },
};

const mutations = {
    SET_DASHBOARD(state, data) {
        state.summary = data.summary || null;
        state.netWorth = data.net_worth || null;
        state.modules = data.modules
            ? Object.entries(data.modules).map(([name, mod]) => normaliseModule(name, mod))
            : [];
        state.alerts = data.alerts || [];
        state.insight = data.fyn_insight || null;
        state.lastFetched = Date.now();
    },

    SET_LOADING(state, loading) {
        state.loading = loading;
    },

    SET_ERROR(state, error) {
        state.error = error;
    },

    CLEAR_CACHE(state) {
        state.summary = null;
        state.netWorth = null;
        state.modules = [];
        state.alerts = [];
        state.insight = null;
        state.lastFetched = null;
        state.error = null;
    },
};

const actions = {
    /**
     * Fetch mobile dashboard data (skips if not stale).
     */
    async fetchDashboard({ commit, getters }) {
        if (!getters.isStale) return;

        commit('SET_LOADING', true);
        commit('SET_ERROR', null);

        try {
            const response = await api.get('/v1/mobile/dashboard');
            commit('SET_DASHBOARD', response.data.data);
        } catch (error) {
            commit('SET_ERROR', error.message || 'Failed to load dashboard');
        } finally {
            commit('SET_LOADING', false);
        }
    },

    /**
     * Force refresh mobile dashboard data (ignores staleness).
     */
    async refreshDashboard({ commit }) {
        commit('SET_LOADING', true);
        commit('SET_ERROR', null);

        try {
            const response = await api.get('/v1/mobile/dashboard');
            commit('SET_DASHBOARD', response.data.data);
        } catch (error) {
            commit('SET_ERROR', error.message || 'Failed to refresh dashboard');
        } finally {
            commit('SET_LOADING', false);
        }
    },

    /**
     * Clear cached dashboard data.
     */
    clearCache({ commit }) {
        commit('CLEAR_CACHE');
    },
};

export default {
    namespaced: true,
    state,
    getters,
    mutations,
    actions,
};
