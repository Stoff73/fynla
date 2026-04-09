/**
 * AI Chat Store Module
 *
 * Manages the AI chat panel state, conversations, messages,
 * and streaming responses.
 */

import aiChatService from '@/services/aiChatService';

import logger from '@/utils/logger';
const state = {
    isOpen: false,
    conversations: [],
    currentConversation: null,
    messages: [],
    streaming: false,
    streamingText: '',
    loading: false,
    loadingConversations: false,
    pendingJourneyPrompt: false,
    error: null,
    tokenLimitReached: false,
    tokenResetAt: null,
    secondsUntilReset: null,
    showHistory: false,
    pendingNavigation: null,
    prefilledPrompt: null,
    abortController: null,
};

const getters = {
    isOpen: (state) => state.isOpen,
    conversations: (state) => state.conversations,
    currentConversation: (state) => state.currentConversation,
    messages: (state) => state.messages,
    streaming: (state) => state.streaming,
    streamingText: (state) => state.streamingText,
    loading: (state) => state.loading,
    loadingConversations: (state) => state.loadingConversations,
    error: (state) => state.error,
    tokenLimitReached: (state) => state.tokenLimitReached,
    tokenResetAt: (state) => state.tokenResetAt,
    secondsUntilReset: (state) => state.secondsUntilReset,
    showHistory: (state) => state.showHistory,
    pendingNavigation: (state) => state.pendingNavigation,
    prefilledPrompt: (state) => state.prefilledPrompt,
    hasConversation: (state) => state.currentConversation !== null,
};

const mutations = {
    SET_OPEN(state, isOpen) {
        state.isOpen = isOpen;
    },

    SET_CONVERSATIONS(state, conversations) {
        state.conversations = conversations;
    },

    SET_CURRENT_CONVERSATION(state, conversation) {
        state.currentConversation = conversation;
    },

    SET_MESSAGES(state, messages) {
        state.messages = messages;
    },

    ADD_MESSAGE(state, message) {
        state.messages.push(message);
    },

    SET_STREAMING(state, streaming) {
        state.streaming = streaming;
    },

    SET_STREAMING_TEXT(state, text) {
        state.streamingText = text;
    },

    APPEND_STREAMING_TEXT(state, text) {
        state.streamingText += text;
    },

    SET_LOADING(state, loading) {
        state.loading = loading;
    },

    SET_LOADING_CONVERSATIONS(state, loading) {
        state.loadingConversations = loading;
    },

    SET_ERROR(state, error) {
        state.error = error;
    },

    SET_TOKEN_LIMIT(state, { reached, resetAt, secondsUntilReset }) {
        state.tokenLimitReached = reached;
        state.tokenResetAt = resetAt;
        state.secondsUntilReset = secondsUntilReset;
    },

    SET_SHOW_HISTORY(state, show) {
        state.showHistory = show;
    },

    SET_PENDING_NAVIGATION(state, routePath) {
        state.pendingNavigation = routePath;
    },

    SET_PREFILLED_PROMPT(state, prompt) {
        state.prefilledPrompt = prompt;
    },

    SET_PENDING_JOURNEY_PROMPT(state, pending) {
        state.pendingJourneyPrompt = pending;
    },

    SET_ABORT_CONTROLLER(state, controller) {
        state.abortController = controller;
    },

    UPDATE_CONVERSATION_TITLE(state, { conversationId, title }) {
        if (state.currentConversation && state.currentConversation.id === conversationId) {
            state.currentConversation.title = title;
        }
        const conv = state.conversations.find((c) => c.id === conversationId);
        if (conv) {
            conv.title = title;
        }
    },

    REMOVE_CONVERSATION(state, conversationId) {
        state.conversations = state.conversations.filter((c) => c.id !== conversationId);
        if (state.currentConversation && state.currentConversation.id === conversationId) {
            state.currentConversation = null;
            state.messages = [];
        }
    },

    RESET(state) {
        state.isOpen = false;
        state.conversations = [];
        state.currentConversation = null;
        state.messages = [];
        state.streaming = false;
        state.streamingText = '';
        state.loading = false;
        state.loadingConversations = false;
        state.error = null;
        state.tokenLimitReached = false;
        state.tokenResetAt = null;
        state.secondsUntilReset = null;
        state.showHistory = false;
        state.pendingNavigation = null;
        state.prefilledPrompt = null;
        state.abortController = null;
        state.pendingJourneyPrompt = false;
    },
};

