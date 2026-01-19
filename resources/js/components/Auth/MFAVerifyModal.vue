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
import api from '@/services/api';

export default {
  name: 'MFAVerifyModal',
  props: {
    isOpen: {
      type: Boolean,
      required: true,
    },
    userId: {
      type: [Number, String],
      required: true,
    },
  },
  emits: ['verified', 'close'],
  data() {
    return {
      code: '',
      recoveryCode: '',
      error: '',
      loading: false,
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
    handleCodeInput(event) {
      // Only allow digits
      this.code = event.target.value.replace(/\D/g, '');
    },
    async verifyCode() {
      if (this.code.length !== 6) return;

      this.error = '';
      this.loading = true;

      try {
        const response = await api.post('/auth/mfa/verify', {
          code: this.code,
          user_id: this.userId,
        });

        if (response.data.success) {
          this.$emit('verified', {
            access_token: response.data.data.token,
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
    async useRecoveryCode() {
      if (!this.recoveryCode.trim()) return;

      this.error = '';
      this.loading = true;

      try {
        const response = await api.post('/auth/mfa/recovery', {
          recovery_code: this.recoveryCode.trim(),
          user_id: this.userId,
        });

        if (response.data.success) {
          // Show warning about remaining codes if needed
          if (response.data.data.warning) {
            alert(response.data.data.warning);
          }

          this.$emit('verified', {
            access_token: response.data.data.token,
            user: response.data.data.user,
          });
        }
      } catch (error) {
        this.error = error.response?.data?.message || 'Invalid recovery code.';
      } finally {
        this.loading = false;
      }
    },
    handleCancel() {
      this.resetForm();
      this.$emit('close');
    },
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
  max-width: 400px;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
}

.modal-header {
  padding: 1rem 1.5rem;
  border-bottom: 1px solid #e5e7eb;
}

.modal-header h3 {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 600;
  color: #111827;
}

.modal-body {
  padding: 1.5rem;
  text-align: center;
}

.description {
  color: #6b7280;
  font-size: 0.875rem;
  margin-bottom: 1.5rem;
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
  border: 2px solid #e5e7eb;
  border-radius: 0.5rem;
  font-family: monospace;
}

.recovery-input:focus {
  outline: none;
  border-color: #3b82f6;
}

.error-message {
  color: #dc2626;
  font-size: 0.875rem;
  margin-bottom: 1rem;
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
  color: #3b82f6;
  font-size: 0.875rem;
  cursor: pointer;
  text-decoration: underline;
}

.text-link:hover {
  color: #2563eb;
}

.modal-footer {
  padding: 1rem 1.5rem;
  border-top: 1px solid #e5e7eb;
  display: flex;
  justify-content: flex-end;
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

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
