import estateService from '@/services/estateService';

const state = {
    assets: [],
    investmentAccounts: [], // Investment accounts from Investment module
    liabilities: [],
    gifts: [],
    trusts: [],
    lpas: [],
    lpaLoading: false,
    ihtProfile: null,
    netWorth: null,
    cashFlow: null,
    analysis: null,
    recommendations: [],
    secondDeathPlanning: null, // Second death IHT planning data
    willInfo: null, // Will information from IHT calculation
    lifeEvents: [],
    lifeEventImpact: null,
    canProceed: true,
    readinessChecks: null,
    loading: false,
    error: null,
};

const getters = {
    assets: (state) => state.assets,
    investmentAccounts: (state) => state.investmentAccounts,
    liabilities: (state) => state.liabilities,

    // All assets (manual + investment accounts)
    allAssets: (state) => {
        const manualAssets = Array.isArray(state.assets) ? state.assets : [];
        const investmentAssets = Array.isArray(state.investmentAccounts) ? state.investmentAccounts : [];
        return [...manualAssets, ...investmentAssets].filter(asset => asset != null);
    },

    // Total assets value (including investment accounts)
    totalAssets: (state, getters) => {
        return getters.allAssets.reduce((sum, asset) => {
            if (!asset || asset.current_value === undefined) return sum;
            return sum + parseFloat(asset.current_value || 0);
        }, 0);
    },

    // Total liabilities value
    totalLiabilities: (state) => {
        return state.liabilities.reduce((sum, liability) => sum + parseFloat(liability.current_balance || 0), 0);
    },

    // Net worth value
    netWorthValue: (state, getters) => {
        return getters.totalAssets - getters.totalLiabilities;
    },

    // IHT liability from analysis
    ihtLiability: (state) => {
        // NEW: For unified IHT calculation with iht_summary structure
        if (state.secondDeathPlanning?.iht_summary?.current?.iht_liability !== undefined) {
            return state.secondDeathPlanning.iht_summary.current.iht_liability;
        }
        // OLD: For married users with second death analysis, use CURRENT (now) IHT liability
        if (state.secondDeathPlanning?.second_death_analysis?.current_iht_calculation?.iht_liability !== undefined) {
            return state.secondDeathPlanning.second_death_analysis.current_iht_calculation.iht_liability;
        }
        // For married users without linked spouse, use user_iht_calculation
        if (state.secondDeathPlanning?.user_iht_calculation?.iht_liability !== undefined) {
            return state.secondDeathPlanning.user_iht_calculation.iht_liability;
        }
        // Otherwise use standard analysis
        return state.analysis?.iht_liability || 0;
    },

    // Gifts made within last 7 years (PETs)
    giftsWithin7Years: (state) => {
        const sevenYearsAgo = new Date();
        sevenYearsAgo.setFullYear(sevenYearsAgo.getFullYear() - 7);

        return state.gifts.filter(gift => {
            const giftDate = new Date(gift.gift_date);
            return giftDate >= sevenYearsAgo;
        });
    },

    // Total value of gifts within 7 years
    giftsWithin7YearsValue: (state, getters) => {
        return getters.giftsWithin7Years.reduce((sum, gift) => sum + parseFloat(gift.gift_value || 0), 0);
    },

    // Assets by type (including investment accounts)
    assetsByType: (state, getters) => {
        const byType = {};
        getters.allAssets.forEach(asset => {
            const type = asset.asset_type || 'Other';
            if (!byType[type]) {
                byType[type] = [];
            }
            byType[type].push(asset);
        });
        return byType;
    },

    // Liabilities by type
    liabilitiesByType: (state) => {
        const byType = {};
        state.liabilities.forEach(liability => {
            const type = liability.liability_type || 'Other';
            if (!byType[type]) {
                byType[type] = [];
            }
            byType[type].push(liability);
        });
        return byType;
    },

    // IHT exempt assets (including investment accounts)
    ihtExemptAssets: (state, getters) => {
        return getters.allAssets.filter(asset => asset.is_iht_exempt);
    },

    // High priority recommendations
    priorityRecommendations: (state) => {
        return state.recommendations.filter(rec => rec.priority === 'high');
    },

    // Alias for netWorthValue (for Dashboard compatibility)
    netWorth: (state, getters) => {
        return getters.netWorthValue;
    },

    // Probate readiness score (0-100)
    probateReadiness: (state) => {
        // Calculate based on having key documents and estate plan
        let score = 0;

        // Has assets recorded (+40)
        if (state.assets.length > 0) score += 40;

        // Has IHT analysis (+30)
        if (state.analysis) score += 30;

        // Has addressed high priority recommendations (+30)
        const highPriority = state.recommendations.filter(rec => rec.priority === 'high');
        if (highPriority.length === 0) score += 30;

        return score;
    },

    // Taxable estate value (AFTER allowances - NRB/RNRB)
    // This is what's actually subject to IHT at 40%
    taxableEstate: (state, getters) => {
        // NEW: For unified IHT calculation with iht_summary structure
        if (state.secondDeathPlanning?.iht_summary?.current?.taxable_estate !== undefined) {
            return state.secondDeathPlanning.iht_summary.current.taxable_estate;
        }
        // OLD: For married users with second death analysis, use CURRENT (now) taxable estate
        if (state.secondDeathPlanning?.second_death_analysis?.current_iht_calculation?.taxable_estate !== undefined) {
            return state.secondDeathPlanning.second_death_analysis.current_iht_calculation.taxable_estate;
        }
        // For married users without linked spouse, use user_iht_calculation
        if (state.secondDeathPlanning?.user_iht_calculation?.taxable_estate !== undefined) {
            return state.secondDeathPlanning.user_iht_calculation.taxable_estate;
        }
        // Otherwise use standard analysis
        if (state.analysis?.taxable_estate !== undefined) {
            return state.analysis.taxable_estate;
        }
        // Fallback if no analysis: return 0 (need IHT calc to get proper taxable estate)
        return 0;
    },

    // Gross estate value (BEFORE allowances)
    // This is total assets minus liabilities
    grossEstate: (state, getters) => {
        // Use net_estate_value from analysis if available
        if (state.analysis && state.analysis.net_estate_value !== undefined) {
            return state.analysis.net_estate_value;
        }

        // Fallback: calculate from store assets minus liabilities
        const totalAssets = getters.totalAssets;
        const totalLiabilities = getters.totalLiabilities;
        return totalAssets - totalLiabilities;
    },

    // Future projected age at death (from mortality tables)
    futureDeathAge: (state) => {
        // NEW: Use unified iht_summary structure
        if (state.secondDeathPlanning?.iht_summary?.projected?.estimated_age_at_death) {
            return state.secondDeathPlanning.iht_summary.projected.estimated_age_at_death;
        }
        // OLD: Fallback to second_death_analysis structure
        return state.secondDeathPlanning?.second_death_analysis?.second_death?.estimated_age_at_death || null;
    },

    // Future projected taxable estate (at mortality age)
    futureTaxableEstate: (state) => {
        // NEW: Use unified iht_summary structure
        if (state.secondDeathPlanning?.iht_summary?.projected?.taxable_estate !== undefined) {
            return state.secondDeathPlanning.iht_summary.projected.taxable_estate;
        }
        // OLD: Fallback to second_death_analysis structure
        return state.secondDeathPlanning?.second_death_analysis?.iht_calculation?.taxable_estate || null;
    },

    // Future projected IHT liability (at mortality age)
    futureIHTLiability: (state) => {
        // NEW: Use unified iht_summary structure
        if (state.secondDeathPlanning?.iht_summary?.projected?.iht_liability !== undefined) {
            return state.secondDeathPlanning.iht_summary.projected.iht_liability;
        }
        // OLD: Fallback to second_death_analysis structure
        return state.secondDeathPlanning?.second_death_analysis?.iht_calculation?.iht_liability || null;
    },

    // Life events relevant to estate module
    upcomingLifeEvents: (state) => state.lifeEvents,
    lifeEventNetImpact: (state) => state.lifeEventImpact?.net_impact || 0,

    canProceed: (state) => state.canProceed,
    readinessChecks: (state) => state.readinessChecks,

    // Lasting Powers of Attorney
    lpas: (state) => state.lpas,
    lpaLoading: (state) => state.lpaLoading,
    propertyFinancialLpas: (state) => state.lpas.filter(l => l.lpa_type === 'property_financial'),
    healthWelfareLpas: (state) => state.lpas.filter(l => l.lpa_type === 'health_welfare'),
    hasRegisteredLpa: (state) => state.lpas.some(l => l.status === 'registered'),

    loading: (state) => state.loading,
    error: (state) => state.error,
};

