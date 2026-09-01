<template>
  <MobileChrome
    title="Specific bequests"
    subtitle="Gifts left to named beneficiaries in your will"
    :loading="loading"
    loading-label="your bequests"
    :edit-details="false"
    back
    @back="goBack"
  >
    <div v-if="loading" class="m-card m-state">
      <p class="m-sub">Loading your bequests…</p>
    </div>

    <div v-else-if="error" class="m-card m-state">
      <p class="m-err">{{ error }}</p>
      <button type="button" class="m-btn" @click="load">Try again</button>
    </div>

    <template v-else>
      <div v-if="bequests.length" class="m-card">
        <p class="m-section-label" style="margin-top:0">
          {{ bequests.length }} {{ bequests.length === 1 ? 'bequest' : 'bequests' }}
        </p>
        <!--
          W-0398. The count is accurate about the table and was misleading about the
          will: the residue, and anyone named to inherit it, is document-only.
        -->
        <p v-if="residuaryNote" class="meb-residuary">{{ residuaryNote }}</p>
        <div v-for="bequest in bequests" :key="bequest.id" class="meb-item">
          <p class="meb-item__name">{{ bequest.beneficiary_name || 'Unnamed beneficiary' }}</p>
          <p class="meb-item__gift">{{ giftDescription(bequest) }}</p>
          <p v-if="bequest.conditions" class="meb-item__conditions">Conditions: {{ bequest.conditions }}</p>
        </div>
      </div>

      <div v-else class="m-card m-state">
        <p class="m-sub">You have not recorded any specific bequests yet.</p>
      </div>

      <div class="m-card">
        <p class="meb-note">
          Adding, editing and removing bequests is part of will planning in the full app.
        </p>
        <button type="button" class="m-btn" :disabled="openingWeb" @click="openWillPlanning">
          {{ openingWeb ? 'Opening…' : 'Manage your will in the full app' }}
        </button>
        <p v-if="handoffError" class="m-err" style="margin-top:12px">{{ handoffError }}</p>
      </div>
    </template>
  </MobileChrome>
</template>

<script>
import { store } from '../../store.js';
import { apiGet } from '../../api.js';
import { handleAuthExpiry } from '../../authExpiry.js';
import MobileChrome from '../../components/MobileChrome.vue';
import { issueWebHandoff } from '../../navigation/webHandoff.js';

// Matches the local formatter in Estate.vue, the screen this one is reached from.
function formatCurrency(value) {
  if (value == null || value === '' || isNaN(Number(value))) return '—';
  return new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP', maximumFractionDigits: 0 }).format(Number(value));
}

export default {
  name: 'MobileEstateBequests',
  components: { MobileChrome },
  data: () => ({ loading: true, error: '', bequests: [], openingWeb: false, handoffError: '', residuaryNote: ''}),
  async created() { await this.load(); },
  methods: {
    goBack() { this.$router.push({ name: 'm-estate' }); },

    giftDescription(bequest) {
      switch (bequest.bequest_type) {
        case 'percentage':
          return `${bequest.percentage_of_estate}% of estate`;
        case 'specific_amount':
          return formatCurrency(bequest.specific_amount);
        case 'specific_asset':
          return `Specific asset: ${bequest.specific_asset_description || 'not described'}`;
        default:
          return 'Residuary bequest';
      }
    },

    async load() {
      this.loading = true;
      this.error = '';
      try {
        const { ok, status, data } = await apiGet('/api/estate/bequests', store.token);
        if (handleAuthExpiry({ status }, this.$router)) return;
        if (!ok) {
          this.error = data?.message || 'We could not load your bequests.';
          return;
        }
        this.bequests = data?.data || [];
        // W-0398 — served with the payload so this screen and the web one say the same
        // thing about what the list does NOT contain.
        this.residuaryNote = data?.residuary_note || '';
      } catch {
        this.error = 'Network error. Please try again.';
      } finally {
        this.loading = false;
      }
    },

    async openWillPlanning() {
      this.openingWeb = true;
      this.handoffError = '';
      try {
        await issueWebHandoff('estate_will');
      } catch {
        this.handoffError = 'We could not open the full app. Please try again.';
        this.openingWeb = false;
      }
    },
  },
};
</script>

<style scoped>
.meb-item { padding: 12px 0; border-bottom: 1px solid var(--light-gray); }
.meb-item:last-of-type { border-bottom: 0; padding-bottom: 0; }
.meb-item__name { font-size: 15px; font-weight: 700; color: var(--horizon-500); margin: 0; }
.meb-item__gift { font-size: 14px; color: var(--neutral-600); margin: 4px 0 0; }
.meb-item__conditions { font-size: 13px; color: var(--neutral-600); margin: 4px 0 0; }
.meb-note { font-size: 13px; color: var(--neutral-600); line-height: 1.5; margin: 0 0 16px; }
.meb-residuary { font-size: 13px; color: var(--neutral-600); line-height: 1.5; margin: 8px 0 0; }
</style>
