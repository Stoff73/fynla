import netWorthService from '../../services/netWorthService';
import propertyService from '../../services/propertyService';
import mortgageService from '../../services/mortgageService';

const state = {
    overview: {
        totalAssets: 0,
        totalLiabilities: 0,
        netWorth: 0,
        breakdown: {},
        liabilitiesBreakdown: {},
        asOfDate: null,
    },
    spouseOverview: null,
    trend: [],
    assetsSummary: {
        pensions: { count: 0, total_value: 0, breakdown: { dc: 0, db: 0, state: 0 } },
        property: { count: 0, total_value: 0 },
        investments: { count: 0, total_value: 0 },
        cash: { count: 0, total_value: 0 },
        business: { count: 0, total_value: 0 },
        chattels: { count: 0, total_value: 0 },
    },
    jointAssets: [],
    properties: [],
    selectedProperty: null,
    mortgages: [],
    selectedMortgage: null,
    loading: false,
    error: null,

    // Preview mode state
    isPreviewMode: false,
    previewData: null,
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
        };
    },

    SET_SPOUSE_OVERVIEW(state, spouseData) {
        state.spouseOverview = spouseData;
    },

    SET_TREND(state, trend) {
        state.trend = trend;
    },

    SET_ASSETS_SUMMARY(state, summary) {
        state.assetsSummary = summary;
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

    RESET_STATE(state) {
        state.overview = {
            totalAssets: 0,
            totalLiabilities: 0,
            netWorth: 0,
            breakdown: {},
            liabilitiesBreakdown: {},
            asOfDate: null,
        };
        state.spouseOverview = null;
        state.trend = [];
        state.assetsSummary = {
            pensions: { count: 0, total_value: 0, breakdown: { dc: 0, db: 0, state: 0 } },
            property: { count: 0, total_value: 0 },
            investments: { count: 0, total_value: 0 },
            cash: { count: 0, total_value: 0 },
            business: { count: 0, total_value: 0 },
            chattels: { count: 0, total_value: 0 },
        };
        state.jointAssets = [];
        state.properties = [];
        state.selectedProperty = null;
        state.mortgages = [];
        state.selectedMortgage = null;
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

    SET_SELECTED_MORTGAGE(state, mortgage) {
        state.selectedMortgage = mortgage;
    },

    ADD_MORTGAGE(state, mortgage) {
        state.mortgages.push(mortgage);
    },

    UPDATE_MORTGAGE(state, updatedMortgage) {
        const index = state.mortgages.findIndex(m => m.id === updatedMortgage.id);
        if (index !== -1) {
            state.mortgages.splice(index, 1, updatedMortgage);
        }
        if (state.selectedMortgage && state.selectedMortgage.id === updatedMortgage.id) {
            state.selectedMortgage = updatedMortgage;
        }
    },

    REMOVE_MORTGAGE(state, mortgageId) {
        state.mortgages = state.mortgages.filter(m => m.id !== mortgageId);
        if (state.selectedMortgage && state.selectedMortgage.id === mortgageId) {
            state.selectedMortgage = null;
        }
    },

    // Preview mode mutations
    SET_PREVIEW_MODE(state, { isPreview, data }) {
        state.isPreviewMode = isPreview;
        state.previewData = data;

        // If entering preview mode with data, populate state from preview data
        if (isPreview && data) {
            // Set properties from preview data
            if (data.properties) {
                state.properties = data.properties;
            }
            // Set mortgages from preview data
            if (data.mortgages) {
                state.mortgages = data.mortgages;
            }
            // Calculate overview from preview data
            const totalAssets = calculatePreviewTotalAssets(data);
            const totalLiabilities = calculatePreviewTotalLiabilities(data);
            state.overview = {
                totalAssets,
                totalLiabilities,
                netWorth: totalAssets - totalLiabilities,
                breakdown: calculatePreviewAssetBreakdown(data),
                liabilitiesBreakdown: calculatePreviewLiabilityBreakdown(data),
                asOfDate: new Date().toISOString(),
            };
            // Set assets summary
            state.assetsSummary = calculatePreviewAssetsSummary(data);
        }
    },

    SET_PREVIEW_CALCULATION(state, result) {
        if (state.isPreviewMode && result) {
            state.overview = {
                totalAssets: result.total_assets || 0,
                totalLiabilities: result.total_liabilities || 0,
                netWorth: result.net_worth || 0,
                breakdown: result.breakdown || {},
                liabilitiesBreakdown: result.liabilities_breakdown || {},
                asOfDate: new Date().toISOString(),
            };
        }
    },
};

// Helper functions for preview calculations
function calculatePreviewTotalAssets(data) {
    let total = 0;

    // Properties
    if (data.properties) {
        total += data.properties.reduce((sum, p) => {
            const value = parseFloat(p.current_value || 0);
            const ownership = parseFloat(p.ownership_percentage || 100) / 100;
            return sum + (value * ownership);
        }, 0);
    }

    // Savings accounts
    if (data.savings_accounts) {
        total += data.savings_accounts.reduce((sum, s) => sum + parseFloat(s.current_balance || 0), 0);
    }

    // Investment accounts
    if (data.investment_accounts) {
        total += data.investment_accounts.reduce((sum, i) => sum + parseFloat(i.current_value || 0), 0);
    }

    // DC Pensions
    if (data.dc_pensions) {
        total += data.dc_pensions.reduce((sum, p) => sum + parseFloat(p.current_fund_value || 0), 0);
    }

    // DB Pensions (20x annual pension as proxy value)
    if (data.db_pensions) {
        total += data.db_pensions.reduce((sum, p) => {
            const annual = parseFloat(p.accrued_annual_pension || 0);
            const lumpSum = parseFloat(p.lump_sum_entitlement || 0);
            return sum + (annual * 20) + lumpSum;
        }, 0);
    }

    return total;
}

function calculatePreviewTotalLiabilities(data) {
    let total = 0;

    // Mortgages
    if (data.mortgages) {
        total += data.mortgages.reduce((sum, m) => {
            const balance = parseFloat(m.outstanding_balance || 0);
            const ownership = parseFloat(m.ownership_percentage || 100) / 100;
            return sum + (balance * ownership);
        }, 0);
    }

    // Other liabilities
    if (data.liabilities) {
        total += data.liabilities.reduce((sum, l) => sum + parseFloat(l.current_balance || 0), 0);
    }

    return total;
}

function calculatePreviewAssetBreakdown(data) {
    const breakdown = {};

    if (data.properties?.length > 0) {
        breakdown.property = data.properties.reduce((sum, p) => {
            const value = parseFloat(p.current_value || 0);
            const ownership = parseFloat(p.ownership_percentage || 100) / 100;
            return sum + (value * ownership);
        }, 0);
    }

    if (data.savings_accounts?.length > 0) {
        breakdown.cash = data.savings_accounts.reduce((sum, s) => sum + parseFloat(s.current_balance || 0), 0);
    }

    if (data.investment_accounts?.length > 0) {
        breakdown.investments = data.investment_accounts.reduce((sum, i) => sum + parseFloat(i.current_value || 0), 0);
    }

    const dcTotal = data.dc_pensions?.reduce((sum, p) => sum + parseFloat(p.current_fund_value || 0), 0) || 0;
    const dbTotal = data.db_pensions?.reduce((sum, p) => {
        const annual = parseFloat(p.accrued_annual_pension || 0);
        const lumpSum = parseFloat(p.lump_sum_entitlement || 0);
        return sum + (annual * 20) + lumpSum;
    }, 0) || 0;

    if (dcTotal > 0 || dbTotal > 0) {
        breakdown.pensions = dcTotal + dbTotal;
    }

    return breakdown;
}

function calculatePreviewLiabilityBreakdown(data) {
    const breakdown = {};

    if (data.mortgages?.length > 0) {
        breakdown.mortgages = data.mortgages.reduce((sum, m) => {
            const balance = parseFloat(m.outstanding_balance || 0);
            const ownership = parseFloat(m.ownership_percentage || 100) / 100;
            return sum + (balance * ownership);
        }, 0);
    }

    if (data.liabilities?.length > 0) {
        data.liabilities.forEach(l => {
            const type = l.liability_type || 'other';
            if (!breakdown[type]) {
                breakdown[type] = 0;
            }
            breakdown[type] += parseFloat(l.current_balance || 0);
        });
    }

    return breakdown;
}

function calculatePreviewAssetsSummary(data) {
    return {
        pensions: {
            count: (data.dc_pensions?.length || 0) + (data.db_pensions?.length || 0) + (data.state_pension ? 1 : 0),
            total_value: (data.dc_pensions?.reduce((sum, p) => sum + parseFloat(p.current_fund_value || 0), 0) || 0) +
                (data.db_pensions?.reduce((sum, p) => (parseFloat(p.accrued_annual_pension || 0) * 20) + parseFloat(p.lump_sum_entitlement || 0), 0) || 0),
            breakdown: {
                dc: data.dc_pensions?.reduce((sum, p) => sum + parseFloat(p.current_fund_value || 0), 0) || 0,
                db: data.db_pensions?.reduce((sum, p) => (parseFloat(p.accrued_annual_pension || 0) * 20) + parseFloat(p.lump_sum_entitlement || 0), 0) || 0,
                state: 0,
            },
        },
        property: {
            count: data.properties?.length || 0,
            total_value: data.properties?.reduce((sum, p) => {
                const value = parseFloat(p.current_value || 0);
                const ownership = parseFloat(p.ownership_percentage || 100) / 100;
                return sum + (value * ownership);
            }, 0) || 0,
        },
        investments: {
            count: data.investment_accounts?.length || 0,
            total_value: data.investment_accounts?.reduce((sum, i) => sum + parseFloat(i.current_value || 0), 0) || 0,
        },
        cash: {
            count: data.savings_accounts?.length || 0,
            total_value: data.savings_accounts?.reduce((sum, s) => sum + parseFloat(s.current_balance || 0), 0) || 0,
        },
        business: {
            count: data.business_interests?.length || 0,
            total_value: data.business_interests?.reduce((sum, b) => {
                const value = parseFloat(b.current_value || 0);
                const ownership = parseFloat(b.ownership_percentage || 100) / 100;
                return sum + (value * ownership);
            }, 0) || 0,
        },
        chattels: {
            count: data.chattels?.length || 0,
            total_value: data.chattels?.reduce((sum, c) => {
                const value = parseFloat(c.current_value || 0);
                const ownership = parseFloat(c.ownership_percentage || 100) / 100;
                return sum + (value * ownership);
            }, 0) || 0,
        },
    };
}

const actions = {
    async fetchOverview({ commit, state }) {
        // Skip API call if in preview mode - data is already loaded
        if (state.isPreviewMode) {
            console.log('[netWorth] Skipping fetchOverview - preview mode active');
            return;
        }

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

    async fetchTrend({ commit, state }, months = 12) {
        // Skip API call if in preview mode
        if (state.isPreviewMode) {
            console.log('[netWorth] Skipping fetchTrend - preview mode active');
            return;
        }

        commit('SET_LOADING', true);
        commit('CLEAR_ERROR');

        try {
            const response = await netWorthService.getTrend(months);

            if (response.success) {
                commit('SET_TREND', response.data);
            } else {
                throw new Error(response.message || 'Failed to fetch net worth trend');
            }
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message || 'Failed to fetch net worth trend';
            commit('SET_ERROR', errorMessage);
            throw error;
        } finally {
            commit('SET_LOADING', false);
        }
    },

    async fetchAssetsSummary({ commit, state }) {
        // Skip API call if in preview mode
        if (state.isPreviewMode) {
            console.log('[netWorth] Skipping fetchAssetsSummary - preview mode active');
            return;
        }

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

    async fetchJointAssets({ commit, state }) {
        // Skip API call if in preview mode
        if (state.isPreviewMode) {
            console.log('[netWorth] Skipping fetchJointAssets - preview mode active');
            return;
        }

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

    async refreshNetWorth({ commit, dispatch }) {
        commit('SET_LOADING', true);
        commit('CLEAR_ERROR');

        try {
            const response = await netWorthService.refresh();

            if (response.success) {
                commit('SET_OVERVIEW', response.data);
                // Also refresh related data
                await Promise.all([
                    dispatch('fetchAssetsSummary'),
                    dispatch('fetchTrend'),
                ]);
            } else {
                throw new Error(response.message || 'Failed to refresh net worth');
            }
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message || 'Failed to refresh net worth';
            commit('SET_ERROR', errorMessage);
            throw error;
        } finally {
            commit('SET_LOADING', false);
        }
    },

    async loadAllData({ dispatch }) {
        await Promise.all([
            dispatch('fetchOverview'),
            dispatch('fetchTrend'),
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
            console.error('fetchProperty error:', error);
            console.error('error.response:', error.response);
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

    async fetchMortgage({ commit }, mortgageId) {
        commit('SET_LOADING', true);
        commit('CLEAR_ERROR');

        try {
            const response = await mortgageService.getMortgage(mortgageId);

            if (response.success) {
                commit('SET_SELECTED_MORTGAGE', response.data);
            } else {
                throw new Error(response.message || 'Failed to fetch mortgage');
            }
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message || 'Failed to fetch mortgage';
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

    // Preview mode action
    setPreviewMode({ commit }, { isPreview, data }) {
        commit('SET_PREVIEW_MODE', { isPreview, data });
    },
};

const getters = {
    // Preview mode getters
    isPreviewMode: (state) => state.isPreviewMode,
    previewData: (state) => state.previewData,

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

    trendData: (state) => state.trend.map(item => ({
        date: item.date,
        month: item.month,
        value: item.net_worth,
    })),

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
