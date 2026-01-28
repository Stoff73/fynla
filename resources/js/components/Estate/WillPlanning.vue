<template>
  <div class="will-planning-tab">
    <!-- Preview Mode Notice -->
    <div v-if="isPreviewMode" class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-5 mb-6 shadow-sm">
      <div class="flex items-start">
        <div class="flex-shrink-0">
          <svg class="h-6 w-6 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.64 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.64 0-8.573-3.007-9.963-7.178z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
        </div>
        <div class="ml-4">
          <h3 class="text-base font-semibold text-blue-800">You're Viewing a Sample Profile</h3>
          <p class="mt-1 text-sm text-blue-700">
            This is a preview using sample data to help you explore how Fynla can support your will planning. Any changes you make here won't be saved.
          </p>
          <p class="mt-2 text-sm text-blue-700">
            <router-link to="/register" class="font-semibold text-blue-600 hover:text-blue-800 underline">Create your free account</router-link> to record your own will details, track when it was last updated, and ensure your estate planning information is always at your fingertips.
          </p>
        </div>
      </div>
    </div>

    <!-- Legal Disclaimer (always shown) -->
    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6">
      <p class="text-xs text-gray-600">
        <strong>Important:</strong> This tool is for planning and record-keeping purposes only and does not constitute legal advice. Always consult a qualified solicitor when creating or updating your will.
      </p>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-8">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
      <p class="mt-2 text-gray-600">Loading will details...</p>
    </div>

    <!-- Main Content -->
    <div v-else>
      <!-- Has Will Question -->
      <div class="bg-white rounded-lg border border-gray-200 p-6 mb-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Do you have a will?</h3>

        <div class="space-y-3">
          <label class="flex items-start cursor-pointer">
            <input
              type="radio"
              v-model="form.has_will"
              :value="true"
              class="mt-1 h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
              @change="handleWillStatusChange"
            />
            <div class="ml-3">
              <span class="block text-sm font-medium text-gray-900">Yes, I have a will</span>
              <span class="block text-xs text-gray-500">Configure your existing will details</span>
            </div>
          </label>
          <label class="flex items-start cursor-pointer">
            <input
              type="radio"
              v-model="form.has_will"
              :value="false"
              class="mt-1 h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
              @change="handleWillStatusChange"
            />
            <div class="ml-3">
              <span class="block text-sm font-medium text-gray-900">No, I don't have a will</span>
              <span class="block text-xs text-gray-500">See how your estate would be distributed under intestacy rules</span>
            </div>
          </label>
          <label class="flex items-start cursor-pointer">
            <input
              type="radio"
              v-model="form.has_will"
              :value="null"
              class="mt-1 h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
              @change="handleWillStatusChange"
            />
            <div class="ml-3">
              <span class="block text-sm font-medium text-gray-900">I'm not sure / I prefer not to say</span>
              <span class="block text-xs text-gray-500">View intestacy information</span>
            </div>
          </label>
        </div>
      </div>

      <!-- Intestacy Rules Display (shown when has_will is false or null) -->
      <IntestacyRules
        v-if="form.has_will === false || form.has_will === null"
        :estate-value="netEstateValue"
        @create-will="createWill"
      />

      <!-- Will Configuration Card (only shown when has_will is true) -->
      <div v-if="form.has_will === true" class="bg-white rounded-lg border border-gray-200 p-6 mb-6">
        <div class="flex justify-between items-start mb-4">
          <h3 class="text-lg font-semibold text-gray-900">Will Configuration</h3>
          <div class="flex gap-3">
            <template v-if="!isEditing">
              <button
                @click="startEditing"
                class="btn-secondary"
              >
                Edit
              </button>
            </template>
            <template v-else>
              <button
                @click="cancelEditing"
                class="btn-secondary"
                :disabled="saving"
              >
                Cancel
              </button>
              <button
                v-preview-disabled="'save'"
                @click="saveWill"
                :disabled="saving"
                class="btn-primary"
              >
                {{ saving ? 'Saving...' : 'Save' }}
              </button>
            </template>
          </div>
        </div>

        <!-- VIEW MODE -->
        <div v-if="!isEditing" class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <div class="text-sm font-medium text-gray-700 mb-1">Will Last Updated</div>
              <p class="text-sm text-gray-900">{{ form.will_last_updated ? formatDate(form.will_last_updated) : 'Not specified' }}</p>
            </div>
            <div>
              <div class="text-sm font-medium text-gray-700 mb-1">Executor</div>
              <p class="text-sm text-gray-900">{{ form.executor_name || 'Not specified' }}</p>
            </div>
          </div>

          <div v-if="isMarried" class="border-t border-gray-200 pt-4">
            <div class="text-sm font-medium text-gray-700 mb-1">Spouse as Primary Beneficiary</div>
            <p class="text-sm text-gray-900">{{ form.spouse_primary_beneficiary ? 'Yes' : 'No' }}</p>
            <p v-if="form.spouse_primary_beneficiary" class="text-sm text-gray-600 mt-1">
              {{ form.spouse_bequest_percentage }}% to spouse ({{ formatCurrency(spouseAmount) }})
            </p>
          </div>

          <div v-if="form.executor_notes" class="border-t border-gray-200 pt-4">
            <div class="text-sm font-medium text-gray-700 mb-1">Executor Notes</div>
            <p class="text-sm text-gray-900 whitespace-pre-line">{{ form.executor_notes }}</p>
          </div>
        </div>

        <!-- EDIT MODE -->
        <div v-else class="space-y-6">
          <!-- Will Last Updated -->
          <div>
            <label for="will_last_updated" class="block text-sm font-medium text-gray-700 mb-2">
              When was your will last updated?
            </label>
            <input
              id="will_last_updated"
              v-model="form.will_last_updated"
              type="date"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
              :max="today"
            />
            <p class="mt-1 text-xs text-gray-500">
              It's recommended to review your will every 5 years or after major life events
            </p>
          </div>

          <!-- Executor Name -->
          <div>
            <label for="executor_name" class="block text-sm font-medium text-gray-700 mb-2">
              Who is your executor?
            </label>
            <input
              id="executor_name"
              v-model="form.executor_name"
              type="text"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
              placeholder="Executor name"
            />
            <p class="mt-1 text-xs text-gray-500">
              The person responsible for administering your estate
            </p>
          </div>

          <!-- Spouse Bequest (only show if married) -->
          <div v-if="isMarried" class="border-t border-gray-200 pt-6">
            <div class="flex items-center justify-between mb-4">
              <label class="block text-sm font-medium text-gray-700">
                Spouse as Primary Beneficiary
              </label>
              <button
                type="button"
                @click="form.spouse_primary_beneficiary = !form.spouse_primary_beneficiary"
                :class="[
                  form.spouse_primary_beneficiary ? 'bg-blue-600' : 'bg-gray-200',
                  'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2'
                ]"
              >
                <span
                  :class="[
                    form.spouse_primary_beneficiary ? 'translate-x-5' : 'translate-x-0',
                    'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out'
                  ]"
                />
              </button>
            </div>

            <div v-if="form.spouse_primary_beneficiary" class="mt-4">
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Percentage to Spouse ({{ form.spouse_bequest_percentage }}%)
              </label>
              <input
                type="range"
                v-model.number="form.spouse_bequest_percentage"
                min="0"
                max="100"
                step="1"
                class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer"
              />
              <div class="flex justify-between text-xs text-gray-500 mt-1">
                <span>0%</span>
                <span>50%</span>
                <span>100%</span>
              </div>
              <p class="text-xs text-gray-600 mt-2">
                <strong>{{ formatCurrency(spouseAmount) }}</strong> will pass to your spouse tax-free (unlimited spouse exemption)
              </p>
              <p v-if="form.spouse_bequest_percentage < 100" class="text-xs text-amber-600 mt-1">
                <strong>{{ formatCurrency(nonSpouseAmount) }}</strong> will be subject to Inheritance Tax calculation (distributed to other beneficiaries)
              </p>
            </div>

            <div v-else class="mt-4 bg-amber-50 border border-amber-200 rounded-lg p-3">
              <p class="text-sm text-amber-800">
                Your spouse is not set as the primary beneficiary. The entire estate will be subject to Inheritance Tax calculation.
              </p>
            </div>
          </div>

          <!-- Executor Notes -->
          <div class="border-t border-gray-200 pt-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Executor Notes (Optional)
            </label>
            <textarea
              v-model="form.executor_notes"
              rows="3"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
              placeholder="Any special instructions or notes for your executor..."
            ></textarea>
          </div>
        </div>
      </div>

      <!-- Bequests Section (only shown when has_will is true) -->
      <div v-if="form.has_will === true" class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-semibold text-gray-900">Specific Bequests</h3>
          <button
            v-preview-disabled="'add'"
            @click="showBequestModal = true"
            class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 text-sm"
          >
            Add Bequest
          </button>
        </div>

        <!-- Bequests List -->
        <div v-if="bequests.length > 0" class="space-y-3">
          <div
            v-for="bequest in bequests"
            :key="bequest.id"
            class="border border-gray-200 rounded-lg p-4 hover:border-blue-300"
          >
            <div class="flex justify-between items-start">
              <div class="flex-1">
                <h4 class="text-sm font-semibold text-gray-900">{{ bequest.beneficiary_name }}</h4>
                <p class="text-xs text-gray-600 mt-1">
                  <span v-if="bequest.bequest_type === 'percentage'">
                    {{ bequest.percentage_of_estate }}% of estate
                  </span>
                  <span v-else-if="bequest.bequest_type === 'specific_amount'">
                    {{ formatCurrency(bequest.specific_amount) }}
                  </span>
                  <span v-else-if="bequest.bequest_type === 'specific_asset'">
                    Specific Asset: {{ bequest.specific_asset_description }}
                  </span>
                  <span v-else>
                    Residuary bequest
                  </span>
                </p>
                <p v-if="bequest.conditions" class="text-xs text-gray-500 mt-1">
                  Conditions: {{ bequest.conditions }}
                </p>
              </div>
              <div class="flex gap-2">
                <button
                  v-preview-disabled="'edit'"
                  @click="editBequest(bequest)"
                  class="text-blue-600 hover:text-blue-800 text-sm"
                >
                  Edit
                </button>
                <button
                  v-preview-disabled="'delete'"
                  @click="deleteBequest(bequest.id)"
                  class="text-red-600 hover:text-red-800 text-sm"
                >
                  Delete
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-else class="text-center py-8 text-gray-500">
          <p class="text-sm">No specific bequests added yet.</p>
          <p class="text-xs mt-1">Click "Add Bequest" to specify gifts to beneficiaries.</p>
        </div>
      </div>
    </div>

    <!-- Success Message -->
    <div v-if="successMessage" class="fixed top-4 right-4 bg-green-50 border border-green-200 rounded-lg p-4 shadow-lg z-50">
      <p class="text-sm text-green-800">{{ successMessage }}</p>
    </div>

    <!-- Error Message -->
    <div v-if="errorMessage" class="fixed top-4 right-4 bg-red-50 border border-red-200 rounded-lg p-4 shadow-lg z-50">
      <p class="text-sm text-red-800">{{ errorMessage }}</p>
    </div>
  </div>
