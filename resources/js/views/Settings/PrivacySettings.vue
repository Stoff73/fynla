<template>
  <AppLayout>
    <div class="privacy-settings">
      <div class="page-header">
        <h1 class="page-title">Privacy & Data Settings</h1>
        <p class="page-description">
          Manage your data privacy preferences and access your personal data
        </p>
      </div>

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
              <h3>Analytics</h3>
              <p>Help us improve the app by collecting usage data.</p>
            </div>
            <div class="consent-toggle">
              <label class="toggle">
                <input
                  v-model="consents.analytics"
                  type="checkbox"
                  @change="updateConsent('analytics', consents.analytics)"
                >
                <span class="toggle-slider"></span>
              </label>
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
          <h2 class="section-title">Delete Your Account</h2>
        </div>
        <p class="section-description">
          Permanently delete your account and all associated data. This action cannot be undone.
        </p>
        <div class="section-actions">
          <button class="btn btn-danger" @click="showDeleteModal = true">
            Request Account Deletion
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
          <a href="mailto:privacy@fynla.org">privacy@fynla.org</a>
        </p>
      </div>

      <!-- Delete Account Modal -->
      <div v-if="showDeleteModal" class="modal-overlay">
        <div class="modal">
          <div class="modal-header">
            <h3>Delete Account</h3>
          </div>
          <div class="modal-body">
            <div class="warning-box">
              <p><strong>Warning:</strong> This will permanently delete:</p>
              <ul>
                <li>Your profile and personal information</li>
                <li>All financial data (properties, accounts, policies)</li>
                <li>Goals and planning history</li>
                <li>All activity logs</li>
              </ul>
              <p class="warning-note">This action cannot be undone.</p>
            </div>
            <div class="form-group">
              <label for="delete-confirm">Type "DELETE" to confirm:</label>
              <input
                id="delete-confirm"
                v-model="deleteConfirmation"
                type="text"
                class="form-input"
                placeholder="DELETE"
              >
            </div>
            <div class="form-group">
              <label for="delete-password">Enter your password:</label>
              <input
                id="delete-password"
                v-model="deletePassword"
                type="password"
                class="form-input"
                placeholder="Your password"
              >
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-outline" @click="showDeleteModal = false">
              Cancel
            </button>
            <button
              class="btn btn-danger"
              :disabled="deleteConfirmation !== 'DELETE' || !deletePassword"
              @click="requestDeletion"
            >
              Delete My Account
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import api from '@/services/api';

