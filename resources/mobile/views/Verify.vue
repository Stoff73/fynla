<template>
  <div class="m-card">
    <h1 class="m-h1">Enter code</h1>
    <p class="m-sub">We sent a 6-digit code to {{ maskedEmail || 'your email' }}.</p>
    <form @submit.prevent="submit">
      <input class="m-field" v-model="code" inputmode="numeric" maxlength="6" placeholder="000000" required />
      <p v-if="error" class="m-err">{{ error }}</p>
      <button class="m-btn" :disabled="loading" type="submit">{{ loading ? 'Verifying…' : 'Verify' }}</button>
    </form>
  </div>
</template>

<script>
import { store } from '../store.js';
import { apiPost } from '../api.js';

export default {
  name: 'MobileVerify',
  data: () => ({ code: '', error: '', loading: false }),
  computed: { maskedEmail: () => store.maskedEmail },
  created() {
    if (!store.challengeToken) this.$router.replace({ name: 'login' });
  },
  methods: {
    async submit() {
      this.error = '';
      this.loading = true;
      try {
        const { ok, data } = await apiPost('/api/auth/verify-code', {
          code: this.code,
          type: 'login',
          challenge_token: store.challengeToken,
        });
        if (ok && data?.data?.access_token) {
          store.setToken(data.data.access_token);
          store.user = data.data.user || null;
          this.$router.push({ name: 'dashboard' });
          return;
        }
        this.error = data?.message || 'Invalid or expired code.';
      } catch (e) {
        this.error = 'Network error. Please try again.';
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>
