<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition ease-out duration-200"
      enter-from-class="translate-y-4 opacity-0"
      enter-to-class="translate-y-0 opacity-100"
      leave-active-class="transition ease-in duration-150"
      leave-from-class="translate-y-0 opacity-100"
      leave-to-class="translate-y-4 opacity-0"
    >
      <div
        v-if="isOpen"
        class="fixed bottom-24 right-6 w-[420px] max-w-[calc(100vw-2rem)] z-[70]
               bg-white rounded-lg border border-gray-200 shadow-md
               flex flex-col transition-all duration-200"
        style="max-height: calc(100vh - 8rem);"
      >
        <!-- Card Header -->
        <div class="px-6 py-4 border-b border-gray-200">
          <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900">Fynla Assistant</h3>
            <div class="flex items-center gap-1">
              <!-- New conversation -->
              <button
                @click="startNew"
                class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full transition-colors"
                title="New conversation"
              >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
              </button>
              <!-- History toggle -->
              <button
                @click="toggleHistory"
                class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full transition-colors"
                :class="{ 'bg-gray-100 text-gray-600': showHistory }"
                title="Conversation history"
              >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </button>
              <!-- Close -->
              <button
                @click="closePanel"
                class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full transition-colors"
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
          <div v-if="showHistory" class="border-b border-gray-200 bg-gray-50 max-h-48 overflow-y-auto">
            <div v-if="loadingConversations" class="p-4 text-center">
              <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-primary-600 mx-auto"></div>
            </div>
            <div v-else-if="conversations.length === 0" class="p-4 text-center text-sm text-gray-500">
              No previous conversations
            </div>
            <div v-else>
              <button
                v-for="conv in conversations"
                :key="conv.id"
                @click="loadConversation(conv.id)"
                class="w-full text-left px-4 py-2.5 hover:bg-gray-100 border-b border-gray-100
                       transition-colors flex items-center justify-between group"
                :class="{ 'bg-blue-50': currentConversation?.id === conv.id }"
              >
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium text-gray-900 truncate">
                    {{ conv.title || 'New conversation' }}
                  </p>
                  <p class="text-xs text-gray-500 mt-0.5">
                    {{ formatRelativeTime(conv.last_message_at || conv.created_at) }}
                  </p>
                </div>
                <button
                  @click.stop="deleteConversation(conv.id)"
                  class="p-1 text-gray-400 hover:text-red-500 opacity-0 group-hover:opacity-100 transition-all"
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
        <div ref="messagesContainer" class="flex-1 overflow-y-auto p-6 space-y-4" style="min-height: 200px; max-height: 400px;">
          <!-- Loading state -->
          <div v-if="loading" class="flex items-center justify-center py-8">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
          </div>

          <!-- Empty state with suggested prompts -->
          <div v-else-if="messages.length === 0 && !streaming" class="flex flex-col items-center justify-center py-4">
            <p class="text-sm text-gray-500 mb-4">How can I help with your finances?</p>

            <div class="space-y-2 w-full">
              <button
                v-for="prompt in suggestedPrompts"
                :key="prompt"
                @click="sendSuggested(prompt)"
                class="w-full text-left px-3 py-2 text-sm bg-gray-50 hover:bg-gray-100
                       border border-gray-200 rounded-lg transition-colors text-gray-700"
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
              <div class="max-w-[85%] rounded-lg px-3 py-2 bg-gray-50 border border-gray-200">
                <div v-if="streamingText" class="text-sm leading-relaxed text-gray-800">
                  <AiMessageContent
                    :message="{ role: 'assistant', content: streamingText }"
                  />
                  <span class="inline-block w-1.5 h-4 bg-primary-600 ml-0.5 animate-pulse"></span>
                </div>
                <div v-else class="flex items-center gap-2">
                  <div class="flex gap-1">
                    <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0ms"></span>
                    <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 150ms"></span>
                    <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 300ms"></span>
                  </div>
                  <span class="text-xs text-gray-500">Thinking...</span>
                </div>
              </div>
            </div>
          </template>

          <!-- Error message -->
          <div v-if="error" class="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
            {{ error }}
          </div>
        </div>

        <!-- Card Footer - Input area -->
        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-lg">
          <div class="flex gap-2">
            <textarea
              ref="inputField"
              v-model="inputMessage"
              @keydown.enter.exact.prevent="send"
              placeholder="Ask about your finances..."
              rows="1"
              :disabled="streaming || loading"
              class="flex-1 resize-none rounded-lg border border-gray-300 px-3 py-2 text-sm
                     focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent
                     disabled:bg-gray-100 disabled:cursor-not-allowed"
              :class="{ 'opacity-60': streaming }"
            ></textarea>
            <button
              @click="send"
              :disabled="!canSend"
              class="px-3 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700
                     transition-colors disabled:opacity-50 disabled:cursor-not-allowed
                     flex items-center justify-center"
            >
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
              </svg>
            </button>
          </div>
          <p class="text-xs text-gray-400 mt-1.5">
            Not regulated financial advice. Press Enter to send.
          </p>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script>
import { mapGetters, mapActions } from 'vuex';
import AiMessageContent from './AiMessageContent.vue';

export default {
    name: 'AiChatPanel',

    components: {
        AiMessageContent,
    },

    data() {
        return {
            inputMessage: '',
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
        ]),

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
                '/retirement': [
                    'Am I on track for retirement?',
                    'What if I increase my pension contributions?',
                    'When can I afford to retire?',
                ],
                '/protection': [
                    'Do I have enough life cover?',
                    'What protection gaps do I have?',
                    'How much income protection do I need?',
                ],
                '/savings': [
                    'How is my emergency fund looking?',
                    'Where should I save next?',
                    'Am I using my Individual Savings Account allowance?',
                ],
                '/investment': [
                    'How is my portfolio performing?',
                    'Is my asset allocation right for me?',
                    'What investment fees am I paying?',
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
        ]),

        async onOpen() {
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

        async send() {
            if (!this.canSend) return;

            const message = this.inputMessage.trim();
            this.inputMessage = '';

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
            const container = this.$refs.messagesContainer;
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        },

        messageClass(msg) {
            if (msg.role === 'user') {
                return 'bg-primary-600 text-white';
            }
            if (msg.role === 'navigation' || msg.role === 'entity_created') {
                return 'bg-transparent p-0';
            }
            return 'bg-gray-50 border border-gray-200 text-gray-800';
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
