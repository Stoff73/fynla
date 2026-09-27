<template>
  <MobileChrome
    ref="chrome"
    :title="card ? card.module_label : 'Action'"
    :subtitle="card && card.topic ? card.topic : 'One of your actions'"
    :edit-details="false"
    :loading="loading"
    loading-label="this action"
    back
    @back="goBack"
  >
    <p v-if="error" class="mac-error">{{ error }}</p>
    <p v-else-if="notFound" class="m-sub">This action is not in your list any more.</p>

    <!-- One action's card (design C). Every field is the server's
         (ActionCardService); this view computes nothing. -->
    <article v-else-if="card" class="m-card mac">
      <p class="mac-eyebrow">
        <span v-if="card.done" class="mac-chip mac-chip--done">Done{{ doneOn ? ' · ' + doneOn : '' }}</span>
        <span v-else-if="card.deadline" class="mac-chip">{{ card.deadline.label }}</span>
        {{ eyebrow }}
      </p>
      <h1 class="mac-title">{{ card.title }}</h1>
      <p v-if="card.description" class="m-sub">{{ card.description }}</p>

      <section v-if="card.why.length" class="mac-section">
        <h2 class="m-section-label">Why this matters for you</h2>
        <ul class="mac-list"><li v-for="line in card.why" :key="line">{{ line }}</li></ul>
      </section>

      <section v-if="card.what_this_changes.length" class="mac-section">
        <h2 class="m-section-label">What this changes</h2>
        <ul class="mac-list"><li v-for="line in card.what_this_changes" :key="line">{{ line }}</li></ul>
      </section>

      <div v-if="card.key_figure" class="mac-figure">
        <p class="mac-figure__label">{{ card.key_figure.label }}</p>
        <p class="mac-figure__value">{{ card.key_figure.value }}</p>
        <p v-if="card.key_figure.sub" class="mac-figure__label">{{ card.key_figure.sub }}</p>
      </div>

      <section v-if="card.funding && card.funding.accounts.length && !card.done" class="mac-section">
        <h2 class="m-section-label">Fund from</h2>
        <label v-for="acc in card.funding.accounts" :key="acc.type + ':' + acc.id" class="mac-fund">
          <input
            type="radio"
            name="fund-from"
            :value="acc.type + ':' + acc.id"
            :checked="acc.id === card.funding.selected_id && acc.type === card.funding.selected_type"
            @change="saveFunding(acc)"
          >
          <span>
            <span class="mac-fund__name">{{ acc.name }} ({{ formatCurrency(acc.balance) }})</span>
            <span v-if="acc.warning" class="mac-fund__warn">{{ acc.warning }}</span>
          </span>
        </label>
      </section>

      <section v-if="card.how_to.length" class="mac-section">
        <h2 class="m-section-label">How to do it</h2>
        <ol class="mac-list mac-list--steps"><li v-for="(step, i) in card.how_to" :key="i">{{ step }}</li></ol>
      </section>

      <p v-if="card.conflict_note" class="m-sub mac-section">{{ card.conflict_note }}</p>
      <p v-if="card.disclaimer" class="mac-disclaimer">{{ card.disclaimer }}</p>

      <div class="mac-actions">
        <button type="button" class="m-btn-ghost" data-testid="ask-fyn" @click="askFyn">Ask Fyn about this</button>
        <button v-if="!card.done && card.go_to" type="button" class="m-btn-ghost" data-testid="go-to" @click="goTo">Go to it</button>
        <button
          v-if="!card.done && card.primary && card.primary.kind === 'mark_done'"
          type="button"
          class="m-btn"
          data-testid="mark-done"
          :disabled="marking"
          @click="markDone"
        >{{ marking ? 'Saving' : 'Mark as done' }}</button>
        <button
          v-else-if="!card.done && card.primary && card.primary.kind === 'capture'"
          type="button"
          class="m-btn"
          data-testid="primary-capture"
          @click="sendToFyn(card.primary.prompt)"
        >Add it now</button>
        <button
          v-else-if="!card.done && card.primary && card.primary.kind === 'navigate'"
          type="button"
          class="m-btn"
          data-testid="primary-navigate"
          @click="navigate"
        >Go to it</button>
      </div>
    </article>
  </MobileChrome>
</template>

<script>
import { store } from '../store.js';
import { apiGet, apiPost, apiPut } from '../api.js';
import { handleAuthExpiry } from '../authExpiry.js';
import { resolveMobileDestination, recordUnknownMobileDestination } from '../navigation/semanticDestinations.js';
import { formatCurrency } from '../utils/currency.js';
import MobileChrome from '../components/MobileChrome.vue';

