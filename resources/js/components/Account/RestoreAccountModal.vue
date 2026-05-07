<template>
  <div v-if="visible" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="fixed inset-0 bg-horizon-600/60" @click="onCancel"></div>

    <div class="relative bg-white rounded-lg shadow-2xl max-w-lg w-full p-8">
      <h2 class="text-h3 text-horizon-500 mb-4">Welcome back, {{ firstName || 'there' }}</h2>

      <p class="text-body text-neutral-500 mb-4">
        We have a record of your previous Fynla account, deleted on
        <strong>{{ deletedDate }}</strong>.
      </p>

      <p class="text-body text-neutral-500 mb-6">
        Your data has been retained for regulatory compliance, and we can restore your account now.
        You'll need to choose a subscription plan after restoration.
      </p>

      <div v-if="requiresPasswordVerification" class="mb-4">
        <label class="block text-body-sm text-neutral-500 mb-1.5">
          Please confirm your password to restore:
        </label>
        <input
          v-model="passwordInput"
          type="password"
          autocomplete="current-password"
          class="w-full px-3 py-2 border border-horizon-300 rounded-md text-sm
                 focus:outline-none focus:ring-2 focus:ring-raspberry-500 focus:border-raspberry-500"
        />
      </div>

      <div v-if="error" class="bg-raspberry-100 border border-raspberry-600/20 rounded-lg p-3 mb-4">
        <p class="text-body-sm text-raspberry-600">{{ error }}</p>
      </div>

      <div class="flex gap-3">
        <button class="btn-secondary flex-1" :disabled="loading" @click="onCancel">Cancel</button>
        <button class="btn-primary flex-1" :disabled="loading || !canRestore" @click="onRestore">
          <span v-if="loading">Restoring...</span>
          <span v-else>Restore my account</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import authService from '@/services/authService';
import logger from '@/utils/logger';

export default {
  name: 'RestoreAccountModal',
  props: {
    visible: { type: Boolean, default: false },
    firstName: { type: String, default: '' },
    deletedAt: { type: String, default: '' },
    requiresPasswordVerification: { type: Boolean, default: false },
    email: { type: String, default: '' },
    restorationToken: { type: String, default: '' },
  },
  emits: ['cancel', 'restored'],
  data() {
    return {
      passwordInput: '',
      loading: false,
      error: null,
    };
  },
  computed: {
    deletedDate() {
      if (!this.deletedAt) return '';
      return new Date(this.deletedAt).toLocaleDateString('en-GB', {
        day: 'numeric', month: 'long', year: 'numeric',
      });
    },
    canRestore() {
      if (this.requiresPasswordVerification) return this.passwordInput.length > 0;
      return !!this.restorationToken;
    },
  },
  methods: {
    onCancel() {
      this.passwordInput = '';
      this.error = null;
      this.$emit('cancel');
    },
    async onRestore() {
      this.loading = true;
      this.error = null;
      try {
        let token = this.restorationToken;
        if (this.requiresPasswordVerification) {
          const res = await authService.restoreCheck(this.email, this.passwordInput);
          token = res.restoration_token;
        }
        const result = await authService.restore(token);
        this.$emit('restored', result);
      } catch (e) {
        logger.error('RestoreAccountModal restore failed', e);
        this.error = e.response?.data?.message || 'Could not restore your account. Please try again.';
        this.loading = false;
      }
    },
  },
};
</script>
