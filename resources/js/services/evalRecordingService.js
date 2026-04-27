import api from './api';

const evalRecordingService = {
    async listSessions(limit = 200) {
        const response = await api.get('/admin/eval-recordings', { params: { limit } });
        return response.data;
    },

    async getSession(sessionId) {
        const response = await api.get(`/admin/eval-recordings/${sessionId}`);
        return response.data;
    },

    async getRawFixture(runId) {
        const response = await api.get(`/admin/eval-recordings/runs/${runId}/raw`);
        return response.data;
    },

    async getSystemPrompt(runId) {
        const response = await api.get(`/admin/eval-recordings/runs/${runId}/system-prompt`);
        return response.data;
    },
};

export default evalRecordingService;
