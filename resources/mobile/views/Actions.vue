<template>
  <MobileChrome
    ref="chrome"
    title="Your actions"
    subtitle="Everything that's open, and what you've already done"
    :edit-details="false"
    :loading="loading"
    loading-label="your actions"
    back
    @back="goBack"
  >
    <p v-if="error" class="ma-error">{{ error }}</p>

    <MobileThresholdStrip :data="thresholds" />

    <section class="m-card" aria-labelledby="m-actions-open">
      <div class="ma-head">
        <h2 class="ma-head__title" id="m-actions-open">Open</h2>
        <span class="ma-count">{{ open.length }}</span>
      </div>

      <p v-if="!open.length" class="ma-empty">Nothing outstanding. Add more of your details and Fyn will suggest the next step.</p>

      <ul v-else class="ma-list">
        <li v-for="(item, index) in open" :key="item.id" class="ma-row" :class="{ 'is-unlock': item.type === 'unlock' }">
          <button type="button" class="ma-row__main" @click="openItem(item)">
            <span class="ma-row__num">{{ index + 1 }}</span>
            <span class="ma-row__text">
              <span class="ma-row__title">{{ item.title }}</span>
              <span class="ma-row__meta">{{ moduleLabel(item) }}<template v-if="item.meta"> · {{ item.meta }}</template></span>
            </span>
          </button>
          <button
            v-if="item.type === 'recommendation'"
            type="button"
            class="ma-row__done"
            :disabled="marking === item.id"
            @click.stop="markDone(item)"
          >{{ marking === item.id ? 'Saving' : 'Mark as done' }}</button>
        </li>
      </ul>
    </section>

    <section v-if="completed.length" class="m-card" aria-labelledby="m-actions-done">
      <div class="ma-head">
        <h2 class="ma-head__title" id="m-actions-done">Done</h2>
        <span class="ma-count ma-count--done">{{ completed.length }}</span>
      </div>
      <ul class="ma-list">
        <li v-for="row in completed" :key="'done-' + row.id" class="ma-row ma-row--done">
          <span class="ma-row__text">
            <span class="ma-row__title">{{ row.recommendation_text }}</span>
            <span class="ma-row__meta">{{ moduleLabel(row) }}</span>
          </span>
          <span class="ma-row__date">{{ doneDate(row) }}</span>
        </li>
      </ul>
    </section>
  </MobileChrome>
</template>

<script>
import { store } from '../store.js';
import { apiGet, apiPost } from '../api.js';
import { handleAuthExpiry } from '../authExpiry.js';
import MobileChrome from '../components/MobileChrome.vue';
import MobileThresholdStrip from '../components/ThresholdStrip.vue';

/**
 * Rule 19 parity for the desktop /actions page: the FULL ranked open list
 * (NextActionsService::buildAll — the same ids, order and actions the
 * dashboard's four slots come from) plus the completed history. The
 * dashboard's "See all actions" used to land on /achievements, so anything
 * below the top four was invisible on `/m` (CSJ 2026-09-17).
 */
