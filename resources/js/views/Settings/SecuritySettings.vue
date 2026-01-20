<template>
  <AppLayout>
    <div class="security-settings">
    <div class="page-header">
      <h1 class="page-title">Security Settings</h1>
      <p class="page-description">
        Manage your account security and privacy settings
      </p>
    </div>

    <!-- MFA Section -->
    <div class="settings-section">
      <div class="section-header">
        <h2 class="section-title">Two-Factor Authentication</h2>
        <span
          v-if="mfaEnabled"
          class="status-badge enabled"
        >Enabled</span>
        <span
          v-else
          class="status-badge disabled"
        >Disabled</span>
      </div>
      <p class="section-description">
        Add an extra layer of security to your account by requiring a verification code from your authenticator app when signing in.
      </p>
      <div class="section-actions">
        <button
          v-if="!mfaEnabled"
          class="btn btn-primary"
          @click="setupMFA"
        >
          Enable Two-Factor Authentication
        </button>
        <button
          v-else
          class="btn btn-danger"
          @click="showDisableMFAModal = true"
        >
          Disable Two-Factor Authentication
        </button>
      </div>
    </div>

    <!-- Active Sessions Section -->
    <div class="settings-section">
      <div class="section-header">
        <h2 class="section-title">Active Sessions</h2>
      </div>
      <p class="section-description">
        View and manage your active login sessions across different devices.
      </p>
      <div
        v-if="sessions.length"
        class="sessions-list"
      >
        <div
          v-for="session in sessions"
          :key="session.id"
          class="session-item"
        >
          <div class="session-info">
            <div class="session-device">
              <span class="device-icon">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  class="h-5 w-5"
                  viewBox="0 0 20 20"
                  fill="currentColor"
                >
                  <path
                    fill-rule="evenodd"
                    d="M3 5a2 2 0 012-2h10a2 2 0 012 2v8a2 2 0 01-2 2h-2.22l.123.489.804.804A1 1 0 0113 18H7a1 1 0 01-.707-1.707l.804-.804L7.22 15H5a2 2 0 01-2-2V5zm5.771 7H5V5h10v7H8.771z"
                    clip-rule="evenodd"
                  />
                </svg>
              </span>
              <span class="device-name">{{ session.device || 'Unknown Device' }}</span>
              <span
                v-if="session.is_current"
                class="current-badge"
              >Current</span>
            </div>
            <div class="session-meta">
              <span class="session-ip">{{ session.ip_address }}</span>
              <span class="session-time">Last active: {{ formatDate(session.last_activity_at) }}</span>
            </div>
          </div>
          <button
            v-if="!session.is_current"
            class="btn btn-sm btn-outline"
            @click="revokeSession(session.id)"
          >
            Revoke
          </button>
        </div>
      </div>
      <p
        v-else
        class="empty-state"
      >
        No active sessions found.
      </p>
      <div
        v-if="sessions.length > 1"
        class="section-actions"
      >
        <button
          class="btn btn-outline btn-danger"
          @click="revokeAllOtherSessions"
        >
          Revoke All Other Sessions
        </button>
      </div>
    </div>

    <!-- Password Section -->
    <div class="settings-section">
      <div class="section-header">
        <h2 class="section-title">Password</h2>
      </div>
      <p class="section-description">
        Ensure your password is strong and unique. We recommend using a password manager.
      </p>
      <div class="section-actions">
        <button
          class="btn btn-primary"
          @click="showChangePasswordModal = true"
        >
          Change Password
        </button>
      </div>
    </div>

    <!-- Security Tips -->
    <div class="settings-section tips-section">
      <div class="section-header">
        <h2 class="section-title">Security Tips</h2>
      </div>
      <ul class="tips-list">
        <li>
          <span class="tip-icon check">&#10003;</span>
          Use a unique password for your Fynla account
        </li>
        <li>
          <span class="tip-icon check">&#10003;</span>
          Enable two-factor authentication for additional security
        </li>
        <li>
          <span class="tip-icon check">&#10003;</span>
          Review your active sessions regularly
        </li>
        <li>
          <span class="tip-icon check">&#10003;</span>
          Never share your login credentials with anyone
        </li>
        <li>
          <span class="tip-icon check">&#10003;</span>
          Log out when using shared devices
        </li>
      </ul>
    </div>

    <!-- MFA Setup Modal -->
    <MFASetupModal
      v-if="showMFASetupModal"
      @close="showMFASetupModal = false"
      @success="onMFAEnabled"
    />

    <!-- Disable MFA Modal -->
    <div
      v-if="showDisableMFAModal"
      class="modal-overlay"
    >
      <div class="modal">
        <div class="modal-header">
          <h3>Disable Two-Factor Authentication</h3>
        </div>
        <div class="modal-body">
          <p>
            This will remove the extra layer of security from your account.
            Are you sure you want to continue?
          </p>
          <div class="form-group">
            <label for="disable-password">Enter your password to confirm:</label>
            <input
              id="disable-password"
              v-model="disablePassword"
              type="password"
              class="form-input"
              placeholder="Your password"
            >
          </div>
        </div>
        <div class="modal-footer">
          <button
            class="btn btn-outline"
            @click="showDisableMFAModal = false"
          >
            Cancel
          </button>
          <button
            class="btn btn-danger"
            :disabled="!disablePassword"
            @click="disableMFA"
          >
            Disable MFA
          </button>
        </div>
      </div>
    </div>

    <!-- Change Password Modal -->
    <div
      v-if="showChangePasswordModal"
      class="modal-overlay"
    >
      <div class="modal">
        <div class="modal-header">
          <h3>Change Password</h3>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label for="current-password">Current Password</label>
            <input
              id="current-password"
              v-model="passwordForm.current_password"
              type="password"
              class="form-input"
            >
          </div>
          <div class="form-group">
            <label for="new-password">New Password</label>
            <input
              id="new-password"
              v-model="passwordForm.new_password"
              type="password"
              class="form-input"
            >
            <p class="form-hint">
              Must be at least 8 characters with uppercase, lowercase, number, and special character.
            </p>
          </div>
          <div class="form-group">
            <label for="confirm-password">Confirm New Password</label>
            <input
              id="confirm-password"
              v-model="passwordForm.new_password_confirmation"
              type="password"
              class="form-input"
            >
          </div>
        </div>
        <div class="modal-footer">
          <button
            class="btn btn-outline"
            @click="showChangePasswordModal = false"
          >
            Cancel
          </button>
          <button
            class="btn btn-primary"
            :disabled="!canChangePassword"
            @click="changePassword"
          >
            Change Password
          </button>
        </div>
      </div>
    </div>
  </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import MFASetupModal from '@/components/Auth/MFASetupModal.vue';
