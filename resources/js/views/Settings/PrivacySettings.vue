<template>
  <AppLayout>
    <div class="privacy-settings module-gradient py-8">
      <div class="mb-8">
        <h1 class="text-h2 font-display text-horizon-500">Settings</h1>
        <p class="mt-2 text-body-base text-neutral-500">
          Manage your data privacy preferences and access your personal data
        </p>
      </div>

      <SettingsTabBar />

      <!-- Consent Preferences Section -->
      <div class="settings-section">
        <div class="section-header">
          <h2 class="section-title">Consent Preferences</h2>
        </div>
        <p class="section-description">
          Manage how we use your data. These preferences help us provide a better experience.
        </p>

        <div class="consent-items">
          <div class="consent-item">
            <div class="consent-info">
              <h3>Essential Services</h3>
              <p>Required for the app to function. Cannot be disabled.</p>
            </div>
            <div class="consent-toggle">
              <span class="toggle-label always-on">Always On</span>
            </div>
          </div>

          <div class="consent-item">
            <div class="consent-info">
              <h3>Marketing Communications</h3>
              <p>Receive updates about new features and financial planning tips.</p>
            </div>
            <div class="consent-toggle">
              <label class="toggle">
                <input
                  v-model="consents.marketing"
                  type="checkbox"
                  @change="updateConsent('marketing', consents.marketing)"
                >
                <span class="toggle-slider"></span>
              </label>
            </div>
          </div>
        </div>
      </div>

      <!-- Data Export Section -->
      <div class="settings-section">
        <div class="section-header">
          <h2 class="section-title">Export Your Data</h2>
        </div>
        <p class="section-description">
          Download a copy of all your personal data in JSON or CSV format. This includes your profile,
          financial accounts, goals, and activity history.
        </p>

        <div v-if="pendingExport" class="export-status">
          <div class="status-icon pending">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <circle cx="12" cy="12" r="10" stroke-width="2" stroke-dasharray="31.4" stroke-dashoffset="10" />
            </svg>
          </div>
          <div class="status-text">
            <strong>Export in progress</strong>
            <p>Your data is being prepared. This may take a few minutes.</p>
          </div>
        </div>

        <div v-else-if="completedExport" class="export-status success">
          <div class="status-icon success">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
          </div>
          <div class="status-text">
            <strong>Export ready</strong>
            <p>Your data export is ready for download.</p>
          </div>
          <button class="btn btn-primary" @click="downloadExport">
            Download
          </button>
        </div>

        <div v-else class="export-actions">
          <div class="format-selector">
            <label>Export format:</label>
            <select v-model="exportFormat">
              <option value="json">JSON (detailed)</option>
              <option value="csv">CSV (spreadsheet)</option>
            </select>
          </div>
          <button class="btn btn-primary" @click="requestExport" :disabled="exportLoading">
            {{ exportLoading ? 'Requesting...' : 'Request Data Export' }}
          </button>
        </div>
      </div>

      <!-- Data Deletion Section -->
      <div class="settings-section danger-section">
        <div class="section-header">
          <h2 class="section-title">Delete Your Data or Account</h2>
        </div>
        <p class="section-description">
          Choose to delete your financial data while keeping your account, or permanently delete your entire account.
        </p>
        <div class="section-actions">
          <button class="btn btn-danger" @click="openDeletionWizard">
            Manage Account Deletion
          </button>
        </div>
      </div>

      <!-- Your Rights Section -->
      <div class="settings-section info-section">
        <div class="section-header">
          <h2 class="section-title">Your Data Rights</h2>
        </div>
        <ul class="rights-list">
          <li>
            <strong>Right to Access:</strong> You can request a copy of all data we hold about you.
          </li>
          <li>
            <strong>Right to Rectification:</strong> You can update your personal information at any time.
          </li>
          <li>
            <strong>Right to Erasure:</strong> You can request deletion of your account and data.
          </li>
          <li>
            <strong>Right to Portability:</strong> You can export your data in a machine-readable format.
          </li>
          <li>
            <strong>Right to Object:</strong> You can opt out of marketing communications.
          </li>
        </ul>
        <p class="contact-info">
          For any data protection queries, contact us at
          <a href="mailto:support@fynla.org">support@fynla.org</a>
        </p>
      </div>

      <!-- Deletion Wizard Modal -->
      <div v-if="deletionWizard.show" class="modal-overlay" @click.self="closeDeletionWizard">
        <div class="modal deletion-modal">
          <!-- Step indicator -->
          <div class="step-indicator">
            <div class="step" :class="{ active: deletionWizard.step >= 1, completed: deletionWizard.step > 1 }">
              <span class="step-number">1</span>
              <span class="step-label">Choose</span>
            </div>
            <div class="step-line" :class="{ active: deletionWizard.step > 1 }"></div>
            <div class="step" :class="{ active: deletionWizard.step >= 2, completed: deletionWizard.step > 2 }">
              <span class="step-number">2</span>
              <span class="step-label">Verify</span>
            </div>
            <div class="step-line" :class="{ active: deletionWizard.step > 2 }"></div>
            <div class="step" :class="{ active: deletionWizard.step >= 3 }">
              <span class="step-number">3</span>
              <span class="step-label">Confirm</span>
            </div>
          </div>

          <!-- Close button -->
          <button class="modal-close" @click="closeDeletionWizard">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>

          <!-- Step 1: Choose deletion type -->
          <div v-if="deletionWizard.step === 1" class="wizard-step">
            <h3 class="wizard-title">What would you like to delete?</h3>

            <div class="deletion-options">
              <button
                class="deletion-option"
                :class="{ selected: deletionWizard.type === 'data' }"
                @click="selectDeletionType('data')"
                :disabled="deletionWizard.loading"
              >
                <div class="option-icon">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </div>
                <h4>Delete My Data</h4>
                <p>Remove all financial data but keep your account active. You'll be returned to an empty dashboard.</p>
              </button>

              <button
                class="deletion-option danger"
                :class="{ selected: deletionWizard.type === 'account' }"
                @click="selectDeletionType('account')"
                :disabled="deletionWizard.loading"
              >
                <div class="option-icon danger">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                  </svg>
                </div>
                <h4>Delete My Account</h4>
                <p>Permanently delete your account and all data. You'll be logged out and won't be able to sign in again.</p>
              </button>
            </div>

            <div v-if="deletionWizard.error" class="error-message">
              {{ deletionWizard.error }}
            </div>

            <div v-if="deletionWizard.loading" class="loading-indicator">
              <div class="w-5 h-5 border-2 border-light-gray border-t-raspberry-500 rounded-full animate-spin"></div>
              <span>Preparing...</span>
            </div>
          </div>

          <!-- Step 2: Verify identity -->
          <div v-if="deletionWizard.step === 2" class="wizard-step">
            <h3 class="wizard-title">Verify Your Identity</h3>

            <div v-if="deletionWizard.verificationMethod === '2fa'" class="verification-section">
              <div class="verification-icon">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
              </div>
              <p class="verification-instruction">Enter the 6-digit code from your authenticator app:</p>
            </div>

            <div v-else class="verification-section">
              <div class="verification-icon email">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
              </div>
              <p class="verification-instruction">We've sent a verification code to your email. Enter it below:</p>
            </div>

            <!-- 6-digit code input -->
            <div class="code-input-container">
              <input
                v-for="(digit, index) in 6"
                :key="index"
                :ref="el => codeInputRefs[index] = el"
                type="text"
                maxlength="1"
                inputmode="numeric"
                pattern="[0-9]*"
                class="code-input"
                :class="{ error: deletionWizard.error }"
                :value="codeDigits[index]"
                @input="handleCodeInput($event, index)"
                @keydown="handleCodeKeydown($event, index)"
                @paste="handleCodePaste"
                :disabled="deletionWizard.loading"
              />
            </div>

            <div v-if="deletionWizard.error" class="error-message">
              {{ deletionWizard.error }}
            </div>

            <div class="verification-actions">
              <button
                v-if="deletionWizard.verificationMethod === 'email'"
                class="btn btn-link"
                @click="resendCode"
                :disabled="deletionWizard.loading || resendCooldown > 0"
              >
                {{ resendCooldown > 0 ? `Resend code in ${resendCooldown}s` : 'Resend code' }}
              </button>

              <button
                class="btn btn-primary"
                @click="verifyIdentity"
                :disabled="!isCodeComplete || deletionWizard.loading"
              >
                {{ deletionWizard.loading ? 'Verifying...' : 'Verify' }}
              </button>
            </div>

            <button class="btn btn-outline btn-back" @click="goBackToStep1">
              Back
            </button>
          </div>

          <!-- Step 3: Final confirmation -->
          <div v-if="deletionWizard.step === 3" class="wizard-step">
            <h3 class="wizard-title">Final Confirmation</h3>

            <div class="warning-box">
              <div class="warning-icon">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
              </div>
              <div class="warning-content">
                <p><strong>Warning:</strong> This will permanently delete:</p>
                <ul>
                  <li>Your profile and personal information</li>
                  <li>All financial data (properties, accounts, policies)</li>
                  <li>Goals and planning history</li>
                  <li>All activity logs</li>
                </ul>
                <p v-if="deletionWizard.type === 'account'" class="warning-note">
                  Your account will be permanently closed and you will be logged out.
                </p>
                <p v-else class="warning-note info">
                  Your account will remain active but all data will be removed.
                </p>
              </div>
            </div>

            <div class="confirmation-input">
              <label>
                Type exactly: <strong>"{{ requiredConfirmationPhrase }}"</strong>
              </label>
              <input
                v-model="deletionWizard.confirmationText"
                type="text"
                class="form-input"
                :placeholder="requiredConfirmationPhrase"
                :disabled="deletionWizard.loading"
              />
            </div>

            <div v-if="deletionWizard.error" class="error-message">
              {{ deletionWizard.error }}
            </div>

            <div class="confirmation-actions">
              <button class="btn btn-outline" @click="goBackToStep2">
                Back
              </button>
              <button
                class="btn btn-danger"
                @click="executeDelete"
                :disabled="!confirmationValid || deletionWizard.loading"
              >
                {{ deletionWizard.loading ? 'Deleting...' : (deletionWizard.type === 'account' ? 'Delete My Account' : 'Delete My Data') }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import privacyService from '@/services/privacyService';
import SettingsTabBar from '@/components/Settings/SettingsTabBar.vue';

import logger from '@/utils/logger';
export default {
  name: 'PrivacySettings',
  components: {
    AppLayout,
    SettingsTabBar,
  },
  data() {
    return {
      consents: {
        marketing: true,
      },
      exportFormat: 'json',
      exportLoading: false,
      pendingExport: null,
      completedExport: null,

      // Deletion wizard state
      deletionWizard: {
        show: false,
        step: 1,
        type: null, // 'account' | 'data'
        sessionToken: null,
        verificationMethod: null, // '2fa' | 'email'
        confirmationText: '',
        loading: false,
        error: null,
      },

      // Code input state
      codeDigits: ['', '', '', '', '', ''],
      codeInputRefs: [],
      resendCooldown: 0,
    };
  },
  computed: {
    requiredConfirmationPhrase() {
      return this.deletionWizard.type === 'account'
        ? 'Delete my Account'
        : 'Delete my Data';
    },
    confirmationValid() {
      return this.deletionWizard.confirmationText === this.requiredConfirmationPhrase;
    },
    isCodeComplete() {
      return this.codeDigits.every(d => d !== '') && this.codeDigits.join('').length === 6;
    },
    fullCode() {
      return this.codeDigits.join('');
    },
  },
  mounted() {
    this.loadConsents();
    this.checkExportStatus();
  },
  methods: {
    async loadConsents() {
      try {
        const response = await privacyService.getConsents();
        const consents = response.data?.consents || [];
        consents.forEach(consent => {
          if (consent.consent_type === 'marketing') {
            this.consents.marketing = consent.granted;
          }
        });
      } catch (error) {
        logger.error('Failed to load consents:', error);
      }
    },
    async updateConsent(type, granted) {
      try {
        await privacyService.updateConsent(type, granted);
        this.$toast?.success?.(`${type} consent updated`);
      } catch (error) {
        this.$toast?.error?.('Failed to update consent') ||
          logger.error('Failed to update consent:', error);
      }
    },
    async checkExportStatus() {
      try {
        const response = await privacyService.getExportStatus();
        const exports = response.data?.exports || [];
        const pending = exports.find(e => e.status === 'pending' || e.status === 'processing');
        const completed = exports.find(e => e.status === 'completed');

        this.pendingExport = pending;
        this.completedExport = completed;
      } catch (error) {
        logger.error('Failed to check export status:', error);
      }
    },
    async requestExport() {
      this.exportLoading = true;
      try {
        await privacyService.requestExport(this.exportFormat);
        this.$toast?.success?.('Data export requested. You will be notified when ready.') ||
          alert('Data export requested. You will be notified when ready.');
        this.checkExportStatus();
      } catch (error) {
        this.$toast?.error?.(error.response?.data?.message || 'Failed to request export') ||
          alert(error.response?.data?.message || 'Failed to request export');
      } finally {
        this.exportLoading = false;
      }
    },
    async downloadExport() {
      if (!this.completedExport) return;
      try {
        const response = await privacyService.downloadExport(this.completedExport.id);
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', `fynla-data-export.${this.completedExport.format}`);
        document.body.appendChild(link);
        link.click();
        link.remove();
      } catch (error) {
        this.$toast?.error?.('Failed to download export') ||
          alert('Failed to download export');
      }
    },

    // Deletion wizard methods
    openDeletionWizard() {
      this.deletionWizard = {
        show: true,
        step: 1,
        type: null,
        sessionToken: null,
        verificationMethod: null,
        confirmationText: '',
        loading: false,
        error: null,
      };
      this.codeDigits = ['', '', '', '', '', ''];
    },
    closeDeletionWizard() {
      this.deletionWizard.show = false;
      this.deletionWizard.step = 1;
      this.deletionWizard.type = null;
      this.deletionWizard.sessionToken = null;
      this.deletionWizard.error = null;
      this.codeDigits = ['', '', '', '', '', ''];
    },

    async selectDeletionType(type) {
      this.deletionWizard.type = type;
      this.deletionWizard.loading = true;
      this.deletionWizard.error = null;

      try {
        const response = await privacyService.initiateErasure(type);
        this.deletionWizard.sessionToken = response.session_token;

        if (response.requires_2fa) {
          this.deletionWizard.verificationMethod = '2fa';
        } else {
          this.deletionWizard.verificationMethod = 'email';
        }

        this.deletionWizard.step = 2;

        // Focus first code input
        this.$nextTick(() => {
          if (this.codeInputRefs[0]) {
            this.codeInputRefs[0].focus();
          }
        });
      } catch (error) {
        this.deletionWizard.error = error.response?.data?.message || 'Failed to initiate deletion';
      } finally {
        this.deletionWizard.loading = false;
      }
    },

    async verifyIdentity() {
      if (!this.isCodeComplete) return;

      this.deletionWizard.loading = true;
      this.deletionWizard.error = null;

      try {
        await privacyService.verifyErasure(this.deletionWizard.sessionToken, this.fullCode);

        this.deletionWizard.step = 3;
      } catch (error) {
        this.deletionWizard.error = error.response?.data?.message || 'Verification failed';
        this.clearCode();
      } finally {
        this.deletionWizard.loading = false;
      }
    },

    async resendCode() {
      if (this.resendCooldown > 0) return;

      try {
        await privacyService.resendErasureCode(this.deletionWizard.sessionToken);

        this.$toast?.success?.('Verification code sent') ||
          alert('Verification code sent to your email');

        // Start cooldown
        this.resendCooldown = 60;
        const interval = setInterval(() => {
          this.resendCooldown--;
          if (this.resendCooldown <= 0) {
            clearInterval(interval);
          }
        }, 1000);
      } catch (error) {
        this.$toast?.error?.(error.response?.data?.message || 'Failed to resend code') ||
          alert(error.response?.data?.message || 'Failed to resend code');
      }
    },

    async executeDelete() {
      if (!this.confirmationValid) return;

      this.deletionWizard.loading = true;
      this.deletionWizard.error = null;

      try {
        const response = await privacyService.executeErasure(
          this.deletionWizard.sessionToken,
          this.deletionWizard.confirmationText
        );

        if (response.logout_required) {
          // Account deleted - log out and redirect
          await this.$store.dispatch('auth/logout');
          this.$router.push('/login');
        } else {
          // Data deleted - show success and reload
          this.$toast?.success?.('Your data has been deleted') ||
            alert('Your data has been deleted');
          this.closeDeletionWizard();
          this.$router.push('/dashboard');
        }
      } catch (error) {
        this.deletionWizard.error = error.response?.data?.message || 'Deletion failed';
      } finally {
        this.deletionWizard.loading = false;
      }
    },

    goBackToStep1() {
      this.deletionWizard.step = 1;
      this.deletionWizard.type = null;
      this.deletionWizard.sessionToken = null;
      this.deletionWizard.error = null;
      this.codeDigits = ['', '', '', '', '', ''];
    },

    goBackToStep2() {
      this.deletionWizard.step = 2;
      this.deletionWizard.confirmationText = '';
      this.deletionWizard.error = null;
    },

    // Code input handlers
    handleCodeInput(event, index) {
      const value = event.target.value.replace(/[^0-9]/g, '');
      this.codeDigits[index] = value;
      this.deletionWizard.error = null;

      // Move to next input
      if (value && index < 5) {
        this.$nextTick(() => {
          if (this.codeInputRefs[index + 1]) {
            this.codeInputRefs[index + 1].focus();
          }
        });
      }
    },

    handleCodeKeydown(event, index) {
      // Handle backspace
      if (event.key === 'Backspace' && !this.codeDigits[index] && index > 0) {
        this.$nextTick(() => {
          if (this.codeInputRefs[index - 1]) {
            this.codeInputRefs[index - 1].focus();
          }
        });
      }
      // Handle arrow keys
      if (event.key === 'ArrowLeft' && index > 0) {
        this.codeInputRefs[index - 1]?.focus();
      }
      if (event.key === 'ArrowRight' && index < 5) {
        this.codeInputRefs[index + 1]?.focus();
      }
    },

    handleCodePaste(event) {
      event.preventDefault();
      const pastedData = event.clipboardData.getData('text').replace(/[^0-9]/g, '').slice(0, 6);
      if (pastedData) {
        pastedData.split('').forEach((digit, i) => {
          if (i < 6) this.codeDigits[i] = digit;
        });
        this.deletionWizard.error = null;
        // Focus the appropriate input
        const nextEmpty = Math.min(pastedData.length, 5);
        this.$nextTick(() => {
          if (this.codeInputRefs[nextEmpty]) {
            this.codeInputRefs[nextEmpty].focus();
          }
        });
      }
    },

    clearCode() {
      this.codeDigits = ['', '', '', '', '', ''];
      this.$nextTick(() => {
        if (this.codeInputRefs[0]) {
          this.codeInputRefs[0].focus();
        }
      });
    },
  },
};
</script>

<style scoped>
.settings-section {
  background: white;
  border-radius: 0.5rem;
  padding: 1.5rem;
  margin-bottom: 1.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.section-header {
  margin-bottom: 0.75rem;
}

.section-title {
  font-size: 1.125rem;
  font-weight: 600;
  @apply text-horizon-700;
  margin: 0;
}

.section-description {
  @apply text-neutral-500;
  font-size: 0.875rem;
  margin-bottom: 1rem;
}

.consent-items {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.consent-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  @apply bg-eggshell-50;
  border-radius: 0.375rem;
}

.consent-info h3 {
  font-size: 0.875rem;
  font-weight: 600;
  @apply text-horizon-700;
  margin: 0 0 0.25rem;
}

.consent-info p {
  font-size: 0.75rem;
  @apply text-neutral-500;
  margin: 0;
}

.toggle-label.always-on {
  font-size: 0.75rem;
  @apply text-spring-500;
  font-weight: 500;
}

.toggle {
  position: relative;
  display: inline-block;
  width: 44px;
  height: 24px;
}

.toggle input {
  opacity: 0;
  width: 0;
  height: 0;
}

.toggle-slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  @apply bg-neutral-300;
  transition: 0.3s;
  border-radius: 24px;
}

.toggle-slider:before {
  position: absolute;
  content: "";
  height: 18px;
  width: 18px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  transition: 0.3s;
  border-radius: 50%;
}

.toggle input:checked + .toggle-slider {
  @apply bg-raspberry-500;
}

.toggle input:checked + .toggle-slider:before {
  transform: translateX(20px);
}

.export-status {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  @apply bg-spring-50 border border-spring-200;
  border-radius: 0.375rem;
}

.export-status.success {
  @apply bg-spring-50 border-spring-200;
}

.status-icon {
  flex-shrink: 0;
}

.status-icon.pending {
  @apply text-violet-500;
}

.status-icon.success {
  @apply text-spring-500;
}

.status-text strong {
  display: block;
  @apply text-horizon-700;
}

.status-text p {
  font-size: 0.75rem;
  @apply text-neutral-500;
  margin: 0;
}

.export-actions {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.format-selector {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.format-selector label {
  font-size: 0.875rem;
  @apply text-horizon-600;
}

.format-selector select {
  padding: 0.5rem 0.75rem;
  @apply border border-light-gray;
  border-radius: 0.375rem;
  font-size: 0.875rem;
}

.danger-section {
  @apply border border-raspberry-200 bg-raspberry-50;
}

.info-section {
  @apply bg-violet-50 border border-violet-200;
}

.rights-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.rights-list li {
  padding: 0.5rem 0;
  font-size: 0.875rem;
  @apply text-violet-700;
}

.rights-list li strong {
  @apply text-violet-800;
}

.contact-info {
  margin-top: 1rem;
  font-size: 0.875rem;
  @apply text-violet-700;
}

.contact-info a {
  @apply text-violet-500;
  text-decoration: underline;
}

.section-actions {
  margin-top: 1rem;
}

/* Deletion Modal Styles */
.modal-overlay {
  position: fixed;
  inset: 0;
  background-color: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 50;
  padding: 1rem;
}

.deletion-modal {
  background: white;
  border-radius: 1rem;
  max-width: 520px;
  width: 100%;
  padding: 2rem;
  position: relative;
  max-height: 90vh;
  overflow-y: auto;
}

.modal-close {
  position: absolute;
  top: 1rem;
  right: 1rem;
  padding: 0.5rem;
  background: none;
  border: none;
  @apply text-neutral-500;
  cursor: pointer;
  border-radius: 0.375rem;
  transition: color 0.2s;
}

.modal-close:hover {
  @apply text-horizon-700;
}

/* Step Indicator */
.step-indicator {
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 2rem;
  padding: 0 1rem;
}

.step {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
}

.step-number {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  @apply bg-eggshell-100 text-neutral-500;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 0.875rem;
  transition: all 0.2s;
}

.step.active .step-number {
  @apply bg-raspberry-500;
  color: white;
}

.step.completed .step-number {
  @apply bg-spring-500;
  color: white;
}

.step-label {
  font-size: 0.75rem;
  @apply text-neutral-500;
}

.step.active .step-label {
  @apply text-raspberry-500;
  font-weight: 500;
}

.step-line {
  flex: 1;
  height: 2px;
  @apply bg-eggshell-100;
  margin: 0 0.5rem;
  margin-bottom: 1.5rem;
  max-width: 60px;
}

.step-line.active {
  @apply bg-spring-500;
}

/* Wizard Steps */
.wizard-step {
  text-align: center;
}

.wizard-title {
  font-size: 1.25rem;
  font-weight: 600;
  @apply text-horizon-700;
  margin-bottom: 1.5rem;
}

/* Deletion Options (Step 1) */
.deletion-options {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  margin-bottom: 1rem;
}

.deletion-option {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 1.5rem;
  @apply border-2 border-light-gray;
  border-radius: 0.75rem;
  background: white;
  cursor: pointer;
  transition: all 0.2s;
  text-align: center;
}

.deletion-option:hover {
  @apply border-raspberry-500 bg-savannah-100;
}

.deletion-option.selected {
  @apply border-raspberry-500 bg-violet-50;
}

.deletion-option.danger:hover {
  @apply border-raspberry-500 bg-raspberry-50;
}

.deletion-option.danger.selected {
  @apply border-raspberry-500 bg-raspberry-50;
}

.option-icon {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  @apply bg-violet-100 text-raspberry-500;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 1rem;
}

.option-icon.danger {
  @apply bg-raspberry-100 text-raspberry-500;
}

.deletion-option h4 {
  font-size: 1rem;
  font-weight: 600;
  @apply text-horizon-700;
  margin: 0 0 0.5rem;
}

.deletion-option p {
  font-size: 0.875rem;
  @apply text-neutral-500;
  margin: 0;
  line-height: 1.5;
}

/* Verification Section (Step 2) */
.verification-section {
  margin-bottom: 1.5rem;
}

.verification-icon {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  @apply bg-violet-100 text-raspberry-500;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 1rem;
}

.verification-icon.email {
  @apply bg-violet-100 text-violet-500;
}

.verification-instruction {
  @apply text-neutral-500;
  font-size: 0.875rem;
}

.code-input-container {
  display: flex;
  justify-content: center;
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.code-input {
  width: 48px;
  height: 56px;
  text-align: center;
  font-size: 1.5rem;
  font-weight: 600;
  @apply border-2 border-light-gray;
  border-radius: 0.5rem;
  outline: none;
  transition: all 0.2s;
}

.code-input:focus {
  @apply border-raspberry-500;
  box-shadow: 0 0 0 3px rgba(88, 84, 230, 0.1);
}

.code-input.error {
  @apply border-raspberry-500 bg-raspberry-50;
}

.verification-actions {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1rem;
}

.btn-back {
  width: 100%;
}

/* Warning Box (Step 3) */
.warning-box {
  display: flex;
  gap: 1rem;
  @apply bg-raspberry-50 border border-raspberry-200;
  border-radius: 0.5rem;
  padding: 1rem;
  margin-bottom: 1.5rem;
  text-align: left;
}

.warning-icon {
  flex-shrink: 0;
  @apply text-raspberry-500;
}

.warning-content p {
  @apply text-raspberry-800;
  font-size: 0.875rem;
  margin: 0 0 0.5rem;
}

.warning-content ul {
  margin: 0.5rem 0;
  padding-left: 1.25rem;
  @apply text-raspberry-800;
  font-size: 0.75rem;
}

.warning-note {
  font-weight: 600;
  margin-top: 0.75rem !important;
}

.warning-note.info {
  @apply text-violet-700;
}

.confirmation-input {
  text-align: left;
  margin-bottom: 1.5rem;
}

.confirmation-input label {
  display: block;
  font-size: 0.875rem;
  @apply text-horizon-600;
  margin-bottom: 0.5rem;
}

.confirmation-input strong {
  @apply text-raspberry-500;
}

.form-input {
  width: 100%;
  padding: 0.75rem 1rem;
  @apply border border-light-gray;
  border-radius: 0.375rem;
  font-size: 1rem;
  transition: border-color 0.2s;
}

.form-input:focus {
  outline: none;
  @apply border-raspberry-500;
  box-shadow: 0 0 0 3px rgba(88, 84, 230, 0.1);
}

.confirmation-actions {
  display: flex;
  gap: 1rem;
}

.confirmation-actions .btn {
  flex: 1;
}

/* Error & Loading States */
.error-message {
  @apply bg-raspberry-50 border border-raspberry-200 text-raspberry-500;
  padding: 0.75rem 1rem;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  margin-bottom: 1rem;
}

.loading-indicator {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  @apply text-neutral-500;
  padding: 1rem;
}

/* Button Styles */
.btn {
  padding: 0.75rem 1.5rem;
  border-radius: 0.375rem;
  font-weight: 500;
  font-size: 0.875rem;
  cursor: pointer;
  transition: all 0.2s;
  border: none;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-primary {
  @apply bg-raspberry-500;
  color: white;
}

.btn-primary:hover:not(:disabled) {
  @apply bg-raspberry-600;
}

.btn-danger {
  @apply bg-raspberry-500;
  color: white;
}

.btn-danger:hover:not(:disabled) {
  @apply bg-raspberry-700;
}

.btn-outline {
  @apply bg-white border border-light-gray text-horizon-600;
}

.btn-outline:hover:not(:disabled) {
  @apply bg-eggshell-50;
}

.btn-link {
  background: none;
  @apply text-raspberry-500;
  padding: 0.5rem;
}

.btn-link:hover:not(:disabled) {
  text-decoration: underline;
}


</style>
