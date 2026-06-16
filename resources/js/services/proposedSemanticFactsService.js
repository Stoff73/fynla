import api from './api';

const proposedSemanticFactsService = {
    async getPending() {
        const response = await api.get('/admin/semantic-facts');
        return response.data;
    },

    async review(factId, action) {
        const response = await api.patch(`/admin/semantic-facts/${factId}`, { action });
        return response.data;
    },
};

export default proposedSemanticFactsService;