</template>

<script>
import api from '@/services/api';
import IntestacyRules from './IntestacyRules.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'WillPlanning',

  mixins: [currencyMixin],

  components: {
    IntestacyRules,
  },

  data() {
    return {
      loading: true,
      saving: false,
      isEditing: false,
      will: null,
      form: {
        has_will: null,
        will_last_updated: '',
        executor_name: '',
        spouse_primary_beneficiary: true,
        spouse_bequest_percentage: 100,
        executor_notes: '',
      },
      originalForm: null,
      bequests: [],
      showBequestModal: false,
      successMessage: '',
      errorMessage: '',
      netEstateValue: 0,
    };
  },

  computed: {
    isPreviewMode() {
      return this.$store.getters['preview/isPreviewMode'];
    },

    isMarried() {
      return this.$store.state.auth?.user?.marital_status === 'married' && this.$store.state.auth?.user?.spouse_id;
    },

    spouseAmount() {
      return this.netEstateValue * (this.form.spouse_bequest_percentage / 100);
    },

    nonSpouseAmount() {
      return this.netEstateValue - this.spouseAmount;
    },

    today() {
      return new Date().toISOString().split('T')[0];
    },
  },

  mounted() {
    // Preview users are real DB users - use normal API to fetch their data
    this.loadWill();
    this.loadBequests();
    this.loadNetEstateValue();
  },

  methods: {
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

    async loadWill() {
      // Preview users are real DB users - use normal API
      try {
        const response = await api.get('/estate/will');
        this.will = response.data.data;

        this.form = {
          has_will: this.will.has_will,
          will_last_updated: this.formatDateForInput(this.will.will_last_updated),
          executor_name: this.will.executor_name || '',
          spouse_primary_beneficiary: this.will.spouse_primary_beneficiary,
          spouse_bequest_percentage: parseFloat(this.will.spouse_bequest_percentage),
          executor_notes: this.will.executor_notes || '',
        };
        this.originalForm = JSON.parse(JSON.stringify(this.form));
      } catch (error) {
        console.error('Failed to load will:', error);
        this.errorMessage = 'Failed to load will details';
        setTimeout(() => this.errorMessage = '', 3000);
      } finally {
        this.loading = false;
      }
    },

    async loadBequests() {
      // Preview users are real DB users - use normal API
      try {
        const response = await api.get('/estate/bequests');
        this.bequests = response.data.data;
      } catch (error) {
        console.error('Failed to load bequests:', error);
      }
    },

    async loadNetEstateValue() {
      // Preview users are real DB users - use normal API
      try {
        const response = await api.post('/estate/calculate-iht');
        // NEW: Use iht_summary.current.net_estate from unified structure
        if (response.data?.iht_summary?.current?.net_estate !== undefined) {
          this.netEstateValue = response.data.iht_summary.current.net_estate;
        } else if (response.data?.data?.net_estate_value !== undefined) {
          // OLD: Fallback for old structure
          this.netEstateValue = response.data.data.net_estate_value;
        } else {
          this.netEstateValue = 0;
        }
      } catch (error) {
        console.error('Failed to load estate value:', error);
        this.netEstateValue = 0;
      }
    },

    startEditing() {
      this.originalForm = JSON.parse(JSON.stringify(this.form));
      this.isEditing = true;
    },

    cancelEditing() {
      if (this.originalForm) {
        this.form = JSON.parse(JSON.stringify(this.originalForm));
      }
      this.isEditing = false;
    },

    formatDate(dateString) {
      if (!dateString) return '';
      try {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
      } catch (e) {
        return dateString;
      }
    },

    async saveWill() {
      if (this.isPreviewMode) {
        return;
      }
      this.saving = true;
      this.errorMessage = '';

      try {
        await api.post('/estate/will', this.form);
        this.successMessage = 'Will saved successfully';
        this.isEditing = false;
        this.originalForm = JSON.parse(JSON.stringify(this.form));
        setTimeout(() => this.successMessage = '', 3000);

        await this.loadWill();
        this.$emit('will-updated');
      } catch (error) {
        console.error('Failed to save will:', error);
        this.errorMessage = error.response?.data?.message || 'Failed to save will';
        setTimeout(() => this.errorMessage = '', 3000);
      } finally {
        this.saving = false;
      }
    },

    handleWillStatusChange() {
      this.saveWill();
    },

    createWill() {
      this.form.has_will = true;
      this.saveWill();
    },

    async deleteBequest(id) {
      if (this.isPreviewMode) {
        return;
      }
      if (!confirm('Are you sure you want to delete this bequest?')) return;

      try {
        await api.delete(`/estate/bequests/${id}`);
        this.successMessage = 'Bequest deleted successfully';
        setTimeout(() => this.successMessage = '', 3000);
        await this.loadBequests();
      } catch (error) {
        console.error('Failed to delete bequest:', error);
        this.errorMessage = 'Failed to delete bequest';
        setTimeout(() => this.errorMessage = '', 3000);
      }
    },

    editBequest(bequest) {
    },
  },
};
</script>

<style scoped>
input[type="range"]::-webkit-slider-thumb {
  -webkit-appearance: none;
  appearance: none;
  width: 20px;
  height: 20px;
  @apply bg-blue-600;
  cursor: pointer;
  border-radius: 50%;
}

input[type="range"]::-moz-range-thumb {
  width: 20px;
  height: 20px;
  @apply bg-blue-600;
  cursor: pointer;
  border-radius: 50%;
  border: none;
}
</style>
