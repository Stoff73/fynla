import api, { apiBaseURL } from './api';
import { getToken } from './tokenStorage';

const aiChatService = {
    /**
     * Get current token usage and reset time.
     */
    async getTokenUsage() {
        const response = await api.get('/ai-chat/token-usage');
        return response.data;
    },

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
    async sendMessageStream(conversationId, message, currentRoute = null, { signal } = {}) {
        const token = await getToken();
        const isCapacitor = typeof window !== 'undefined' && window.location.protocol === 'capacitor:';

        const response = await fetch(`${apiBaseURL}/api/ai-chat/conversations/${conversationId}/messages`, {
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
            // Capacitor cross-origin: omit credentials to avoid CORS cookie issues
            credentials: isCapacitor ? 'omit' : 'same-origin',
            signal,
        });

        if (!response.ok) {
            const errorText = await response.text().catch(() => '');
            throw new Error(`Chat request failed: ${response.status} ${errorText}`);
        }

        // WKWebView may not support ReadableStream — fall back to text parsing
        if (!response.body) {
            const text = await response.text();
            // Create a synthetic reader from the full response
            const encoder = new TextEncoder();
            const stream = new ReadableStream({
                start(controller) {
                    controller.enqueue(encoder.encode(text));
                    controller.close();
                },
            });
            return stream.getReader();
        }

        return response.body.getReader();
    },
};

export default aiChatService;