const actions = {
    // Fetch all estate data
    async fetchEstateData({ commit }) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await estateService.getEstateData();
            commit('setAssets', response.data.assets || []);
            commit('setInvestmentAccounts', response.data.investment_accounts || []);
            commit('setLiabilities', response.data.liabilities || []);
            commit('setGifts', response.data.gifts || []);
            commit('setTrusts', response.data.trusts || []);
            commit('setIHTProfile', response.data.iht_profile);
            commit('setLifeEvents', response.data.life_events || []);
            commit('setLifeEventImpact', response.data.life_event_impact || null);
            if (response.data.will_info) {
                commit('setWillInfo', response.data.will_info);
            }
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to fetch estate data';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    // Analyse estate
    async analyseEstate({ commit }, data) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await estateService.analyzeEstate(data);
            const responseData = response.data?.data || response.data;

            // Guard: handle can_proceed: false
            if (responseData?.can_proceed === false) {
                commit('SET_CAN_PROCEED', false);
                commit('SET_READINESS_CHECKS', responseData?.readiness_checks || null);
                commit('setAnalysis', null);
                return response;
            }

            commit('SET_CAN_PROCEED', true);
            commit('SET_READINESS_CHECKS', null);
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

    // Calculate IHT
    async calculateIHT({ commit }, data) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await estateService.calculateIHT(data);
            // Response has iht_summary structure - use same state as married users
            commit('setSecondDeathPlanning', response);
            // Extract will info from response
            if (response?.will_info) {
                commit('setWillInfo', response.will_info);
            }
            return response;
        } catch (error) {
            const errorMessage = error.message || 'IHT calculation failed';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    // Fetch net worth
    async fetchNetWorth({ commit }) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await estateService.getNetWorth();
            commit('setNetWorth', response.data);
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to fetch net worth';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    // Fetch cash flow
    async fetchCashFlow({ commit }, taxYear) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await estateService.getCashFlow(taxYear);
            // response is already response.data from the service
            // which is { success: true, data: {...} }
            commit('setCashFlow', response.data);
            return response;
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message || 'Failed to fetch cash flow';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    // Asset actions
    async createAsset({ commit }, assetData) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await estateService.createAsset(assetData);
            commit('addAsset', response.data.data);
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to create asset';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    async updateAsset({ commit }, { id, assetData }) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await estateService.updateAsset(id, assetData);
            commit('updateAsset', response.data.data);
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to update asset';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    async deleteAsset({ commit }, id) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await estateService.deleteAsset(id);
            commit('removeAsset', id);
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to delete asset';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    // Liability actions
    async createLiability({ commit, dispatch }, liabilityData) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await estateService.createLiability(liabilityData);
            // Don't use addLiability here — the component re-fetches after save
            // which gives us clean data from the API with correct resource shape
            await dispatch('netWorth/refreshNetWorth', null, { root: true });
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to create liability';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    async updateLiability({ commit, dispatch }, { id, liabilityData }) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await estateService.updateLiability(id, liabilityData);
            commit('updateLiability', response.data.data);
            // Refresh net worth after updating liability
            await dispatch('netWorth/refreshNetWorth', null, { root: true });
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to update liability';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    async deleteLiability({ commit, dispatch }, id) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await estateService.deleteLiability(id);
            commit('removeLiability', id);
            // Refresh net worth after deleting liability
            await dispatch('netWorth/refreshNetWorth', null, { root: true });
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to delete liability';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    // Gift actions
    async createGift({ commit }, giftData) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await estateService.createGift(giftData);
            commit('addGift', response.data);
            return response;
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message || 'Failed to create gift';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    async updateGift({ commit }, { id, giftData }) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await estateService.updateGift(id, giftData);
            commit('updateGift', response.data);
            return response;
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message || 'Failed to update gift';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    async deleteGift({ commit }, id) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await estateService.deleteGift(id);
            commit('removeGift', id);
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to delete gift';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    // IHT Planning action (covers single and married couples)
    async calculateIHTPlanning({ commit }) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await estateService.calculateIHTPlanning();
            commit('setSecondDeathPlanning', response);
            // Extract will info from response
            if (response?.will_info) {
                commit('setWillInfo', response.will_info);
            }

            // If spouse is not linked, the backend returns user_iht_calculation
            // Store this in analysis state so the dashboard getters can access it
            if (response?.requires_spouse_link && response?.user_iht_calculation) {
                commit('setAnalysis', response.user_iht_calculation);
            }

            return response;
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message || 'Failed to calculate IHT planning';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    // Save will information
    async saveWill({ commit }, willData) {
        try {
            const response = await estateService.saveWill(willData);
            commit('setWillInfo', {
                has_will: willData.has_will,
                will_answered: true,
            });
            return response;
        } catch (error) {
            throw error;
        }
    },

    // Trust actions
    async fetchTrusts({ commit }) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await estateService.getTrusts();
            commit('setTrusts', response.data || []);
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to fetch trusts';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    async createTrust({ commit }, trustData) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await estateService.createTrust(trustData);
            commit('addTrust', response.data);
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to create trust';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    async updateTrust({ commit }, { id, data }) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await estateService.updateTrust(id, data);
            commit('updateTrust', response.data);
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to update trust';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    async removeTrust({ commit }, id) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await estateService.deleteTrust(id);
            commit('removeTrust', id);
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to delete trust';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    // Lasting Power of Attorney actions
    async fetchLpas({ commit }) {
        commit('setLpaLoading', true);
        commit('setError', null);

        try {
            const response = await estateService.getLpas();
            commit('setLpas', response.data || []);
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to fetch Lasting Powers of Attorney';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLpaLoading', false);
        }
    },

    async createLpa({ commit }, data) {
        commit('setLpaLoading', true);
        commit('setError', null);

        try {
            const response = await estateService.createLpa(data);
            commit('addLpa', response.data);
            return response;
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message || 'Failed to create Lasting Power of Attorney';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLpaLoading', false);
        }
    },

    async updateLpa({ commit }, { id, data }) {
        commit('setLpaLoading', true);
        commit('setError', null);

        try {
            const response = await estateService.updateLpa(id, data);
            commit('updateLpa', response.data);
            return response;
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message || 'Failed to update Lasting Power of Attorney';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLpaLoading', false);
        }
    },

    async removeLpa({ commit }, id) {
        commit('setLpaLoading', true);
        commit('setError', null);

        try {
            const response = await estateService.deleteLpa(id);
            commit('removeLpa', id);
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to delete Lasting Power of Attorney';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLpaLoading', false);
        }
    },
};

