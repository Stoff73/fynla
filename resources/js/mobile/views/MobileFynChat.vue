<template>
  <div class="fyn-chat flex flex-col h-full bg-eggshell-500">
    <!-- Messages area -->
    <div
      ref="messagesArea"
      class="flex-1 overflow-y-auto px-4 pt-4 pb-2 space-y-3"
    >
      <!-- Suggested prompts when no messages -->
      <SuggestedPrompts
        v-if="!hasMessages && !streaming"
        @select="handlePromptSelect"
      />

      <!-- Message list -->
      <template v-for="(msg, index) in messages" :key="msg.id || index">
        <ChatBubble
          v-if="msg.role === 'user' || msg.role === 'assistant'"
          :role="msg.role"
          :content="msg.content"
          :metadata="msg.metadata"
        />

        <!-- Navigation message -->
        <div
          v-else-if="msg.role === 'navigation'"
          class="flex justify-center"
        >
          <button
            class="text-sm text-violet-500 bg-violet-50 rounded-lg px-3 py-1.5 font-medium"
            @click="handleNavigation(msg.metadata?.route_path)"
          >
            {{ msg.metadata?.description || 'Navigate' }}
          </button>
        </div>

        <!-- Tool execution -->
        <ToolExecutionStatus
          v-else-if="msg.role === 'entity_created'"
          :message="msg.content"
        />
      </template>

      <!-- Streaming response -->
      <ChatBubble
        v-if="streaming && streamingText"
        role="assistant"
        :content="streamingText"
      />

      <!-- Typing indicator while streaming with no text yet -->
      <TypingIndicator v-if="streaming && !streamingText" />

      <!-- Tool execution indicator -->
      <ToolExecutionStatus v-if="loading && !streaming" />

      <!-- Error message -->
      <div v-if="error" class="flex justify-center">
        <div class="bg-light-pink-100 text-raspberry-500 rounded-xl px-4 py-2.5 text-sm max-w-[85%]">
          <p class="font-medium">Something went wrong</p>
          <p class="text-xs mt-1 opacity-80">{{ error }}</p>
          <button
            class="mt-2 text-xs font-medium underline"
            @click="$store.commit('aiChat/SET_ERROR', null)"
          >
            Dismiss
          </button>
        </div>
      </div>

      <div ref="scrollAnchor"></div>
    </div>

    <!-- Input bar -->
    <div class="flex-shrink-0 border-t border-light-gray bg-white px-4 py-3"
         :style="{ paddingBottom: `calc(${keyboardOffset}px + env(safe-area-inset-bottom, 0px) + 12px)` }">
      <div class="flex items-end gap-2">
        <textarea
          ref="inputField"
          v-model="inputText"
          :disabled="streaming || loading"
          placeholder="How can I help you?"
          rows="1"
          class="flex-1 bg-eggshell-500 rounded-2xl px-4 py-2.5 text-sm text-horizon-500
                 placeholder-neutral-500 resize-none outline-none focus:ring-2 focus:ring-violet-500
                 max-h-32 overflow-y-auto"
          @keydown.enter.exact="handleSend"
          @input="autoResize"
        ></textarea>

        <!-- Voice input button (lazy loaded) -->
        <VoiceInputButton
          v-if="voiceAvailable"
          @transcript="handleVoiceTranscript"
          @partial="handleVoicePartial"
        />

        <!-- Send button -->
        <button
          :disabled="!canSend"
          class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center transition-colors"
          :class="canSend ? 'bg-raspberry-500 text-white' : 'bg-neutral-200 text-neutral-400'"
          @click="handleSend"
        >
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 19V5m0 0l-7 7m7-7l7 7" />
          </svg>
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import { defineAsyncComponent } from 'vue';
import { mapGetters, mapActions } from 'vuex';
import ChatBubble from '@/mobile/ChatBubble.vue';
import TypingIndicator from '@/mobile/TypingIndicator.vue';
import ToolExecutionStatus from '@/mobile/ToolExecutionStatus.vue';
import SuggestedPrompts from '@/mobile/SuggestedPrompts.vue';

