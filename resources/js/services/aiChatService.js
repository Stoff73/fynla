import api from './api';
import { getToken } from './tokenStorage';

const aiChatService = {
    /**
     * Get list of user's conversations.
     */
    async getConversations() {
        const response = await api.get('/ai-chat/conversations');
        return response.data;
    },

    /**
     * Create a new conversation.
     */
    async createConversation(currentRoute = null) {
        const response = await api.post('/ai-chat/conversations', {
            current_route: currentRoute,
        });
        return response.data;
    },

    /**
     * Load a conversation with messages.
     */
    async getConversation(conversationId) {
        const response = await api.get(`/ai-chat/conversations/${conversationId}`);
        return response.data;
    },

    /**
     * Delete a conversation.
     */
    async deleteConversation(conversationId) {
        const response = await api.delete(`/ai-chat/conversations/${conversationId}`);
        return response.data;
    },

    /**
     * Send a message and return a ReadableStream reader for SSE.
     * Uses fetch() instead of axios because axios doesn't support streaming.
     */
    async sendMessageStream(conversationId, message, currentRoute = null) {
        const token = await getToken();

        const response = await fetch(`/api/ai-chat/conversations/${conversationId}/messages`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'text/event-stream',
                'Authorization': `Bearer ${token}`,
            },
            body: JSON.stringify({
                message,
                current_route: currentRoute,
            }),
        });

        if (!response.ok) {
            throw new Error(`Chat request failed: ${response.status}`);
        }

        return response.body.getReader();
    },
};

export default aiChatService;