const actions = {
    /**
     * Toggle the chat panel open/closed.
     */
    toggle({ commit, state, dispatch }) {
        const newState = !state.isOpen;
        commit('SET_OPEN', newState);

        // Close info guide when opening chat
        if (newState) {
            dispatch('infoGuide/close', null, { root: true });
        }
    },

    /**
     * Open the chat panel.
     */
    open({ commit, dispatch }) {
        commit('SET_OPEN', true);
        dispatch('infoGuide/close', null, { root: true });
    },

    /**
     * Close the chat panel.
     */
    close({ commit }) {
        commit('SET_OPEN', false);
    },

    /**
     * Toggle the history drawer.
     */
    async toggleHistory({ commit, state, dispatch }) {
        const newState = !state.showHistory;
        commit('SET_SHOW_HISTORY', newState);

        // Fetch fresh conversations when opening history
        if (newState) {
            await dispatch('fetchConversations');
        }
    },

    /**
     * Fetch all conversations.
     */
    async fetchConversations({ commit }) {
        commit('SET_LOADING_CONVERSATIONS', true);

        try {
            const response = await aiChatService.getConversations();
            commit('SET_CONVERSATIONS', response.data || []);
        } catch (error) {
            logger.error('Failed to fetch conversations:', error);
        } finally {
            commit('SET_LOADING_CONVERSATIONS', false);
        }
    },

    /**
     * Start a new conversation.
     */
    async startNewConversation({ commit, rootState }) {
        commit('SET_LOADING', true);
        commit('SET_ERROR', null);
        commit('SET_MESSAGES', []);
        commit('SET_STREAMING_TEXT', '');

        try {
            const currentRoute = rootState.route?.path || window.location.pathname;
            const response = await aiChatService.createConversation(currentRoute);
            commit('SET_CURRENT_CONVERSATION', response.data);
        } catch (error) {
            logger.error('Failed to create conversation:', error);
            commit('SET_ERROR', 'Failed to start a new conversation. Please try again.');
        } finally {
            commit('SET_LOADING', false);
        }
    },

    /**
     * Load an existing conversation.
     */
    async loadConversation({ commit }, conversationId) {
        commit('SET_LOADING', true);
        commit('SET_ERROR', null);
        commit('SET_SHOW_HISTORY', false);

        try {
            const response = await aiChatService.getConversation(conversationId);
            commit('SET_CURRENT_CONVERSATION', response.data.conversation);
            commit('SET_MESSAGES', response.data.messages || []);
        } catch (error) {
            logger.error('Failed to load conversation:', error);
            commit('SET_ERROR', 'Failed to load conversation.');
        } finally {
            commit('SET_LOADING', false);
        }
    },

    /**
     * Delete a conversation.
     */
    async deleteConversation({ commit }, conversationId) {
        try {
            await aiChatService.deleteConversation(conversationId);
            commit('REMOVE_CONVERSATION', conversationId);
        } catch (error) {
            logger.error('Failed to delete conversation:', error);
        }
    },

    /**
     * Send a message and handle the streaming response.
     */
    async sendMessage({ commit, dispatch, state, rootState }, message) {
        if (!state.currentConversation) return;

        // Add user message to local state immediately
        commit('ADD_MESSAGE', {
            id: 'temp_' + Date.now(),
            role: 'user',
            content: message,
            created_at: new Date().toISOString(),
        });

        commit('SET_STREAMING', true);
        commit('SET_STREAMING_TEXT', '');
        commit('SET_ERROR', null);

        const abortController = new AbortController();
        commit('SET_ABORT_CONTROLLER', abortController);

        const currentRoute = rootState.route?.path || window.location.pathname;

        try {
            const reader = await aiChatService.sendMessageStream(
                state.currentConversation.id,
                message,
                currentRoute,
                { signal: abortController.signal },
            );

            const decoder = new TextDecoder();
            let buffer = '';

            while (true) {
                const { done, value } = await reader.read();

                if (done) break;

                buffer += decoder.decode(value, { stream: true });
                const lines = buffer.split('\n');
                buffer = lines.pop() || '';

                for (const line of lines) {
                    if (!line.startsWith('data: ')) continue;

                    try {
                        const event = JSON.parse(line.slice(6));

                        switch (event.type) {
                            case 'content':
                                commit('APPEND_STREAMING_TEXT', event.text);
                                break;

                            case 'title':
                                commit('UPDATE_CONVERSATION_TITLE', {
                                    conversationId: state.currentConversation.id,
                                    title: event.title,
                                });
                                break;

                            case 'navigation':
                                commit('ADD_MESSAGE', {
                                    id: 'nav_' + Date.now(),
                                    role: 'navigation',
                                    content: event.description || '',
                                    metadata: {
                                        route_path: event.route_path,
                                        description: event.description,
                                    },
                                    created_at: new Date().toISOString(),
                                });
                                commit('SET_PENDING_NAVIGATION', event.route_path);
                                break;

                            case 'fill_form':
                                // Navigate to the page first
                                if (event.route) {
                                    commit('SET_PENDING_NAVIGATION', event.route);
                                }
                                // Queue the fill — aiFormFill processes them sequentially
                                dispatch('aiFormFill/startFill', {
                                    entityType: event.entity_type,
                                    fields: event.fields,
                                    route: event.route,
                                    mode: event.mode || 'create',
                                    entityId: event.entity_id || null,
                                }, { root: true });
                                break;

                            case 'entity_created':
                                commit('ADD_MESSAGE', {
                                    id: 'entity_' + Date.now(),
                                    role: 'entity_created',
                                    content: event.name || '',
                                    metadata: {
                                        entity_type: event.entity_type,
                                        entity_id: event.entity_id,
                                    },
                                    created_at: new Date().toISOString(),
                                });
                                break;

                            case 'token_limit':
                                commit('SET_TOKEN_LIMIT', {
                                    reached: true,
                                    resetAt: event.reset_at,
                                    secondsUntilReset: event.seconds_until_reset,
                                });
                                break;

                            case 'error':
                                commit('SET_ERROR', event.message);
                                break;

                            case 'done':
                                // Finalise assistant message
                                if (state.streamingText) {
                                    commit('ADD_MESSAGE', {
                                        id: event.message_id || 'msg_' + Date.now(),
                                        role: 'assistant',
                                        content: state.streamingText,
                                        created_at: new Date().toISOString(),
                                    });
                                }
                                break;
                        }
                    } catch {
                        // Skip malformed SSE lines
                    }
                }
            }
        } catch (error) {
            // Don't show error if the user intentionally cancelled
            if (error.name === 'AbortError') {
                return;
            }
            logger.error('Chat streaming error:', error);
            commit('SET_ERROR', 'Connection lost. Please try again.');
        } finally {
            // Detect empty response — stream completed but Fyn never replied
            if (state.streaming && !state.streamingText && !state.error) {
                commit('SET_ERROR', 'Fyn couldn\'t generate a response. This can happen with longer conversations — try starting a new one.');
            }
            commit('SET_STREAMING', false);
            commit('SET_STREAMING_TEXT', '');
            commit('SET_ABORT_CONTROLLER', null);
        }
    },

    /**
     * Abort an in-progress streaming response.
     */
    abortStreaming({ commit, state }) {
        if (state.abortController) {
            state.abortController.abort();
            commit('SET_ABORT_CONTROLLER', null);
        }

        // If there was partial streaming text, save it as the assistant message
        if (state.streamingText) {
            commit('ADD_MESSAGE', {
                id: 'msg_aborted_' + Date.now(),
                role: 'assistant',
                content: state.streamingText + '\n\n*[Response stopped]*',
                created_at: new Date().toISOString(),
            });
        }

        commit('SET_STREAMING', false);
        commit('SET_STREAMING_TEXT', '');
    },

    /**
     * Pre-fill the chat input with a prompt (e.g. from Learn Hub).
     */
    prefillPrompt({ commit }, prompt) {
        commit('SET_PREFILLED_PROMPT', prompt);
    },

    /**
     * Reset state (for logout).
     */
    reset({ commit }) {
        commit('RESET');
    },
};

export default {
    namespaced: true,
    state,
    getters,
    mutations,
    actions,
};
