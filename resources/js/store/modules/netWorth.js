import netWorthService from '../../services/netWorthService';
import propertyService from '../../services/propertyService';
import mortgageService from '../../services/mortgageService';
import logger from '../../utils/logger';

const state = {
    overview: {
        totalAssets: 0,
        totalLiabilities: 0,
        netWorth: 0,
        breakdown: {},
        liabilitiesBreakdown: {},
        asOfDate: null,
        hasDbPensions: false,
    },
    spouseOverview: null,
    assetsSummary: {
        pensions: { count: 0, total_value: 0, breakdown: { dc: 0, db: 0, state: 0 } },
        property: { count: 0, total_value: 0 },
        investments: { count: 0, total_value: 0 },
        cash: { count: 0, total_value: 0 },
        business: { count: 0, total_value: 0 },
        chattels: { count: 0, total_value: 0 },
    },
    assetsSummaryDetailed: {
        pensions: { count: 0, total_value: 0, items: [] },
        property: { count: 0, total_value: 0, items: [] },
        investments: { count: 0, total_value: 0, items: [] },
        cash: { count: 0, total_value: 0, items: [] },
        business: { count: 0, total_value: 0, items: [] },
        chattels: { count: 0, total_value: 0, items: [] },
    },
    jointAssets: [],
    properties: [],
    selectedProperty: null,
    mortgages: [],
    loading: false,
    error: null,
    isDetailView: false,
};

const mutations = {
    SET_OVERVIEW(state, overview) {
        state.overview = {
            totalAssets: overview.total_assets || 0,
            totalLiabilities: overview.total_liabilities || 0,
            netWorth: overview.net_worth || 0,
            breakdown: overview.breakdown || {},
            liabilitiesBreakdown: overview.liabilities_breakdown || {},
            asOfDate: overview.as_of_date || null,
            hasDbPensions: overview.has_db_pensions || false,
        };
    },

    SET_SPOUSE_OVERVIEW(state, spouseData) {
        state.spouseOverview = spouseData;
    },

    SET_ASSETS_SUMMARY(state, summary) {
        state.assetsSummary = summary;
    },

    SET_ASSETS_SUMMARY_DETAILED(state, summary) {
        state.assetsSummaryDetailed = summary;
    },

    SET_JOINT_ASSETS(state, jointAssets) {
        state.jointAssets = jointAssets;
    },

    SET_LOADING(state, loading) {
        state.loading = loading;
    },

    SET_ERROR(state, error) {
        state.error = error;
    },

    CLEAR_ERROR(state) {
        state.error = null;
    },

    SET_DETAIL_VIEW(state, isDetailView) {
        state.isDetailView = isDetailView;
    },

    RESET_STATE(state) {
        state.overview = {
            totalAssets: 0,
            totalLiabilities: 0,
            netWorth: 0,
            breakdown: {},
            liabilitiesBreakdown: {},
            asOfDate: null,
            hasDbPensions: false,
        };
        state.spouseOverview = null;
        state.assetsSummary = {
            pensions: { count: 0, total_value: 0, breakdown: { dc: 0, db: 0, state: 0 } },
            property: { count: 0, total_value: 0 },
            investments: { count: 0, total_value: 0 },
            cash: { count: 0, total_value: 0 },
            business: { count: 0, total_value: 0 },
            chattels: { count: 0, total_value: 0 },
        };
        state.assetsSummaryDetailed = {
            pensions: { count: 0, total_value: 0, items: [] },
            property: { count: 0, total_value: 0, items: [] },
            investments: { count: 0, total_value: 0, items: [] },
            cash: { count: 0, total_value: 0, items: [] },
            business: { count: 0, total_value: 0, items: [] },
            chattels: { count: 0, total_value: 0, items: [] },
        };
        state.jointAssets = [];
        state.properties = [];
        state.selectedProperty = null;
        state.mortgages = [];
        state.loading = false;
        state.error = null;
    },

    // Property mutations
    SET_PROPERTIES(state, properties) {
        state.properties = properties;
    },

    SET_SELECTED_PROPERTY(state, property) {
        state.selectedProperty = property;
    },

    ADD_PROPERTY(state, property) {
        state.properties.push(property);
    },

    UPDATE_PROPERTY(state, updatedProperty) {
        const index = state.properties.findIndex(p => p.id === updatedProperty.id);
        if (index !== -1) {
            state.properties.splice(index, 1, updatedProperty);
        }
        if (state.selectedProperty && state.selectedProperty.id === updatedProperty.id) {
            state.selectedProperty = updatedProperty;
        }
    },

    REMOVE_PROPERTY(state, propertyId) {
        state.properties = state.properties.filter(p => p.id !== propertyId);
        if (state.selectedProperty && state.selectedProperty.id === propertyId) {
            state.selectedProperty = null;
        }
    },

    // Mortgage mutations
    SET_MORTGAGES(state, mortgages) {
        state.mortgages = mortgages;
    },

    ADD_MORTGAGE(state, mortgage) {
        state.mortgages.push(mortgage);
    },

    UPDATE_MORTGAGE(state, updatedMortgage) {
        const index = state.mortgages.findIndex(m => m.id === updatedMortgage.id);
        if (index !== -1) {
            state.mortgages.splice(index, 1, updatedMortgage);
        }
    },

    REMOVE_MORTGAGE(state, mortgageId) {
        state.mortgages = state.mortgages.filter(m => m.id !== mortgageId);
    },
};

