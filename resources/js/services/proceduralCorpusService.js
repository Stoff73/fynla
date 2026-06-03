import api from './api';

const proceduralCorpusService = {
    async getCorpus() {
        const response = await api.get('/admin/procedural-corpus');
        return response.data;
    },

    async getProcedure(procedureId) {
        const response = await api.get(`/admin/procedural-corpus/${encodeURIComponent(procedureId)}`);
        return response.data;
    },
};

export default proceduralCorpusService;
