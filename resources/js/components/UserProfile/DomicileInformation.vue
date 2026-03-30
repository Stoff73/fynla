<template>
  <div>
    <div class="mb-6 flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
      <div>
        <h2 class="text-h4 font-semibold text-horizon-500">Domicile Information</h2>
        <p class="mt-1 text-body-sm text-neutral-500">
          Your domicile status affects UK inheritance tax on your worldwide assets
        </p>
      </div>
      <button
        v-if="!isEditing"
        type="button"
        @click="enableEdit"
        class="btn-primary w-full sm:w-auto"
      >
        Edit
      </button>
    </div>

    <!-- Success Message -->
    <div v-if="successMessage" class="rounded-md bg-success-50 p-4 mb-6">
      <div class="flex">
        <div class="ml-3">
          <p class="text-body-sm font-medium text-success-800">
            {{ successMessage }}
          </p>
        </div>
      </div>
    </div>

    <!-- Error Message -->
    <div v-if="errorMessage" class="rounded-md bg-raspberry-50 p-4 mb-6">
      <div class="flex">
        <div class="ml-3">
          <h3 class="text-body-sm font-medium text-raspberry-800">Error updating domicile information</h3>
          <div class="mt-2 text-body-sm text-raspberry-700">
            <p>{{ errorMessage }}</p>
          </div>
        </div>
      </div>
    </div>

    <form @submit.prevent="handleSubmit" class="space-y-6">
      <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <p class="text-body-sm text-blue-800">
          <strong>Why this matters:</strong> UK domicile status determines which assets are subject to UK inheritance tax. Non-UK domiciled individuals only pay tax on UK assets, while UK domiciled individuals pay tax on worldwide assets.
        </p>
      </div>

      <!-- Country of Birth -->
      <div>
        <label for="country_of_birth" class="block text-body-sm font-medium text-neutral-500 mb-1">
          Where were you born?
        </label>
        <CountrySelector
          v-model="form.country_of_birth"
          :required="true"
          :disabled="!isEditing"
          placeholder="Search for your country of birth..."
          @update:model-value="handleCountryChange"
        />
        <p class="mt-1 text-body-xs text-neutral-500">
          Your country of birth helps us determine your domicile status for tax purposes.
        </p>
      </div>

      <!-- UK Arrival Date (conditional - shown only for non-UK born) -->
      <div v-if="shouldShowUKArrivalDate" class="space-y-4 border-t pt-4">
        <h4 class="text-body font-medium text-horizon-500">
          UK Residency Information
        </h4>

        <div>
          <label for="uk_arrival_date" class="block text-body-sm font-medium text-neutral-500 mb-1">
            Date Moved to UK
          </label>
          <input
            id="uk_arrival_date"
            v-model="form.uk_arrival_date"
            type="date"
            class="input-field"
            :max="today"
            :required="shouldShowUKArrivalDate"
            :disabled="!isEditing"
            @change="calculateYearsResident"
          />
          <p class="mt-1 text-body-xs text-neutral-500">
            When did you first move to the UK?
          </p>
        </div>

        <div v-if="yearsResident !== null" class="bg-eggshell-500 rounded-lg p-4">
          <p class="text-body-sm text-neutral-500">
            <strong>Years UK Resident:</strong> {{ yearsResident }} years
          </p>
          <p class="mt-2 text-body-sm font-medium text-horizon-500">
            <strong>Domicile Status:</strong> {{ domicileStatusLabel }}
          </p>
          <p v-if="isDeemedDomiciled" class="mt-2 text-body-sm text-blue-700">
            You are considered deemed domiciled in the UK because you have been resident for at least 15 of the last 20 tax years. This means you are subject to UK inheritance tax on your worldwide assets.
          </p>
          <p v-else class="mt-2 text-body-sm text-blue-700">
            You are not yet deemed domiciled. You only pay UK inheritance tax on UK assets. You will need {{ 15 - yearsResident }} more year(s) of UK residence to become deemed domiciled.
          </p>
        </div>
      </div>

      <!-- Action Buttons -->
      <div v-if="isEditing" class="flex justify-end space-x-3">
        <button
          type="button"
          @click="cancelEdit"
          class="btn-secondary"
        >
          Cancel
        </button>
        <button
          type="submit"
          :disabled="saving"
          class="btn-primary"
        >
          {{ saving ? 'Saving...' : 'Save Changes' }}
        </button>
      </div>
    </form>
  </div>
</template>

<script>
import CountrySelector from '@/components/Shared/CountrySelector.vue';
import api from '@/services/api';

