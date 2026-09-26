<template>
  <AppLayout>
    <div class="py-8 max-w-3xl">
      <router-link to="/actions" class="text-body-sm text-horizon-400 hover:text-raspberry-500">All actions</router-link>

      <div v-if="loading" class="flex items-center justify-center h-64">
        <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
      </div>

      <p v-else-if="notFound" class="mt-6 text-body text-neutral-500">
        This action is not in your list any more.
      </p>

      <!-- One action's detail card (design C). Every field is the server's
           (ActionCardService); this view computes nothing. -->
      <article v-else-if="card" class="action-card module-gradient mt-4">
        <div class="flex flex-wrap items-center gap-2 mb-3">
          <span v-if="card.done" class="chip bg-spring-100 text-spring-700">Done{{ doneOn ? ' · ' + doneOn : '' }}</span>
          <span v-else-if="card.deadline" class="chip bg-violet-100 text-violet-700">{{ card.deadline.label }}</span>
          <span class="text-xs font-semibold text-neutral-500">{{ eyebrow }}</span>
        </div>

        <h1 class="text-h4 font-display font-bold text-horizon-500">{{ card.title }}</h1>
        <p v-if="card.description" class="mt-2 text-body text-neutral-600">{{ card.description }}</p>

        <section v-if="card.why.length" class="mt-6">
          <h2 class="section-title">Why this matters for you</h2>
          <ul class="list-disc pl-5 space-y-1 text-body-sm text-horizon-500">
            <li v-for="line in card.why" :key="line">{{ line }}</li>
          </ul>
        </section>

        <section v-if="card.what_this_changes.length" class="mt-6">
          <h2 class="section-title">What this changes</h2>
          <ul class="list-disc pl-5 space-y-1 text-body-sm text-horizon-500">
            <li v-for="line in card.what_this_changes" :key="line">{{ line }}</li>
          </ul>
        </section>

        <div v-if="card.key_figure" class="key-figure mt-6">
          <p class="text-xs font-semibold text-neutral-500">{{ card.key_figure.label }}</p>
          <p class="text-h4 font-bold text-horizon-500">{{ card.key_figure.value }}</p>
          <p v-if="card.key_figure.sub" class="text-xs text-neutral-500">{{ card.key_figure.sub }}</p>
        </div>

        <section v-if="card.funding && card.funding.accounts.length && !card.done" class="mt-6">
          <h2 class="section-title">Fund from</h2>
          <div class="space-y-2">
            <label
              v-for="acc in card.funding.accounts"
              :key="acc.type + ':' + acc.id"
              class="flex items-start gap-3 p-3 rounded-lg border border-light-gray cursor-pointer"
            >
              <input
                type="radio"
                name="fund-from"
                class="mt-1"
                :value="acc.type + ':' + acc.id"
                :checked="acc.id === card.funding.selected_id && acc.type === card.funding.selected_type"
                @change="saveFunding(acc)"
              >
              <span class="min-w-0">
                <span class="block text-body-sm font-semibold text-horizon-500">{{ acc.name }} ({{ formatCurrency(acc.balance) }})</span>
                <span v-if="acc.warning" class="block text-xs text-violet-700 mt-0.5">{{ acc.warning }}</span>
              </span>
            </label>
          </div>
        </section>

        <section v-if="card.how_to.length" class="mt-6">
          <h2 class="section-title">How to do it</h2>
          <ol class="list-decimal pl-5 space-y-1 text-body-sm text-horizon-500">
            <li v-for="(step, i) in card.how_to" :key="i" data-testid="how-to-step">{{ step }}</li>
          </ol>
        </section>

        <p v-if="card.conflict_note" class="mt-6 text-body-sm text-neutral-600">{{ card.conflict_note }}</p>
        <p v-if="card.disclaimer" class="mt-6 text-xs text-neutral-500">{{ card.disclaimer }}</p>

        <div class="mt-8 flex flex-wrap gap-3">
          <button type="button" class="btn-secondary" data-testid="ask-fyn" @click="askFyn">Ask Fyn about this</button>
          <button
            v-if="!card.done && card.go_to"
            type="button"
            class="btn-secondary"
            data-testid="go-to"
            @click="goTo"
          >Go to it</button>
          <button
            v-if="!card.done && card.primary && card.primary.kind === 'mark_done'"
            type="button"
            class="btn-primary"
            data-testid="mark-done"
            :disabled="marking"
            @click="markDone"
          >Mark as done</button>
          <button
            v-else-if="!card.done && card.primary && card.primary.kind === 'capture'"
            type="button"
            class="btn-primary"
            data-testid="primary-capture"
            @click="openFynWith(card.primary.prompt)"
          >Add it now</button>
          <button
            v-else-if="!card.done && card.primary && card.primary.kind === 'navigate'"
            type="button"
            class="btn-primary"
            data-testid="primary-navigate"
            @click="navigate"
          >Go to it</button>
        </div>
      </article>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import api from '@/services/api';
