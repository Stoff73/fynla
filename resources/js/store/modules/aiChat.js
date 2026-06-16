/**
 * AI Chat Store Module
 *
 * Manages the AI chat panel state, conversations, messages,
 * and streaming responses.
 */

import aiChatService from '@/services/aiChatService';
import { stripTags } from '@/utils/stripTags';

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
    onboardingLayout: 'wide',    // 'wide' | 'standard' — driven by onboarding_layout_change SSE
    skipLink: null,              // { label, color } when a state exposes a skip link
    previewCta: null,            // { label, route } when advice emits a signup CTA
    // True while the current conversation is a Fyn-driven onboarding
    // conversation (started via startOnboardingConversation, i.e. the
    // "Quick start with Fyn" CTA). AppLayout reads this to activate the
    // wide-chat wrapper on /dashboard routes — the onboarding surface is
    // not tied to a specific URL path.
    isOnboardingActive: false,
    // Set true when the backend emits a consent_required event (either as
    // a 403 on send or as a mid-stream SSE event after withdrawal). UI
    // components watch this getter to surface a re-consent prompt; the
    // existing /settings GDPR consent UI handles the actual re-grant.
    consentRequired: false,
    // FR-M14 — true while the planner runs (before any content), so the UI can
    // show "Fyn is thinking…". Cleared on the first content token or done.
    thinking: false,
    // FR-M9 — { conversation_id, message } when the user has an unfinished
    // conversation to resume; surfaced as a prompt on chat open.
    pendingResumption: null,
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
    onboardingLayout: (state) => state.onboardingLayout,
    skipLink: (state) => state.skipLink,
    previewCta: (state) => state.previewCta,
    isOnboardingActive: (state) => state.isOnboardingActive,
    consentRequired: (state) => state.consentRequired,
    thinking: (state) => state.thinking,
    pendingResumption: (state) => state.pendingResumption,
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

    // FR-M7 — flip a turn's queue status (queued / processing / answered /
    // cancelled) so its bubble re-renders greyed / pulsing / normal, and swap the
    // optimistic temp id for the real backend id so cancel/stream calls target it.
    SET_MESSAGE_STATUS(state, { id, status, realId = null }) {
        const message = state.messages.find((m) => m.id === id);
        if (!message) return;
        message.status = status;
        if (realId !== null) message.id = realId;
    },

    REMOVE_MESSAGE(state, id) {
        const index = state.messages.findIndex((m) => m.id === id);
        if (index !== -1) state.messages.splice(index, 1);
    },

    SET_STREAMING(state, streaming) {
        state.streaming = streaming;
    },

    SET_THINKING(state, thinking) {
        state.thinking = thinking;
    },

    SET_PENDING_RESUMPTION(state, pending) {
        state.pendingResumption = pending;
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

    SET_ONBOARDING_LAYOUT(state, mode) {
        state.onboardingLayout = mode === 'standard' ? 'standard' : 'wide';
    },

    SET_SKIP_LINK(state, skipLink) {
        state.skipLink = skipLink || null;
    },

    SET_PREVIEW_CTA(state, cta) {
        state.previewCta = cta || null;
    },

    SET_IS_ONBOARDING_ACTIVE(state, value) {
        state.isOnboardingActive = !!value;
    },

    SET_CONSENT_REQUIRED(state, value) {
        state.consentRequired = !!value;
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
        state.onboardingLayout = 'wide';
        state.skipLink = null;
        state.previewCta = null;
        state.isOnboardingActive = false;
        state.consentRequired = false;
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
        // Starting a fresh non-onboarding conversation clears the onboarding
        // active flag so the wide-chat/blur drops back to normal.
        commit('SET_IS_ONBOARDING_ACTIVE', false);

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
     *
     * Normalises the persisted message shape to match the streamed shape:
     * assistant rows with `metadata.bubbles` are split into a plain
     * `assistant` row (text only) plus a `quick_replies` row (bubbles),
     * mirroring what the SSE path produces. Without this split, AiChatPanel
     * (which only renders bubbles when `msg.role === 'quick_replies'`) shows
     * a resumed onboarding turn with no bubbles.
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

            const raw = response.data.messages || [];
            const normalised = [];
            for (const m of raw) {
                const bubbles = m?.metadata?.bubbles;
                const skipLink = m?.metadata?.skip_link || null;
                const actionBubbles = Boolean(m?.metadata?.action_bubbles);
                const hasBubbles = Array.isArray(bubbles) && bubbles.length > 0;

                if (m.role === 'assistant' && hasBubbles) {
                    // Split into text + quick_replies so AiChatPanel renders both.
                    if (m.content) {
                        normalised.push({ ...m, metadata: { ...m.metadata, bubbles: undefined } });
                    }
                    normalised.push({
                        id: `qr_${m.id}`,
                        role: 'quick_replies',
                        content: '',
                        metadata: { bubbles, skip_link: skipLink, action_bubbles: actionBubbles },
                        created_at: m.created_at,
                    });
                } else {
                    normalised.push(m);
                }
            }
            commit('SET_MESSAGES', normalised);
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

        // Add user message to local state immediately. Strip HTML tags so the
        // optimistic bubble matches what SanitizeInput middleware writes to
        // the DB — otherwise the user sees their own "<script>...</script>"
        // input rendered as visible text in the bubble (escaped, but ugly).
        const displayMessage = stripTags(message);
        const tempId = 'temp_' + Date.now();

        commit('ADD_MESSAGE', {
            id: tempId,
            role: 'user',
            content: displayMessage,
            created_at: new Date().toISOString(),
        });

        // FR-M7 — tracks whether this turn streamed to completion (vs was queued
        // or errored) so the finally only pops the next queued turn on success.
        let streamedToCompletion = false;

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

            // FR-M7 — a turn sent while another is still streaming is QUEUED (or
            // rejected past the depth cap); the service returns a typed marker
            // instead of a stream reader. Render the optimistic bubble greyed
            // (with its real backend id for cancel / stream-next) and stop here —
            // it will be streamed when the in-flight turn finishes.
            if (reader && reader.queued) {
                commit('SET_MESSAGE_STATUS', { id: tempId, status: 'queued', realId: reader.messageId });
                commit('SET_STREAMING', false);
                return;
            }
            if (reader && reader.rejected) {
                commit('REMOVE_MESSAGE', tempId);
                commit('SET_ERROR', reader.message || 'You have a few messages already waiting — let Fyn answer those first.');
                commit('SET_STREAMING', false);
                return;
            }

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
                            case 'thinking':
                                // FR-M14 — planner is running; show "Fyn is thinking…".
                                commit('SET_THINKING', true);
                                break;

                            case 'content':
                                if (state.thinking) {
                                    commit('SET_THINKING', false);
                                }
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
                                // Entering a profile-review pause — refresh the auth
                                // user + family members so ProfileReviewPanel has the
                                // data the director just wrote (FR-M22: "renders
                                // captured personal / family / employment / expenditure
                                // fields"). Without this refresh the panel reads stale
                                // Vuex state from page load and shows only fields that
                                // existed then.
                                if (event.mode === 'standard') {
                                    dispatch('auth/fetchUser', null, { root: true }).catch(() => {});
                                    dispatch('userProfile/fetchFamilyMembers', null, { root: true }).catch(() => {});
                                }
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
                                // Should never reach here — AdviceFyn::wrapStream strips
                                // handoff events from the outbound SSE per INV-2.4.1.
                                // Log only.
                                logger.debug('[chat] handoff leaked', event);
                                break;

                            case 'handoff_error':
                                // April30Updates F-1 / INV-2.4.5 — the LLM emitted a
                                // delegate_to_capture with a malformed payload. Surface
                                // a single short message so the user knows the request
                                // didn't land. Render as a normal assistant content
                                // bubble (per INV-2.4.3 styling rule — no special chrome).
                                commit('ADD_MESSAGE', {
                                    id: 'handoff_error_' + Date.now(),
                                    role: 'assistant',
                                    content: event.message || "I couldn't pick up that request — could you try again?",
                                    created_at: new Date().toISOString(),
                                });
                                logger.warn('[chat] handoff_error', {
                                    reason: event.reason,
                                });
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
                                // existing navigation handler. Also clear the Fyn
                                // onboarding flag so the wide-chat/blur layout
                                // returns to normal.
                                commit('SET_IS_ONBOARDING_ACTIVE', false);
                                commit('SET_PENDING_NAVIGATION', event.nextRoute || '/dashboard');
                                // Refresh dashboard data — onboarding may have created
                                // goals, family members, income, expenditure, and other
                                // records that the dashboard's charts and cards display.
                                // If the user lands on the same route they were on, the
                                // router push is a no-op and no remount fires — explicit
                                // refresh below ensures the visible state matches the
                                // newly captured data.
                                dispatch('auth/fetchUser', null, { root: true }).catch(() => {});
                                dispatch('goals/fetchProjection', null, { root: true }).catch(() => {});
                                dispatch('goals/fetchDashboardOverview', null, { root: true }).catch(() => {});
                                dispatch('netWorth/refreshNetWorth', null, { root: true }).catch(() => {});
                                break;

                            case 'token_limit':
                                commit('SET_TOKEN_LIMIT', {
                                    reached: true,
                                    resetAt: event.reset_at,
                                    secondsUntilReset: event.seconds_until_reset,
                                });
                                break;

                            case 'consent_required':
                                // S0.9 — backend re-checks ai_chat consent on every
                                // SSE iteration. If the user withdrew consent
                                // mid-stream the controller emits this terminal
                                // event and closes the connection. Surface the
                                // re-consent prompt and stop streaming locally.
                                commit('SET_CONSENT_REQUIRED', true);
                                commit('SET_STREAMING', false);
                                // No user-facing consent toggle exists — AI chat
                                // consent is granted at registration via the
                                // privacy policy. If consent has been withdrawn
                                // (e.g. by support action or a GDPR request),
                                // the user has to contact support to restore it.
                                commit('SET_ERROR', 'AI chat consent has been withdrawn. Contact Fynla support to restore your AI features.');
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
            // FR-M7 — the in-flight turn streamed to completion; the finally
            // pops the next queued turn for this conversation.
            streamedToCompletion = true;
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
            //
            // Skip the empty-response error when the stream legitimately
            // ended with no assistant turn — token_limit and consent_required
            // both close the stream after a single terminal SSE event and
            // produce no message. Without these guards the violet token-
            // limit notice and the raspberry empty-response banner both
            // render, with the banner overwriting the legitimate notice
            // (BS-13 RED until session 89).
            const producedNewMessages = state.messages.length > preStreamMessageCount;
            if (
                state.streaming
                && !state.streamingText
                && !producedNewMessages
                && !state.error
                && !state.tokenLimitReached
                && !state.consentRequired
            ) {
                commit('SET_ERROR', 'Fyn couldn\'t generate a response. This can happen with longer conversations — try starting a new one.');
            }
            commit('SET_STREAMING', false);
            commit('SET_THINKING', false);
            commit('SET_STREAMING_TEXT', '');
            commit('SET_ABORT_CONTROLLER', null);

            // FR-M7 — now the in-flight turn is done, pop the next queued turn
            // (if any). Fire-and-forget: streamNextQueued opens its own stream.
            if (streamedToCompletion) {
                dispatch('streamNextQueued');
            }
        }
    },

    /**
     * FR-M7 — stream the next queued turn for the current conversation once the
     * in-flight one has finished (frontend-driven transport). Fired from
     * sendMessage's finally and chains from its own until the queue is empty.
     *
     * v1 auto-streams advice-mode queues only. Onboarding is a guided bubble flow
     * where concurrent free-text is rare; a queued onboarding turn is left queued
     * (the user can cancel it) rather than risk dropping the director's rich
     * bubble events through this advice-focused consumer.
     */
    async streamNextQueued({ commit, dispatch, state, rootState }) {
        if (!state.currentConversation || state.streaming || state.isOnboardingActive) return;

        const queued = state.messages.find((m) => m.status === 'queued');
        if (!queued) return;

        commit('SET_MESSAGE_STATUS', { id: queued.id, status: 'processing' });
        commit('SET_STREAMING', true);
        commit('SET_STREAMING_TEXT', '');
        commit('SET_ERROR', null);

        const abortController = new AbortController();
        commit('SET_ABORT_CONTROLLER', abortController);
        const currentRoute = rootState.route?.path || window.location.pathname;
        let streamedToCompletion = false;

        try {
            const reader = await aiChatService.streamQueuedMessage(
                state.currentConversation.id,
                queued.id,
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
                            case 'thinking':
                                // FR-M14 — planner is running; show "Fyn is thinking…".
                                commit('SET_THINKING', true);
                                break;

                            case 'content':
                                if (state.thinking) {
                                    commit('SET_THINKING', false);
                                }
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
                                    metadata: { route_path: event.route_path, description: event.description },
                                    created_at: new Date().toISOString(),
                                });
                                commit('SET_PENDING_NAVIGATION', event.route_path);
                                break;
                            case 'entity_created':
                                commit('ADD_MESSAGE', {
                                    id: 'entity_' + Date.now(),
                                    role: 'entity_created',
                                    content: event.name || '',
                                    metadata: { entity_type: event.entity_type, entity_id: event.entity_id },
                                    created_at: new Date().toISOString(),
                                });
                                break;
                            case 'handoff_error':
                                commit('ADD_MESSAGE', {
                                    id: 'handoff_error_' + Date.now(),
                                    role: 'assistant',
                                    content: event.message || "I couldn't pick up that request — could you try again?",
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
                            case 'consent_required':
                                commit('SET_CONSENT_REQUIRED', true);
                                commit('SET_STREAMING', false);
                                break;
                            case 'error':
                                commit('SET_ERROR', event.message);
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
                            default:
                                logger.debug('[chat] queued-turn: unhandled event', event.type);
                        }
                    } catch {
                        // Skip malformed SSE lines
                    }
                }
            }

            // The queued turn has been answered — flip its bubble to normal
            // (stable id) so it reads like any other answered turn.
            commit('SET_MESSAGE_STATUS', { id: queued.id, status: 'answered' });
            streamedToCompletion = true;
        } catch (error) {
            if (error.name === 'AbortError') return;
            logger.error('Queued-turn streaming error:', error);
            commit('SET_ERROR', 'Connection lost. Please try again.');
        } finally {
            commit('SET_STREAMING', false);
            commit('SET_THINKING', false);
            commit('SET_STREAMING_TEXT', '');
            commit('SET_ABORT_CONTROLLER', null);
            if (streamedToCompletion) {
                dispatch('streamNextQueued');
            }
        }
    },

    /**
     * FR-M7 scenario 4 — cancel a still-queued turn before it streams.
     */
    async cancelQueued({ commit, state }, messageId) {
        if (!state.currentConversation) return;
        try {
            await aiChatService.cancelQueuedMessage(state.currentConversation.id, messageId);
            commit('SET_MESSAGE_STATUS', { id: messageId, status: 'cancelled' });
        } catch (error) {
            logger.error('Failed to cancel queued message:', error);
        }
    },

    /**
     * FR-M9 — on chat open, check for an unfinished conversation to resume.
     */
    async fetchResumption({ commit }) {
        try {
            const { data } = await aiChatService.getResumption();
            if (data?.has_pending) {
                commit('SET_PENDING_RESUMPTION', {
                    conversationId: data.conversation_id,
                    message: data.message,
                });
            } else {
                commit('SET_PENDING_RESUMPTION', null);
            }
        } catch (e) {
            // Non-fatal — resumption is an enhancement, not a blocker.
            logger.debug('[chat] resumption check failed', e);
        }
    },

    /**
     * FR-M9 — acknowledge the resumption (continue OR start fresh both clear it
     * server-side and dismiss the prompt).
     */
    async acknowledgeResumption({ commit, state }) {
        const pending = state.pendingResumption;
        commit('SET_PENDING_RESUMPTION', null);
        if (pending?.conversationId) {
            try {
                await aiChatService.clearResumption(pending.conversationId);
            } catch (e) {
                logger.debug('[chat] clearResumption failed', e);
            }
        }
    },

    /**
     * Post a routed action (resume / continue / restart / skip / something_else)
     * against the current conversation. Streams the director's response via
     * SSE and commits events using the same mutations as sendMessage.
     * Phase 12 — replaces the old sentinel-string resume/skip/restart
     * hack.
     */
    async postAction({ commit, dispatch, state }, action) {
        if (!state.currentConversation) {
            logger.warn('[chat] postAction called without a current conversation');
            return;
        }

        const validActions = ['resume', 'continue', 'restart', 'skip', 'something_else'];
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
                            case 'thinking':
                                // FR-M14 — planner is running; show "Fyn is thinking…".
                                commit('SET_THINKING', true);
                                break;

                            case 'content':
                                if (state.thinking) {
                                    commit('SET_THINKING', false);
                                }
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
                                // See sendMessage handler — refresh auth/family when
                                // entering a profile-review pause so ProfileReviewPanel
                                // shows the director's latest writes.
                                if (event.mode === 'standard') {
                                    dispatch('auth/fetchUser', null, { root: true }).catch(() => {});
                                    dispatch('userProfile/fetchFamilyMembers', null, { root: true }).catch(() => {});
                                }
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
            commit('SET_THINKING', false);
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
    async startOnboardingConversation({ commit, dispatch, state, rootState }, payload = {}) {
        // Forward the optional `from` entry-source identifier so the
        // onboarding director can pre-select the campaign / journey via
        // config('onboarding.campaign_map') / journey_map.
        const fromParam = (payload && typeof payload.from === 'string') ? payload.from : null;

        // Reset chat state before starting
        commit('SET_LOADING', true);
        commit('SET_ERROR', null);
        commit('SET_MESSAGES', []);
        commit('SET_STREAMING_TEXT', '');
        // Phase 13 — Fyn-driven onboarding is active. Unlocks the wide-chat
        // wrapper in AppLayout regardless of URL path.
        commit('SET_IS_ONBOARDING_ACTIVE', true);
        commit('SET_ONBOARDING_LAYOUT', 'wide');

        // Check status first — if already in progress, fire the resume action
        // so the director emits a clean welcome-back greeting + Continue/Start
        // over bubbles. We do NOT dump the prior history into the active chat —
        // that lives in the conversation history sidebar. The active chat
        // surface is reserved for the welcome-back turn so the user gets a
        // friendly summary of where they left off (per OnboardingChatDirector
        // ::handleResumeAction → describeStep), not a wall of past messages.
        try {
            const status = await aiChatService.getOnboardingStatus();
            const inProgress = status?.data?.in_progress === true;
            const conversationId = status?.data?.conversation_id;
            if (inProgress && conversationId) {
                commit('SET_CURRENT_CONVERSATION', {
                    id: conversationId,
                    title: 'Onboarding',
                    message_count: 0,
                });
                commit('SET_LOADING', false);
                await dispatch('postAction', 'resume');
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
            reader = await aiChatService.startOnboardingStream({
                signal: abortController.signal,
                from: fromParam,
            });
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

                            case 'thinking':
                                // FR-M14 — planner is running; show "Fyn is thinking…".
                                commit('SET_THINKING', true);
                                break;

                            case 'content':
                                if (state.thinking) {
                                    commit('SET_THINKING', false);
                                }
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
