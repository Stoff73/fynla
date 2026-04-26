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

    /**
     * Get the current user's onboarding status. Used by the chat panel
     * on open to decide whether to call /start or resume an existing
     * onboarding conversation.
     */
    async getOnboardingStatus() {
        const response = await api.get('/ai-chat/onboarding/status');
        return response.data;
    },

    /**
     * Start the Fyn-driven onboarding flow. Backend creates a fresh
     * Onboarding conversation and streams the first assistant turn
     * (bubbles for path_choice) with no preceding user message.
     *
     * Returns a ReadableStream reader — the caller consumes the SSE
     * stream the same way sendMessageStream does.
     */
    async startOnboardingStream({ signal } = {}) {
        const token = await getToken();
        const isCapacitor = typeof window !== 'undefined' && window.location.protocol === 'capacitor:';

        const response = await fetch(`${apiBaseURL}/api/ai-chat/onboarding/start`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'text/event-stream',
                'Authorization': `Bearer ${token}`,
            },
            body: '{}',
            credentials: isCapacitor ? 'omit' : 'same-origin',
            signal,
        });

        if (!response.ok) {
            // Non-SSE JSON failures (409, 503, 403) — surface the reason
            // so the store action can fall back to the normal chat path.
            const payload = await response.json().catch(() => ({}));
            const err = new Error(`Onboarding start failed: ${response.status}`);
            err.status = response.status;
            err.reason = payload.reason || null;
            throw err;
        }

        if (!response.body) {
            const text = await response.text();
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

    /**
     * Post a routed action (resume / continue / restart / skip / something_else)
     * against an existing conversation. Replaces the old sentinel-string user-
     * message path. Returns a ReadableStream reader; the caller consumes
     * the SSE stream exactly like sendMessageStream.
     *
     * @param {number} conversationId
     * @param {'resume'|'continue'|'restart'|'skip'|'something_else'} action
     */
    async postActionStream(conversationId, action, { signal } = {}) {
        const token = await getToken();
        const isCapacitor = typeof window !== 'undefined' && window.location.protocol === 'capacitor:';

        const response = await fetch(`${apiBaseURL}/api/ai-chat/conversations/${conversationId}/action`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'text/event-stream',
                'Authorization': `Bearer ${token}`,
            },
            body: JSON.stringify({ action }),
            credentials: isCapacitor ? 'omit' : 'same-origin',
            signal,
        });

        if (!response.ok) {
            const errorText = await response.text().catch(() => '');
            throw new Error(`Action ${action} failed: ${response.status} ${errorText}`);
        }

        if (!response.body) {
            const text = await response.text();
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