export default {
  name: 'MobileActions',
  components: { MobileChrome, MobileThresholdStrip },

  data() {
    return {
      loading: true,
      error: '',
      marking: null,
      open: [],
      completed: [],
      thresholds: { strip: null, lines: [] },
    };
  },

  async created() {
    await this.load();
  },

  methods: {
    goBack() {
      this.$router.push({ name: 'dashboard' });
    },

    // The server sends the label (NextActionsService::moduleDisplayLabel) so
    // the vocabulary lives in one place; the fallback only covers a row that
    // predates it.
    moduleLabel(row) {
      return row.module_label || 'General';
    },

    doneDate(row) {
      if (!row.completed_at) return '';
      const d = new Date(row.completed_at);
      return isNaN(d.getTime()) ? '' : d.toLocaleDateString('en-GB');
    },

    // Every action opens its own card (design C, CSJ 2026-09-26); the card
    // carries the action's own button and Ask Fyn.
    openItem(item) {
      if (!item || !item.id) return;
      const navigation = this.$router.push({ name: 'm-action-card', params: { id: item.id } });
      if (navigation?.catch) navigation.catch(() => {});
    },

    async markDone(item) {
      if (!item.id || this.marking) return;
      this.marking = item.id;
      try {
        const res = await apiPost(`/api/recommendations/${item.id}/mark-done`, {
          module: item.module || 'general',
          recommendation_text: item.title || '',
        }, store.token);
        if (handleAuthExpiry(res, this.$router)) return;
        await Promise.all([store.fetchStatus(), this.load({ silent: true })]);
      } catch {
        this.error = 'We could not save that just now. Please try again.';
      } finally {
        this.marking = null;
      }
    },

    async load({ silent = false } = {}) {
      if (!silent) this.loading = true;
      this.error = '';
      try {
        const res = await apiGet('/api/recommendations/actions', store.token);
        if (handleAuthExpiry(res, this.$router)) return;
        if (!res.ok) {
          this.error = 'We could not load your actions. Please try again.';
          return;
        }
        const d = res.data?.data || {};
        this.open = Array.isArray(d.open) ? d.open : [];
        this.completed = Array.isArray(d.completed) ? d.completed : [];

        const thresholds = await apiGet('/api/thresholds', store.token);
        if (handleAuthExpiry(thresholds, this.$router)) return;
        if (thresholds.ok) this.thresholds = thresholds.data?.data || { strip: null, lines: [] };
      } catch {
        this.error = 'Network error. Please try again.';
      } finally {
        if (!silent) this.loading = false;
      }
    },
  },
};
</script>

<style scoped>
.ma-error { margin: 0 4px 12px; font-size: 13px; color: var(--raspberry-600); }
.ma-head { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; margin-bottom: 10px; }
.ma-head__title { margin: 0; font-size: 16px; font-weight: 800; color: var(--horizon-500); }
.ma-count { font-size: 12px; font-weight: 800; color: var(--white); background: var(--raspberry-500); border-radius: 999px; padding: 2px 9px; }
.ma-count--done { background: var(--spring-500); }
.ma-empty { margin: 0; font-size: 13px; line-height: 1.5; color: var(--neutral-600); }
.ma-list { list-style: none; margin: 0; padding: 0; }
.ma-row { display: flex; align-items: center; gap: 10px; padding: 12px 0; border-bottom: 1px solid var(--horizon-200); }
.ma-row:last-child { border-bottom: 0; padding-bottom: 0; }
.ma-row__main { display: flex; align-items: center; gap: 10px; flex: 1 1 auto; min-width: 0; padding: 0; border: 0; background: transparent; text-align: left; cursor: pointer; }
.ma-row__main:active { opacity: 0.72; }
.ma-row__num { flex: 0 0 auto; width: 22px; height: 22px; border-radius: 999px; background: var(--light-pink-100); color: var(--raspberry-500); font-size: 11px; font-weight: 800; display: flex; align-items: center; justify-content: center; }
.ma-row.is-unlock .ma-row__num { background: var(--horizon-100); color: var(--horizon-500); }
.ma-row__text { display: block; min-width: 0; }
.ma-row__title { display: block; font-size: 15px; font-weight: 700; color: var(--horizon-500); }
.ma-row__meta { display: block; margin-top: 2px; font-size: 12px; line-height: 1.45; color: var(--neutral-600); }
.ma-row__done { flex: 0 0 auto; padding: 6px 10px; border: 1px solid var(--horizon-200); border-radius: 999px; background: var(--white); font-size: 12px; font-weight: 700; color: var(--horizon-500); cursor: pointer; }
.ma-row__done:disabled { opacity: 0.6; cursor: default; }
.ma-row--done .ma-row__title { color: var(--neutral-600); }
.ma-row__date { flex: 0 0 auto; font-size: 12px; font-weight: 700; color: var(--spring-600); white-space: nowrap; }
</style>