export default {
  name: 'PrivacySettings',
  components: {
    AppLayout,
  },
  data() {
    return {
      consents: {
        analytics: false,
        marketing: false,
      },
      exportFormat: 'json',
      exportLoading: false,
      pendingExport: null,
      completedExport: null,
      showDeleteModal: false,
      deleteConfirmation: '',
      deletePassword: '',
    };
  },
  mounted() {
    this.loadConsents();
    this.checkExportStatus();
  },
  methods: {
    async loadConsents() {
      try {
        const response = await api.get('/auth/gdpr/consents');
        const consents = response.data.data?.consents || [];
        consents.forEach(consent => {
          if (consent.consent_type === 'analytics') {
            this.consents.analytics = consent.granted;
          } else if (consent.consent_type === 'marketing') {
            this.consents.marketing = consent.granted;
          }
        });
      } catch (error) {
        console.error('Failed to load consents:', error);
      }
    },
    async updateConsent(type, granted) {
      try {
        await api.put('/auth/gdpr/consents', {
          consent_type: type,
          granted: granted,
        });
        this.$toast?.success?.(`${type} consent updated`) ||
          console.log(`${type} consent updated`);
      } catch (error) {
        this.$toast?.error?.('Failed to update consent') ||
          console.error('Failed to update consent:', error);
      }
    },
    async checkExportStatus() {
      try {
        const response = await api.get('/auth/gdpr/export/status');
        const exports = response.data.data?.exports || [];
        const pending = exports.find(e => e.status === 'pending' || e.status === 'processing');
        const completed = exports.find(e => e.status === 'completed');

        this.pendingExport = pending;
        this.completedExport = completed;
      } catch (error) {
        console.error('Failed to check export status:', error);
      }
    },
    async requestExport() {
      this.exportLoading = true;
      try {
        await api.post('/auth/gdpr/export', {
          format: this.exportFormat,
        });
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
        const response = await api.get(`/auth/gdpr/export/${this.completedExport.id}/download`, {
          responseType: 'blob',
        });
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
    async requestDeletion() {
      try {
        await api.post('/auth/gdpr/erasure', {
          password: this.deletePassword,
        });
        this.$toast?.success?.('Account deletion request submitted. You will receive a confirmation email.') ||
          alert('Account deletion request submitted. You will receive a confirmation email.');
        this.showDeleteModal = false;
      } catch (error) {
        this.$toast?.error?.(error.response?.data?.message || 'Failed to submit deletion request') ||
          alert(error.response?.data?.message || 'Failed to submit deletion request');
      }
    },
  },
};
</script>

<style scoped>
.privacy-settings {
  max-width: 800px;
  margin: 0 auto;
  padding: 2rem;
}

.page-header {
  margin-bottom: 2rem;
}

.page-title {
  font-size: 1.75rem;
  font-weight: 700;
  color: #111827;
  margin-bottom: 0.5rem;
}

.page-description {
  color: #6b7280;
}

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
  color: #111827;
  margin: 0;
}

.section-description {
  color: #6b7280;
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
  background-color: #f9fafb;
  border-radius: 0.375rem;
}

.consent-info h3 {
  font-size: 0.875rem;
  font-weight: 600;
  color: #111827;
  margin: 0 0 0.25rem;
}

.consent-info p {
  font-size: 0.75rem;
  color: #6b7280;
  margin: 0;
}

.toggle-label.always-on {
  font-size: 0.75rem;
  color: #059669;
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
  background-color: #d1d5db;
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
  background-color: #3b82f6;
}

.toggle input:checked + .toggle-slider:before {
  transform: translateX(20px);
}

.export-status {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  background-color: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: 0.375rem;
}

.export-status.success {
  background-color: #f0fdf4;
  border-color: #bbf7d0;
}

.status-icon {
  flex-shrink: 0;
}

.status-icon.pending {
  color: #f59e0b;
}

.status-icon.success {
  color: #059669;
}

.status-text strong {
  display: block;
  color: #111827;
}

.status-text p {
  font-size: 0.75rem;
  color: #6b7280;
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
  color: #374151;
}

.format-selector select {
  padding: 0.5rem 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 0.375rem;
  font-size: 0.875rem;
}

.danger-section {
  border: 1px solid #fecaca;
  background-color: #fef2f2;
}

.info-section {
  background-color: #eff6ff;
  border: 1px solid #bfdbfe;
}

.rights-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.rights-list li {
  padding: 0.5rem 0;
  font-size: 0.875rem;
  color: #1e40af;
}

.rights-list li strong {
  color: #1e3a8a;
}

.contact-info {
  margin-top: 1rem;
  font-size: 0.875rem;
  color: #1e40af;
}

.contact-info a {
  color: #2563eb;
  text-decoration: underline;
}

/* Modal/button/form styles are in app.css */
.modal {
  max-width: 440px;
}

.modal-header h3 {
  color: #dc2626;
}

.warning-box {
  background-color: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 0.375rem;
  padding: 1rem;
  margin-bottom: 1rem;
}

.warning-box p {
  color: #991b1b;
  font-size: 0.875rem;
  margin: 0;
}

.warning-box ul {
  margin: 0.5rem 0;
  padding-left: 1.25rem;
  color: #991b1b;
  font-size: 0.75rem;
}

.warning-note {
  font-weight: 600;
  margin-top: 0.75rem !important;
}

.section-actions {
  margin-top: 1rem;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.animate-spin {
  animation: spin 1s linear infinite;
}
</style>
