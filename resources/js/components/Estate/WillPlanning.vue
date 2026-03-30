<template>
  <div class="will-planning-tab">
    <!-- Preview Mode Notice -->
    <div v-if="isPreviewMode" class="bg-savannah-100 border border-violet-200 rounded-xl p-5 mb-6 shadow-sm">
      <div class="flex items-start">
        <div class="flex-shrink-0">
          <svg class="h-6 w-6 text-violet-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.64 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.64 0-8.573-3.007-9.963-7.178z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
        </div>
        <div class="ml-4">
          <h3 class="text-base font-semibold text-violet-800">You're Viewing a Sample Profile</h3>
          <p class="mt-1 text-sm text-violet-700">
            This is a preview using sample data to help you explore how Fynla can support your will planning. Any changes you make here won't be saved.
          </p>
          <p class="mt-2 text-sm text-violet-700">
            <router-link to="/register" class="font-semibold text-violet-600 hover:text-violet-800 underline">Create your free account</router-link> to record your own will details, track when it was last updated, and ensure your estate planning information is always at your fingertips.
          </p>
        </div>
      </div>
    </div>

    <!-- Legal Disclaimer (always shown) -->
    <div class="bg-eggshell-500 border border-light-gray rounded-lg p-4 mb-6">
      <p class="text-xs text-neutral-500">
        <strong>Important:</strong> This tool is for planning and record-keeping purposes only and does not constitute legal advice. Always consult a qualified solicitor when creating or updating your will.
      </p>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-8">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-violet-600"></div>
      <p class="mt-2 text-neutral-500">Loading will details...</p>
    </div>

    <!-- Main Content -->
    <div v-else>
      <!-- Intestacy Rules Display (shown when has_will is false or null AND not editing AND has estate data) -->
      <IntestacyRules
        v-if="(form.has_will === false || form.has_will === null) && !isEditing && netEstateValue > 0"
        :estate-value="netEstateValue"
        @create-will="createWill"
      />

      <!-- Empty estate message (shown when no will and no estate data) -->
      <div v-else-if="(form.has_will === false || form.has_will === null) && !isEditing && netEstateValue === 0" class="bg-eggshell-500 border border-light-gray rounded-lg p-6 mb-6">
        <h3 class="text-lg font-semibold text-horizon-500 mb-2">How Your Estate Would Be Distributed</h3>
        <p class="text-sm text-neutral-500">
          Add your assets and liabilities to see how your estate would be distributed under UK intestacy rules.
        </p>
      </div>

      <!-- Will Planning Card -->
      <div class="bg-white rounded-lg border border-light-gray p-6 mb-6">
        <div class="flex justify-between items-start mb-4">
          <h3 class="text-lg font-semibold text-horizon-500">Will Planning</h3>
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
        <div v-if="!isEditing">
          <!-- Show will details if user has a will -->
          <div v-if="form.has_will === true" class="space-y-4">
            <!-- Link to full will document if created via builder -->
            <div v-if="will && will.will_document_id" class="flex items-center justify-between bg-spring-50 border border-spring-200 rounded-lg p-3">
              <div>
                <p class="text-sm font-medium text-spring-800">Will created with the Will Builder</p>
                <p class="text-xs text-spring-600">View, print, or edit your full will document</p>
              </div>
              <button
                @click="openWillBuilder"
                class="px-4 py-2 bg-raspberry-500 text-white text-sm font-medium rounded-button hover:bg-raspberry-600 transition-colors"
              >
                View Will
              </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <div class="text-sm font-medium text-neutral-500 mb-1">Will Last Updated</div>
                <p class="text-sm text-horizon-500">{{ form.will_last_updated ? formatDate(form.will_last_updated) : 'Not specified' }}</p>
              </div>
              <div>
                <div class="text-sm font-medium text-neutral-500 mb-1">Last Reviewed</div>
                <p class="text-sm text-horizon-500">{{ form.last_reviewed_date ? formatDate(form.last_reviewed_date) : 'Not reviewed' }}</p>
                <p v-if="isWillStale" class="text-xs text-violet-600 mt-1">
                  Your will has not been reviewed recently. It is recommended to review your will every 3-5 years.
                </p>
              </div>
              <div>
                <div class="text-sm font-medium text-neutral-500 mb-1">{{ form.executors.filter(e => e.trim()).length > 1 ? 'Executors' : 'Executor' }}</div>
                <p v-for="(executor, i) in form.executors.filter(e => e.trim())" :key="i" class="text-sm text-horizon-500">{{ executor }}</p>
                <p v-if="!form.executors.some(e => e.trim())" class="text-sm text-horizon-500">Not specified</p>
              </div>
            </div>

            <!-- Will Document Details (from Will Builder) -->
            <div v-if="willDocument" class="border-t border-light-gray pt-4 space-y-3">
              <div v-if="willDocument.residuary_estate && willDocument.residuary_estate.length > 0">
                <div class="text-sm font-medium text-neutral-500 mb-1">Residuary Estate Beneficiaries</div>
                <div v-for="(ben, i) in willDocument.residuary_estate" :key="i" class="text-sm text-horizon-500">
                  {{ ben.beneficiary_name || ben.name || 'Not specified' }} — {{ ben.percentage }}%
                  <span v-if="ben.substitution_beneficiary || ben.substitute" class="text-neutral-500">(if predeceased: {{ ben.substitution_beneficiary || ben.substitute }})</span>
                </div>
              </div>
              <div v-if="willDocument.specific_gifts && willDocument.specific_gifts.length > 0">
                <div class="text-sm font-medium text-neutral-500 mb-1">Specific Gifts</div>
                <div v-for="(gift, i) in willDocument.specific_gifts" :key="i" class="text-sm text-horizon-500">
                  {{ gift.type === 'cash' ? formatCurrency(gift.amount) : gift.description }} to {{ gift.recipient }}
                </div>
              </div>
              <div v-if="willDocument.funeral_preference">
                <div class="text-sm font-medium text-neutral-500 mb-1">Funeral Wishes</div>
                <p class="text-sm text-horizon-500 capitalize">{{ willDocument.funeral_preference }}</p>
                <p v-if="willDocument.funeral_wishes_notes" class="text-sm text-neutral-500 mt-1">{{ willDocument.funeral_wishes_notes }}</p>
              </div>
            </div>

            <div v-if="isMarried" class="border-t border-light-gray pt-4">
              <div class="text-sm font-medium text-neutral-500 mb-1">Spouse as Primary Beneficiary</div>
              <p class="text-sm text-horizon-500">{{ form.spouse_primary_beneficiary ? 'Yes' : 'No' }}</p>
              <p v-if="form.spouse_primary_beneficiary" class="text-sm text-neutral-500 mt-1">
                {{ form.spouse_bequest_percentage }}% to spouse ({{ formatCurrency(spouseAmount) }})
              </p>
            </div>

            <div v-if="form.executor_notes" class="border-t border-light-gray pt-4">
              <div class="text-sm font-medium text-neutral-500 mb-1">Executor Notes</div>
              <p class="text-sm text-horizon-500 whitespace-pre-line">{{ form.executor_notes }}</p>
            </div>
          </div>

          <!-- Show message if no will -->
          <div v-else class="text-sm text-neutral-500">
            <p v-if="form.has_will === false">You have indicated that you don't have a will. Click Edit to update this.</p>
            <p v-else>Will status not specified. Click Edit to configure your will details.</p>
            <router-link
              to="/estate/will-builder"
              class="inline-flex items-center gap-2 mt-3 px-4 py-2 bg-raspberry-500 text-white rounded-lg text-sm font-medium hover:bg-raspberry-600 transition-colors"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
              </svg>
              Build Your Will
            </router-link>
          </div>
        </div>

        <!-- EDIT MODE -->
        <div v-else class="space-y-6">
          <!-- Has Will Question -->
          <div>
            <label class="block text-sm font-medium text-neutral-500 mb-3">Do you have a will?</label>
            <div class="space-y-2">
              <label class="inline-flex items-center">
                <input
                  type="radio"
                  v-model="form.has_will"
                  :value="true"
                  class="form-radio text-raspberry-500"
                  @change="handleWillStatusChange"
                />
                <span class="ml-2 text-sm text-neutral-500">Yes</span>
              </label>
              <label class="inline-flex items-center ml-6">
                <input
                  type="radio"
                  v-model="form.has_will"
                  :value="false"
                  class="form-radio text-raspberry-500"
                  @change="handleWillStatusChange"
                />
                <span class="ml-2 text-sm text-neutral-500">No</span>
              </label>
            </div>
          </div>

          <!-- Will Details (shown when has_will is true) -->
          <template v-if="form.has_will === true">
            <!-- Will Last Updated -->
            <div>
              <label for="will_last_updated" class="block text-sm font-medium text-neutral-500 mb-2">
                When was your will last updated?
              </label>
              <input
                id="will_last_updated"
                v-model="form.will_last_updated"
                type="date"
                class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:ring-violet-500 focus:border-violet-500"
                :max="today"
              />
              <p class="mt-1 text-xs text-neutral-500">
                It's recommended to review your will every 5 years or after major life events
              </p>
            </div>

            <!-- Last Reviewed Date -->
            <div>
              <label for="last_reviewed_date" class="block text-sm font-medium text-neutral-500 mb-2">
                When was your will last reviewed?
              </label>
              <input
                id="last_reviewed_date"
                v-model="form.last_reviewed_date"
                type="date"
                class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:ring-violet-500 focus:border-violet-500"
                :max="today"
              />
              <p class="mt-1 text-xs text-neutral-500">
                It is recommended to review your will every 3-5 years or after significant life events
              </p>
            </div>

            <!-- Executors -->
            <div>
              <label class="block text-sm font-medium text-neutral-500 mb-2">
                Who {{ form.executors.length > 1 ? 'are your executors' : 'is your executor' }}?
              </label>
              <div v-for="(executor, index) in form.executors" :key="index" class="flex items-center gap-2 mb-2">
                <input
                  v-model="form.executors[index]"
                  type="text"
                  class="flex-1 px-3 py-2 border border-horizon-300 rounded-md focus:ring-violet-500 focus:border-violet-500"
                  :placeholder="index === 0 ? 'Primary executor name' : 'Additional executor name'"
                />
                <button
                  v-if="form.executors.length > 1"
                  type="button"
                  @click="form.executors.splice(index, 1)"
                  class="p-2 text-neutral-500 hover:text-raspberry-500 transition-colors"
                  title="Remove executor"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
              <button
                type="button"
                @click="form.executors.push('')"
                class="text-sm text-raspberry-500 hover:text-raspberry-600 font-medium transition-colors"
              >
                + Add executor
              </button>
            </div>

            <!-- Spouse Bequest (only show if married) -->
            <div v-if="isMarried" class="border-t border-light-gray pt-6">
              <div class="flex items-center justify-between mb-4">
                <label class="block text-sm font-medium text-neutral-500">
                  Spouse as Primary Beneficiary
                </label>
                <button
                  type="button"
                  @click="form.spouse_primary_beneficiary = !form.spouse_primary_beneficiary"
                  :class="[
                    form.spouse_primary_beneficiary ? 'bg-raspberry-500' : 'bg-savannah-200',
                    'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2'
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
                <label class="block text-sm font-medium text-neutral-500 mb-2">
                  Percentage to Spouse ({{ form.spouse_bequest_percentage }}%)
                </label>
                <input
                  type="range"
                  v-model.number="form.spouse_bequest_percentage"
                  min="0"
                  max="100"
                  step="1"
                  class="w-full h-2 bg-savannah-200 rounded-lg appearance-none cursor-pointer"
                />
                <div class="flex justify-between text-xs text-neutral-500 mt-1">
                  <span>0%</span>
                  <span>50%</span>
                  <span>100%</span>
                </div>
                <p class="text-xs text-neutral-500 mt-2">
                  <strong>{{ formatCurrency(spouseAmount) }}</strong> will pass to your spouse tax-free (unlimited spouse exemption)
                </p>
                <p v-if="form.spouse_bequest_percentage < 100" class="text-xs text-violet-600 mt-1">
                  <strong>{{ formatCurrency(nonSpouseAmount) }}</strong> will be subject to Inheritance Tax calculation (distributed to other beneficiaries)
                </p>
              </div>

              <div v-else class="mt-4 bg-violet-50 border border-violet-200 rounded-lg p-3">
                <p class="text-sm text-violet-800">
                  Your spouse is not set as the primary beneficiary. The entire estate will be subject to Inheritance Tax calculation.
                </p>
              </div>
            </div>

            <!-- Executor Notes -->
            <div class="border-t border-light-gray pt-6">
              <label class="block text-sm font-medium text-neutral-500 mb-2">
                Executor Notes (Optional)
              </label>
              <textarea
                v-model="form.executor_notes"
                rows="3"
                class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:ring-violet-500 focus:border-violet-500"
                placeholder="Any special instructions or notes for your executor..."
              ></textarea>
            </div>
          </template>

          <!-- No Will Message (shown when has_will is false in edit mode) -->
          <div v-if="form.has_will === false" class="bg-spring-50 p-4 rounded-lg border border-spring-200">
            <p class="text-sm text-spring-800">
              <strong>Important:</strong> Without a will, your estate will be distributed according to intestacy rules, which may not reflect your wishes.
            </p>
          </div>
        </div>

        <!-- Bequests Section (inside card, shown when has_will is true and not editing) -->
        <div v-if="form.has_will === true && !isEditing" class="border-t border-light-gray mt-6 pt-6">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-semibold text-horizon-500">Specific Bequests</h3>
          <button
            v-preview-disabled="'add'"
            @click="showBequestModal = true"
            class="px-4 py-2 bg-raspberry-500 text-white rounded-button hover:bg-raspberry-600 text-sm"
          >
            Add Bequest
          </button>
        </div>

        <!-- Bequests List -->
        <div v-if="bequests.length > 0" class="space-y-3">
          <div
            v-for="bequest in bequests"
            :key="bequest.id"
            class="border border-light-gray rounded-lg p-4 hover:border-violet-300"
          >
            <div class="flex justify-between items-start">
              <div class="flex-1">
                <h4 class="text-sm font-semibold text-horizon-500">{{ bequest.beneficiary_name }}</h4>
                <p class="text-xs text-neutral-500 mt-1">
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
                <p v-if="bequest.conditions" class="text-xs text-neutral-500 mt-1">
                  Conditions: {{ bequest.conditions }}
                </p>
              </div>
              <div class="flex gap-2">
                <button
                  v-preview-disabled="'edit'"
                  @click="editBequest(bequest)"
                  class="text-violet-600 hover:text-violet-800 text-sm"
                >
                  Edit
                </button>
                <button
                  v-preview-disabled="'delete'"
                  @click="deleteBequest(bequest.id)"
                  class="text-raspberry-600 hover:text-raspberry-800 text-sm"
                >
                  Delete
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-else class="text-center py-8 text-neutral-500">
          <p class="text-sm">No specific bequests added yet.</p>
          <p class="text-xs mt-1">Click "Add Bequest" to specify gifts to beneficiaries.</p>
        </div>
      </div>
      </div>
    </div>

    <!-- Success Message -->
    <div v-if="successMessage" class="fixed top-4 right-4 bg-spring-50 border border-spring-200 rounded-lg p-4 shadow-lg z-50">
      <p class="text-sm text-spring-800">{{ successMessage }}</p>
    </div>

    <!-- Error Message -->
    <div v-if="errorMessage" class="fixed top-4 right-4 bg-raspberry-50 border border-raspberry-200 rounded-lg p-4 shadow-lg z-50">
      <p class="text-sm text-raspberry-800">{{ errorMessage }}</p>
    </div>
  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import api from '@/services/api';
