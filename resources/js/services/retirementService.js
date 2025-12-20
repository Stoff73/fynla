import api from './api';

const API_BASE = '/retirement';

export default {
    /**
     * Get all retirement data for the authenticated user
     */
    async getRetirementData() {
        const response = await api.get(API_BASE);
        return response.data;
    },

    /**
     * Run retirement analysis
     */
    async analyzeRetirement(data = {}) {
        const response = await api.post(`${API_BASE}/analyze`, data);
        return response.data;
    },

    /**
     * Get retirement recommendations
     */
    async getRecommendations() {
        const response = await api.get(`${API_BASE}/recommendations`);
        return response.data;
    },

    /**
     * Run what-if scenario
     */
    async runScenario(scenarioData) {
        const response = await api.post(`${API_BASE}/scenarios`, scenarioData);
        return response.data;
    },

    /**
     * Get annual allowance status for a tax year
     */
    async getAnnualAllowance(taxYear) {
        const response = await api.get(`${API_BASE}/annual-allowance/${taxYear}`);
        return response.data;
    },

    /**
     * Get retirement projections (Monte Carlo + income drawdown)
     */
    async getProjections() {
        const response = await api.get(`${API_BASE}/projections`);
        return response.data;
    },

    // DC Pension CRUD operations
    async createDCPension(pensionData) {
        const response = await api.post(`${API_BASE}/pensions/dc`, pensionData);
        return response.data;
    },

    async updateDCPension(id, pensionData) {
        const response = await api.put(`${API_BASE}/pensions/dc/${id}`, pensionData);
        return response.data;
    },

    async deleteDCPension(id) {
        const response = await api.delete(`${API_BASE}/pensions/dc/${id}`);
        return response.data;
    },

    // DB Pension CRUD operations
    async createDBPension(pensionData) {
        const response = await api.post(`${API_BASE}/pensions/db`, pensionData);
        return response.data;
    },

    async updateDBPension(id, pensionData) {
        const response = await api.put(`${API_BASE}/pensions/db/${id}`, pensionData);
        return response.data;
    },

    async deleteDBPension(id) {
        const response = await api.delete(`${API_BASE}/pensions/db/${id}`);
        return response.data;
    },

    // State Pension
    async updateStatePension(data) {
        const response = await api.post(`${API_BASE}/state-pension`, data);
        return response.data;
    },
};
