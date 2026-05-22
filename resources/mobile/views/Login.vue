<template>
  <div class="m-card">
    <h1 class="m-h1">Sign in</h1>
    <p class="m-sub">Fynla mobile (scaffold)</p>
    <form @submit.prevent="submit">
      <input class="m-field" v-model="email" type="email" placeholder="Email" autocomplete="username" required />
      <input class="m-field" v-model="password" type="password" placeholder="Password" autocomplete="current-password" required />
      <p v-if="error" class="m-err">{{ error }}</p>
      <button class="m-btn" :disabled="loading" type="submit">{{ loading ? 'Signing in…' : 'Continue' }}</button>
    </form>
  </div>
</template>

<script>
import { store } from '../store.js';
import { apiPost } from '../api.js';

export default {
  name: 'MobileLogin',
  data: () => ({ email: '', password: '', error: '', loading: false }),
  methods: {
    async submit() {
      this.error = '';
      this.loading = true;
      try {
        const { ok, data } = await apiPost('/api/auth/login', { email: this.email, password: this.password });
        if (ok && data?.requires_verification) {
          store.challengeToken = data.data.challenge_token;
          store.maskedEmail = data.data.email;
          this.$router.push({ name: 'verify' });
          return;
        }
        this.error = data?.message || 'Login failed. Check your details and try again.';
      } catch (e) {
        this.error = 'Network error. Please try again.';
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>