import IntestacyRules from './IntestacyRules.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

import logger from '@/utils/logger';
export default {
  name: 'WillPlanning',

  emits: ['will-updated'],

  mixins: [currencyMixin],

  components: {
    IntestacyRules,
  },

  props: {
    startInEditMode: {
      type: Boolean,
      default: false,
    },
  },

  data() {
    return {
      loading: true,
      saving: false,
      isEditing: this.startInEditMode,
      will: null,
      willDocument: null,
      form: {
        has_will: null,
        will_last_updated: '',
        last_reviewed_date: '',
        executors: [''],
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
      successTimeout: null,
      errorTimeout: null,
    };
  },

  computed: {
    ...mapGetters('auth', ['currentUser']),

    isPreviewMode() {
      return this.$store.getters['preview/isPreviewMode'];
    },

    isMarried() {
      return this.currentUser?.marital_status === 'married' && this.currentUser?.spouse_id;
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

    isWillStale() {
      const reviewDate = this.form.last_reviewed_date || this.form.will_last_updated;
      if (!reviewDate) return true;
      const date = new Date(reviewDate);
      const threeYearsAgo = new Date();
      threeYearsAgo.setFullYear(threeYearsAgo.getFullYear() - 3);
      return date < threeYearsAgo;
    },
  },

  beforeUnmount() {
    if (this.successTimeout) clearTimeout(this.successTimeout);
    if (this.errorTimeout) clearTimeout(this.errorTimeout);
  },

  mounted() {
    // Preview users are real DB users - use normal API to fetch their data
    this.loadWill();
    this.loadBequests();
    this.loadNetEstateValue();
  },

  methods: {
    openWillBuilder() {
      // Navigate to the will builder — it will show the completed will in Review mode
      this.$router.push('/estate/will-builder?view=document');
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

    async loadWill() {
      // Preview users are real DB users - use normal API
      try {
        const response = await api.get('/estate/will');
        this.will = response.data.data;

        // Parse executors — backend stores as comma-separated string in executor_name
        const executorStr = this.will.executor_name || '';
        const executors = executorStr ? executorStr.split(',').map(e => e.trim()).filter(Boolean) : [''];

        this.form = {
          has_will: this.will.has_will,
          will_last_updated: this.formatDateForInput(this.will.will_last_updated),
          last_reviewed_date: this.formatDateForInput(this.will.last_reviewed_date),
          executors: executors.length > 0 ? executors : [''],
          spouse_primary_beneficiary: this.will.spouse_primary_beneficiary,
          spouse_bequest_percentage: parseFloat(this.will.spouse_bequest_percentage),
          executor_notes: this.will.executor_notes || '',
        };
        this.originalForm = JSON.parse(JSON.stringify(this.form));

        // Load full WillDocument if exists (created via Will Builder)
        if (this.will.will_document_id) {
          try {
            const docResponse = await api.get(`/estate/will-builder/${this.will.will_document_id}`);
            this.willDocument = docResponse.data?.data || null;
          } catch {
            // Will document not available — that's OK
          }
        }
      } catch (error) {
        logger.error('Failed to load will:', error);
        this.errorMessage = 'Failed to load will details';
        if (this.errorTimeout) clearTimeout(this.errorTimeout);
        this.errorTimeout = setTimeout(() => this.errorMessage = '', 3000);
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
        logger.error('Failed to load bequests:', error);
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
        logger.error('Failed to load estate value:', error);
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
        // Convert executors array to comma-separated string for backend
        const payload = {
          ...this.form,
          executor_name: this.form.executors.filter(e => e.trim()).join(', '),
        };
        delete payload.executors;
        await api.post('/estate/will', payload);
        this.successMessage = 'Will saved successfully';
        this.isEditing = false;
        this.originalForm = JSON.parse(JSON.stringify(this.form));
        if (this.successTimeout) clearTimeout(this.successTimeout);
        this.successTimeout = setTimeout(() => this.successMessage = '', 3000);

        await this.loadWill();
        this.$emit('will-updated');
      } catch (error) {
        logger.error('Failed to save will:', error);
        this.errorMessage = error.response?.data?.message || 'Failed to save will';
        if (this.errorTimeout) clearTimeout(this.errorTimeout);
        this.errorTimeout = setTimeout(() => this.errorMessage = '', 3000);
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
        if (this.successTimeout) clearTimeout(this.successTimeout);
        this.successTimeout = setTimeout(() => this.successMessage = '', 3000);
        await this.loadBequests();
      } catch (error) {
        logger.error('Failed to delete bequest:', error);
        this.errorMessage = 'Failed to delete bequest';
        if (this.errorTimeout) clearTimeout(this.errorTimeout);
        this.errorTimeout = setTimeout(() => this.errorMessage = '', 3000);
      }
    },

    editBequest(bequest) {
    },
  },
};
</script>

