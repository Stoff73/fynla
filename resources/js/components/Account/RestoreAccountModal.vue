<template>
  <div
    v-if="visible"
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
    @keydown="onDialogKeydown"
  >
    <div class="fixed inset-0 bg-horizon-600/60" aria-hidden="true" @click="onCancel"></div>

    <div
      ref="dialog"
      class="relative bg-white rounded-lg shadow-2xl max-w-lg w-full p-8"
      role="dialog"
      aria-modal="true"
      aria-labelledby="restore-account-title"
      tabindex="-1"
    >
      <h2 id="restore-account-title" class="text-h3 text-horizon-500 mb-4">
        Welcome back, {{ firstName || 'there' }}
      </h2>

      <p v-if="deletedAt" class="text-body text-neutral-500 mb-4">
        We have a record of your previous Fynla account, deleted on
        <strong>{{ deletedDate }}</strong>.
      </p>

      <p v-else class="text-body text-neutral-500 mb-4">
        We found a previous Fynla account linked to these details.
      </p>

      <p class="text-body text-neutral-500 mb-6">
        Your data has been retained for regulatory compliance, and we can restore your account now.
        After restoration, you can continue where you left off.
      </p>

      <div v-if="requiresPasswordVerification" class="mb-4">
        <label for="restore-account-password" class="block text-body-sm text-neutral-500 mb-1.5">
          Please confirm your password to restore:
        </label>
        <input
          id="restore-account-password"
          ref="passwordInput"
          v-model="passwordInput"
          type="password"
          autocomplete="current-password"
          class="w-full px-3 py-2 border border-horizon-300 rounded-md text-sm
                 focus:outline-none focus:ring-2 focus:ring-raspberry-500 focus:border-raspberry-500"
        />
      </div>

      <div v-if="mfaRequired" class="mb-4">
        <label for="restore-account-mfa" class="block text-body-sm text-neutral-500 mb-1.5">
          Enter your authenticator or recovery code:
        </label>
        <input
          id="restore-account-mfa"
          ref="mfaInput"
          v-model="mfaCode"
          type="text"
          inputmode="text"
          autocomplete="one-time-code"
          class="w-full px-3 py-2 border border-horizon-300 rounded-md text-sm
                 focus:outline-none focus:ring-2 focus:ring-raspberry-500 focus:border-raspberry-500"
        />
      </div>

      <div v-if="error" class="bg-raspberry-100 border border-raspberry-600/20 rounded-lg p-3 mb-4">
        <p class="text-body-sm text-raspberry-600">{{ error }}</p>
      </div>

      <div class="flex gap-3">
        <button
          ref="cancelButton"
          type="button"
          class="btn-secondary flex-1"
          :disabled="loading"
          @click="onCancel"
        >
          Cancel
        </button>
        <button
          ref="restoreButton"
          type="button"
          class="btn-primary flex-1"
          :disabled="loading || !canRestore"
          @click="onRestore"
        >
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
    registrationHandoff: { type: String, default: '' },
  },
  emits: ['cancel', 'restored'],
  data() {
    return {
      passwordInput: '',
      mfaCode: '',
      mfaRequired: false,
      pendingRestorationToken: '',
      loading: false,
      error: null,
      previouslyFocusedElement: null,
    };
  },
  watch: {
    visible: {
      immediate: true,
      handler(isVisible) {
        if (isVisible) {
          if (typeof document !== 'undefined') {
            this.previouslyFocusedElement = document.activeElement;
          }
          this.$nextTick(this.focusInitialControl);
          return;
        }

        this.restorePreviousFocus();
      },
    },
  },
  beforeUnmount() {
    this.restorePreviousFocus();
  },
  computed: {
    deletedDate() {
      if (!this.deletedAt) return '';
      return new Date(this.deletedAt).toLocaleDateString('en-GB', {
        day: 'numeric', month: 'long', year: 'numeric',
      });
    },
    canRestore() {
      if (this.mfaRequired) return this.mfaCode.trim().length > 0;
      if (this.requiresPasswordVerification) return this.passwordInput.length > 0;
      return !!this.restorationToken;
    },
  },
  methods: {
    focusInitialControl() {
      const target = this.mfaRequired
        ? this.$refs.mfaInput
        : (this.requiresPasswordVerification
          ? this.$refs.passwordInput
          : (this.$refs.restoreButton || this.$refs.cancelButton));
      target?.focus();
    },
    restorePreviousFocus() {
      if (this.previouslyFocusedElement?.isConnected) {
        this.previouslyFocusedElement.focus();
      }
      this.previouslyFocusedElement = null;
    },
    onDialogKeydown(event) {
      if (event.key === 'Escape') {
        event.preventDefault();
        this.onCancel();
        return;
      }
      if (event.key !== 'Tab' || !this.$refs.dialog) return;

      const controls = Array.from(this.$refs.dialog.querySelectorAll(
        'button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), a[href], [tabindex]:not([tabindex="-1"])',
      ));
      if (controls.length === 0) {
        event.preventDefault();
        this.$refs.dialog.focus();
        return;
      }

      const first = controls[0];
      const last = controls[controls.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    },
    clearSensitiveState() {
      this.passwordInput = '';
      this.mfaCode = '';
      this.mfaRequired = false;
      this.pendingRestorationToken = '';
      this.error = null;
    },
    onCancel() {
      this.clearSensitiveState();
      this.$emit('cancel');
    },
    async onRestore() {
      this.loading = true;
      this.error = null;
      try {
        let token = this.pendingRestorationToken || this.restorationToken;
        if (this.requiresPasswordVerification && !this.pendingRestorationToken) {
          const res = this.registrationHandoff
            ? await authService.restoreCheckHandoff(this.registrationHandoff, this.passwordInput)
            : await authService.restoreCheck(this.email, this.passwordInput);
          token = res.restoration_token;
          if (res.requires_mfa) {
            this.pendingRestorationToken = token;
            this.mfaRequired = true;
            this.passwordInput = '';
            this.loading = false;
            this.$nextTick(this.focusInitialControl);
            return;
          }
        }
        const result = await authService.restore(token, this.mfaRequired ? this.mfaCode : null);
        this.clearSensitiveState();
        this.loading = false;
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