const actions = {
    setDetailView({ commit }, isDetailView) {
        commit('SET_DETAIL_VIEW', isDetailView);
    },

    async fetchOverview({ commit }) {
        commit('SET_LOADING', true);
        commit('CLEAR_ERROR');

        try {
            const response = await netWorthService.getOverview();

            if (response.success) {
                commit('SET_OVERVIEW', response.data);
                // Set spouse data if it exists
                if (response.spouse_data) {
                    commit('SET_SPOUSE_OVERVIEW', response.spouse_data);
                } else {
                    commit('SET_SPOUSE_OVERVIEW', null);
                }
            } else {
                throw new Error(response.message || 'Failed to fetch net worth overview');
            }
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message || 'Failed to fetch net worth overview';
            commit('SET_ERROR', errorMessage);
            throw error;
        } finally {
            commit('SET_LOADING', false);
        }
    },

    async fetchAssetsSummary({ commit }) {
        commit('SET_LOADING', true);
        commit('CLEAR_ERROR');

        try {
            const response = await netWorthService.getAssetsSummary();

            if (response.success) {
                commit('SET_ASSETS_SUMMARY', response.data);
            } else {
                throw new Error(response.message || 'Failed to fetch assets summary');
            }
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message || 'Failed to fetch assets summary';
            commit('SET_ERROR', errorMessage);
            throw error;
        } finally {
            commit('SET_LOADING', false);
        }
    },

    async fetchJointAssets({ commit }) {
        commit('SET_LOADING', true);
        commit('CLEAR_ERROR');

        try {
            const response = await netWorthService.getJointAssets();

            if (response.success) {
                commit('SET_JOINT_ASSETS', response.data);
            } else {
                throw new Error(response.message || 'Failed to fetch joint assets');
            }
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message || 'Failed to fetch joint assets';
            commit('SET_ERROR', errorMessage);
            throw error;
        } finally {
            commit('SET_LOADING', false);
        }
    },

    async fetchAssetsSummaryDetailed({ commit }) {
        commit('SET_LOADING', true);
        commit('CLEAR_ERROR');

        try {
            const response = await netWorthService.getAssetsSummaryDetailed();

            if (response.success) {
                commit('SET_ASSETS_SUMMARY_DETAILED', response.data);
            } else {
                throw new Error(response.message || 'Failed to fetch detailed assets summary');
            }
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message || 'Failed to fetch detailed assets summary';
            commit('SET_ERROR', errorMessage);
            throw error;
        } finally {
            commit('SET_LOADING', false);
        }
    },

    async refreshNetWorth({ commit, dispatch }) {
        commit('SET_LOADING', true);
        commit('CLEAR_ERROR');

        try {
            // First, invalidate cache and recalculate on backend
            const refreshResponse = await netWorthService.refresh();

            if (!refreshResponse.success) {
                throw new Error(refreshResponse.message || 'Failed to refresh net worth');
            }

            // Then fetch the complete overview (includes spouse data)
            // This ensures all data is properly synced
            const overviewResponse = await netWorthService.getOverview();

            if (overviewResponse.success) {
                commit('SET_OVERVIEW', overviewResponse.data);
                // Set spouse data if it exists
                if (overviewResponse.spouse_data) {
                    commit('SET_SPOUSE_OVERVIEW', overviewResponse.spouse_data);
                } else {
                    commit('SET_SPOUSE_OVERVIEW', null);
                }
            } else {
                // Fall back to refresh response data if overview fails
                commit('SET_OVERVIEW', refreshResponse.data);
            }

            // Also refresh related data
            await dispatch('fetchAssetsSummary');
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message || 'Failed to refresh net worth';
            commit('SET_ERROR', errorMessage);
            throw error;
        } finally {
            commit('SET_LOADING', false);
        }
    },

    /**
     * Sync related modules after property/mortgage changes
     * This ensures Estate Planning, Expenses, and Dashboard show updated data
     */
    async syncRelatedModules({ dispatch, rootGetters }) {
        try {
            // Refresh estate data (for IHT calculations and estate planning)
            await dispatch('estate/fetchEstateData', null, { root: true });

            // Recalculate IHT planning
            const user = rootGetters['auth/user'];
            if (user?.marital_status === 'married' || user?.marital_status === 'civil_partnership') {
                await dispatch('estate/calculateIHTPlanning', null, { root: true });
            }
        } catch (error) {
            // Log but don't throw - this is a background sync
            logger.error('Failed to sync related modules', error.message);
        }
    },

    async loadAllData({ dispatch }) {
        await Promise.all([
            dispatch('fetchOverview'),
            dispatch('fetchAssetsSummary'),
        ]);
    },

    // Property actions
    async fetchProperties({ commit }) {
        commit('SET_LOADING', true);
        commit('CLEAR_ERROR');

        try {
            const response = await propertyService.getProperties();

            if (response.success) {
                commit('SET_PROPERTIES', response.data.properties || []);
            } else {
                throw new Error(response.message || 'Failed to fetch properties');
            }
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message || 'Failed to fetch properties';
            commit('SET_ERROR', errorMessage);
            throw error;
        } finally {
            commit('SET_LOADING', false);
        }
    },

    async fetchProperty({ commit }, propertyId) {
        commit('SET_LOADING', true);
        commit('CLEAR_ERROR');

        try {
            const response = await propertyService.getProperty(propertyId);

            if (response.success) {
                commit('SET_SELECTED_PROPERTY', response.data.property);
            } else {
                throw new Error(response.message || 'Failed to fetch property');
            }
        } catch (error) {
            logger.error('fetchProperty failed', error.message);
            const errorMessage = error.response?.data?.message || error.message || 'Failed to fetch property';
            commit('SET_ERROR', errorMessage);
            throw error;
        } finally {
            commit('SET_LOADING', false);
        }
    },

    async createProperty({ commit, dispatch }, propertyData) {
        commit('SET_LOADING', true);
        commit('CLEAR_ERROR');

        try {
            const response = await propertyService.createProperty(propertyData);

            if (response.success) {
                commit('ADD_PROPERTY', response.data);
                // Refresh net worth after adding property
                await dispatch('refreshNetWorth');
                // Sync estate planning, expenses, and other modules
                dispatch('syncRelatedModules');
                return response.data;
            } else {
                throw new Error(response.message || 'Failed to create property');
            }
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message || 'Failed to create property';
            commit('SET_ERROR', errorMessage);
            throw error;
        } finally {
            commit('SET_LOADING', false);
        }
    },

    async updateProperty({ commit, dispatch }, { id, data }) {
        commit('SET_LOADING', true);
        commit('CLEAR_ERROR');

        try {
            const response = await propertyService.updateProperty(id, data);

            if (response.success) {
                commit('UPDATE_PROPERTY', response.data);
                // Refresh net worth after updating property
                await dispatch('refreshNetWorth');
                // Sync estate planning, expenses, and other modules
                dispatch('syncRelatedModules');
                return response.data;
            } else {
                throw new Error(response.message || 'Failed to update property');
            }
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message || 'Failed to update property';
            commit('SET_ERROR', errorMessage);
            throw error;
        } finally {
            commit('SET_LOADING', false);
        }
    },

    async deleteProperty({ commit, dispatch }, propertyId) {
        commit('SET_LOADING', true);
        commit('CLEAR_ERROR');

        try {
            const response = await propertyService.deleteProperty(propertyId);

            if (response.success) {
                commit('REMOVE_PROPERTY', propertyId);
                // Refresh net worth after deleting property
                await dispatch('refreshNetWorth');
                // Sync estate planning, expenses, and other modules
                dispatch('syncRelatedModules');
            } else {
                throw new Error(response.message || 'Failed to delete property');
            }
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message || 'Failed to delete property';
            commit('SET_ERROR', errorMessage);
            throw error;
        } finally {
            commit('SET_LOADING', false);
        }
    },

    // Mortgage actions
    async fetchPropertyMortgages({ commit }, propertyId) {
        commit('SET_LOADING', true);
        commit('CLEAR_ERROR');

        try {
            const response = await propertyService.getPropertyMortgages(propertyId);

            if (response.success) {
                commit('SET_MORTGAGES', response.data.mortgages || []);
            } else {
                throw new Error(response.message || 'Failed to fetch mortgages');
            }
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message || 'Failed to fetch mortgages';
            commit('SET_ERROR', errorMessage);
            throw error;
        } finally {
            commit('SET_LOADING', false);
        }
    },

    async createMortgage({ commit, dispatch }, { propertyId, data }) {
        commit('SET_LOADING', true);
        commit('CLEAR_ERROR');

        try {
            const response = await propertyService.createPropertyMortgage(propertyId, data);

            if (response.success) {
                commit('ADD_MORTGAGE', response.data);
                // Refresh property to update equity
                await dispatch('fetchProperty', propertyId);
                // Refresh net worth
                await dispatch('refreshNetWorth');
                // Sync estate planning, expenses, and other modules
                dispatch('syncRelatedModules');
                return response.data;
            } else {
                throw new Error(response.message || 'Failed to create mortgage');
            }
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message || 'Failed to create mortgage';
            commit('SET_ERROR', errorMessage);
            throw error;
        } finally {
            commit('SET_LOADING', false);
        }
    },

    async updateMortgage({ commit, dispatch }, { id, data, propertyId }) {
        commit('SET_LOADING', true);
        commit('CLEAR_ERROR');

        try {
            const response = await mortgageService.updateMortgage(id, data);

            if (response.success) {
                commit('UPDATE_MORTGAGE', response.data);
                // Refresh property to update equity
                if (propertyId) {
                    await dispatch('fetchProperty', propertyId);
                }
                // Refresh net worth
                await dispatch('refreshNetWorth');
                // Sync estate planning, expenses, and other modules
                dispatch('syncRelatedModules');
                return response.data;
            } else {
                throw new Error(response.message || 'Failed to update mortgage');
            }
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message || 'Failed to update mortgage';
            commit('SET_ERROR', errorMessage);
            throw error;
        } finally {
            commit('SET_LOADING', false);
        }
    },

    async deleteMortgage({ commit, dispatch }, { id, propertyId }) {
        commit('SET_LOADING', true);
        commit('CLEAR_ERROR');

        try {
            const response = await mortgageService.deleteMortgage(id);

            if (response.success) {
                commit('REMOVE_MORTGAGE', id);
                // Refresh property to update equity
                if (propertyId) {
                    await dispatch('fetchProperty', propertyId);
                }
                // Refresh net worth
                await dispatch('refreshNetWorth');
                // Sync estate planning, expenses, and other modules
                dispatch('syncRelatedModules');
            } else {
                throw new Error(response.message || 'Failed to delete mortgage');
            }
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message || 'Failed to delete mortgage';
            commit('SET_ERROR', errorMessage);
            throw error;
        } finally {
            commit('SET_LOADING', false);
        }
    },

    async calculateSDLT({ commit }, data) {
        commit('CLEAR_ERROR');

        try {
            const response = await propertyService.calculateSDLT(data);

            if (response.success) {
                return response.data;
            } else {
                throw new Error(response.message || 'Failed to calculate SDLT');
            }
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message || 'Failed to calculate SDLT';
            commit('SET_ERROR', errorMessage);
            throw error;
        }
    },

    async calculateCGT({ commit }, { propertyId, data }) {
        commit('CLEAR_ERROR');

        try {
            const response = await propertyService.calculateCGT(propertyId, data);

            if (response.success) {
                return response.data;
            } else {
                throw new Error(response.message || 'Failed to calculate CGT');
            }
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message || 'Failed to calculate CGT';
            commit('SET_ERROR', errorMessage);
            throw error;
        }
    },

    async calculateRentalIncomeTax({ commit }, propertyId) {
        commit('CLEAR_ERROR');

        try {
            const response = await propertyService.calculateRentalIncomeTax(propertyId);

            if (response.success) {
                return response.data;
            } else {
                throw new Error(response.message || 'Failed to calculate rental income tax');
            }
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message || 'Failed to calculate rental income tax';
            commit('SET_ERROR', errorMessage);
            throw error;
        }
    },

    async getAmortizationSchedule({ commit }, mortgageId) {
        commit('CLEAR_ERROR');

        try {
            const response = await mortgageService.getAmortizationSchedule(mortgageId);

            if (response.success) {
                return response.data;
            } else {
                throw new Error(response.message || 'Failed to get amortization schedule');
            }
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message || 'Failed to get amortization schedule';
            commit('SET_ERROR', errorMessage);
            throw error;
        }
    },

    async calculateMortgagePayment({ commit }, data) {
        commit('CLEAR_ERROR');

        try {
            const response = await mortgageService.calculatePayment(data);

            if (response.success) {
                return response.data;
            } else {
                throw new Error(response.message || 'Failed to calculate payment');
            }
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message || 'Failed to calculate payment';
            commit('SET_ERROR', errorMessage);
            throw error;
        }
    },

    resetState({ commit }) {
        commit('RESET_STATE');
    },
};

