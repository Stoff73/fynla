<template>
  <component :is="docked ? 'div' : Teleport" :to="docked ? undefined : 'body'" :class="docked ? 'flex flex-col w-full h-full' : ''">
    <Transition
      v-if="!docked"
      enter-active-class="transition ease-out duration-200"
      :enter-from-class="isMobile ? 'translate-y-full opacity-0' : 'translate-y-4 opacity-0'"
      :enter-to-class="isMobile ? 'translate-y-0 opacity-100' : 'translate-y-0 opacity-100'"
      leave-active-class="transition ease-in duration-150"
      :leave-from-class="isMobile ? 'translate-y-0 opacity-100' : 'translate-y-0 opacity-100'"
      :leave-to-class="isMobile ? 'translate-y-full opacity-0' : 'translate-y-4 opacity-0'"
    >
      <div
        v-if="isOpen"
        :class="[
          isMobile
            ? 'fixed inset-0 z-[70] flex flex-col bg-white chat-mobile-container'
            : 'fixed bottom-24 right-6 w-[420px] max-w-[calc(100vw-2rem)] z-[70] bg-white rounded-lg border border-light-gray shadow-md flex flex-col transition-all duration-200'
        ]"
        :style="isMobile ? {} : { maxHeight: 'calc(100vh - 8rem)' }"
      >
        <!-- Card Header -->
        <div :class="[
          'border-b border-light-gray',
          isMobile ? 'px-4 py-3' : 'px-6 py-4'
        ]">
          <div class="flex items-center justify-between">
            <!-- Mobile: close button on the left -->
            <button
              v-if="isMobile"
              @click="closePanel"
              class="p-2 -ml-2 text-neutral-500 hover:text-horizon-500 rounded-full transition-colors"
              title="Close"
            >
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>

            <h3 :class="[
              'font-semibold text-horizon-500',
              isMobile ? 'text-base flex-1 text-center' : 'text-lg'
            ]">Fynla Assistant</h3>

            <div class="flex items-center gap-1">
              <!-- New conversation -->
              <button
                @click="startNew"
                :class="[
                  'text-horizon-400 hover:text-neutral-500 hover:bg-savannah-100 rounded-full transition-colors',
                  isMobile ? 'p-2' : 'p-1.5'
                ]"
                title="New conversation"
              >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" :class="isMobile ? 'w-5 h-5' : 'w-4 h-4'">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
              </button>
              <!-- History toggle -->
              <button
                @click="toggleHistory"
                :class="[
                  'text-horizon-400 hover:text-neutral-500 hover:bg-savannah-100 rounded-full transition-colors',
                  isMobile ? 'p-2' : 'p-1.5',
                  { 'bg-savannah-100 text-neutral-500': showHistory }
                ]"
                title="Conversation history"
              >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" :class="isMobile ? 'w-5 h-5' : 'w-4 h-4'">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </button>
              <!-- Close (desktop only) -->
              <button
                v-if="!isMobile"
                @click="closePanel"
                class="p-1.5 text-horizon-400 hover:text-neutral-500 hover:bg-savannah-100 rounded-full transition-colors"
                title="Close"
              >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>
        </div>

        <!-- History drawer -->
        <Transition
          enter-active-class="transition ease-out duration-200"
          enter-from-class="-translate-y-2 opacity-0"
          enter-to-class="translate-y-0 opacity-100"
          leave-active-class="transition ease-in duration-150"
          leave-from-class="translate-y-0 opacity-100"
          leave-to-class="-translate-y-2 opacity-0"
        >
          <div v-if="showHistory" class="border-b border-light-gray bg-savannah-100 max-h-48 overflow-y-auto">
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
                class="w-full text-left px-4 py-2.5 hover:bg-savannah-100 border-b border-light-gray
                       transition-colors flex items-center justify-between group"
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
                  class="p-1 text-horizon-400 hover:text-raspberry-500 opacity-0 group-hover:opacity-100 transition-all"
                  title="Delete conversation"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                  </svg>
                </button>
              </button>
            </div>
          </div>
        </Transition>

        <!-- Card Body - Messages area -->
        <div
          ref="messagesContainer"
          :class="[
            'flex-1 overflow-y-auto space-y-4',
            isMobile ? 'p-4' : 'p-6'
          ]"
          :style="isMobile ? { minHeight: '0' } : { minHeight: '200px', maxHeight: '400px' }"
        >
          <!-- Loading state -->
          <div v-if="loading" class="flex items-center justify-center py-8">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-raspberry-600"></div>
          </div>

          <!-- Empty state with suggested prompts -->
          <div v-else-if="messages.length === 0 && !streaming" class="flex flex-col items-center justify-center py-4">
            <p class="text-sm text-neutral-500 mb-4">How can I help with your finances?</p>

            <div class="space-y-2 w-full">
              <button
                v-for="prompt in suggestedPrompts"
                :key="prompt"
                @click="sendSuggested(prompt)"
                class="w-full text-left px-3 py-2 text-sm bg-savannah-100 hover:bg-savannah-100
                       border border-light-gray rounded-lg transition-colors text-neutral-500"
              >
                {{ prompt }}
              </button>
            </div>
          </div>

          <!-- Message list -->
          <template v-else>
            <div
              v-for="msg in messages"
              :key="msg.id"
              class="flex"
              :class="msg.role === 'user' ? 'justify-end' : 'justify-start'"
            >
              <div
                class="max-w-[85%] rounded-lg px-3 py-2"
                :class="messageClass(msg)"
              >
                <AiMessageContent
                  :message="msg"
                  @navigate="handleNavigation"
                />
              </div>
            </div>

            <!-- Streaming indicator -->
            <div v-if="streaming" class="flex justify-start">
              <div class="max-w-[85%] rounded-lg px-3 py-2 bg-savannah-100 border border-light-gray">
                <div v-if="streamingText" class="text-sm leading-relaxed text-horizon-500">
                  <AiMessageContent
                    :message="{ role: 'assistant', content: streamingText }"
                  />
                  <span class="inline-block w-1.5 h-4 bg-raspberry-600 ml-0.5 animate-pulse"></span>
                </div>
                <div v-else class="flex items-center gap-2">
                  <div class="flex gap-1">
                    <span class="w-2 h-2 bg-horizon-400 rounded-full animate-bounce" style="animation-delay: 0ms"></span>
                    <span class="w-2 h-2 bg-horizon-400 rounded-full animate-bounce" style="animation-delay: 150ms"></span>
                    <span class="w-2 h-2 bg-horizon-400 rounded-full animate-bounce" style="animation-delay: 300ms"></span>
                  </div>
                  <span class="text-xs text-neutral-500">Thinking...</span>
                </div>
              </div>
            </div>

            <!-- Cancel streaming button -->
            <div v-if="streaming" class="flex justify-center py-2">
              <button
                @click="cancelStreaming"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-body-sm text-neutral-500 hover:text-raspberry-500 bg-white border border-light-gray rounded-full shadow-sm hover:shadow transition-all"
              >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
                Stop generating
              </button>
            </div>
          </template>

          <!-- Error message -->
          <div v-if="error" class="p-3 bg-raspberry-50 border border-raspberry-200 rounded-lg text-sm text-raspberry-700">
            {{ error }}
          </div>
        </div>

        <!-- Card Footer - Input area -->
        <div :class="[
          'border-t border-light-gray bg-savannah-100',
          isMobile ? 'px-4 py-3' : 'px-6 py-4 rounded-b-lg'
        ]">
          <div class="flex gap-2">
            <textarea
              ref="inputField"
              v-model="inputMessage"
              @keydown.enter.exact.prevent="send"
              placeholder="Ask about your finances..."
              rows="1"
              :disabled="streaming || loading"
              class="flex-1 resize-none rounded-lg border border-horizon-300 px-3 py-2 text-sm
                     focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent
                     disabled:bg-savannah-100 disabled:cursor-not-allowed"
              :class="{ 'opacity-60': streaming }"
            ></textarea>
            <button
              @click="send"
              :disabled="!canSend"
              class="px-3 py-2 bg-raspberry-600 text-white rounded-lg hover:bg-raspberry-700
                     transition-colors disabled:opacity-50 disabled:cursor-not-allowed
                     flex items-center justify-center"
            >
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
              </svg>
            </button>
          </div>
          <p class="text-xs text-horizon-400 mt-1.5">
            Not regulated financial advice. Press Enter to send.
          </p>
        </div>
      </div>
    </Transition>
    <!-- Docked mode: full-height inline panel, always visible -->
    <div
      v-if="docked"
      class="flex flex-col bg-white w-full h-full"
    >
      <!-- Docked Header -->
      <div class="flex items-center justify-between px-4 py-3 flex-shrink-0 border-b border-light-gray">
        <h3 class="text-sm font-semibold text-horizon-500">Fynla Assistant</h3>
        <div class="flex items-center gap-1">
          <button
            @click="startNew"
            class="p-1.5 text-horizon-400 hover:text-neutral-500 hover:bg-savannah-100 rounded-full transition-colors"
            title="New conversation"
          >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
          </button>
          <button
            @click="toggleHistory"
            class="p-1.5 text-horizon-400 hover:text-neutral-500 hover:bg-savannah-100 rounded-full transition-colors"
            :class="{ 'bg-savannah-100 text-neutral-500': showHistory }"
            title="Conversation history"
          >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Docked Messages -->
      <div ref="dockedMessagesContainer" class="flex-1 overflow-y-auto px-4 py-3 space-y-4 scrollbar-thin">
        <!-- Empty state -->
        <div v-if="!messages || messages.length === 0" class="flex flex-col items-center justify-center h-full text-center py-8">
          <div class="w-12 h-12 bg-savannah-100 rounded-full flex items-center justify-center mb-3">
            <svg class="w-6 h-6 text-raspberry-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
            </svg>
          </div>
          <p class="text-sm font-medium text-horizon-500 mb-1">Hi, I'm Fyn</p>
          <p class="text-xs text-neutral-500">Ask me anything about your finances</p>
        </div>

        <!-- Messages -->
        <template v-for="(msg, idx) in messages" :key="idx">
          <div :class="msg.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
            <div :class="[
              'max-w-[85%] rounded-lg px-3 py-2 text-sm',
              msg.role === 'user'
                ? 'bg-raspberry-500 text-white rounded-br-sm'
                : 'bg-savannah-100 text-horizon-500 rounded-bl-sm'
            ]">
              <AiMessageContent v-if="msg.role === 'assistant'" :message="msg" @navigate="handleNavigation" />
              <span v-else>{{ msg.content }}</span>
            </div>
          </div>
        </template>

        <!-- Streaming indicator -->
        <div v-if="streaming" class="flex justify-start">
          <div class="max-w-[85%] rounded-lg px-3 py-2 text-sm bg-savannah-100 text-horizon-500 rounded-bl-sm">
            <AiMessageContent v-if="streamingText" :message="{ role: 'assistant', content: streamingText }" />
            <span v-else class="flex items-center gap-1 text-neutral-500">
              <span class="w-1.5 h-1.5 bg-neutral-500 rounded-full animate-bounce" style="animation-delay: 0ms"></span>
              <span class="w-1.5 h-1.5 bg-neutral-500 rounded-full animate-bounce" style="animation-delay: 150ms"></span>
              <span class="w-1.5 h-1.5 bg-neutral-500 rounded-full animate-bounce" style="animation-delay: 300ms"></span>
            </span>
          </div>
        </div>
      </div>

      <!-- Stop streaming button (docked) -->
      <div v-if="streaming" class="px-4 pb-2 flex-shrink-0">
        <button @click="cancelStreaming" class="w-full py-1.5 text-xs font-medium text-neutral-500 bg-savannah-100 hover:bg-savannah-200 rounded-lg transition-colors">
          Stop generating
        </button>
      </div>

      <!-- Docked Input -->
      <div class="flex-shrink-0 border-t border-light-gray p-4">
        <div class="flex items-end gap-2">
          <textarea
            ref="dockedInputField"
            v-model="inputMessage"
            @keydown.enter.exact.prevent="send"
            placeholder="Ask Fyn anything..."
            rows="1"
            class="flex-1 min-w-0 resize-none rounded-lg border border-light-gray px-3 py-2.5 text-sm text-horizon-500 placeholder-neutral-500
                   focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent
                   disabled:bg-savannah-100 disabled:cursor-not-allowed"
            :class="{ 'opacity-60': streaming }"
          ></textarea>
          <button
            @click="send"
            :disabled="!canSend"
            class="flex-shrink-0 p-2.5 bg-raspberry-600 text-white rounded-lg hover:bg-raspberry-700
                   transition-colors disabled:opacity-50 disabled:cursor-not-allowed
                   flex items-center justify-center"
          >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
            </svg>
          </button>
        </div>
        <p class="text-xs text-horizon-400 mt-1.5">
          Not regulated financial advice. Press Enter to send.
        </p>
      </div>
    </div>
  </component>