export default {
  name: 'MobileActionCard',
  components: { MobileChrome },

  data() {
    return { card: null, loading: true, notFound: false, error: '', marking: false };
  },

  computed: {
    eyebrow() {
      return [this.card.module_label, this.card.topic].filter(Boolean).join(' · ');
    },
    doneOn() {
      if (!this.card.completed_at) return '';
      const d = new Date(this.card.completed_at);
      return isNaN(d.getTime()) ? '' : d.toLocaleDateString('en-GB');
    },
  },

  async created() {
    await this.load();
  },

  methods: {
    formatCurrency,

    goBack() {
      this.$router.push({ name: 'm-actions' });
    },

    async load() {
      this.loading = true;
      this.error = '';
      this.notFound = false;
      try {
        const res = await apiGet(`/api/recommendations/actions/${encodeURIComponent(String(this.$route.params.id || ''))}`, store.token);
        if (handleAuthExpiry(res, this.$router)) return;
        if (res.status === 404) {
          this.notFound = true;
          return;
        }
        if (!res.ok) {
          this.error = 'We could not load this action. Please try again.';
          return;
        }
        this.card = res.data?.data || null;
      } catch {
        this.error = 'Network error. Please try again.';
      } finally {
        this.loading = false;
      }
    },

    async markDone() {
      if (this.marking) return;
      this.marking = true;
      try {
        const res = await apiPost(`/api/recommendations/${encodeURIComponent(this.card.id)}/mark-done`, {
          module: this.card.module || 'general',
          recommendation_text: this.card.title || '',
        }, store.token);
        if (handleAuthExpiry(res, this.$router)) return;
        await this.load();
      } catch {
        this.error = 'We could not save that just now. Please try again.';
      } finally {
        this.marking = false;
      }
    },

    async saveFunding(acc) {
      const res = await apiPut('/api/plans/tax/funding-source', {
        action_category: this.card.id.replace(/^tax_/, ''),
        target_account_id: 0,
        funding_source_type: acc.type,
        funding_source_id: acc.id,
      }, store.token);
      if (handleAuthExpiry(res, this.$router)) return;
      if (res.ok) {
        this.card.funding.selected_id = acc.id;
        this.card.funding.selected_type = acc.type;
      } else {
        this.error = 'We could not save that account. Please try again.';
      }
    },

    async sendToFyn(prompt) {
      const chrome = this.$refs.chrome;
      if (!chrome) return;
      await chrome.openFyn();
      chrome.send(prompt);
    },

    async askFyn() {
      const ask = this.card.ask_fyn;
      if (ask && ask.kind === 'contextual') {
        await this.$refs.chrome?.openContextualFyn(ask.request);
        return;
      }
      await this.sendToFyn(ask && ask.prompt);
    },

    goTo() {
      const navigation = this.$router.push(resolveMobileDestination(this.card.go_to, recordUnknownMobileDestination));
      if (navigation?.catch) navigation.catch(() => {});
    },

    navigate() {
      const navigation = this.$router.push(resolveMobileDestination(this.card.primary, recordUnknownMobileDestination));
      if (navigation?.catch) navigation.catch(() => {});
    },
  },
};
</script>

<style scoped>
.mac { display: flex; flex-direction: column; gap: 8px; }
.mac-eyebrow { margin: 0; display: flex; flex-wrap: wrap; align-items: center; gap: 8px; font-size: 12px; font-weight: 700; color: var(--neutral-600); }
.mac-title { margin: 0; font-size: 20px; font-weight: 800; line-height: 1.3; color: var(--horizon-500); }
.mac-section { margin-top: 12px; }
.mac-list { margin: 0; padding-left: 18px; list-style: disc; display: flex; flex-direction: column; gap: 4px; font-size: 14px; line-height: 1.5; color: var(--horizon-500); }
.mac-list--steps { list-style: decimal; }
.mac-figure { margin-top: 12px; padding: 12px; border-radius: 12px; background: var(--eggshell-500); }
.mac-figure__label { margin: 0; font-size: 12px; color: var(--neutral-600); }
.mac-figure__value { margin: 0; font-size: 20px; font-weight: 800; color: var(--horizon-500); }
.mac-fund { display: flex; align-items: flex-start; gap: 10px; margin-top: 6px; padding: 10px; border: 1px solid var(--horizon-200); border-radius: 10px; }
.mac-fund__name { display: block; font-size: 14px; font-weight: 700; color: var(--horizon-500); }
.mac-fund__warn { display: block; margin-top: 2px; font-size: 12px; color: var(--violet-500); }
.mac-disclaimer { margin: 12px 0 0; font-size: 12px; color: var(--neutral-600); }
.mac-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 16px; }
.mac-chip { padding: 1px 9px; border: 1px solid var(--violet-500); border-radius: 999px; font-size: 12px; font-weight: 700; color: var(--violet-500); background: var(--white); }
.mac-chip--done { color: var(--spring-600); background: var(--spring-200); }
.mac-error { margin: 0 4px 12px; font-size: 13px; color: var(--raspberry-600); }
</style>
