<template>
  <div class="letter-to-spouse">
    <!-- Header -->
    <div class="bg-white shadow-sm rounded-lg p-6 mb-6">
      <h2 class="text-2xl font-bold text-gray-900 mb-2">Letter to Spouse</h2>
      <p class="text-gray-600 mb-4">
        This letter provides crucial information for your spouse in the event of your death. It will be auto-populated with information from your profile.
        Your spouse can view this letter but cannot edit it. You can view your spouse's letter in read-only mode.
      </p>

      <!-- View Toggle -->
      <div v-if="hasSpouse" class="flex space-x-4 mt-4">
        <button
          @click="viewMode = 'my'"
          :class="[
            'px-4 py-2 rounded-lg font-medium transition-colors',
            viewMode === 'my'
              ? 'bg-primary-600 text-white'
              : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
          ]"
        >
          My Letter
        </button>
        <button
          @click="loadSpouseLetter"
          :class="[
            'px-4 py-2 rounded-lg font-medium transition-colors',
            viewMode === 'spouse'
              ? 'bg-primary-600 text-white'
              : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
          ]"
        >
          {{ spouseName }}'s Letter
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center items-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600"></div>
    </div>

    <!-- Letter Content -->
    <div v-else class="space-y-6">
      <!-- Read-only Banner for Spouse View -->
      <div v-if="viewMode === 'spouse'" class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded">
        <div class="flex items-center">
          <svg class="h-5 w-5 text-blue-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
          </svg>
          <p class="text-sm text-blue-700 font-medium">
            Viewing {{ spouseName }}'s letter (read-only). You cannot edit this letter.
          </p>
        </div>
      </div>

      <!-- Part 1: What to Do Immediately -->
      <div class="bg-white shadow-sm rounded-lg p-6">
        <h3 class="text-xl font-bold text-gray-900 mb-4 border-b-2 border-primary-600 pb-2">
          Part 1: What to Do Immediately
        </h3>

        <div class="space-y-4">
          <!-- Immediate Actions -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Immediate Actions Checklist
            </label>
            <textarea
              v-model="formData.immediate_actions"
              :readonly="isReadOnly"
              rows="8"
              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
              :class="isReadOnly ? 'bg-gray-100' : ''"
              placeholder="1. Contact executor immediately&#10;2. Notify employer HR&#10;3. Access joint accounts for immediate expenses..."
            ></textarea>
          </div>

          <!-- Key Contacts Grid -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Executor Name</label>
              <input
                v-model="formData.executor_name"
                :readonly="isReadOnly"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
                :class="isReadOnly ? 'bg-gray-100' : ''"
                placeholder="Full name"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Executor Contact</label>
              <input
                v-model="formData.executor_contact"
                :readonly="isReadOnly"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
                :class="isReadOnly ? 'bg-gray-100' : ''"
                placeholder="Phone / email"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Attorney Name</label>
              <input
                v-model="formData.attorney_name"
                :readonly="isReadOnly"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
                :class="isReadOnly ? 'bg-gray-100' : ''"
                placeholder="Full name"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Attorney Contact</label>
              <input
                v-model="formData.attorney_contact"
                :readonly="isReadOnly"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
                :class="isReadOnly ? 'bg-gray-100' : ''"
                placeholder="Phone / email"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Financial Advisor Name</label>
              <input
                v-model="formData.financial_advisor_name"
                :readonly="isReadOnly"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
                :class="isReadOnly ? 'bg-gray-100' : ''"
                placeholder="Full name"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Financial Advisor Contact</label>
              <input
                v-model="formData.financial_advisor_contact"
                :readonly="isReadOnly"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
                :class="isReadOnly ? 'bg-gray-100' : ''"
                placeholder="Phone / email"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Accountant Name</label>
              <input
                v-model="formData.accountant_name"
                :readonly="isReadOnly"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
                :class="isReadOnly ? 'bg-gray-100' : ''"
                placeholder="Full name"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Accountant Contact</label>
              <input
                v-model="formData.accountant_contact"
                :readonly="isReadOnly"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
                :class="isReadOnly ? 'bg-gray-100' : ''"
                placeholder="Phone / email"
              />
            </div>
          </div>

          <!-- Immediate Funds Access -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Accessing Immediate Funds
            </label>
            <textarea
              v-model="formData.immediate_funds_access"
              :readonly="isReadOnly"
              rows="4"
              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
              :class="isReadOnly ? 'bg-gray-100' : ''"
              placeholder="Explain which joint accounts can be accessed immediately, where emergency cash is kept, etc."
            ></textarea>
          </div>

          <!-- Employer Information -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Employer HR Contact</label>
              <input
                v-model="formData.employer_hr_contact"
                :readonly="isReadOnly"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
                :class="isReadOnly ? 'bg-gray-100' : ''"
                placeholder="HR phone / email"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Employer Benefits Information</label>
              <input
                v-model="formData.employer_benefits_info"
                :readonly="isReadOnly"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
                :class="isReadOnly ? 'bg-gray-100' : ''"
                placeholder="Life insurance, pension contact details"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Part 2: Accessing and Managing Accounts -->
      <div class="bg-white shadow-sm rounded-lg p-6">
        <h3 class="text-xl font-bold text-gray-900 mb-4 border-b-2 border-primary-600 pb-2">
          Part 2: Accessing and Managing Accounts
        </h3>

        <div class="space-y-4">
          <!-- Online Accounts -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Password Manager / Online Account Access
            </label>
            <textarea
              v-model="formData.password_manager_info"
              :readonly="isReadOnly"
              rows="3"
              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
              :class="isReadOnly ? 'bg-gray-100' : ''"
              placeholder="e.g., 1Password account details, master password location, emergency access setup..."
            ></textarea>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Phone Plan Information
            </label>
            <textarea
              v-model="formData.phone_plan_info"
              :readonly="isReadOnly"
              rows="2"
              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
              :class="isReadOnly ? 'bg-gray-100' : ''"
              placeholder="Keep my phone active for account verification purposes. Provider, account number..."
            ></textarea>
          </div>

          <!-- Financial Accounts -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Bank Accounts (Auto-populated)
            </label>
            <textarea
              v-model="formData.bank_accounts_info"
              :readonly="isReadOnly"
              rows="6"
              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 font-mono text-sm"
              :class="isReadOnly ? 'bg-gray-100' : ''"
              placeholder="List of bank accounts will appear here..."
            ></textarea>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Investment Accounts (Auto-populated)
            </label>
            <textarea
              v-model="formData.investment_accounts_info"
              :readonly="isReadOnly"
              rows="6"
              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 font-mono text-sm"
              :class="isReadOnly ? 'bg-gray-100' : ''"
              placeholder="List of investment accounts will appear here..."
            ></textarea>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Insurance Policies (Auto-populated)
            </label>
            <textarea
              v-model="formData.insurance_policies_info"
              :readonly="isReadOnly"
              rows="6"
              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 font-mono text-sm"
              :class="isReadOnly ? 'bg-gray-100' : ''"
              placeholder="Life insurance, critical illness, income protection, home, and auto insurance details..."
            ></textarea>
          </div>

          <!-- Assets -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Real Estate (Auto-populated)
            </label>
            <textarea
              v-model="formData.real_estate_info"
              :readonly="isReadOnly"
              rows="6"
              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 font-mono text-sm"
              :class="isReadOnly ? 'bg-gray-100' : ''"
              placeholder="Property details, title deeds location..."
            ></textarea>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Vehicles
            </label>
            <textarea
              v-model="formData.vehicles_info"
              :readonly="isReadOnly"
              rows="3"
              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
              :class="isReadOnly ? 'bg-gray-100' : ''"
              placeholder="Car make/model, registration, V5C location, finance details..."
            ></textarea>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Valuable Items / Collectibles
            </label>
            <textarea
              v-model="formData.valuable_items_info"
              :readonly="isReadOnly"
              rows="3"
              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
              :class="isReadOnly ? 'bg-gray-100' : ''"
              placeholder="Jewelry, art, antiques, watch collection, etc. Include valuations and insurance details..."
            ></textarea>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Cryptocurrency
            </label>
            <textarea
              v-model="formData.cryptocurrency_info"
              :readonly="isReadOnly"
              rows="3"
              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
              :class="isReadOnly ? 'bg-gray-100' : ''"
              placeholder="Wallet addresses, exchange accounts, hardware wallet location and recovery seed information..."
            ></textarea>
          </div>

          <!-- Liabilities -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Liabilities (Auto-populated)
            </label>
            <textarea
              v-model="formData.liabilities_info"
              :readonly="isReadOnly"
              rows="6"
              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 font-mono text-sm"
              :class="isReadOnly ? 'bg-gray-100' : ''"
              placeholder="Mortgages, loans, credit cards, and other debts..."
            ></textarea>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Recurring Bills
            </label>
            <textarea
              v-model="formData.recurring_bills_info"
              :readonly="isReadOnly"
              rows="4"
              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
              :class="isReadOnly ? 'bg-gray-100' : ''"
              placeholder="Council tax, utilities, subscriptions, insurance premiums, etc."
            ></textarea>
          </div>
        </div>
      </div>

      <!-- Part 3: Long-term Plans -->
      <div class="bg-white shadow-sm rounded-lg p-6">
        <h3 class="text-xl font-bold text-gray-900 mb-4 border-b-2 border-primary-600 pb-2">
          Part 3: Long-term Plans and Considerations
        </h3>

        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Estate Documents Location
            </label>
            <textarea
              v-model="formData.estate_documents_location"
              :readonly="isReadOnly"
              rows="3"
              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
              :class="isReadOnly ? 'bg-gray-100' : ''"
              placeholder="Location of will, trust documents, power of attorney, etc. (e.g., safe deposit box, solicitor's office, home safe)"
            ></textarea>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Beneficiary Information (Auto-populated)
            </label>
            <textarea
              v-model="formData.beneficiary_info"
              :readonly="isReadOnly"
              rows="4"
              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 font-mono text-sm"
              :class="isReadOnly ? 'bg-gray-100' : ''"
              placeholder="Beneficiaries of life insurance, pensions, and estate..."
            ></textarea>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Children's Education Plans (Auto-populated)
            </label>
            <textarea
              v-model="formData.children_education_plans"
              :readonly="isReadOnly"
              rows="4"
              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 font-mono text-sm"
              :class="isReadOnly ? 'bg-gray-100' : ''"
              placeholder="University plans, savings accounts for education, guardian preferences..."
            ></textarea>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Financial Guidance (Auto-populated)
            </label>
            <textarea
              v-model="formData.financial_guidance"
              :readonly="isReadOnly"
              rows="6"
              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 font-mono text-sm"
              :class="isReadOnly ? 'bg-gray-100' : ''"
              placeholder="General financial advice, contacts for financial advisors..."
            ></textarea>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              State Pension / Social Security Information
            </label>
            <textarea
              v-model="formData.social_security_info"
              :readonly="isReadOnly"
              rows="3"
              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
              :class="isReadOnly ? 'bg-gray-100' : ''"
              placeholder="National Insurance number, State Pension forecast, when to claim, survivor benefits..."
            ></textarea>
          </div>
        </div>
      </div>

      <!-- Part 4: Funeral and Final Wishes -->
      <div class="bg-white shadow-sm rounded-lg p-6">
        <h3 class="text-xl font-bold text-gray-900 mb-4 border-b-2 border-primary-600 pb-2">
          Part 4: Funeral and Final Wishes
        </h3>

        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Funeral Preference
            </label>
            <select
              v-model="formData.funeral_preference"
              :disabled="isReadOnly"
              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
              :class="isReadOnly ? 'bg-gray-100' : ''"
            >
              <option value="not_specified">Not Specified</option>
              <option value="burial">Burial</option>
              <option value="cremation">Cremation</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Funeral Service Details
            </label>
            <textarea
              v-model="formData.funeral_service_details"
              :readonly="isReadOnly"
              rows="4"
              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
              :class="isReadOnly ? 'bg-gray-100' : ''"
              placeholder="Preferred funeral home, religious/secular service, readings, music, attendees, location, etc."
            ></textarea>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Obituary Wishes
            </label>
            <textarea
              v-model="formData.obituary_wishes"
              :readonly="isReadOnly"
              rows="3"
              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
              :class="isReadOnly ? 'bg-gray-100' : ''"
              placeholder="Key accomplishments, charities for donations in lieu of flowers, etc."
            ></textarea>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Additional Wishes
            </label>
            <textarea
              v-model="formData.additional_wishes"
              :readonly="isReadOnly"
              rows="4"
              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
              :class="isReadOnly ? 'bg-gray-100' : ''"
              placeholder="Any other final wishes, messages to loved ones, etc."
            ></textarea>
          </div>
        </div>
      </div>

      <!-- Save Button -->
      <div v-if="!isReadOnly" class="flex justify-end space-x-4">
        <button
          @click="saveLetter"
          :disabled="saving"
          class="px-6 py-3 bg-primary-600 text-white rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed font-medium"
        >
          {{ saving ? 'Saving...' : 'Save Letter' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import api from '@/services/api';

export default {
  name: 'LetterToSpouse',

  data() {
    return {
      loading: true,
      saving: false,
      viewMode: 'my', // 'my' or 'spouse'
      hasSpouse: false,
      spouseName: '',
      formData: {
        // Part 1
        immediate_actions: '',
        executor_name: '',
        executor_contact: '',
        attorney_name: '',
        attorney_contact: '',
        financial_advisor_name: '',
        financial_advisor_contact: '',
        accountant_name: '',
        accountant_contact: '',
        immediate_funds_access: '',
        employer_hr_contact: '',
        employer_benefits_info: '',
        // Part 2
        password_manager_info: '',
        phone_plan_info: '',
        bank_accounts_info: '',
        investment_accounts_info: '',
        insurance_policies_info: '',
        real_estate_info: '',
        vehicles_info: '',
        valuable_items_info: '',
        cryptocurrency_info: '',
        liabilities_info: '',
        recurring_bills_info: '',
        // Part 3
        estate_documents_location: '',
        beneficiary_info: '',
        children_education_plans: '',
        financial_guidance: '',
        social_security_info: '',
        // Part 4
        funeral_preference: 'not_specified',
        funeral_service_details: '',
        obituary_wishes: '',
        additional_wishes: '',
      },
      myLetterData: null,
      spouseLetterData: null,
    };
  },

  computed: {
    isPreviewMode() {
      return this.$store.getters['preview/isPreviewMode'];
    },

    isReadOnly() {
      return this.viewMode === 'spouse';
    },
  },

  async mounted() {
    if (!this.isPreviewMode) {
      await this.loadMyLetter();
      await this.checkSpouse();
    } else {
      console.log('[LetterToSpouse] Preview mode - skipping API calls');
      this.loading = false;
    }
  },

  methods: {
    async loadMyLetter() {
      if (this.isPreviewMode) {
        console.log('[LetterToSpouse] Preview mode - skipping loadMyLetter');
        return;
      }
      this.loading = true;
      try {
        const response = await api.get('/user/letter-to-spouse');
        this.myLetterData = response.data.data;
        if (this.viewMode === 'my') {
          this.populateForm(this.myLetterData);
        }
      } catch (error) {
        console.error('Error loading letter:', error);
        this.$emit('error', 'Failed to load your letter');
      } finally {
        this.loading = false;
      }
    },

    async checkSpouse() {
      if (this.isPreviewMode) {
        console.log('[LetterToSpouse] Preview mode - skipping checkSpouse');
        return;
      }
      try {
        const userResponse = await api.get('/auth/user');
        const user = userResponse.data;
        this.hasSpouse = !!user.spouse_id;

        if (this.hasSpouse) {
          // Get spouse info from family members
          const familyResponse = await api.get('/user/family-members');
          const spouse = familyResponse.data.find(m => m.relationship === 'spouse');
          if (spouse) {
            this.spouseName = spouse.name;
          }
        }
      } catch (error) {
        console.error('Error checking spouse:', error);
      }
    },

    async loadSpouseLetter() {
      if (this.isPreviewMode) {
        console.log('[LetterToSpouse] Preview mode - skipping loadSpouseLetter');
        return;
      }
      if (this.viewMode === 'spouse' && this.spouseLetterData) {
        this.populateForm(this.spouseLetterData);
        return;
      }

      this.loading = true;
      this.viewMode = 'spouse';

      try {
        const response = await api.get('/user/letter-to-spouse/spouse');
        this.spouseLetterData = response.data.data;
        this.spouseName = response.data.spouse_name;
        this.populateForm(this.spouseLetterData);
      } catch (error) {
        console.error('Error loading spouse letter:', error);
        this.$emit('error', 'Failed to load spouse letter');
        this.viewMode = 'my';
        this.populateForm(this.myLetterData);
      } finally {
        this.loading = false;
      }
    },

    populateForm(data) {
      if (!data) return;

      Object.keys(this.formData).forEach(key => {
        if (data[key] !== undefined && data[key] !== null) {
          this.formData[key] = data[key];
        }
      });
    },

    async saveLetter() {
      if (this.isPreviewMode || this.isReadOnly) {
        console.log('[LetterToSpouse] Preview mode or read only - skipping saveLetter');
        return;
      }

      this.saving = true;
      try {
        const response = await api.put('/user/letter-to-spouse', this.formData);
        this.myLetterData = response.data.data;
        this.$emit('success', 'Letter saved successfully');
      } catch (error) {
        console.error('Error saving letter:', error);
        this.$emit('error', 'Failed to save letter');
      } finally {
        this.saving = false;
      }
    },
  },
};
</script>

<style scoped>
.letter-to-spouse {
  @apply max-w-5xl mx-auto;
}
</style>
