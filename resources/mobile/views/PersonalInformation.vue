<template>
  <MobileChrome
    title="Personal Information"
    subtitle="Your canonical profile and financial position"
    :loading="loading"
    loading-label="your personal information"
    :edit-details="false"
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

      <section class="m-card profile-card" aria-labelledby="profile-domicile-heading">
        <h2 id="profile-domicile-heading" class="m-section-label">Domicile</h2>
        <p class="m-sub profile-copy">{{ domicileLabel }}</p>
        <p v-if="profile.domicile_info?.country_of_birth" class="profile-note">
          Country of birth: {{ profile.domicile_info.country_of_birth }}
        </p>
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
import { apiGet } from '../api.js';
import { handleAuthExpiry } from '../authExpiry.js';
import MobileChrome from '../components/MobileChrome.vue';
import { store } from '../store.js';

export default {
  name: 'MobilePersonalInformation',
  components: { MobileChrome },
  data: () => ({
    loading: true,
    error: '',
    profile: null,
  }),
  computed: {
    hasProfile() {
      return Boolean(this.profile?.personal_info);
    },
    personalInfo() {
      return this.profile?.personal_info || {};
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
    address() {
      const address = this.personalInfo.address || {};
      return [address.line_1, address.line_2, address.city, address.county, address.postcode]
        .filter(Boolean)
        .join(', ') || 'Not recorded';
    },
  },
  created() {
    this.load();
  },
  methods: {
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
</style>