import { currencyMixin } from '@/mixins/currencyMixin';
import logger from '@/utils/logger';
import { resolveWebDestination } from '@/utils/semanticDestinations';

export default {
  name: 'ActionCardView',
  components: { AppLayout },
  mixins: [currencyMixin],

  data() {
    return { card: null, loading: true, notFound: false, marking: false };
  },

  computed: {
    actionId() {
      return String(this.$route.params.actionId || '');
    },
    eyebrow() {
      return [this.card.module_label, this.card.topic].filter(Boolean).join(' · ');
    },
    doneOn() {
      if (!this.card.completed_at) return '';
      const d = new Date(this.card.completed_at);
      return isNaN(d.getTime()) ? '' : d.toLocaleDateString('en-GB');
    },
  },

  watch: {
    actionId() {
      this.load();
    },
  },

  mounted() {
    this.load();
  },

  methods: {
    async load() {
      this.loading = true;
      this.notFound = false;
      try {
        const { data } = await api.get(`/recommendations/actions/${encodeURIComponent(this.actionId)}`);
        this.card = data?.data ?? null;
      } catch (e) {
        if (e?.response?.status === 404) this.notFound = true;
        else logger.error('[ActionCard] load failed:', e);
      } finally {
        this.loading = false;
      }
    },

    async markDone() {
      if (this.marking) return;
      this.marking = true;
      try {
        await api.post(`/recommendations/${encodeURIComponent(this.card.id)}/mark-done`, {
          module: this.card.module || 'general',
          recommendation_text: this.card.title || '',
        });
        await this.load();
      } catch (e) {
        logger.error('[ActionCard] mark-done failed:', e);
      } finally {
        this.marking = false;
      }
    },

    async saveFunding(acc) {
      try {
        await api.put('/plans/tax/funding-source', {
          action_category: this.card.id.replace(/^tax_/, ''),
          target_account_id: 0,
          funding_source_type: acc.type,
          funding_source_id: acc.id,
        });
        this.card.funding.selected_id = acc.id;
        this.card.funding.selected_type = acc.type;
      } catch (e) {
        logger.error('[ActionCard] funding save failed:', e);
      }
    },

    openFyn() {
      this.$store.dispatch('aiChat/open');
      window.dispatchEvent(new Event('fyn-open-chat'));
    },

    openFynWith(prompt) {
      this.$store.dispatch('aiChat/prefillPrompt', prompt);
      this.openFyn();
    },

    async askFyn() {
      const ask = this.card.ask_fyn;
      if (ask && ask.kind === 'contextual') {
        await this.$store.dispatch('aiChat/startContextualConversation', ask.request);
        this.openFyn();
        return;
      }
      this.openFynWith(ask && ask.prompt);
    },

    goTo() {
      const route = resolveWebDestination(this.card.go_to.destination);
      if (route) this.$router.push(route);
    },

    navigate() {
      const route = resolveWebDestination(this.card.primary.destination);
      if (route) this.$router.push(route);
    },
  },
};
</script>

<style scoped>
.action-card {
  @apply bg-white rounded-card border border-light-gray p-6;
}

.chip {
  @apply text-xs font-semibold px-2.5 py-0.5 rounded-full;
}

.section-title {
  @apply text-body-sm font-bold text-horizon-500 mb-2;
}

.key-figure {
  @apply bg-eggshell-500 rounded-lg p-4;
}
</style>
