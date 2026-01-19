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
import api from '@/services/api';

export default {
  name: 'MFASetupModal',
  emits: ['close', 'success'],
  data() {
    return {
      step: 1,
      qrCode: null,
      secret: '',
      verificationCode: '',
      recoveryCodes: [],
      error: '',
      verifying: false,
    };
  },
  mounted() {
    this.initSetup();
  },
  methods: {
    async initSetup() {
      try {
        const response = await api.post('/auth/mfa/setup');
        this.qrCode = response.data.data.qr_code;
        this.secret = response.data.data.secret;
      } catch (error) {
        this.error = 'Failed to initialize MFA setup. Please try again.';
      }
    },
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
    copyRecoveryCodes() {
      const text = this.recoveryCodes.join('\n');
      navigator.clipboard.writeText(text);
      this.$toast?.success?.('Recovery codes copied to clipboard') ||
        alert('Recovery codes copied to clipboard');
    },
    finish() {
      this.$emit('success');
    },
  },
};
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  inset: 0;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 50;
}

.modal {
  background: white;
  border-radius: 0.5rem;
  width: 100%;
  max-width: 440px;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
}

.modal-header {
  padding: 1rem 1.5rem;
  border-bottom: 1px solid #e5e7eb;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h3 {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 600;
}

.close-btn {
  background: none;
  border: none;
  font-size: 1.5rem;
  cursor: pointer;
  color: #6b7280;
  line-height: 1;
}

.close-btn:hover {
  color: #111827;
}

.modal-body {
  padding: 1.5rem;
}

.setup-step {
  text-align: center;
}

.step-description {
  color: #6b7280;
  margin-bottom: 1.5rem;
}

.step-description.success {
  color: #059669;
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
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
}

.qr-placeholder {
  width: 200px;
  height: 200px;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: #f3f4f6;
  border-radius: 0.5rem;
  margin: 0 auto 1.5rem;
}

.loading {
  color: #6b7280;
}

.manual-entry {
  margin-bottom: 1.5rem;
}

.manual-entry p {
  font-size: 0.875rem;
  color: #6b7280;
  margin-bottom: 0.5rem;
}

.secret-code {
  display: block;
  background-color: #f3f4f6;
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

.code-input {
  width: 160px;
  padding: 0.75rem;
  font-size: 1.5rem;
  text-align: center;
  letter-spacing: 0.25em;
  border: 2px solid #e5e7eb;
  border-radius: 0.5rem;
  font-family: monospace;
}

.code-input:focus {
  outline: none;
  border-color: #3b82f6;
}

.error-message {
  color: #dc2626;
  font-size: 0.875rem;
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
  background-color: #d1fae5;
  color: #059669;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
  font-weight: bold;
  margin: 0 auto 1rem;
}

.recovery-codes-section {
  background-color: #fef3c7;
  border: 1px solid #fbbf24;
  border-radius: 0.5rem;
  padding: 1rem;
  margin-bottom: 1.5rem;
}

.recovery-warning {
  color: #92400e;
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

.btn {
  padding: 0.5rem 1rem;
  border-radius: 0.375rem;
  font-weight: 500;
  font-size: 0.875rem;
  cursor: pointer;
  border: none;
  transition: all 0.15s;
}

.btn-primary {
  background-color: #3b82f6;
  color: white;
}

.btn-primary:hover {
  background-color: #2563eb;
}

.btn-outline {
  background-color: white;
  border: 1px solid #d1d5db;
  color: #374151;
}

.btn-outline:hover {
  background-color: #f9fafb;
}

.btn-sm {
  padding: 0.25rem 0.75rem;
  font-size: 0.75rem;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