import logger from '@/utils/logger';
export default {
  name: 'DomicileInformation',

  components: {
    CountrySelector,
  },

  props: {
    user: {
      type: [Object, null],
      default: null,
    },
    domicileInfo: {
      type: Object,
      default: null,
    },
  },

  emits: ['updated'],

  data() {
    return {
      isEditing: false,
      saving: false,
      successMessage: '',
      errorMessage: '',
      form: {
        domicile_status: this.user?.domicile_status || 'uk_domiciled',
        country_of_birth: this.user?.country_of_birth || 'United Kingdom',
        uk_arrival_date: this.formatDateForInput(this.user?.uk_arrival_date) || '',
        years_uk_resident: this.user?.years_uk_resident || null,
        deemed_domicile_date: this.user?.deemed_domicile_date || null,
      },
      originalForm: {},
      yearsResident: null,
      messageTimeout: null,
    };
  },

  computed: {
    isPreviewMode() {
      return this.$store.getters['preview/isPreviewMode'];
    },

    today() {
      return new Date().toISOString().split('T')[0];
    },

    isDeemedDomiciled() {
      return this.yearsResident !== null && this.yearsResident >= 15;
    },

    shouldShowUKArrivalDate() {
      // Show UK arrival date field only if born outside UK
      return this.form.country_of_birth &&
             this.form.country_of_birth !== 'United Kingdom';
    },

    domicileStatusLabel() {
      if (this.form.country_of_birth === 'United Kingdom') {
        return 'UK Domiciled';
      }

      if (this.isDeemedDomiciled) {
        return 'Deemed UK Domiciled';
      }

      return 'Non-UK Domiciled';
    },
  },

  watch: {
    user: {
      handler(newUser) {
        if (!this.isEditing && newUser) {
          this.form = {
            domicile_status: newUser.domicile_status || '',
            country_of_birth: newUser.country_of_birth || '',
            uk_arrival_date: this.formatDateForInput(newUser.uk_arrival_date) || '',
          };
        }
      },
      deep: true,
    },
  },

  beforeUnmount() {
    if (this.messageTimeout) clearTimeout(this.messageTimeout);
  },

  mounted() {
    // Calculate years resident if uk_arrival_date exists
    if (this.form.uk_arrival_date) {
      this.calculateYearsResident();
    }
  },

  methods: {
    calculateYearsResident() {
      if (!this.form.uk_arrival_date) {
        this.yearsResident = null;
        return;
      }

      const arrival = new Date(this.form.uk_arrival_date);
      const now = new Date();
      const years = Math.floor((now - arrival) / (365.25 * 24 * 60 * 60 * 1000));

      this.yearsResident = Math.max(0, years);
      this.form.years_uk_resident = this.yearsResident;

      // Calculate deemed domicile date if applicable
      if (this.yearsResident >= 15) {
        const deemedDate = new Date(arrival);
        deemedDate.setFullYear(deemedDate.getFullYear() + 15);
        this.form.deemed_domicile_date = deemedDate.toISOString().split('T')[0];
      } else {
        this.form.deemed_domicile_date = null;
      }

      // Auto-determine domicile status
      this.updateDomicileStatus();
    },

    handleCountryChange() {
      // If UK born, clear UK arrival fields and set as UK domiciled
      if (this.form.country_of_birth === 'United Kingdom') {
        this.form.uk_arrival_date = '';
        this.form.years_uk_resident = null;
        this.form.deemed_domicile_date = null;
        this.yearsResident = null;
        this.form.domicile_status = 'uk_domiciled';
      } else {
        // Non-UK born - status will be determined by years resident
        this.updateDomicileStatus();
      }
    },

    updateDomicileStatus() {
      // Auto-determine domicile status based on country of birth and years resident
      if (this.form.country_of_birth === 'United Kingdom') {
        this.form.domicile_status = 'uk_domiciled';
      } else if (this.yearsResident !== null && this.yearsResident >= 15) {
        // Deemed domiciled if 15+ years resident
        this.form.domicile_status = 'uk_domiciled';
      } else {
        this.form.domicile_status = 'non_uk_domiciled';
      }
    },

    enableEdit() {
      this.isEditing = true;
      this.originalForm = { ...this.form };
      this.successMessage = '';
      this.errorMessage = '';
    },

    cancelEdit() {
      this.form = { ...this.originalForm };
      this.isEditing = false;
      this.errorMessage = '';

      // Recalculate years resident with original data
      if (this.form.uk_arrival_date) {
        this.calculateYearsResident();
      }
    },

    async handleSubmit() {
      if (this.isPreviewMode) {
        return;
      }
      // Validate required fields
      if (!this.form.country_of_birth) {
        this.errorMessage = 'Please select your country of birth';
        return;
      }

      // Validate UK arrival date if required (non-UK born)
      if (this.shouldShowUKArrivalDate && !this.form.uk_arrival_date) {
        this.errorMessage = 'Please enter the date you moved to the UK';
        return;
      }

      // Auto-determine domicile status before saving
      this.updateDomicileStatus();

      this.saving = true;
      this.errorMessage = '';
      this.successMessage = '';

      try {
        const response = await api.put('/user/profile/domicile', this.form);

        if (response.data.success) {
          this.successMessage = response.data.message;
          this.isEditing = false;

          // Emit updated event to parent
          this.$emit('updated', response.data.data.user);

          // Clear success message after 5 seconds
          if (this.messageTimeout) clearTimeout(this.messageTimeout);
          this.messageTimeout = setTimeout(() => {
            this.successMessage = '';
          }, 5000);
        }
      } catch (error) {
        logger.error('Error updating domicile info:', error);

        if (error.response?.data?.errors) {
          const errors = error.response.data.errors;
          this.errorMessage = Object.values(errors).flat().join(' ');
        } else if (error.response?.data?.message) {
          this.errorMessage = error.response.data.message;
        } else {
          this.errorMessage = 'An unexpected error occurred. Please try again.';
        }
      } finally {
        this.saving = false;
      }
    },

    formatDate(dateString) {
      if (!dateString) return '';
      const date = new Date(dateString);
      return date.toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
      });
    },

    formatDateForInput(date) {
      if (!date) return '';
      try {
        // If it's already in YYYY-MM-DD format, return it
        if (typeof date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(date)) {
          return date;
        }
        // Parse and format the date
        const dateObj = new Date(date);
        if (isNaN(dateObj.getTime())) return '';
        const year = dateObj.getFullYear();
        const month = String(dateObj.getMonth() + 1).padStart(2, '0');
        const day = String(dateObj.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
      } catch (e) {
        return '';
      }
    },
  },
};
</script>