import api from '@/services/api';

export default {
  name: 'SecuritySettings',
  components: {
    AppLayout,
    MFASetupModal,
  },
  data() {
    return {
      mfaEnabled: false,
      sessions: [],
      showMFASetupModal: false,
      showDisableMFAModal: false,
      showChangePasswordModal: false,
      disablePassword: '',
      passwordForm: {
        current_password: '',
        new_password: '',
        new_password_confirmation: '',
      },
      loading: false,
    };
  },
  computed: {
    canChangePassword() {
      return (
        this.passwordForm.current_password &&
        this.passwordForm.new_password &&
        this.passwordForm.new_password_confirmation &&
        this.passwordForm.new_password === this.passwordForm.new_password_confirmation
      );
    },
  },
  mounted() {
    this.loadMFAStatus();
    this.loadSessions();
  },
  methods: {
    async loadMFAStatus() {
      try {
        const response = await api.get('/auth/mfa/status');
        this.mfaEnabled = response.data.data.mfa_enabled;
      } catch (error) {
        console.error('Failed to load MFA status:', error);
      }
    },
    async loadSessions() {
      try {
        const response = await api.get('/auth/sessions');
        this.sessions = response.data.data.sessions || [];
      } catch (error) {
        console.error('Failed to load sessions:', error);
      }
    },
    setupMFA() {
      this.showMFASetupModal = true;
    },
    async onMFAEnabled() {
      this.mfaEnabled = true;
      this.showMFASetupModal = false;
      // Update user in Vuex store so navbar/dashboard hide the 2FA prompts
      await this.$store.dispatch('auth/fetchUser');
    },
    async disableMFA() {
      try {
        await api.post('/auth/mfa/disable', {
          password: this.disablePassword,
        });
        this.mfaEnabled = false;
        this.showDisableMFAModal = false;
        this.disablePassword = '';
        // Update user in Vuex store so navbar/dashboard show the 2FA prompts again
        await this.$store.dispatch('auth/fetchUser');
        this.$toast?.success?.('Two-factor authentication has been disabled.');
      } catch (error) {
        this.$toast?.error?.(error.response?.data?.message || 'Failed to disable MFA.');
      }
    },
    async revokeSession(sessionId) {
      try {
        await api.delete(`/auth/sessions/${sessionId}`);
        this.sessions = this.sessions.filter((s) => s.id !== sessionId);
        this.$toast?.success?.('Session revoked successfully.');
      } catch (error) {
        this.$toast?.error?.(error.response?.data?.message || 'Failed to revoke session.');
      }
    },
    async revokeAllOtherSessions() {
      try {
        await api.delete('/auth/sessions/others/all');
        this.sessions = this.sessions.filter((s) => s.is_current);
        this.$toast?.success?.('All other sessions have been revoked.');
      } catch (error) {
        this.$toast?.error?.(error.response?.data?.message || 'Failed to revoke sessions.');
      }
    },
    async changePassword() {
      try {
        await api.post('/auth/change-password', this.passwordForm);
        this.showChangePasswordModal = false;
        this.passwordForm = {
          current_password: '',
          new_password: '',
          new_password_confirmation: '',
        };
        this.$toast?.success?.('Password changed successfully.');
      } catch (error) {
        this.$toast?.error?.(error.response?.data?.message || 'Failed to change password.');
      }
    },
    formatDate(dateString) {
      if (!dateString) return 'Unknown';
      return new Date(dateString).toLocaleString();
    },
  },
};
</script>

