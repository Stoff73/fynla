<template>
  <MobileChrome title="Settings" subtitle="Your account, privacy, and support">
    <div v-if="error" class="m-card m-state" role="alert">
      <p class="m-err">{{ error }}</p>
      <button type="button" class="m-btn" @click="openPrivacyData">Try again</button>
    </div>

    <section class="m-card settings-group" aria-labelledby="settings-preferences-heading">
      <h2 id="settings-preferences-heading" class="m-section-label">Preferences</h2>
      <button type="button" class="settings-row" data-testid="settings-notifications" @click="$router.push('/notifications')">
        <span><strong>Notifications</strong><small>Choose the updates you receive</small></span>
        <span aria-hidden="true">›</span>
      </button>
      <button type="button" class="settings-row" data-testid="settings-spouse-sharing" @click="$router.push('/spouse-sharing')">
        <span><strong>Household sharing</strong><small>Who can see your financial information</small></span>
        <span aria-hidden="true">›</span>
      </button>
      <button type="button" class="settings-row" data-testid="settings-privacy-data" :disabled="handoffBusy" @click="openPrivacyData">
        <span><strong>Privacy and data</strong><small>Manage your private data securely</small></span>
        <span aria-hidden="true">›</span>
      </button>
    </section>

    <section class="m-card settings-group" aria-labelledby="settings-support-heading">
      <h2 id="settings-support-heading" class="m-section-label">Help and legal</h2>
      <button type="button" class="settings-row" data-testid="settings-help" @click="openPublicWebPath('/help')">
        <span><strong>Help</strong></span><span aria-hidden="true">›</span>
      </button>
      <button type="button" class="settings-row" data-testid="settings-privacy-policy" @click="openPublicWebPath('/privacy')">
        <span><strong>Privacy Policy</strong></span><span aria-hidden="true">›</span>
      </button>
      <button type="button" class="settings-row" data-testid="settings-terms" @click="openPublicWebPath('/terms')">
        <span><strong>Terms</strong></span><span aria-hidden="true">›</span>
      </button>
    </section>
  </MobileChrome>
</template>

<script>
import MobileChrome from '../components/MobileChrome.vue';
import { issueWebHandoff, openPublicWebPath } from '../navigation/webHandoff.js';

export default {
  name: 'MobileSettings',
  components: { MobileChrome },
  data: () => ({ error: '', handoffBusy: false }),
  methods: {
    openPublicWebPath,
    async openPrivacyData() {
      this.handoffBusy = true;
      this.error = '';
      try {
        await issueWebHandoff('privacy');
      } catch {
        this.error = 'Privacy and data is temporarily unavailable. Please try again.';
      } finally {
        this.handoffBusy = false;
      }
    },
  },
};
</script>

<style scoped>
.settings-group { margin-bottom: 12px; padding-bottom: 0; overflow: hidden; }
.settings-group .m-section-label { margin-top: 0; }
.settings-row { width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 14px 0; border: 0; border-top: 1px solid var(--horizon-100); background: transparent; color: var(--horizon-500); text-align: left; }
.settings-row span:first-child { display: flex; flex-direction: column; gap: 3px; }
.settings-row strong { font-size: 14px; }
.settings-row small { color: var(--neutral-500); font-size: 12px; }
.settings-row:disabled { opacity: .6; }
</style>
