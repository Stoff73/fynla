import api from './api';

const aiAuditService = {
    async getUsers(search = '', page = 1) {
        const response = await api.get('/admin/ai-audit/users', {
            params: { search, page },
        });
        return response.data;
    },

    async getUserConversations(userId) {
        const response = await api.get(`/admin/ai-audit/users/${userId}/conversations`);
        return response.data;
    },

    async getConversationMessages(conversationId) {
        const response = await api.get(`/admin/ai-audit/conversations/${conversationId}/messages`);
        return response.data;
    },
};

export default aiAuditService;
