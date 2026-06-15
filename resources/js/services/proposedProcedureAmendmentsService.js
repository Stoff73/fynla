import api from './api';

const proposedProcedureAmendmentsService = {
    async getPending() {
        const response = await api.get('/admin/procedure-amendments');
        return response.data;
    },

    async review(amendmentId, action) {
        const response = await api.patch(`/admin/procedure-amendments/${amendmentId}`, { action });
        return response.data;
    },
};

export default proposedProcedureAmendmentsService;
