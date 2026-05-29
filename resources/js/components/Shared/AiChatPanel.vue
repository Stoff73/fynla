<template>
  <!--
    AiChatPanel renders the chat body ONCE inside AiChatPanelShell's slot.
    The shell handles the structural difference between the docked sidebar
    (always-visible flex column inside AppLayout) and the floating modal
    (Teleported to body, Transition-animated, gated on isOpen). Anything
    that should appear in BOTH layouts lives in the body below — the
    token-limit notice, the empty-response error, the message list, the
    suggestions panel, the input — so a future change updates both
    layouts together. See AiChatPanelShell.vue for the wrapper logic.
  -->
  <AiChatPanelShell :docked="docked" :is-open="isOpen" :is-mobile="isMobile">

    <!-- ============================================================
         Header
         Same buttons in both layouts: New conversation + History toggle.
         The trailing button differs: modal-desktop closes (X), modal-
         mobile shows a labelled "Close" on the LEFT (iOS pattern), docked
         emits 'collapse' (»»). The Fyn icon + heading anchors the left
         side in docked mode; modal hides the icon to keep the header
         compact. Sizing density (`text-sm`/`p-1.5` vs `text-lg`/`p-2`)
         is set via computed classes that key off the `docked` prop.
         ============================================================ -->
    <div :class="headerOuterClasses">
      <div class="flex items-center justify-between">

        <!-- Mobile-modal: left-aligned close button. Rule #14: text only, no icon. -->
        <button
          v-if="!docked && isMobile"
          @click="closePanel"
          class="p-2 -ml-2 text-neutral-500 hover:text-horizon-500 rounded-md transition-colors text-sm font-medium"
        >
          Close
        </button>

        <!-- Title cluster (Fyn icon shown only in docked mode) -->
        <div :class="headerTitleClusterClasses">
          <img v-if="docked" :src="fynIconUrl" alt="Fyn" class="w-7 h-7 rounded-full" />
          <h3 :class="headerTitleClasses">Fyn</h3>
        </div>

        <!-- Right-side controls -->
        <div class="flex items-center gap-1">
          <!-- Rule #14: chat header chrome uses text labels, no icons. -->
          <button
            @click="startNew"
            :class="headerButtonClasses"
            class="text-xs font-medium px-2"
          >
            New
          </button>
          <button
            @click="toggleHistory"
            :class="[headerButtonClasses, { 'bg-savannah-100 text-neutral-500': showHistory }]"
            class="text-xs font-medium px-2"
          >
            History
          </button>

          <!-- Modal desktop: close -->
          <button
            v-if="!docked && !isMobile"
            @click="closePanel"
            class="p-1.5 text-horizon-400 hover:text-neutral-500 hover:bg-savannah-100 rounded-md transition-colors text-xs font-medium"
          >
            Close
          </button>

          <!-- Docked: collapse to icon strip -->
          <button
            v-if="docked"
            @click="$emit('collapse')"
            class="ml-1 px-2 h-7 flex items-center justify-center rounded-md bg-light-blue-100 text-horizon-500 hover:bg-light-blue-500 hover:text-white transition-colors text-xs font-medium"
          >
            Collapse
          </button>
        </div>
      </div>
    </div>

    <!-- ============================================================
         History drawer (collapsible list of past conversations)
         ============================================================ -->
    <Transition
      enter-active-class="transition ease-out duration-200"
      enter-from-class="-translate-y-2 opacity-0"
      enter-to-class="translate-y-0 opacity-100"
      leave-active-class="transition ease-in duration-150"
      leave-from-class="translate-y-0 opacity-100"
      leave-to-class="-translate-y-2 opacity-0"
    >
      <div
        v-if="showHistory"
        class="border-b border-light-gray bg-savannah-100 max-h-48 overflow-y-auto flex-shrink-0"
      >
        <div v-if="loadingConversations" class="p-4 text-center">
          <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-raspberry-600 mx-auto"></div>
        </div>
        <div v-else-if="conversations.length === 0" class="p-4 text-center text-sm text-neutral-500">
          No previous conversations
        </div>
        <div v-else>
          <button
            v-for="conv in conversations"
            :key="conv.id"
            @click="loadConversation(conv.id)"
            class="w-full text-left px-4 py-2.5 hover:bg-savannah-200 border-b border-light-gray transition-colors flex items-center justify-between group"
            :class="{ 'bg-violet-50': currentConversation?.id === conv.id }"
          >
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-horizon-500 truncate">
                {{ conv.title || 'New conversation' }}
              </p>
              <p class="text-xs text-neutral-500 mt-0.5">
                {{ formatRelativeTime(conv.last_message_at || conv.created_at) }}
              </p>
            </div>
            <button
              @click.stop="deleteConversation(conv.id)"
              class="px-2 py-1 text-horizon-400 hover:text-raspberry-500 opacity-0 group-hover:opacity-100 transition-all text-xs font-medium"
            >
              Delete
            </button>
          </button>
        </div>
      </div>
    </Transition>

    <!-- ============================================================
         Messages container — empty state, message list, streaming
         indicator, token-limit notice, error notice, scroll spacer
         ============================================================ -->
    <div
      ref="messagesContainer"
      :class="messagesContainerClasses"
      :style="messagesContainerStyle"
    >
      <!-- Loading state (modal-only initial fetch spinner) -->
      <div v-if="loading" class="flex items-center justify-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-raspberry-600"></div>
      </div>

      <!-- Empty state — simple greeting; suggested prompts live in the
           dedicated collapsible panel below the message list. -->
      <div
        v-else-if="(!messages || messages.length === 0) && !streaming"
        class="flex flex-col items-center justify-center h-full text-center py-8"
      >
        <p class="text-sm font-medium text-horizon-500 mb-1">Hi, I'm Fyn</p>
        <p class="text-xs text-neutral-500">Ask me anything about your finances</p>
      </div>

      <!-- Message list -->
      <template v-else>
        <div
          v-for="(msg, idx) in messages"
          :key="msg.id ?? idx"
          v-show="msg.role !== 'entity_created'"
        >
          <!-- Quick-reply bubbles (Fyn onboarding tool output) -->
          <FynQuickReplies
            v-if="msg.role === 'quick_replies'"
            :prompt-text="msg.content"
            :bubbles="msg.metadata?.bubbles || []"
            :disabled="streaming || loading || idx !== latestQuickRepliesIndex"
            @select="b => handleQuickReplySelect(b, msg)"
          />
          <!-- Phase 13 — capture_complete record-card row -->
          <div
            v-else-if="msg.role === 'capture_complete'"
            class="flex justify-start"
          >
            <div class="max-w-[85%] w-full rounded-lg bg-savannah-100 border border-light-gray px-3 py-2 space-y-2 text-sm">
              <p class="font-semibold text-horizon-500">{{ msg.content }}</p>
              <div
                v-if="Array.isArray(msg.metadata?.records_created) && msg.metadata.records_created.length"
                class="space-y-1"
              >
                <div
                  v-for="rec in msg.metadata.records_created"
                  :key="`${rec.type}-${rec.id}`"
                  class="flex items-center justify-between rounded bg-white border border-light-gray px-2 py-1 text-xs"
                >
                  <span class="font-semibold text-horizon-500">{{ formatRecordCardLabel(rec) }}</span>
                  <button
                    type="button"
                    @click="handleRecordView(rec)"
                    class="text-raspberry-500 underline hover:text-raspberry-600"
                  >
                    View
                  </button>
                </div>
              </div>
            </div>
          </div>
          <div
            v-else
            :class="msg.role === 'user' ? 'flex justify-end' : 'flex justify-start'"
          >
            <div
              :class="[
                'max-w-[85%] rounded-lg px-3 py-2 text-sm',
                msg.role === 'user' ? 'rounded-br-sm' : 'rounded-bl-sm',
                messageClass(msg),
              ]"
            >
              <AiMessageContent
                v-if="msg.role === 'assistant'"
                :message="msg"
                @navigate="handleNavigation"
              />
              <span v-else>{{ msg.content }}</span>
            </div>
          </div>
        </div>

        <!-- Streaming indicator -->
        <div v-if="streaming" class="flex justify-start">
          <div class="max-w-[85%] rounded-lg px-3 py-2 text-sm bg-savannah-100 border border-light-gray text-horizon-500 rounded-bl-sm">
            <div v-if="streamingText" class="leading-relaxed">
              <AiMessageContent :message="{ role: 'assistant', content: streamingText }" />
              <span class="inline-block w-1.5 h-4 bg-raspberry-600 ml-0.5 animate-pulse"></span>
            </div>
            <span v-else class="flex items-center text-neutral-500">
              <transition name="fade" mode="out-in">
                <span :key="thinkingStatus" class="text-xs">{{ thinkingStatus }}...</span>
              </transition>
            </span>
          </div>
        </div>
      </template>

      <!-- Token-limit reached — distinct violet system notice (BS-13).
           v-if takes precedence over the error banner so the legitimate
           daily-cap message is never overwritten by the empty-response
           fallback in aiChat.js. -->
      <div
        v-if="tokenLimitReached"
        class="p-4 bg-violet-50 border border-violet-200 rounded-lg text-sm text-horizon-500"
      >
        <p class="font-semibold mb-2">You've reached your daily Fyn usage limit</p>
        <p class="text-neutral-500">
          Your allowance resets in
          <span class="font-semibold text-violet-600">{{ resetCountdown }}</span>
        </p>
      </div>

      <!-- Error banner — shown when the SSE stream hit a director / Claude
           failure that wasn't a token-limit or consent-required event.
           Without it, SET_ERROR commits silently and the user just sees
           a stalled chat. -->
      <div
        v-else-if="error && !streaming"
        class="p-3 bg-raspberry-50 border border-raspberry-200 rounded-lg text-sm text-raspberry-700"
      >
        {{ error }}
      </div>

      <!-- Bottom scroll spacer — reserves room so the latest user bubble
           can always be scrolled to the TOP of the visible area (without
           this, short conversations can't scroll enough and the user's
           reply anchors mid-panel instead of at the top). Only rendered
           once at least one user bubble exists; on the first assistant-
           only turn it would otherwise push the greeting off the top
           of the viewport. -->
      <div
        v-if="hasUserMessage"
        class="flex-shrink-0"
        :style="{ height: scrollSpacerHeight + 'px' }"
        aria-hidden="true"
      ></div>
    </div>

    <!-- Stop streaming button — full-width pill below the message list -->
    <div v-if="streaming" class="px-4 pb-2 flex-shrink-0">
      <button
        @click="cancelStreaming"
        class="w-full py-1.5 text-xs font-medium text-neutral-500 bg-savannah-100 hover:bg-savannah-200 rounded-lg transition-colors"
      >
        Stop generating
      </button>
    </div>

    <!-- Suggestions panel — collapsible list of route-aware prompts.
         Hidden during onboarding because the director drives turns with
         state-specific bubbles; a generic suggestions list is chrome
         leakage that confuses the flow. -->
    <div
      v-if="suggestedPrompts.length > 0 && !isOnboardingActive"
      class="flex-shrink-0 border-t border-light-gray"
    >
      <button
        @click="suggestionsCollapsed = !suggestionsCollapsed"
        class="w-full flex items-center justify-between px-4 py-2 text-xs font-semibold text-neutral-500 uppercase tracking-wider hover:bg-savannah-100 transition-colors"
      >
        <span>Suggestions</span>
        <span class="text-[10px] font-medium normal-case tracking-normal">{{ suggestionsCollapsed ? 'show' : 'hide' }}</span>
      </button>
      <div v-if="!suggestionsCollapsed" class="px-4 pb-3 space-y-1.5">
        <button
          v-for="prompt in suggestedPrompts"
          :key="prompt"
          @click="sendSuggested(prompt)"
          class="w-full text-left px-3 py-2 text-sm bg-savannah-100 hover:bg-savannah-200 border border-light-gray rounded-lg transition-colors text-neutral-500"
        >
          {{ prompt }}
        </button>
      </div>
    </div>

    <!-- ============================================================
         Input area — preview CTA, skip link, textarea, send button.
         Drag handle is rendered only in docked mode (the modal panel
         doesn't need vertical resize because its max-height is fixed).
         ============================================================ -->
    <div
      ref="inputContainer"
      :data-docked-input="docked ? '' : null"
      class="flex-shrink-0 border-t border-light-gray"
      :style="docked && dockedInputHeight ? { height: dockedInputHeight + 'px' } : {}"
    >
      <!-- Drag handle (docked only) -->
      <div
        v-if="docked"
        class="flex items-center justify-center h-3 cursor-row-resize group"
        @mousedown.prevent="startInputResize"
      >
        <div class="w-10 h-1 rounded-full bg-neutral-300 group-hover:bg-neutral-400 transition-colors"></div>
      </div>

      <div :class="docked ? 'flex flex-col h-[calc(100%-12px)] px-4 pb-4' : (isMobile ? 'px-4 py-3' : 'px-6 py-4')">
        <!-- Phase 13 — preview signup CTA after an advice short-circuit -->
        <div v-if="previewCta" class="mb-2">
          <router-link
            :to="previewCta.route"
            class="inline-flex items-center px-4 py-2 text-sm font-semibold text-white bg-spring-500 rounded-lg hover:bg-spring-600 transition-colors"
          >
            {{ previewCta.label }}
          </router-link>
        </div>

        <!-- Phase 10 — inline skip link for grouped_extract states (spouse) -->
        <div v-if="skipLink" class="mb-2">
          <button
            @click="handleSkipLink"
            type="button"
            class="text-sm font-semibold text-raspberry-500 underline hover:text-raspberry-600"
          >
            {{ skipLink.label }}
          </button>
        </div>

        <div :class="docked ? 'flex items-end gap-2 flex-1 min-h-0' : 'flex gap-2'">
          <textarea
            ref="inputField"
            v-model="inputMessage"
            @keydown.enter.exact.prevent="send"
            :placeholder="inputPlaceholder"
            :rows="docked ? undefined : 1"
            :disabled="streaming || loading || tokenLimitReached"
            :class="[
              'flex-1 resize-none rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent disabled:bg-savannah-100 disabled:cursor-not-allowed',
              docked
                ? 'min-w-0 h-full border border-light-gray text-horizon-500 placeholder-neutral-500 py-2.5'
                : 'border border-horizon-300',
              { 'opacity-60': streaming || tokenLimitReached }
            ]"
          ></textarea>
          <button
            @click="send"
            :disabled="!canSend"
            :class="[
              'bg-raspberry-600 text-white rounded-lg hover:bg-raspberry-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center text-sm font-semibold',
              docked ? 'flex-shrink-0 px-4 py-2.5' : 'px-4 py-2'
            ]"
          >
            Send
          </button>
        </div>

        <p :class="docked ? 'text-xs text-horizon-400 mt-1.5 flex-shrink-0' : 'text-xs text-horizon-400 mt-1.5'">
          Not regulated financial advice.<br>Press Enter to send.
        </p>
      </div>
    </div>

  </AiChatPanelShell>
