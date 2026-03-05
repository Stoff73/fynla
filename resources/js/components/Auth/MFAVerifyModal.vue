<template>
  <div v-if="isOpen" class="modal-overlay">
    <div class="modal">
      <div class="modal-header">
        <h3>Two-Factor Authentication</h3>
      </div>

      <div class="modal-body">
        <!-- TOTP Code Entry -->
        <div v-if="!showRecoveryInput">
          <p class="description">
            Enter the 6-digit code from your authenticator app.
          </p>
          <div class="code-input-container">
            <input
              v-model="code"
              type="text"
              maxlength="6"
              class="code-input"
              placeholder="000000"
              :disabled="loading"
              @keyup.enter="verifyCode"
              @input="handleCodeInput"
              ref="codeInput"
            >
          </div>
          <p v-if="error" class="error-message">
            {{ error }}
          </p>
          <div class="actions">
            <button
              class="btn btn-primary"
              :disabled="code.length !== 6 || loading"
              @click="verifyCode"
            >
              {{ loading ? 'Verifying...' : 'Verify' }}
            </button>
          </div>
          <div class="recovery-link">
            <button class="text-link" @click="showRecoveryInput = true">
              Lost access to authenticator? Use a recovery code
            </button>
          </div>
        </div>

        <!-- Recovery Code Entry -->
        <div v-else>
          <p class="description">
            Enter one of your recovery codes to sign in.
          </p>
          <div class="recovery-input-container">
            <input
              v-model="recoveryCode"
              type="text"
              class="recovery-input"
              placeholder="XXXX-XXXX-XXXX"
              :disabled="loading"
              @keyup.enter="useRecoveryCode"
            >
          </div>
          <p v-if="error" class="error-message">
            {{ error }}
          </p>
          <div class="actions">
            <button
              class="btn btn-primary"
              :disabled="!recoveryCode.trim() || loading"
              @click="useRecoveryCode"
            >
              {{ loading ? 'Verifying...' : 'Use Recovery Code' }}
            </button>
          </div>
          <div class="recovery-link">
            <button class="text-link" @click="showRecoveryInput = false">
              Back to authenticator code
            </button>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-outline" @click="handleCancel">
          Cancel
        </button>
      </div>
    </div>
  </div>
</template>

<script>
/**
 * @fileoverview MFA Verification Modal Component
 * Handles TOTP code verification during login when MFA is enabled.
 * Supports both authenticator codes and recovery codes.
 */
import api from '@/services/api';

export default {
  name: 'MFAVerifyModal',
  props: {
    /** @type {boolean} Whether the modal is visible */
    isOpen: {
      type: Boolean,
      required: true,
    },
    /** @type {number|string|null} User ID (legacy, prefer mfaToken) */
    userId: {
      type: [Number, String],
      default: null,
    },
    /** @type {string|null} Secure challenge token from login response */
    mfaToken: {
      type: String,
      default: null,
    },
  },
  emits: ['verified', 'close'],
  data() {
    return {
      /** @type {string} TOTP code from authenticator app */
      code: '',
      /** @type {string} Recovery code for backup authentication */
      recoveryCode: '',
      /** @type {string} Error message to display */
      error: '',
      /** @type {boolean} Whether verification is in progress */
      loading: false,
      /** @type {boolean} Whether showing recovery code input */
      showRecoveryInput: false,
    };
  },
  watch: {
    isOpen(newVal) {
      if (newVal) {
        this.resetForm();
        this.$nextTick(() => {
          this.$refs.codeInput?.focus();
        });
      }
    },
  },
  methods: {
    /**
     * Filter input to only allow digits in TOTP code
     * @param {Event} event - Input event
     */
    handleCodeInput(event) {
      this.code = event.target.value.replace(/\D/g, '');
    },
    /**
     * Verify TOTP code with the server
     * @returns {Promise<void>}
     */
    async verifyCode() {
      if (this.code.length !== 6) return;

      this.error = '';
      this.loading = true;

      try {
        // Build request payload - prefer mfa_token over user_id
        const payload = { code: this.code };
        if (this.mfaToken) {
          payload.mfa_token = this.mfaToken;
        }
        if (this.userId) {
          payload.user_id = this.userId;
        }

        const response = await api.post('/auth/mfa/verify', payload);

        if (response.data.success) {
          this.$emit('verified', {
            access_token: response.data.data.access_token,
            user: response.data.data.user,
          });
        }
      } catch (error) {
        this.error = error.response?.data?.message || 'Invalid code. Please try again.';
        this.code = '';
      } finally {
        this.loading = false;
      }
    },
    /**
     * Authenticate using a recovery code
     * @returns {Promise<void>}
     */
    async useRecoveryCode() {
      if (!this.recoveryCode.trim()) return;

      this.error = '';
      this.loading = true;

      try {
        // Build request payload - prefer mfa_token over user_id
        const payload = { recovery_code: this.recoveryCode.trim() };
        if (this.mfaToken) {
          payload.mfa_token = this.mfaToken;
        }
        if (this.userId) {
          payload.user_id = this.userId;
        }

        const response = await api.post('/auth/mfa/recovery', payload);

        if (response.data.success) {
          // Show warning about remaining codes if needed
          if (response.data.data.warning) {
            alert(response.data.data.warning);
          }

          this.$emit('verified', {
            access_token: response.data.data.access_token,
            user: response.data.data.user,
          });
        }
      } catch (error) {
        this.error = error.response?.data?.message || 'Invalid recovery code.';
      } finally {
        this.loading = false;
      }
    },
    /**
     * Handle cancel button click - reset form and emit close event
     */
    handleCancel() {
      this.resetForm();
      this.$emit('close');
    },
    /**
     * Reset all form fields to initial state
     */
    resetForm() {
      this.code = '';
      this.recoveryCode = '';
      this.error = '';
      this.showRecoveryInput = false;
    },
  },
};
</script>

<style scoped>
/* Component-specific styles - modal/button base styles are in app.css */
.modal {
  max-width: 400px;
}

.modal-body {
  text-align: center;
}

.description {
  @apply text-neutral-500;
  font-size: 0.875rem;
  margin-bottom: 1.5rem;
}

.code-input-container {
  display: flex;
  justify-content: center;
  margin-bottom: 1rem;
}

.recovery-input-container {
  display: flex;
  justify-content: center;
  margin-bottom: 1rem;
}

.recovery-input {
  width: 200px;
  padding: 0.75rem;
  font-size: 1rem;
  text-align: center;
  letter-spacing: 0.1em;
  @apply border-2 border-light-gray;
  border-radius: 0.5rem;
  font-family: monospace;
}

.recovery-input:focus {
  outline: none;
  @apply border-violet-500;
}

.actions {
  margin-bottom: 1rem;
}

.recovery-link {
  margin-top: 1rem;
}

.text-link {
  background: none;
  border: none;
  @apply text-raspberry-500;
  font-size: 0.875rem;
  cursor: pointer;
  text-decoration: underline;
}

.text-link:hover {
  @apply text-violet-600;
}
</style>
