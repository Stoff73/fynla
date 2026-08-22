<template>
  <MobileChrome
    title="Personal Information"
    subtitle="Your canonical profile and financial position"
    :loading="loading"
    loading-label="your personal information"
    :contextual-request="contextualRequest"
  >
    <div v-if="error" class="m-card m-state" role="alert">
      <p class="m-err">{{ error }}</p>
      <button
        type="button"
        class="m-btn"
        data-testid="personal-information-retry"
        @click="load"
      >Try again</button>
    </div>

    <div v-else-if="!hasProfile" class="m-card m-state">
      <p class="m-sub">No personal information is available yet.</p>
    </div>

    <template v-else>
      <section class="m-card profile-card" aria-labelledby="profile-personal-heading">
        <h2 id="profile-personal-heading" class="m-section-label">About you</h2>
        <dl class="profile-list">
          <div class="profile-row"><dt>Name</dt><dd>{{ personalInfo.name || '—' }}</dd></div>
          <div class="profile-row"><dt>Email</dt><dd>{{ personalInfo.email || '—' }}</dd></div>
          <div class="profile-row"><dt>Date of birth</dt><dd>{{ personalInfo.date_of_birth || '—' }}</dd></div>
          <div class="profile-row"><dt>National Insurance</dt><dd>{{ personalInfo.national_insurance_number || 'Not recorded' }}</dd></div>
          <div class="profile-row"><dt>Address</dt><dd>{{ address }}</dd></div>
        </dl>
      </section>

      <section class="m-card profile-card" aria-labelledby="profile-household-heading">
        <h2 id="profile-household-heading" class="m-section-label">Household</h2>
        <dl class="profile-list">
          <div class="profile-row"><dt>Household</dt><dd>{{ householdLabel }}</dd></div>
          <div v-if="profile.spouse" class="profile-row"><dt>Spouse or partner</dt><dd>{{ profile.spouse.name }}</dd></div>
        </dl>
      </section>

      <section
        v-if="dependants.length"
        class="m-card profile-card"
        aria-labelledby="profile-dependants-heading"
        data-testid="personal-information-dependants"
      >
        <h2 id="profile-dependants-heading" class="m-section-label">Dependants</h2>
        <dl class="profile-list">
          <div v-for="dependant in dependants" :key="dependant.id" class="profile-row">
            <dt>{{ dependant.first_name || dependant.name || 'Dependant' }}</dt>
            <dd>{{ dependantLabel(dependant) }}</dd>
          </div>
        </dl>
      </section>

      <section class="m-card profile-card" aria-labelledby="profile-domicile-heading">
        <h2 id="profile-domicile-heading" class="m-section-label">Domicile</h2>
        <p class="m-sub profile-copy">{{ domicileLabel }}</p>
        <p v-if="profile.domicile_info?.country_of_birth" class="profile-note">
          Country of birth: {{ profile.domicile_info.country_of_birth }}
        </p>
      </section>

      <section class="m-card profile-card" aria-labelledby="profile-health-heading">
        <div class="profile-head">
          <h2 id="profile-health-heading" class="m-section-label">Health and lifestyle</h2>
          <button
            v-if="!editingHealth"
            type="button"
            class="m-btn-ghost profile-edit"
            data-testid="health-edit"
            @click="startEditingHealth"
          >Edit</button>
        </div>

        <p v-if="healthError" class="m-err" role="alert" data-testid="health-error">{{ healthError }}</p>

        <dl v-if="!editingHealth" class="profile-list">
          <div class="profile-row">
            <dt>General health</dt>
            <dd data-testid="health-status-value">{{ healthStatusLabel }}</dd>
          </div>
          <div class="profile-row">
            <dt>Smoking</dt>
            <dd data-testid="smoking-status-value">{{ smokingStatusLabel }}</dd>
          </div>
          <div class="profile-row">
            <dt>Highest education level</dt>
            <dd data-testid="education-level-value">{{ educationLevelLabel }}</dd>
          </div>
        </dl>

        <form v-else class="profile-form" @submit.prevent="saveHealth">
          <label class="profile-field">
            <span class="profile-field__label">Are you in good health?</span>
            <select v-model="healthForm.health_status" class="profile-input" data-testid="health-status-input">
              <option value="">Select an answer</option>
              <option v-for="option in healthStatusOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </label>

          <label class="profile-field">
            <span class="profile-field__label">Do you smoke?</span>
            <select v-model="healthForm.smoking_status" class="profile-input" data-testid="smoking-status-input">
              <option value="">Select an answer</option>
              <option v-for="option in smokingStatusOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </label>

          <label class="profile-field">
            <span class="profile-field__label">Highest education level</span>
            <select v-model="healthForm.education_level" class="profile-input" data-testid="education-level-input">
              <option value="">Select an answer</option>
              <option v-for="option in educationLevelOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </label>

          <p class="profile-note">
            Health and smoking details shape your protection premium estimates and life expectancy projections.
          </p>

          <div class="profile-actions">
            <button type="button" class="m-btn-ghost" data-testid="health-cancel" :disabled="savingHealth" @click="cancelEditingHealth">
              Cancel
            </button>
            <button type="submit" class="m-btn profile-save" data-testid="health-save" :disabled="savingHealth">
              {{ savingHealth ? 'Saving…' : 'Save changes' }}
            </button>
          </div>
        </form>
      </section>

      <section class="m-card profile-card" aria-labelledby="profile-summary-heading">
        <h2 id="profile-summary-heading" class="m-section-label">Financial summary</h2>
        <dl class="profile-list">
          <div class="profile-row"><dt>Annual income</dt><dd>{{ money(profile.income_occupation?.total_annual_income) }}</dd></div>
          <div class="profile-row"><dt>Monthly expenditure</dt><dd>{{ money(profile.expenditure?.monthly_expenditure) }}</dd></div>
          <div class="profile-row"><dt>Total assets</dt><dd>{{ money(profile.assets_summary?.total) }}</dd></div>
          <div class="profile-row"><dt>Total liabilities</dt><dd>{{ money(profile.liabilities_summary?.total) }}</dd></div>
          <div class="profile-row profile-row--total"><dt>Net Worth</dt><dd>{{ money(profile.net_worth) }}</dd></div>
        </dl>
      </section>
    </template>
  </MobileChrome>