const getters = {
    overview: (state) => state.overview,

    netWorth: (state) => state.overview.netWorth,

    totalAssets: (state) => state.overview.totalAssets,

    totalLiabilities: (state) => state.overview.totalLiabilities,

    assetBreakdown: (state) => {
        const breakdown = state.overview.breakdown;
        const total = state.overview.totalAssets;

        if (!breakdown || total === 0) {
            return [];
        }

        return Object.entries(breakdown).map(([key, value]) => ({
            type: key,
            value: value,
            percentage: ((value / total) * 100).toFixed(2),
        }));
    },

    hasAssets: (state) => state.overview.totalAssets > 0,

    assetCounts: (state) => ({
        pensions: state.assetsSummary.pensions?.count || 0,
        property: state.assetsSummary.property?.count || 0,
        investments: state.assetsSummary.investments?.count || 0,
        cash: state.assetsSummary.cash?.count || 0,
        business: state.assetsSummary.business?.count || 0,
        chattels: state.assetsSummary.chattels?.count || 0,
    }),

    totalAssetCount: (state, getters) => {
        const counts = getters.assetCounts;
        return Object.values(counts).reduce((sum, count) => sum + count, 0);
    },

    formattedNetWorth: (state) => {
        return new Intl.NumberFormat('en-GB', {
            style: 'currency',
            currency: 'GBP',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(state.overview.netWorth);
    },

    formattedAssets: (state) => {
        return new Intl.NumberFormat('en-GB', {
            style: 'currency',
            currency: 'GBP',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(state.overview.totalAssets);
    },

    formattedLiabilities: (state) => {
        return new Intl.NumberFormat('en-GB', {
            style: 'currency',
            currency: 'GBP',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(state.overview.totalLiabilities);
    },
};

export default {
    namespaced: true,
    state,
    mutations,
    actions,
    getters,
};