</template>

<script>
import { mapGetters, mapActions } from 'vuex';
import AiMessageContent from './AiMessageContent.vue';

import analyticsService from '@/services/analyticsService';

export default {
    name: 'AiChatPanel',

    components: {
        AiMessageContent,
    },

    props: {
        docked: {
            type: Boolean,
            default: false,
        },
    },

    data() {
        return {
            inputMessage: '',
            windowWidth: window.innerWidth,
            Teleport: 'Teleport',
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
            'showHistory',
            'pendingNavigation',
        ]),

        isMobile() {
            return this.windowWidth < 768;
        },

        canSend() {
            return this.inputMessage.trim().length > 0 && !this.streaming && !this.loading;
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
        this.handleResize = () => { this.windowWidth = window.innerWidth; };
        window.addEventListener('resize', this.handleResize);

        // In docked mode, auto-open and load conversations immediately
        if (this.docked) {
            this.$store.dispatch('aiChat/open');
            this.onOpen();
        }
    },

    beforeUnmount() {
        if (this.handleResize) {
            window.removeEventListener('resize', this.handleResize);
        }
    },

    watch: {
        isOpen(newVal) {
            if (newVal) {
                this.onOpen();
            }
        },

        messages() {
            this.$nextTick(() => this.scrollToBottom());
        },

        streamingText() {
            this.$nextTick(() => this.scrollToBottom());
        },

        pendingNavigation(routePath) {
            if (routePath) {
                this.$router.push(routePath);
                this.$store.commit('aiChat/SET_PENDING_NAVIGATION', null);
            }
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
        ]),

        async onOpen() {
            analyticsService.trackChatOpened();

            await this.fetchConversations();

            if (!this.currentConversation) {
                await this.startNewConversation();
            }

            this.$nextTick(() => {
                this.$refs.inputField?.focus();
            });
        },

        async startNew() {
            await this.startNewConversation();
            this.$nextTick(() => {
                this.$refs.inputField?.focus();
            });
        },

        closePanel() {
            this.close();
        },

        cancelStreaming() {
            this.abortStreaming();
        },

        async send() {
            if (!this.canSend) return;

            const message = this.inputMessage.trim();
            this.inputMessage = '';

            analyticsService.trackChatMessageSent(message.length);
            await this.sendMessage(message);
        },

        sendSuggested(prompt) {
            this.inputMessage = prompt;
            this.send();
        },

        handleNavigation(routePath) {
            this.$router.push(routePath);
        },

        scrollToBottom() {
            const container = this.$refs.messagesContainer || this.$refs.dockedMessagesContainer;
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        },

        messageClass(msg) {
            if (msg.role === 'user') {
                return 'bg-raspberry-600 text-white';
            }
            if (msg.role === 'navigation' || msg.role === 'entity_created') {
                return 'bg-transparent p-0';
            }
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
</style>
