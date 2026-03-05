<template>
  <div class="modal-overlay">
    <div class="modal">
      <div class="modal-header">
        <h3>Set Up Two-Factor Authentication</h3>
        <button
          class="close-btn"
          @click="$emit('close')"
        >
          &times;
        </button>
      </div>

      <div class="modal-body">
        <!-- Step 1: Scan QR Code -->
        <div
          v-if="step === 1"
          class="setup-step"
        >
          <p class="step-description">
            Scan this QR code with your authenticator app (like Google Authenticator or Authy):
          </p>
          <div
            v-if="qrCode"
            class="qr-container"
          >
            <img
              :src="qrCode"
              alt="MFA QR Code"
              class="qr-image"
            >
          </div>
          <div
            v-else
            class="qr-placeholder"
          >
            <span class="loading">Loading QR code...</span>
          </div>
          <div class="manual-entry">
            <p>Or enter this code manually:</p>
            <code class="secret-code">{{ secret }}</code>
          </div>
          <button
            class="btn btn-primary"
            :disabled="!qrCode"
            @click="step = 2"
          >
            Next
          </button>
        </div>

        <!-- Step 2: Verify Code -->
        <div
          v-if="step === 2"
          class="setup-step"
        >
          <p class="step-description">
            Enter the 6-digit code from your authenticator app to verify setup:
          </p>
          <div class="code-input-container">
            <input
              v-model="verificationCode"
              type="text"
              maxlength="6"
              class="code-input"
              placeholder="000000"
              @keyup.enter="verifySetup"
            >
          </div>
          <p
            v-if="error"
            class="error-message"
          >
            {{ error }}
          </p>
          <div class="step-actions">
            <button
              class="btn btn-outline"
              @click="step = 1"
            >
              Back
            </button>
            <button
              class="btn btn-primary"
              :disabled="verificationCode.length !== 6 || verifying"
              @click="verifySetup"
            >
              {{ verifying ? 'Verifying...' : 'Verify' }}
            </button>
          </div>
        </div>

        <!-- Step 3: Recovery Codes -->
        <div
          v-if="step === 3"
          class="setup-step"
        >
          <div class="success-icon">&#10003;</div>
          <p class="step-description success">
            Two-factor authentication is now enabled!
          </p>
          <div class="recovery-codes-section">
            <p class="recovery-warning">
              Save these recovery codes in a safe place. You can use them to access your account if you lose your authenticator device.
            </p>
            <div class="recovery-codes">
              <code
                v-for="code in recoveryCodes"
                :key="code"
                class="recovery-code"
              >{{ code }}</code>
            </div>
            <button
              class="btn btn-outline btn-sm"
              @click="copyRecoveryCodes"
            >
              Copy Codes
            </button>
          </div>
          <button
            class="btn btn-primary"
            @click="finish"
          >
            Done
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
/**
 * @fileoverview MFA Setup Modal Component
 * Provides a multi-step wizard for setting up two-factor authentication.
 * Step 1: Display QR code and secret for authenticator app
 * Step 2: Verify TOTP code from authenticator
 * Step 3: Display and save recovery codes
 */
import api from '@/services/api';

export default {
  name: 'MFASetupModal',
  emits: ['close', 'success'],
  data() {
    return {
      /** @type {number} Current setup wizard step (1-3) */
      step: 1,
      /** @type {string|null} Base64 QR code image data */
      qrCode: null,
      /** @type {string} TOTP secret key for manual entry */
      secret: '',
      /** @type {string} User-entered verification code */
      verificationCode: '',
      /** @type {string[]} Generated recovery codes */
      recoveryCodes: [],
      /** @type {string} Error message to display */
      error: '',
      /** @type {boolean} Whether verification is in progress */
      verifying: false,
    };
  },
  mounted() {
    this.initSetup();
  },
  methods: {
    /**
     * Initialize MFA setup by fetching QR code and secret from API
     * @returns {Promise<void>}
     */
    async initSetup() {
      try {
        const response = await api.post('/auth/mfa/setup');
        this.qrCode = response.data.data.qr_code;
        this.secret = response.data.data.secret;
      } catch (error) {
        this.error = 'Failed to initialize MFA setup. Please try again.';
      }
    },
    /**
     * Verify the TOTP code entered by user and enable MFA
     * @returns {Promise<void>}
     */
    async verifySetup() {
      this.error = '';
      this.verifying = true;

      try {
        const response = await api.post('/auth/mfa/verify-setup', {
          code: this.verificationCode,
        });
        this.recoveryCodes = response.data.data.recovery_codes;
        this.step = 3;
      } catch (error) {
        this.error = error.response?.data?.message || 'Invalid code. Please try again.';
      } finally {
        this.verifying = false;
      }
    },
    /**
     * Copy recovery codes to clipboard
     */
    copyRecoveryCodes() {
      const text = this.recoveryCodes.join('\n');
      navigator.clipboard.writeText(text);
      this.$toast?.success?.('Recovery codes copied to clipboard') ||
        alert('Recovery codes copied to clipboard');
    },
    /**
     * Complete MFA setup and emit success event
     */
    finish() {
      this.$emit('success');
    },
  },
};
</script>

<style scoped>
/* Component-specific styles - modal base styles are in app.css */
.modal {
  max-width: 440px;
}

.setup-step {
  text-align: center;
}

.step-description {
  @apply text-neutral-500;
  margin-bottom: 1.5rem;
}

.step-description.success {
  @apply text-spring-600;
  font-weight: 500;
}

.qr-container {
  display: flex;
  justify-content: center;
  margin-bottom: 1.5rem;
}

.qr-image {
  width: 200px;
  height: 200px;
  @apply border border-light-gray;
  border-radius: 0.5rem;
}

.qr-placeholder {
  width: 200px;
  height: 200px;
  display: flex;
  align-items: center;
  justify-content: center;
  @apply bg-savannah-100;
  border-radius: 0.5rem;
  margin: 0 auto 1.5rem;
}

.loading {
  @apply text-neutral-500;
}

.manual-entry {
  margin-bottom: 1.5rem;
}

.manual-entry p {
  font-size: 0.875rem;
  @apply text-neutral-500;
  margin-bottom: 0.5rem;
}

.secret-code {
  display: block;
  @apply bg-savannah-100;
  padding: 0.75rem;
  border-radius: 0.375rem;
  font-family: monospace;
  font-size: 1rem;
  letter-spacing: 0.1em;
  word-break: break-all;
}

.code-input-container {
  display: flex;
  justify-content: center;
  margin-bottom: 1rem;
}

.step-actions {
  display: flex;
  justify-content: center;
  gap: 0.75rem;
}

.success-icon {
  width: 60px;
  height: 60px;
  @apply bg-spring-100;
  @apply text-spring-600;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
  font-weight: bold;
  margin: 0 auto 1rem;
}

.recovery-codes-section {
  @apply bg-violet-100;
  @apply border border-violet-300;
  border-radius: 0.5rem;
  padding: 1rem;
  margin-bottom: 1.5rem;
}

.recovery-warning {
  @apply text-violet-800;
  font-size: 0.875rem;
  margin-bottom: 1rem;
}

.recovery-codes {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.recovery-code {
  background-color: white;
  padding: 0.5rem;
  border-radius: 0.25rem;
  font-family: monospace;
  font-size: 0.875rem;
}

/* Button styles are in app.css */
</style>
