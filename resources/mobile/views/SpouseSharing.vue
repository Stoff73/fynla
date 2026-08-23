<template>
  <MobileChrome
    title="Household sharing"
    subtitle="Who can see your financial information"
    :loading="loading"
    loading-label="your sharing settings"
    :edit-details="false"
    back
    @back="$router.push('/settings')"
  >
    <div v-if="loadError" class="m-card m-state" role="alert">
      <p class="m-err">{{ loadError }}</p>
      <button type="button" class="m-btn" data-testid="sharing-retry" @click="load">Try again</button>
    </div>

    <template v-else>
      <div v-if="actionError" class="m-card m-state" role="alert">
        <p class="m-err">{{ actionError }}</p>
      </div>

      <!-- Somebody has asked to link with this account. -->
      <section v-if="awaitingYourResponse" class="m-card" data-testid="sharing-incoming">
        <h2 class="m-section-label">A request for you</h2>
        <p class="sharing-copy">
          <strong>{{ counterpartyName }}</strong> has asked to link their Fynla household with yours.
          Accepting lets them see your assets, liabilities and income. Nothing is shared until you accept.
        </p>
        <p v-if="counterpartyEmail" class="sharing-meta">{{ counterpartyEmail }}</p>
        <div class="sharing-actions">
          <button type="button" class="m-btn" data-testid="sharing-accept" :disabled="busy" @click="respond('accept')">
            {{ busy ? 'Saving...' : 'Accept' }}
          </button>
          <button type="button" class="m-btn-ghost" data-testid="sharing-decline" :disabled="busy" @click="respond('reject')">
            Decline
          </button>
        </div>
      </section>

      <!-- This account asked somebody else. -->
      <section v-else-if="awaitingTheirResponse" class="m-card" data-testid="sharing-outgoing">
        <h2 class="m-section-label">Waiting for confirmation</h2>
        <p class="sharing-copy">
          We have asked <strong>{{ counterpartyName || 'your partner' }}</strong> to confirm the link.
          Nothing is shared until they accept.
        </p>
        <div class="sharing-actions">
          <button type="button" class="m-btn-ghost" data-testid="sharing-withdraw" :disabled="busy" @click="respond('revoke')">
            {{ busy ? 'Saving...' : 'Withdraw request' }}
          </button>
        </div>
      </section>

      <!-- Sharing is on. -->
      <section v-else-if="canViewSpouseData" class="m-card" data-testid="sharing-active">
        <h2 class="m-section-label">Sharing is on</h2>
        <p class="sharing-copy">
          You and <strong>{{ counterpartyName || 'your partner' }}</strong> can each see the other's
          financial information for joint planning.
        </p>
        <div class="sharing-actions">
          <button type="button" class="m-btn-ghost" data-testid="sharing-revoke" :disabled="busy" @click="respond('revoke')">
            {{ busy ? 'Saving...' : 'Stop sharing' }}
          </button>
        </div>
      </section>

      <!-- Linked, but sharing withdrawn or declined. -->
      <section v-else-if="hasSpouse" class="m-card" data-testid="sharing-off">
        <h2 class="m-section-label">Sharing is off</h2>
        <p class="sharing-copy">
          Your accounts are linked, but financial information is not being shared.
        </p>
      </section>

      <section v-else class="m-card" data-testid="sharing-none">
        <h2 class="m-section-label">No linked partner</h2>
        <p class="sharing-copy">
          Add your partner in your household details to plan together. They will be asked to confirm
          before anything is shared.
        </p>
      </section>
    </template>
  </MobileChrome>
</template>

<script>
/**
 * Rule 19 — /m parity for spouse data sharing.
 *
 * Not optional, and not a web handoff: the spouse-permission notification email
 * links to `/settings/spouse-permission`, and phones are routed to `/m`. Without
 * this screen a mobile invitee follows that email and lands nowhere, which is
 * exactly the condition that made consent unobtainable and led the backend to
 * forge it (W-0347). The person most likely to be answering on a phone is the
 * one being asked.
 *
 * Same endpoints as the web component — one backend, one contract (Rule 20).
 */
import { apiGet, apiPost, apiDelete } from '../api.js';
import { handleAuthExpiry } from '../authExpiry.js';
import MobileChrome from '../components/MobileChrome.vue';
import { store } from '../store.js';

export default {
  name: 'MobileSpouseSharing',
  components: { MobileChrome },
  data: () => ({
    status: null,
    loading: true,
    loadError: '',
    actionError: '',
    busy: false,
  }),
  computed: {
    hasSpouse() { return Boolean(this.status?.has_spouse); },
    awaitingYourResponse() { return Boolean(this.status?.awaiting_your_response); },
    awaitingTheirResponse() { return Boolean(this.status?.awaiting_their_response); },
    canViewSpouseData() { return Boolean(this.status?.can_view_spouse_data); },
    counterpartyName() { return this.status?.spouse?.name || ''; },
    counterpartyEmail() { return this.status?.spouse?.email || ''; },
  },
  async mounted() {
    await this.load();
  },
  methods: {
    async load() {
      this.loading = true;
      this.loadError = '';
      try {
        // apiGet resolves { ok, status, data } on a non-2xx rather than
        // throwing, so the 401 check is on the resolved value.
        const { ok, status, data } = await apiGet('/api/spouse-permission/status', store.token);
        if (handleAuthExpiry({ status }, this.$router)) return;
        if (ok) this.status = data?.data ?? null;
        else this.loadError = 'We could not load your sharing settings.';
      } catch {
        this.loadError = 'Network error. Please try again.';
      } finally {
        this.loading = false;
      }
    },
    async respond(action) {
      this.busy = true;
      this.actionError = '';
      try {
        const { ok, status } = action === 'revoke'
          ? await apiDelete('/api/spouse-permission/revoke', store.token)
          : await apiPost(`/api/spouse-permission/${action}`, {}, store.token);
        if (handleAuthExpiry({ status }, this.$router)) return;
        if (ok) await this.load();
        else this.actionError = 'That did not save. Please try again.';
      } catch {
        this.actionError = 'Network error. Please try again.';
      } finally {
        this.busy = false;
      }
    },
  },
};
</script>

<style scoped>
.sharing-copy { color: var(--horizon-500); font-size: 14px; line-height: 1.5; margin: 0 0 8px; }
.sharing-meta { color: var(--neutral-500); font-size: 12px; margin: 0 0 12px; }
.sharing-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 14px; }
</style>