</template>

<script>
import { mapGetters, mapActions } from 'vuex';
import AiMessageContent from './AiMessageContent.vue';
import AiChatPanelShell from './AiChatPanelShell.vue';
import FynQuickReplies from '@/components/Fyn/FynQuickReplies.vue';

import analyticsService from '@/services/analyticsService';
import { matchNavigationIntent } from '@/utils/chatNavigationRouter';
import { fynIconUrl } from '@/constants/fynIcon';

export default {
    name: 'AiChatPanel',

    components: {
        AiChatPanelShell,
        AiMessageContent,
        FynQuickReplies,
    },

    props: {
        docked: {
            type: Boolean,
            default: false,
        },
    },

    emits: ['collapse'],

    data() {
        return {
            fynIconUrl,
            inputMessage: '',
            windowWidth: window.innerWidth,
            dockedInputHeight: 0,
            _defaultInputHeight: 0,
            suggestionsCollapsed: true,
            _resizing: false,
            _resizeStartY: 0,
            _resizeStartHeight: 0,
            thinkingStatusIndex: 0,
            _thinkingTimer: null,
            countdownSeconds: null,
            _countdownTimer: null,
            scrollSpacerHeight: 300,
        };
    },

    computed: {
        ...mapGetters('aiChat', [
            'isOpen',
            'conversations',
            'currentConversation',
            'messages',
            'streaming',
            'streamingText',
            'loading',
            'loadingConversations',
            'error',
            'tokenLimitReached',
            'secondsUntilReset',
            'showHistory',
            'pendingNavigation',
            'skipLink',
            'previewCta',
            'onboardingLayout',
            'isOnboardingActive',
        ]),

        // True once the user has sent at least one message in this
        // conversation. Gates the bottom scroll spacer — on the very
        // first turn (assistant-only welcome) the spacer would otherwise
        // push the greeting off the top of the viewport.
        hasUserMessage() {
            return Array.isArray(this.messages) && this.messages.some((m) => m && m.role === 'user');
        },

        inputPlaceholder() {
            if (this.tokenLimitReached) return 'Daily limit reached — resets at midnight';
            return 'Ask Fyn...';
        },

        // Header / messages-container class strings vary by layout density.
        // Modal headers are the white card chrome (px-4/6 py-3/4); docked
        // headers are the compact bg-light-gray strip (px-4 py-2.5).
        // These computeds keep the template free of inline ternaries.
        headerOuterClasses() {
            if (this.docked) {
                return 'flex-shrink-0 border-b border-light-gray bg-light-gray px-4 py-2.5';
            }
            return ['border-b border-light-gray', this.isMobile ? 'px-4 py-3' : 'px-6 py-4'];
        },

        headerTitleClusterClasses() {
            if (this.docked) return 'flex items-center gap-2';
            // Modal mobile centres the title between the left close button
            // and the right control buttons; modal desktop left-aligns it.
            return this.isMobile ? 'flex-1 text-center' : '';
        },

        headerTitleClasses() {
            if (this.docked) return 'text-sm font-bold text-horizon-500';
            return ['font-semibold text-horizon-500', this.isMobile ? 'text-base' : 'text-lg'];
        },

        headerButtonClasses() {
            const base = 'text-horizon-400 hover:text-neutral-500 hover:bg-savannah-100 rounded-full transition-colors';
            if (this.docked) return `${base} p-1.5`;
            return `${base} ${this.isMobile ? 'p-2' : 'p-1.5'}`;
        },

        headerButtonIconClasses() {
            if (this.docked) return 'w-4 h-4';
            return this.isMobile ? 'w-5 h-5' : 'w-4 h-4';
        },

        messagesContainerClasses() {
            if (this.docked) return 'flex-1 overflow-y-auto px-4 py-3 space-y-4 scrollbar-thin';
            return [
                'flex-1 overflow-y-auto space-y-4',
                this.isMobile ? 'p-4' : 'p-6',
            ];
        },

        messagesContainerStyle() {
            if (this.docked || this.isMobile) return {};
            return { minHeight: '200px', maxHeight: '400px' };
        },

        resetCountdown() {
            if (!this.countdownSeconds || this.countdownSeconds <= 0) return 'shortly';
            const hours = Math.floor(this.countdownSeconds / 3600);
            const minutes = Math.floor((this.countdownSeconds % 3600) / 60);
            if (hours > 0) return `${hours}h ${minutes}m`;
            return `${minutes}m`;
        },

        isMobile() {
            return this.windowWidth < 768;
        },

        thinkingStatusMessages() {
            return [
                'Processing your request',
                'Reviewing your financial data',
                'Checking your accounts',
                'Analysing your position',
                'Running calculations',
                'Preparing your response',
            ];
        },

        thinkingStatus() {
            return this.thinkingStatusMessages[this.thinkingStatusIndex % this.thinkingStatusMessages.length];
        },

        canSend() {
            return this.inputMessage.trim().length > 0 && !this.streaming && !this.loading && !this.tokenLimitReached;
        },

        // Index of the most recent quick_replies message in the message
        // list. Used to disable historical bubble sets so the user cannot
        // click an earlier answer after they have moved past it. Returns
        // -1 if no quick_replies messages exist.
        latestQuickRepliesIndex() {
            for (let i = this.messages.length - 1; i >= 0; i--) {
                if (this.messages[i]?.role === 'quick_replies') {
                    return i;
                }
            }
            return -1;
        },

        suggestedPrompts() {
            const route = this.$route?.path || '';
            const prompts = {
                '/dashboard': [
                    'What should I focus on first?',
                    'How is my financial health overall?',
                    'What are my top recommendations?',
                ],
                '/net-worth/retirement': [
                    'Am I on track for retirement?',
                    'What if I increase my pension contributions?',
                    'When can I afford to retire?',
                ],
                '/net-worth/cash': [
                    'How is my emergency fund looking?',
                    'Where should I save next?',
                    'Am I using my Individual Savings Account allowance?',
                ],
                '/net-worth/investments': [
                    'How is my portfolio performing?',
                    'Is my asset allocation right for me?',
                    'What investment fees am I paying?',
                ],
                '/net-worth/property': [
                    'What is my property portfolio worth?',
                    'How much equity do I have?',
                    'Should I consider remortgaging?',
                ],
                '/net-worth': [
                    'What is my total net worth?',
                    'How are my assets allocated?',
                    'What are my biggest liabilities?',
                ],
                '/protection': [
                    'Do I have enough life cover?',
                    'What protection gaps do I have?',
                    'How much income protection do I need?',
                ],
                '/estate': [
                    'What is my Inheritance Tax position?',
                    'How can I reduce my estate tax?',
                    'Do I need to update my will?',
                ],
                '/goals': [
                    'Am I on track with my goals?',
                    'Help me create a savings goal',
                    'What life events should I plan for?',
                ],
            };

            for (const [prefix, items] of Object.entries(prompts)) {
                if (route.startsWith(prefix)) {
                    return items;
                }
            }

            return prompts['/dashboard'];
        },
    },

    mounted() {
        this.handleResize = () => {
            this.windowWidth = window.innerWidth;
            this.updateScrollSpacer();
        };
        window.addEventListener('resize', this.handleResize);

        // Every route change (/dashboard ↔ /profile during a profile-review
        // pause, for example) destroys AppLayout and therefore destroys
        // this AiChatPanel instance — AppLayout is mounted inside each view
        // rather than above the router-view. When we mount fresh we start
        // at scrollTop=0, which from the user's POV looks like the chat
        // rewound to the welcome message. If Vuex state tells us there IS
        // an active conversation with user turns, anchor the scroll to the
        // latest user bubble so the current prompt stays visible.
        this.$nextTick(() => {
            if (this.hasUserMessage) {
                this.scrollToLastUserMessage();
            }
        });

        // In docked mode, auto-open and load conversations immediately
        // dispatch('open') sets isOpen=true which triggers the watcher to call onOpen()
        // Skip on mobile — docked panel is CSS-hidden, floating panel handles chat instead
        if (this.docked && window.innerWidth >= 1024) {
            this.$store.dispatch('aiChat/open');

            // Measure natural input height after render to use as default & minimum
            this.$nextTick(() => {
                const inputContainer = this.$refs.inputContainer;
                if (inputContainer) {
                    const naturalHeight = inputContainer.offsetHeight;
                    this._defaultInputHeight = naturalHeight;
                    this.dockedInputHeight = naturalHeight;
                }
                this.updateScrollSpacer();
            });
        } else {
            this.$nextTick(() => this.updateScrollSpacer());
        }
    },

    beforeUnmount() {
        if (this.handleResize) {
            window.removeEventListener('resize', this.handleResize);
        }
        if (this._onResizeMove) {
            document.removeEventListener('mousemove', this._onResizeMove);
        }
        if (this._onResizeEnd) {
            document.removeEventListener('mouseup', this._onResizeEnd);
        }
        if (this._thinkingTimer) {
            clearInterval(this._thinkingTimer);
        }
        if (this._countdownTimer) {
            clearInterval(this._countdownTimer);
        }
    },

    watch: {
        isOpen(newVal) {
            if (newVal) {
                this.onOpen();
            }
        },

        secondsUntilReset(newVal) {
            if (this._countdownTimer) {
                clearInterval(this._countdownTimer);
                this._countdownTimer = null;
            }
            if (newVal && newVal > 0) {
                this.countdownSeconds = newVal;
                this._countdownTimer = setInterval(() => {
                    this.countdownSeconds--;
                    if (this.countdownSeconds <= 0) {
                        clearInterval(this._countdownTimer);
                        this._countdownTimer = null;
                        this.$store.commit('aiChat/SET_TOKEN_LIMIT', { reached: false, resetAt: null, secondsUntilReset: null });
                    }
                }, 1000);
            }
        },

        messages(newMessages, oldMessages) {
            // Anchor the latest user bubble to the TOP of the chat viewport so
            // Fyn's reply streams in below it and stays visible. If there's
            // no user bubble yet (first turn of onboarding), fall back to
            // scrollToBottom so the assistant's content is visible.
            if (!newMessages || !oldMessages) return;
            if (newMessages.length <= oldMessages.length) return;
            const lastMsg = newMessages[newMessages.length - 1];
            if (!lastMsg) return;

            if (lastMsg.role === 'user') {
                this.$nextTick(() => this.scrollToLastUserMessage());
            } else if (lastMsg.role === 'assistant' || lastMsg.role === 'quick_replies') {
                this.$nextTick(() => {
                    const container = this.$refs.messagesContainer || this.$refs.dockedMessagesContainer;
                    if (!container) return;
                    const userBubbles = container.querySelectorAll('.bg-raspberry-500, .bg-raspberry-600');
                    if (userBubbles.length > 0) {
                        this.scrollToLastUserMessage();
                    } else {
                        // No user bubble yet (first turn / resumed onboarding).
                        // Scroll to TOP so the welcome + bubbles are visible.
                        // scrollToBottom() here hides the top of the welcome
                        // when combined content exceeds the container height.
                        container.scrollTop = 0;
                    }
                });
            }
        },

        streaming(isStreaming) {
            if (isStreaming) {
                // Scroll user message to top now that thinking indicator is in the DOM
                this.$nextTick(() => this.scrollToLastUserMessage());
                // Start rotating status messages
                this.thinkingStatusIndex = 0;
                this._thinkingTimer = setInterval(() => {
                    this.thinkingStatusIndex++;
                }, 2500);
            } else {
                // Stop rotating status messages
                if (this._thinkingTimer) {
                    clearInterval(this._thinkingTimer);
                    this._thinkingTimer = null;
                }
            }
        },

        pendingNavigation(routePath) {
            if (routePath) {
                this.handleNavigation(routePath);
                this.$store.commit('aiChat/SET_PENDING_NAVIGATION', null);
            }
        },

        // Re-anchor the chat to the latest turn whenever the onboarding
        // layout flips. Entering a profile-review pause shrinks the
        // aside (712→356) and leaving restores it; both transitions also
        // push the Vue Router, which causes the underlying <router-view>
        // to swap and re-render. Without a delayed scroll the container
        // ends up at scrollTop=0 (visible = welcome) because the
        // router-view swap races our initial scroll call.
        //
        // Two-phase scroll: one on the next tick (in case nothing races
        // us), and one on a 200 ms timeout to outlast the router-view
        // re-render and any streamed follow-up. Both call the same
        // helper — if the first already anchored correctly the second
        // is a no-op.
        // Re-anchor the chat when the layout flips WITHOUT a route change.
        // Route-change-driven transitions (entering and leaving a profile-
        // review pause) destroy and remount AppLayout / AiChatPanel, so
        // the scroll re-anchor happens in mounted() rather than here.
        // This watcher still runs for same-route layout flips (defensive).
        onboardingLayout() {
            this.$nextTick(() => {
                const container = this.$refs.messagesContainer || this.$refs.dockedMessagesContainer;
                if (!container) return;
                if (this.hasUserMessage) {
                    this.scrollToLastUserMessage();
                }
            });
        },
    },

    methods: {
        ...mapActions('aiChat', [
            'close',
            'toggle',
            'toggleHistory',
            'fetchConversations',
            'startNewConversation',
            'loadConversation',
            'deleteConversation',
            'sendMessage',
            'abortStreaming',
            'postAction',
        ]),

        /**
         * Phase 10 — skip-link click handler. Posts {action: 'skip'} to the
         * new action endpoint, which the director routes through handleSkipAction.
         */
        async handleSkipLink() {
            if (this.streaming) return;
            await this.postAction('skip');
        },

        /**
         * Phase 13 — record-card "View" button. Navigates to the relevant
         * module page for the captured record.
         */
        handleRecordView(record) {
            // Each entity type the create_* handlers emit needs an explicit
            // entry — falling back to /dashboard makes the View link feel
            // broken. Aliases keep both the historical short keys
            // ("life_insurance") AND the canonical long keys the protection
            // handler emits ("life_insurance_policy") working.
            const routeMap = {
                savings_account: '/net-worth/cash',
                investment_account: '/net-worth/investments',
                holding: '/net-worth/investments',
                dc_pension: '/net-worth/retirement',
                db_pension: '/net-worth/retirement',
                pension: '/net-worth/retirement',
                property: '/net-worth/property',
                mortgage: '/net-worth/property',
                life_insurance: '/protection',
                life_insurance_policy: '/protection',
                critical_illness: '/protection',
                critical_illness_policy: '/protection',
                income_protection: '/protection',
                income_protection_policy: '/protection',
                protection_policy: '/protection',
                trust: '/trusts',
                business_interest: '/net-worth/business',
                chattel: '/net-worth/chattels',
                liability: '/net-worth/liabilities',
                estate_liability: '/net-worth/liabilities',
                asset: '/net-worth/wealth-summary',
                estate_asset: '/net-worth/wealth-summary',
                estate_gift: '/estate',
                family_member: '/profile?section=family',
                will: '/estate/will-builder',
                power_of_attorney: '/estate/power-of-attorney',
                lasting_power_of_attorney: '/estate/power-of-attorney',
                goal: '/goals',
                life_event: '/goals?tab=events',
                what_if_scenario: '/planning/what-if',
            };
            const route = routeMap[record.type] || '/dashboard';
            this.$router.push(route);
        },

        formatEntityType(type) {
            // CLAUDE.md Rule #10 — no acronyms in user-facing text. ISA is
            // the only permitted abbreviation; everything else is spelled
            // out ("Lasting Power of Attorney", not "LPA").
            const labels = {
                savings_account: 'Savings account',
                investment_account: 'Investment account',
                holding: 'Investment holding',
                dc_pension: 'Pension',
                db_pension: 'Pension',
                pension: 'Pension',
                property: 'Property',
                mortgage: 'Mortgage',
                life_insurance: 'Life insurance',
                life_insurance_policy: 'Life insurance',
                critical_illness: 'Critical illness',
                critical_illness_policy: 'Critical illness',
                income_protection: 'Income protection',
                income_protection_policy: 'Income protection',
                protection_policy: 'Protection policy',
                trust: 'Trust',
                business_interest: 'Business interest',
                chattel: 'Valuable',
                liability: 'Liability',
                estate_liability: 'Liability',
                asset: 'Asset',
                estate_asset: 'Asset',
                estate_gift: 'Gift',
                family_member: 'Family member',
                will: 'Will',
                power_of_attorney: 'Lasting Power of Attorney',
                lasting_power_of_attorney: 'Lasting Power of Attorney',
                goal: 'Goal',
                life_event: 'Life event',
                what_if_scenario: 'What-if scenario',
            };
            return labels[type] || type.replace(/_/g, ' ');
        },

        /**
         * Personalised one-line label for a created record card. Combines
         * the user-facing entity name (e.g. provider) with the friendly
         * type label so the card reads "Aviva — Life insurance" rather
         * than the bare "life_insurance_policy" slug.
         */
        formatRecordCardLabel(record) {
            const typeLabel = this.formatEntityType(record.type);
            const name = (record.name || '').trim();
            if (name === '' || name.toLowerCase() === typeLabel.toLowerCase()) {
                return typeLabel;
            }
            return `${name} — ${typeLabel}`;
        },

        startInputResize(e) {
            this._resizing = true;
            this._resizeStartY = e.clientY;
            this._resizeStartHeight = this.dockedInputHeight;
            document.body.style.cursor = 'row-resize';
            document.body.style.userSelect = 'none';
            this._onResizeMove = (ev) => {
                if (!this._resizing) return;
                const delta = this._resizeStartY - ev.clientY;
                const newHeight = Math.max(this._defaultInputHeight, Math.min(400, this._resizeStartHeight + delta));
                this.dockedInputHeight = newHeight;
            };
            this._onResizeEnd = () => {
                this._resizing = false;
                document.body.style.cursor = '';
                document.body.style.userSelect = '';
                document.removeEventListener('mousemove', this._onResizeMove);
                document.removeEventListener('mouseup', this._onResizeEnd);
            };
            document.addEventListener('mousemove', this._onResizeMove);
            document.addEventListener('mouseup', this._onResizeEnd);
        },

        async onOpen() {
            analyticsService.trackChatOpened();

            const s = () => this.$store.state.aiChat;

            // If there's already an active conversation with messages or streaming,
            // don't replace it — just fetch the conversation list for history.
            const hasActive = () => s().currentConversation
                && (s().messages.length > 0 || s().streaming);

            // Another action may have kicked off a conversation while the
            // panel was mounting (Dashboard.vue dispatches
            // startOnboardingConversation in parallel when openFyn=journey).
            // Skip our own initialiser if we can see it is in flight.
            const busy = () => s().loading || s().streaming;

            if (hasActive() || busy()) {
                await this.fetchConversations();
                this.$nextTick(() => this.$refs.inputField?.focus());
                return;
            }

            await this.fetchConversations();

            // RE-CHECK after the fetch. Dashboard.vue's
            // startOnboardingConversation may have populated the store
            // while we were awaiting the conversations list — creating a
            // fresh empty conversation at this point would orphan the
            // real onboarding conversation and split the transcript.
            if (hasActive() || busy()) {
                this.$nextTick(() => this.$refs.inputField?.focus());
                return;
            }

            // If the user is mid-onboarding from a previous tab/session,
            // resume the director flow instead of starting a blank chat.
            const user = this.$store.getters['auth/user'];
            const isMidOnboarding = !!(
                user
                && user.onboarding_completed === false
                && user.onboarding_fyn_step
            );

            if (isMidOnboarding) {
                // startOnboardingConversation detects in_progress via the
                // /status endpoint and loads the existing conversation.
                await this.$store.dispatch('aiChat/startOnboardingConversation');
            } else {
                await this.startNewConversation();
            }

            this.$nextTick(() => {
                this.$refs.inputField?.focus();
            });
        },

        async startNew() {
            // Clear current state and start fresh
            this.$store.commit('aiChat/SET_MESSAGES', []);
            this.$store.commit('aiChat/SET_SHOW_HISTORY', false);
            this.$store.commit('aiChat/SET_STREAMING_TEXT', '');
            this.$store.commit('aiChat/SET_ERROR', null);
            await this.startNewConversation();
            this.$nextTick(() => {
                this.$refs.inputField?.focus();
            });
        },

        closePanel() {
            // Clear conversation state so next open starts fresh
            this.$store.commit('aiChat/SET_CURRENT_CONVERSATION', null);
            this.$store.commit('aiChat/SET_MESSAGES', []);
            this.$store.commit('aiChat/SET_SHOW_HISTORY', false);
            this.$store.commit('aiChat/SET_STREAMING_TEXT', '');
            this.close();
            window.dispatchEvent(new Event('fyn-chat-interaction'));
        },

        cancelStreaming() {
            this.abortStreaming();
        },

        async send() {
            if (!this.canSend) return;
            window.dispatchEvent(new Event('fyn-chat-interaction'));

            const message = this.inputMessage.trim();
            this.inputMessage = '';

            // Check for navigation intent — handle locally without LLM call
            const navMatch = matchNavigationIntent(message);
            if (navMatch) {
                // Add user message to chat
                this.$store.commit('aiChat/ADD_MESSAGE', {
                    id: 'user_' + Date.now(),
                    role: 'user',
                    content: message,
                    created_at: new Date().toISOString(),
                });
                // Add assistant response locally
                this.$store.commit('aiChat/ADD_MESSAGE', {
                    id: 'nav_' + Date.now(),
                    role: 'assistant',
                    content: navMatch.response,
                    created_at: new Date().toISOString(),
                });
                // Navigate
                this.handleNavigation(navMatch.route);
                return;
            }

            analyticsService.trackChatMessageSent(message.length);
            await this.sendMessage(message);
        },

        async sendSuggested(prompt) {
            const message = prompt.trim();
            if (!message || this.streaming || this.loading) return;
            window.dispatchEvent(new Event('fyn-chat-interaction'));

            this.inputMessage = '';

            const navMatch = matchNavigationIntent(message);
            if (navMatch) {
                this.$store.commit('aiChat/ADD_MESSAGE', {
                    id: 'user_' + Date.now(),
                    role: 'user',
                    content: message,
                    created_at: new Date().toISOString(),
                });
                this.$store.commit('aiChat/ADD_MESSAGE', {
                    id: 'nav_' + Date.now(),
                    role: 'assistant',
                    content: navMatch.response,
                    created_at: new Date().toISOString(),
                });
                this.handleNavigation(navMatch.route);
                return;
            }

            analyticsService.trackChatMessageSent(message.length);
            await this.sendMessage(message);
        },

        // Handler for FynQuickReplies clicks. Two modes:
        //   - Action bubbles (parent msg.metadata.action_bubbles === true):
        //     dispatch postAction(bubble.id) so the director routes via
        //     handleAction (resume / continue / restart / skip / something_else).
        //     The bubble's label must NOT be sent as a user message — that
        //     would persist a sentinel like "Continue" to ai_messages and
        //     OnboardingDirector::handleUserMessage would treat it as a state
        //     response, producing a duplicate turn and overwriting the
        //     conversation title via HasAiChat's first-message title hook.
        //   - Regular bubbles: send the bubble's label as if the user had
        //     typed it. The matchNavigationIntent check is skipped because
        //     onboarding bubbles are never navigation intents.
        async handleQuickReplySelect(bubble, msg) {
            if (this.streaming || this.loading) return;
            const isActionBubble = Boolean(msg?.metadata?.action_bubbles);
            const id = (bubble && bubble.id) ? String(bubble.id).trim() : '';
            const label = (bubble && bubble.label) ? bubble.label.trim() : '';
            if (isActionBubble) {
                if (!id) return;
                window.dispatchEvent(new Event('fyn-chat-interaction'));
                analyticsService.trackChatMessageSent(label.length);
                await this.postAction(id);
                return;
            }
            if (!label) return;
            window.dispatchEvent(new Event('fyn-chat-interaction'));
            analyticsService.trackChatMessageSent(label.length);
            await this.sendMessage(label);
        },

        handleNavigation(routePath) {
            // Parse query strings properly for Vue Router
            if (routePath && routePath.includes('?')) {
                const [path, queryString] = routePath.split('?');
                const query = {};
                new URLSearchParams(queryString).forEach((value, key) => {
                    query[key] = value;
                });
                this.$router.push({ path, query });
            } else {
                this.$router.push(routePath);
            }
        },

        scrollToBottom() {
            const container = this.$refs.messagesContainer || this.$refs.dockedMessagesContainer;
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        },

        scrollToLastUserMessage() {
            const container = this.$refs.messagesContainer || this.$refs.dockedMessagesContainer;
            if (!container) return;

            const userBubbles = container.querySelectorAll('.bg-raspberry-500, .bg-raspberry-600');
            const lastBubble = userBubbles[userBubbles.length - 1];

            if (!lastBubble) {
                container.scrollTop = container.scrollHeight;
                return;
            }

            // Ensure the bottom spacer is sized so there's enough scroll room
            // to push the user bubble flush with the top.
            this.updateScrollSpacer();

            // Direct scrollTop math — more reliable than scrollIntoView which
            // browsers may skip if the element is already "visible enough".
            // Walk up offsetParent chain to account for nested positioning.
            const containerRect = container.getBoundingClientRect();
            const bubbleRect = lastBubble.getBoundingClientRect();
            const delta = bubbleRect.top - containerRect.top;
            container.scrollTop = container.scrollTop + delta;
        },

        updateScrollSpacer() {
            const container = this.$refs.messagesContainer || this.$refs.dockedMessagesContainer;
            if (!container) return;
            // Reserve scroll room equal to the container viewport minus a
            // single message worth of padding. This guarantees the latest
            // user bubble can always reach the top of the visible area.
            const target = Math.max(200, container.clientHeight - 100);
            if (this.scrollSpacerHeight !== target) {
                this.scrollSpacerHeight = target;
            }
        },

        scrollToLastAssistantMessage() {
            const container = this.$refs.messagesContainer || this.$refs.dockedMessagesContainer;
            if (!container) return;

            // Match both assistant text bubbles (bg-savannah-100) and the
            // FynQuickReplies wrapper (.fyn-quick-replies) so grouped and
            // bubble turns both scroll into view correctly.
            const targets = container.querySelectorAll('.bg-savannah-100, .fyn-quick-replies');
            const last = targets[targets.length - 1];
            if (last) {
                last.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } else {
                this.scrollToBottom();
            }
        },

        messageClass(msg) {
            if (msg.role === 'user') {
                return 'bg-raspberry-500 text-white';
            }
            if (msg.role === 'navigation' || msg.role === 'entity_created') {
                return 'bg-transparent p-0';
            }
            // Pinned by tests/Feature/Fyn/CaptureCompleteStylingTest.php —
            // capture_complete bubbles assert this exact substring so the
            // capture-confirmation card stays visually identical to a
            // normal assistant bubble. Don't append chat-bubble corner
            // tweaks (rounded-bl-sm / rounded-br-sm) here; those belong
            // on the bubble's outer class array in the template.
            return 'bg-savannah-100 border border-light-gray text-horizon-500';
        },

        formatRelativeTime(dateStr) {
            if (!dateStr) return '';
            const date = new Date(dateStr);
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);
            const diffHours = Math.floor(diffMs / 3600000);
            const diffDays = Math.floor(diffMs / 86400000);

            if (diffMins < 1) return 'Just now';
            if (diffMins < 60) return `${diffMins}m ago`;
            if (diffHours < 24) return `${diffHours}h ago`;
            if (diffDays < 7) return `${diffDays}d ago`;
            return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
        },
    },
};
</script>

<style scoped>
/* Use dynamic viewport height for mobile to stay above the virtual keyboard */
@supports (height: 100dvh) {
    .chat-mobile-container {
        height: 100dvh;
    }
}

/* Fade transition for rotating thinking status */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