export default {
  name: 'MobileFynChat',

  components: {
    ChatBubble,
    TypingIndicator,
    ToolExecutionStatus,
    SuggestedPrompts,
    VoiceInputButton: defineAsyncComponent(() => import('@/mobile/VoiceInputButton.vue')),
  },

  data() {
    return {
      inputText: '',
      keyboardOffset: 0,
      voiceAvailable: false,
    };
  },

  computed: {
    ...mapGetters('aiChat', [
      'messages',
      'streaming',
      'streamingText',
      'loading',
      'error',
      'hasConversation',
      'prefilledPrompt',
    ]),

    hasMessages() {
      return this.messages.length > 0;
    },

    canSend() {
      return this.inputText.trim().length > 0 && !this.streaming && !this.loading;
    },
  },

  watch: {
    messages: {
      handler() {
        this.$nextTick(() => this.scrollToBottom());
      },
      deep: true,
    },

    streamingText() {
      this.$nextTick(() => this.scrollToBottom());
    },

    error(val) {
      if (val) {
        this.$nextTick(() => this.scrollToBottom());
      }
    },

    prefilledPrompt(prompt) {
      if (prompt) {
        this.inputText = prompt;
        this.$store.commit('aiChat/SET_PREFILLED_PROMPT', null);
        this.$nextTick(() => {
          if (this.$refs.inputField) {
            this.$refs.inputField.focus();
          }
        });
      }
    },
  },

  async mounted() {
    // Start conversation immediately — don't block on voice check
    if (!this.hasConversation) {
      await this.startNewConversation();
    }

    // Handle prefilled prompt
    if (this.prefilledPrompt) {
      this.inputText = this.prefilledPrompt;
      this.$store.commit('aiChat/SET_PREFILLED_PROMPT', null);
    }

    // Keyboard handling for Capacitor
    this.setupKeyboardListeners();
    this.scrollToBottom();

    // Check voice availability in background (non-blocking)
    this.checkVoiceAvailability();
  },

  beforeUnmount() {
    this.removeKeyboardListeners();
  },

  methods: {
    ...mapActions('aiChat', ['sendMessage', 'startNewConversation', 'abortStreaming']),

    async checkVoiceAvailability() {
      if (typeof window !== 'undefined' && window.Capacitor) {
        try {
          const { SpeechRecognition } = await import('@capacitor-community/speech-recognition');
          const { available } = await SpeechRecognition.available();
          this.voiceAvailable = available;
        } catch {
          this.voiceAvailable = false;
        }
      } else {
        this.voiceAvailable = typeof window !== 'undefined'
          && ('SpeechRecognition' in window || 'webkitSpeechRecognition' in window);
      }
    },

    async handleSend(event) {
      if (event && event.type === 'keydown') {
        event.preventDefault();
      }

      const text = this.inputText.trim();
      if (!text || this.streaming || this.loading) return;

      this.inputText = '';
      this.resetTextarea();

      if (!this.hasConversation) {
        await this.startNewConversation();
      }

      await this.sendMessage(text);
    },

    handlePromptSelect(prompt) {
      this.inputText = prompt;
      this.$nextTick(() => this.handleSend());
    },

    handleVoiceTranscript(transcript) {
      this.inputText = transcript;
      this.$nextTick(() => this.autoResize());
    },

    handleVoicePartial(partial) {
      this.inputText = partial;
      this.$nextTick(() => this.autoResize());
    },

    handleNavigation(routePath) {
      if (routePath) {
        this.$router.push(routePath);
      }
    },

    scrollToBottom() {
      if (this.$refs.scrollAnchor) {
        this.$refs.scrollAnchor.scrollIntoView({ behavior: 'smooth' });
      }
    },

    autoResize() {
      const el = this.$refs.inputField;
      if (!el) return;
      el.style.height = 'auto';
      el.style.height = Math.min(el.scrollHeight, 128) + 'px';
    },

    resetTextarea() {
      if (this.$refs.inputField) {
        this.$refs.inputField.style.height = 'auto';
      }
    },

    async setupKeyboardListeners() {
      if (typeof window !== 'undefined' && window.Capacitor?.Plugins?.Keyboard) {
        const { Keyboard } = window.Capacitor.Plugins;
        this._keyboardShowHandle = await Keyboard.addListener('keyboardWillShow', (info) => {
          this.keyboardOffset = info.keyboardHeight || 0;
          this.$nextTick(() => this.scrollToBottom());
        });
        this._keyboardHideHandle = await Keyboard.addListener('keyboardWillHide', () => {
          this.keyboardOffset = 0;
        });
      }
    },

    async removeKeyboardListeners() {
      await this._keyboardShowHandle?.remove();
      await this._keyboardHideHandle?.remove();
    },
  },
};
</script>

<style scoped>
.fyn-chat {
  /* Ensure chat fills available space in MobileLayout */
  min-height: 0;
}
</style>