</template>

<script>
import { apiGet, apiPut } from '../api.js';
import { handleAuthExpiry } from '../authExpiry.js';
import MobileChrome from '../components/MobileChrome.vue';
import {
  HEALTH_STATUS_OPTIONS,
  SMOKING_STATUS_OPTIONS,
  EDUCATION_LEVEL_OPTIONS,
  formatHealthStatus,
  formatSmokingStatus,
  formatEducationLevel,
} from '../constants/profileOptions.js';
import { buildContextualConversationRequest } from '../fyn/contextualConversation.js';
import { store } from '../store.js';

export default {
  name: 'MobilePersonalInformation',
  components: { MobileChrome },
  data: () => ({
    loading: true,
    error: '',
    profile: null,
    healthStatusOptions: HEALTH_STATUS_OPTIONS,
    smokingStatusOptions: SMOKING_STATUS_OPTIONS,
    educationLevelOptions: EDUCATION_LEVEL_OPTIONS,
    editingHealth: false,
    savingHealth: false,
    healthError: '',
    healthForm: { health_status: '', smoking_status: '', education_level: '' },
  }),
  computed: {
    contextualRequest() {
      return buildContextualConversationRequest({
        action: 'edit',
        resourceType: 'personal_information',
        currentDestination: { screen: 'personal_information', params: {}, fallback: 'dashboard' },
        origin: { kind: 'surface_action' },
      });
    },
    hasProfile() {
      return Boolean(this.profile?.personal_info);
    },
    personalInfo() {
      return this.profile?.personal_info || {};
    },
    dependants() {
      return (this.profile?.family_members || []).filter((member) => member.is_dependent);
    },
    householdLabel() {
      return this.profile?.household?.name
        || (this.profile?.spouse?.name ? `Household with ${this.profile.spouse.name}` : 'Single-person household');
    },
    domicileLabel() {
      const domicile = this.profile?.domicile_info || {};
      if (domicile.explanation) return domicile.explanation;
      return domicile.domicile_status
        ? domicile.domicile_status.replaceAll('_', ' ')
        : 'Not recorded';
    },
    healthStatusLabel() {
      return formatHealthStatus(this.personalInfo.health_status);
    },
    smokingStatusLabel() {
      return formatSmokingStatus(this.personalInfo.smoking_status);
    },
    educationLevelLabel() {
      return formatEducationLevel(this.personalInfo.education_level);
    },
    address() {
      const address = this.personalInfo.address || {};
      return [address.line_1, address.line_2, address.city, address.county, address.postcode]
        .filter(Boolean)
        .join(', ') || 'Not recorded';
    },
  },
  created() {
    this.load();
    // A View link tapped while already on this screen re-navigates to the same
    // route, so nothing remounts and created() never runs again. Same tick the
    // other /m screens watch.
    this.$watch(() => store.screenRefreshTick, () => { this.load(); });
  },
  methods: {
    startEditingHealth() {
      this.healthError = '';
      this.healthForm = {
        health_status: this.personalInfo.health_status || '',
        smoking_status: this.personalInfo.smoking_status || '',
        education_level: this.personalInfo.education_level || '',
      };
      this.editingHealth = true;
    },
    cancelEditingHealth() {
      this.editingHealth = false;
      this.healthError = '';
    },
    /**
     * Same endpoint and same validator as the desktop Health & Lifestyle form —
     * PUT /api/user/profile/personal -> UpdatePersonalInfoRequest. /m does not get
     * its own write path (Rule 20), which also means it inherits that request's
     * handling of an unanswered select: '' becomes null via
     * ConvertEmptyStringsToNull, and prepareForValidation drops the key rather
     * than writing null to smoking_status, which is NOT NULL (W-0006).
     */
    async saveHealth() {
      this.savingHealth = true;
      this.healthError = '';
      try {
        const { ok, status, data } = await apiPut('/api/user/profile/personal', this.healthForm, store.token);
        if (handleAuthExpiry({ status }, this.$router)) return;
        if (!ok) {
          this.healthError = data?.message || 'We could not save your health and lifestyle details.';
          return;
        }
        this.editingHealth = false;
        await this.load();
      } catch {
        this.healthError = 'Network error. Please try again.';
      } finally {
        this.savingHealth = false;
      }
    },
    dependantLabel(dependant) {
      const relationship = {
        child: 'Child',
        parent: 'Parent',
        other_dependent: 'Dependant',
      }[dependant.relationship] || 'Dependant';
      const age = dependant.age;

      return age == null ? relationship : `${relationship}, aged ${age}`;
    },
    money(value) {
      if (value == null || value === '' || Number.isNaN(Number(value))) return '—';
      return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
        maximumFractionDigits: 0,
      }).format(Number(value));
    },
    async load() {
      this.loading = true;
      this.error = '';
      this.profile = null;
      try {
        const { ok, status, data } = await apiGet('/api/user/profile', store.token);
        if (handleAuthExpiry({ status }, this.$router)) return;
        if (ok) this.profile = data?.data || data || {};
        else this.error = data?.message || 'We could not load your personal information.';
      } catch {
        this.error = 'Network error. Please try again.';
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>

<style scoped>
.profile-card { margin-bottom: 12px; }
.profile-card .m-section-label { margin-top: 0; }
.profile-list { margin: 0; }
.profile-row { display: flex; justify-content: space-between; gap: 16px; padding: 10px 0; border-bottom: 1px solid var(--horizon-100); }
.profile-row:last-child { border-bottom: 0; }
.profile-row dt { color: var(--neutral-500); font-size: 13px; }
.profile-row dd { margin: 0; color: var(--horizon-500); font-size: 13px; font-weight: 700; text-align: right; overflow-wrap: anywhere; }
.profile-row--total dt, .profile-row--total dd { font-size: 15px; font-weight: 800; }
.profile-copy { margin: 0; }
.profile-note { margin: 8px 0 0; color: var(--neutral-500); font-size: 12px; }
.profile-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.profile-head .m-section-label { margin-bottom: 8px; }
.profile-edit { flex: 0 0 auto; padding: 6px 12px; }
.profile-form { display: flex; flex-direction: column; gap: 14px; margin-top: 4px; }
.profile-field { display: flex; flex-direction: column; gap: 6px; }
.profile-field__label { color: var(--neutral-500); font-size: 13px; }
.profile-input { width: 100%; padding: 12px; border: 1px solid var(--horizon-200); border-radius: 8px; background: var(--white); color: var(--horizon-500); font-size: 15px; }
.profile-input:focus { outline: 2px solid var(--violet-500); outline-offset: 1px; }
.profile-actions { display: flex; gap: 10px; align-items: center; }
.profile-actions .m-btn-ghost { flex: 0 0 auto; }
.profile-save { flex: 1 1 auto; }
</style>