const mutations = {
    setAssets(state, assets) {
        state.assets = assets;
    },

    setInvestmentAccounts(state, investmentAccounts) {
        state.investmentAccounts = investmentAccounts;
    },

    setLiabilities(state, liabilities) {
        state.liabilities = liabilities;
    },

    setGifts(state, gifts) {
        state.gifts = gifts;
    },

    setTrusts(state, trusts) {
        state.trusts = trusts;
    },

    setIHTProfile(state, profile) {
        state.ihtProfile = profile;
    },

    setLifeEvents(state, events) {
        state.lifeEvents = events;
    },

    setLifeEventImpact(state, impact) {
        state.lifeEventImpact = impact;
    },

    setNetWorth(state, netWorth) {
        state.netWorth = netWorth;
    },

    setCashFlow(state, cashFlow) {
        state.cashFlow = cashFlow;
    },

    setAnalysis(state, analysis) {
        state.analysis = analysis;
    },

    setRecommendations(state, recommendations) {
        state.recommendations = recommendations;
    },

    setSecondDeathPlanning(state, secondDeathPlanning) {
        state.secondDeathPlanning = secondDeathPlanning;
    },

    setWillInfo(state, willInfo) {
        state.willInfo = willInfo;
    },

    addAsset(state, asset) {
        state.assets.push(asset);
    },

    updateAsset(state, asset) {
        const index = state.assets.findIndex(a => a.id === asset.id);
        if (index !== -1) {
            state.assets.splice(index, 1, asset);
        }
    },

    removeAsset(state, id) {
        const index = state.assets.findIndex(a => a.id === id);
        if (index !== -1) {
            state.assets.splice(index, 1);
        }
    },

    addLiability(state, liability) {
        state.liabilities.push(liability);
    },

    updateLiability(state, liability) {
        const index = state.liabilities.findIndex(l => l.id === liability.id);
        if (index !== -1) {
            state.liabilities.splice(index, 1, liability);
        }
    },

    removeLiability(state, id) {
        const index = state.liabilities.findIndex(l => l.id === id);
        if (index !== -1) {
            state.liabilities.splice(index, 1);
        }
    },

    addGift(state, gift) {
        state.gifts.push(gift);
    },

    updateGift(state, gift) {
        const index = state.gifts.findIndex(g => g.id === gift.id);
        if (index !== -1) {
            state.gifts.splice(index, 1, gift);
        }
    },

    removeGift(state, id) {
        const index = state.gifts.findIndex(g => g.id === id);
        if (index !== -1) {
            state.gifts.splice(index, 1);
        }
    },

    addTrust(state, trust) {
        state.trusts.push(trust);
    },

    updateTrust(state, trust) {
        const index = state.trusts.findIndex(t => t.id === trust.id);
        if (index !== -1) {
            state.trusts.splice(index, 1, trust);
        }
    },

    removeTrust(state, id) {
        const index = state.trusts.findIndex(t => t.id === id);
        if (index !== -1) {
            state.trusts.splice(index, 1);
        }
    },

    setLpas(state, lpas) {
        state.lpas = lpas;
    },

    setLpaLoading(state, loading) {
        state.lpaLoading = loading;
    },

    addLpa(state, lpa) {
        state.lpas.push(lpa);
    },

    updateLpa(state, lpa) {
        const index = state.lpas.findIndex(l => l.id === lpa.id);
        if (index !== -1) {
            state.lpas.splice(index, 1, lpa);
        }
    },

    removeLpa(state, id) {
        const index = state.lpas.findIndex(l => l.id === id);
        if (index !== -1) {
            state.lpas.splice(index, 1);
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