<style scoped>
.security-settings {
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
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}

.section-title {
  font-size: 1.125rem;
  font-weight: 600;
  color: #111827;
  margin: 0;
}

.status-badge {
  font-size: 0.75rem;
  font-weight: 500;
  padding: 0.25rem 0.5rem;
  border-radius: 9999px;
}

.status-badge.enabled {
  background-color: #d1fae5;
  color: #065f46;
}

.status-badge.disabled {
  background-color: #fee2e2;
  color: #991b1b;
}

.section-description {
  color: #6b7280;
  font-size: 0.875rem;
  margin-bottom: 1rem;
}

.section-actions {
  margin-top: 1rem;
}

.sessions-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.session-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  background-color: #f9fafb;
  border-radius: 0.375rem;
}

.session-device {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.25rem;
}

.device-icon {
  color: #6b7280;
}

.device-name {
  font-weight: 500;
  color: #111827;
}

.current-badge {
  font-size: 0.75rem;
  background-color: #dbeafe;
  color: #1e40af;
  padding: 0.125rem 0.375rem;
  border-radius: 0.25rem;
}

.session-meta {
  display: flex;
  gap: 1rem;
  font-size: 0.75rem;
  color: #6b7280;
}

.tips-section {
  background-color: #f0fdf4;
  border: 1px solid #bbf7d0;
}

.tips-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.tips-list li {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0;
  color: #166534;
}

.tip-icon.check {
  color: #16a34a;
  font-weight: bold;
}

/* Modal/button/form styles are in app.css */
.modal {
  max-width: 400px;
}

.btn-outline.btn-danger {
  border-color: #fca5a5;
  color: #dc2626;
  background-color: white;
}

.btn-outline.btn-danger:hover {
  background-color: #fef2f2;
}

.empty-state {
  color: #6b7280;
  font-style: italic;
}
</style>
