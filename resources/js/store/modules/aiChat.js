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
    error: null,
    tokenLimitReached: false,
    tokenResetAt: null,
    secondsUntilReset: null,
    showHistory: false,
    pendingNavigation: null,
    prefilledPrompt: null,
    abortController: null,
    // Persona split (Phase 13):
    personaMode: 'advice',       // 'advice' | 'capturing' — driven by persona_state_change SSE
    onboardingLayout: 'wide',    // 'wide' | 'standard' — driven by onboarding_layout_change SSE
    skipLink: null,              // { label, color } when a state exposes a skip link
    previewCta: null,            // { label, route } when advice emits a signup CTA
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
    personaMode: (state) => state.personaMode,
    onboardingLayout: (state) => state.onboardingLayout,
    skipLink: (state) => state.skipLink,
    previewCta: (state) => state.previewCta,
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

    SET_ABORT_CONTROLLER(state, controller) {
        state.abortController = controller;
    },

    SET_PERSONA_MODE(state, mode) {
        state.personaMode = mode === 'capturing' ? 'capturing' : 'advice';
    },

    SET_ONBOARDING_LAYOUT(state, mode) {
        state.onboardingLayout = mode === 'standard' ? 'standard' : 'wide';
    },

    SET_SKIP_LINK(state, skipLink) {
        state.skipLink = skipLink || null;
    },

    SET_PREVIEW_CTA(state, cta) {
        state.previewCta = cta || null;
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
        state.personaMode = 'advice';
        state.onboardingLayout = 'wide';
        state.skipLink = null;
        state.previewCta = null;
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
        // Historical conversations are display-only. Clear any lingering
        // pendingNavigation so that loading a completed onboarding
        // transcript can never accidentally re-navigate the router.
        commit('SET_PENDING_NAVIGATION', null);

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

        // Snapshot count AFTER adding the user message so we can detect whether
        // the stream produced any new assistant/quick_replies/etc. messages.
        // Without this check the finally-block below fires a false-positive
        // "Fyn couldn't generate a response" banner on pure quick_replies turns
        // (the onboarding director's base_dependants + add_more loops emit
        // quick_replies events that ADD_MESSAGE directly without populating
        // streamingText).
        const preStreamMessageCount = state.messages.length;

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
                                // Hand the fill to aiFormFill. startFill drives navigation
                                // itself so that multi-entity messages navigate in queue
                                // order (previously, setting pendingNavigation here clobbered
                                // the first entity's route when a second fill_form arrived).
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

                            case 'quick_replies':
                                // Flush any streaming text into a normal assistant message first
                                // so the bubbles appear AFTER the intro text Claude wrote.
                                if (state.streamingText) {
                                    commit('ADD_MESSAGE', {
                                        id: 'qr_text_' + Date.now(),
                                        role: 'assistant',
                                        content: state.streamingText,
                                        created_at: new Date().toISOString(),
                                    });
                                    commit('SET_STREAMING_TEXT', '');
                                }
                                commit('ADD_MESSAGE', {
                                    id: 'qr_' + Date.now(),
                                    role: 'quick_replies',
                                    content: event.prompt_text || '',
                                    metadata: {
                                        bubbles: event.bubbles || [],
                                        skip_link: event.skip_link || null,
                                        action_bubbles: Boolean(event.action_bubbles),
                                    },
                                    created_at: new Date().toISOString(),
                                });
                                // Phase 10 — propagate skip-link metadata to the
                                // global getter so persistent affordances (e.g.
                                // the spouse skip) can be rendered outside the
                                // bubble list too.
                                commit('SET_SKIP_LINK', event.skip_link || null);
                                break;

                            case 'onboarding_advance':
                                // Informational — the director transitioned from one
                                // state to another. No UI change yet; logged for debug.
                                logger.debug('[onboarding] advance', event.from_step, '→', event.to_step);
                                break;

                            case 'onboarding_layout_change':
                                // Phase 13 — director signals layout switch (wide/standard)
                                // for pause states. FynOnboardingChat.vue watches this
                                // getter to shrink the chat container and unblur the
                                // dashboard while in standard mode.
                                commit('SET_ONBOARDING_LAYOUT', event.mode);
                                break;

                            case 'persona_state_change':
                                // Phase 13 — orchestrator signals advice <-> capturing.
                                // AiChatPanel.vue swaps input placeholder and surfaces
                                // the capturing pill based on this getter.
                                commit('SET_PERSONA_MODE', event.current);
                                break;

                            case 'capture_complete':
                                // Phase 13 — orchestrator fires this after data-capture
                                // Fyn emits capture_complete. Records are added to the
                                // message stream as a record-card bubble for the UI.
                                commit('ADD_MESSAGE', {
                                    id: 'capture_' + Date.now(),
                                    role: 'capture_complete',
                                    content: event.summary || '',
                                    metadata: {
                                        records_created: event.records_created || [],
                                    },
                                    created_at: new Date().toISOString(),
                                });
                                break;

                            case 'handoff':
                                // Should never reach here — FynPersonaInvoker strips
                                // handoff events from the outbound SSE. Log only.
                                logger.debug('[chat] handoff leaked', event);
                                break;

                            case 'skip_link':
                                // Phase 10 — director-emitted skip link affordance for
                                // grouped_extract states (e.g. base_spouse). The bubbles
                                // path bundles skip_link into the quick_replies event;
                                // this separate type carries it for non-bubble turns.
                                commit('SET_SKIP_LINK', event.skip_link || null);
                                break;

                            case 'preview_cta':
                                // Phase 13 — orchestrator surfaces a signup CTA after
                                // short-circuiting a preview user.
                                commit('SET_PREVIEW_CTA', {
                                    label: event.label || 'Sign up',
                                    route: event.route || '/register',
                                });
                                break;

                            case 'onboarding_complete':
                                // Terminal state — the director marked the user as
                                // onboarded and told us where to navigate next.
                                // SET_PENDING_NAVIGATION is picked up by AiChatPanel's
                                // existing navigation handler.
                                commit('SET_PENDING_NAVIGATION', event.nextRoute || '/dashboard');
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
                                // Finalise assistant message and clear the live
                                // stream buffer so the next event does not
                                // re-commit the same text. The quick_replies
                                // branch above also flushes streamingText as a
                                // fallback — without this clear, a normal
                                // assistant turn followed by a director-emitted
                                // quick_replies (e.g. asset_capture → add_more)
                                // would commit the same message twice.
                                if (state.streamingText) {
                                    commit('ADD_MESSAGE', {
                                        id: event.message_id || 'msg_' + Date.now(),
                                        role: 'assistant',
                                        content: state.streamingText,
                                        created_at: new Date().toISOString(),
                                    });
                                    commit('SET_STREAMING_TEXT', '');
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
            // Detect empty response — stream completed but Fyn never replied.
            // "Replied" = either streamingText has content OR new messages
            // (assistant, quick_replies, navigation, entity_created, etc.)
            // were pushed during the stream.
            const producedNewMessages = state.messages.length > preStreamMessageCount;
            if (state.streaming && !state.streamingText && !producedNewMessages && !state.error) {
                commit('SET_ERROR', 'Fyn couldn\'t generate a response. This can happen with longer conversations — try starting a new one.');
            }
            commit('SET_STREAMING', false);
            commit('SET_STREAMING_TEXT', '');
            commit('SET_ABORT_CONTROLLER', null);
        }
    },

    /**
     * Post a routed action (resume / continue / restart / skip) against
     * the current conversation. Streams the director's response via SSE
     * and commits events using the same mutations as sendMessage.
     * Phase 12 — replaces the old sentinel-string resume/skip/restart
     * hack.
     */
    async postAction({ commit, dispatch, state }, action) {
        if (!state.currentConversation) {
            logger.warn('[chat] postAction called without a current conversation');
            return;
        }

        const validActions = ['resume', 'continue', 'restart', 'skip'];
        if (!validActions.includes(action)) {
            logger.warn('[chat] invalid action', action);
            return;
        }

        commit('SET_STREAMING', true);
        commit('SET_STREAMING_TEXT', '');
        commit('SET_ERROR', null);

        const abortController = new AbortController();
        commit('SET_ABORT_CONTROLLER', abortController);

        let reader;
        try {
            reader = await aiChatService.postActionStream(
                state.currentConversation.id,
                action,
                { signal: abortController.signal },
            );
        } catch (error) {
            logger.error('[chat] postAction failed', error);
            commit('SET_ERROR', 'Could not complete that action. Please try again.');
            commit('SET_STREAMING', false);
            commit('SET_ABORT_CONTROLLER', null);
            return;
        }

        const decoder = new TextDecoder();
        let buffer = '';

        try {
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

                            case 'quick_replies':
                                if (state.streamingText) {
                                    commit('ADD_MESSAGE', {
                                        id: 'qr_text_' + Date.now(),
                                        role: 'assistant',
                                        content: state.streamingText,
                                        created_at: new Date().toISOString(),
                                    });
                                    commit('SET_STREAMING_TEXT', '');
                                }
                                commit('ADD_MESSAGE', {
                                    id: 'qr_' + Date.now(),
                                    role: 'quick_replies',
                                    content: event.prompt_text || '',
                                    metadata: {
                                        bubbles: event.bubbles || [],
                                        skip_link: event.skip_link || null,
                                        action_bubbles: Boolean(event.action_bubbles),
                                    },
                                    created_at: new Date().toISOString(),
                                });
                                commit('SET_SKIP_LINK', event.skip_link || null);
                                break;

                            case 'onboarding_advance':
                                logger.debug('[onboarding] advance', event.from_step, '→', event.to_step);
                                break;

                            case 'onboarding_layout_change':
                                commit('SET_ONBOARDING_LAYOUT', event.mode);
                                break;

                            case 'skip_link':
                                commit('SET_SKIP_LINK', event.skip_link || null);
                                break;

                            case 'done':
                                if (state.streamingText) {
                                    commit('ADD_MESSAGE', {
                                        id: event.message_id || 'msg_' + Date.now(),
                                        role: 'assistant',
                                        content: state.streamingText,
                                        created_at: new Date().toISOString(),
                                    });
                                    commit('SET_STREAMING_TEXT', '');
                                }
                                break;

                            case 'error':
                                commit('SET_ERROR', event.message);
                                break;
                        }
                    } catch {
                        // Skip malformed SSE lines
                    }
                }
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                logger.error('postAction streaming error:', error);
                commit('SET_ERROR', 'Connection lost. Please try again.');
            }
        } finally {
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
     * Get the user's current onboarding status from the backend director.
     * Returns {in_progress, current_step, path, selection, conversation_id}.
     */
    async getOnboardingStatus() {
        try {
            const response = await aiChatService.getOnboardingStatus();
            return response.data || { in_progress: false };
        } catch (error) {
            logger.error('Failed to get onboarding status:', error);
            return { in_progress: false };
        }
    },

    /**
     * Start (or resume) the Fyn-driven onboarding conversation. On first
     * open from the "Quick start with Fyn" CTA, this calls the backend
     * /onboarding/start endpoint which emits turn 1 via SSE with no
     * preceding user message — the user sees Fyn's greeting + bubbles as
     * the first thing in an empty chat.
     *
     * On subsequent loads (tab reopen, reload), checks /onboarding/status
     * first. If the user is already mid-flow, loads the existing
     * conversation. If onboarding is already complete or the feature flag
     * is off, falls back to a normal startNewConversation.
     */
    async startOnboardingConversation({ commit, dispatch, state, rootState }) {
        // Reset chat state before starting
        commit('SET_LOADING', true);
        commit('SET_ERROR', null);
        commit('SET_MESSAGES', []);
        commit('SET_STREAMING_TEXT', '');

        // Check status first — if already in progress, resume instead of starting
        try {
            const status = await aiChatService.getOnboardingStatus();
            const inProgress = status?.data?.in_progress === true;
            const conversationId = status?.data?.conversation_id;
            if (inProgress && conversationId) {
                commit('SET_LOADING', false);
                await dispatch('loadConversation', conversationId);
                return;
            }
        } catch (error) {
            // Non-fatal — we'll attempt /start anyway and fall back if it 503s
            logger.warn('[onboarding] status check failed, proceeding to /start', error);
        }

        commit('SET_STREAMING', true);

        const abortController = new AbortController();
        commit('SET_ABORT_CONTROLLER', abortController);

        let reader;
        try {
            reader = await aiChatService.startOnboardingStream({ signal: abortController.signal });
        } catch (error) {
            // 503 disabled / 409 already_completed / 403 preview_mode — fall back
            // to a normal empty chat so the user can still talk to Fyn.
            logger.warn('[onboarding] /start failed, falling back to normal chat', error);
            commit('SET_STREAMING', false);
            commit('SET_LOADING', false);
            await dispatch('startNewConversation');
            return;
        }

        commit('SET_LOADING', false);

        const decoder = new TextDecoder();
        let buffer = '';

        try {
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
                            case 'conversation_created':
                                // Backend created the AiConversation row — store the
                                // reference so sendMessage() knows where to POST.
                                commit('SET_CURRENT_CONVERSATION', {
                                    id: event.conversation_id,
                                    title: event.title || 'Onboarding',
                                    message_count: 0,
                                });
                                break;

                            case 'resume':
                                // User is already mid-flow — switch to the existing
                                // conversation and load its history.
                                if (event.conversation_id) {
                                    await dispatch('loadConversation', event.conversation_id);
                                }
                                return;

                            case 'content':
                                commit('APPEND_STREAMING_TEXT', event.text);
                                break;

                            case 'quick_replies':
                                if (state.streamingText) {
                                    commit('ADD_MESSAGE', {
                                        id: 'qr_text_' + Date.now(),
                                        role: 'assistant',
                                        content: state.streamingText,
                                        created_at: new Date().toISOString(),
                                    });
                                    commit('SET_STREAMING_TEXT', '');
                                }
                                commit('ADD_MESSAGE', {
                                    id: 'qr_' + Date.now(),
                                    role: 'quick_replies',
                                    content: event.prompt_text || '',
                                    metadata: { bubbles: event.bubbles || [] },
                                    created_at: new Date().toISOString(),
                                });
                                break;

                            case 'onboarding_advance':
                                logger.debug('[onboarding] advance', event.from_step, '→', event.to_step);
                                break;

                            case 'done':
                                if (state.streamingText) {
                                    commit('ADD_MESSAGE', {
                                        id: event.message_id || 'msg_' + Date.now(),
                                        role: 'assistant',
                                        content: state.streamingText,
                                        created_at: new Date().toISOString(),
                                    });
                                    commit('SET_STREAMING_TEXT', '');
                                }
                                break;

                            case 'error':
                                commit('SET_ERROR', event.message);
                                break;
                        }
                    } catch {
                        // Skip malformed SSE lines
                    }
                }
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                logger.error('[onboarding] stream error', error);
                commit('SET_ERROR', 'Onboarding is temporarily unavailable. Please try again.');
            }
        } finally {
            commit('SET_STREAMING', false);
            commit('SET_ABORT_CONTROLLER', null);
        }
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
