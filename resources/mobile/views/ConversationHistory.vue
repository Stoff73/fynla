<template>
  <MobileChrome
    ref="chrome"
    title="Conversation History"
    subtitle="Return to your previous conversations with Fyn"
    :loading="loading"
    loading-label="your conversations"
    :edit-details="false"
    back
    @back="goBack"
  >
    <div v-if="error" class="m-card m-state" role="alert">
      <p class="m-err">{{ error }}</p>
      <button
        type="button"
        class="m-btn"
        data-testid="conversation-history-retry"
        @click="load"
      >Try again</button>
    </div>

    <div v-else-if="!conversations.length" class="m-card m-state">
      <p class="m-sub">No conversations yet. Start a chat with Fyn and it will appear here.</p>
    </div>

    <template v-else>
      <section
        v-for="section in sections"
        v-show="section.items.length"
        :key="section.mode"
        class="m-card mch-section"
        :data-testid="`history-${section.mode}`"
      >
        <h2 class="m-section-label mch-heading">{{ section.label }}</h2>
        <article
          v-for="conversation in section.items"
          :key="conversation.id"
          class="mch-conversation"
          :data-testid="`conversation-${conversation.id}`"
        >
          <div class="mch-head">
            <div>
              <h3 class="mch-purpose">{{ conversation.purpose }}</h3>
              <p v-if="conversation.title && conversation.title !== conversation.purpose" class="mch-title">
                {{ conversation.title }}
              </p>
            </div>
            <span class="mch-status">{{ statusLabel(conversation.status) }}</span>
          </div>

          <p v-if="conversation.related_entity?.available" class="mch-entity">
            {{ conversation.related_entity.label }}
          </p>
          <p v-else-if="conversation.related_entity?.explanation" class="mch-unavailable">
            {{ conversation.related_entity.explanation }}
          </p>
          <p v-if="conversation.last_message_summary" class="mch-summary">
            {{ conversation.last_message_summary }}
          </p>
          <p class="mch-time">{{ formatDateTime(conversation.last_message_at) }}</p>

          <div class="mch-actions">
            <button
              type="button"
              class="m-btn mch-open"
              :data-testid="`open-conversation-${conversation.id}`"
              @click="openTranscript(conversation.id)"
            >Open conversation</button>
            <button
              v-if="conversation.related_entity?.available === false"
              type="button"
              class="mch-fallback"
              :data-testid="`fallback-conversation-${conversation.id}`"
              @click="openFallback(conversation)"
            >Return to {{ fallbackLabel(conversation) }}</button>
          </div>
        </article>
      </section>
    </template>
  </MobileChrome>
</template>

<script>
import { apiGet } from '../api.js';
import { handleAuthExpiry } from '../authExpiry.js';
import MobileChrome from '../components/MobileChrome.vue';
import { resolveMobileDestination } from '../navigation/semanticDestinations.js';
import { store } from '../store.js';

const SECTION_DEFINITIONS = [
  { mode: 'onboarding', label: 'Onboarding' },
  { mode: 'contextual', label: 'Contextual' },
  { mode: 'general', label: 'General Fyn' },
];

export default {
  name: 'MobileConversationHistory',
  components: { MobileChrome },
  data: () => ({
    loading: true,
    error: '',
    conversations: [],
  }),
  computed: {
    sections() {
      return SECTION_DEFINITIONS.map((definition) => ({
        ...definition,
        items: this.conversations.filter((conversation) => conversation.mode === definition.mode),
      }));
    },
  },
  async created() {
    await this.load();
  },
  methods: {
    goBack() {
      this.$router.push('/dashboard');
    },
    statusLabel(status) {
      return status === 'paused' ? 'Paused' : 'Active';
    },
    formatDateTime(value) {
      if (!value) return '';
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return '';
      return date.toLocaleString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      });
    },
    fallbackLabel(conversation) {
      return conversation.fallback_destination?.screen?.replaceAll('_', ' ') || 'dashboard';
    },
    async openTranscript(conversationId) {
      await this.$refs.chrome?.openConversation(conversationId);
      await this.$refs.chrome?.revealLoadedConversation();
    },
    openFallback(conversation) {
      const path = resolveMobileDestination({ destination: conversation.fallback_destination });
      this.$router.push(path);
    },
    async load() {
      this.loading = true;
      this.error = '';
      this.conversations = [];
      try {
        const response = await apiGet('/api/ai-chat/conversations', store.token);
        if (handleAuthExpiry(response, this.$router)) return;
        if (!response.ok) {
          this.error = response.data?.message || 'We could not load your conversations.';
          return;
        }
        const history = response.data?.data || response.data || [];
        this.conversations = Array.isArray(history) ? history : [];
      } catch {
        this.error = 'Network error. Please try again.';
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>

<style scoped>
.mch-section { margin-bottom: 12px; }
.mch-heading { margin-top: 0; }
.mch-conversation { padding: 14px 0; border-bottom: 1px solid var(--light-gray); }
.mch-conversation:first-of-type { padding-top: 4px; }
.mch-conversation:last-child { padding-bottom: 0; border-bottom: 0; }
.mch-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
.mch-purpose { margin: 0; color: var(--horizon-500); font-size: 15px; font-weight: 800; }
.mch-title, .mch-entity, .mch-summary, .mch-time, .mch-unavailable { margin: 5px 0 0; }
.mch-title, .mch-time { color: var(--neutral-500); font-size: 12px; }
.mch-entity { color: var(--horizon-500); font-size: 13px; font-weight: 700; }
.mch-summary { color: var(--neutral-600); font-size: 13px; line-height: 1.45; }
.mch-unavailable { color: var(--raspberry-600); font-size: 13px; font-weight: 700; }
.mch-status { flex-shrink: 0; padding: 2px 8px; border-radius: var(--radius-full); color: var(--violet-600); background: var(--violet-50); font-size: 11px; font-weight: 800; text-transform: uppercase; }
.mch-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin-top: 12px; }
.mch-open { width: auto; margin: 0; }
.mch-fallback { padding: 0; border: 0; background: transparent; color: var(--raspberry-600); font-size: 12px; font-weight: 800; cursor: pointer; text-transform: capitalize; }
</style>
