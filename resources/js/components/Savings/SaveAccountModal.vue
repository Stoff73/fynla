<template>
  <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <!-- Background overlay -->
    <div
      class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
      @click="handleClose"
    ></div>

    <!-- Modal container -->
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

      <!-- Modal panel -->
      <div
        class="relative inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full"
      >
        <!-- Header -->
        <div class="bg-white px-6 pt-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-semibold text-gray-900">
              {{ isEditing ? 'Edit Account' : 'Add Account' }}
            </h3>
            <button
              @click="handleClose"
              class="text-gray-400 hover:text-gray-500 transition-colors"
            >
              <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M6 18L18 6M6 6l12 12"
                />
              </svg>
            </button>
          </div>
        </div>

        <!-- Form -->
        <form @submit.prevent="handleSubmit" class="px-6 pb-6">
          <div class="space-y-4 max-h-[calc(100vh-300px)] overflow-y-auto pr-2">
            <!-- Institution -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Institution <span class="text-red-500">*</span>
              </label>
              <input
                v-model="formData.institution"
                type="text"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                placeholder="e.g., Halifax, Barclays, Marcus"
              />
            </div>

            <!-- Account Type -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Account Type <span class="text-red-500">*</span>
              </label>
              <select
                v-model="formData.account_type"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              >
                <option value="">Select account type...</option>
                <option value="savings_account">Savings Account</option>
                <option value="current_account">Current Account</option>
                <option value="easy_access">Easy Access</option>
                <option value="notice">Notice Account</option>
                <option value="fixed">Fixed Term</option>
              </select>
            </div>

            <!-- Current Balance -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Current Balance <span class="text-red-500">*</span>
              </label>
              <div class="relative">
                <span class="absolute left-3 top-2.5 text-gray-500">£</span>
                <input
                  v-model.number="formData.current_balance"
                  type="number"
                  step="0.01"
                  required
                  min="0"
                  class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="0.00"
                />
              </div>
            </div>

            <!-- Interest Rate -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Interest Rate <span class="text-red-500">*</span>
              </label>
              <div class="relative">
                <input
                  v-model.number="formData.interest_rate"
                  type="number"
                  step="0.01"
                  required
                  min="0"
                  max="20"
                  class="w-full pr-8 pl-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="0.00"
                />
                <span class="absolute right-3 top-2.5 text-gray-500">%</span>
              </div>
              <p class="text-xs text-gray-500 mt-1">
                Enter as percentage (e.g., 5.0 for 5%). Maximum 20%.
              </p>
            </div>

            <!-- Access Type -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Access Type <span class="text-red-500">*</span>
              </label>
              <select
                v-model="formData.access_type"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              >
                <option value="immediate">Immediate</option>
                <option value="notice">Notice Required</option>
                <option value="fixed">Fixed Term</option>
              </select>
            </div>

            <!-- Notice Period (if access_type is notice) -->
            <div v-if="formData.access_type === 'notice'">
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Notice Period (days)
              </label>
              <input
                v-model.number="formData.notice_period_days"
                type="number"
                min="1"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                placeholder="e.g., 30, 60, 90"
              />
            </div>

            <!-- Maturity Date (if access_type is fixed) -->
            <div v-if="formData.access_type === 'fixed'">
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Maturity Date
              </label>
              <input
                v-model="formData.maturity_date"
                type="date"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              />
            </div>

            <!-- Emergency Fund Status -->
            <div class="flex items-center">
              <input
                v-model="formData.is_emergency_fund"
                type="checkbox"
                id="is_emergency_fund"
                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
              />
              <label for="is_emergency_fund" class="ml-2 block text-sm text-gray-700">
                This forms part of my emergency fund
              </label>
            </div>

            <!-- ISA Status -->
            <div class="flex items-center">
              <input
                v-model="formData.is_isa"
                type="checkbox"
                id="is_isa"
                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
              />
              <label for="is_isa" class="ml-2 block text-sm text-gray-700">
                This is a tax-free savings account (ISA)
              </label>
            </div>

            <!-- Country Selector (hidden for ISAs - UK only by law) -->
            <div v-if="!formData.is_isa">
              <label for="country" class="block text-sm font-medium text-gray-700 mb-1">
                Account Country
              </label>
              <CountrySelector
                v-model="formData.country"
                placeholder="Select country where account is held"
                id="country"
              />
              <p class="text-sm text-gray-500 mt-1">Country where the savings account is held</p>
            </div>

            <!-- ISA Details (if is_isa is true) -->
            <div v-if="formData.is_isa" class="space-y-4 pl-6 border-l-2 border-blue-200">
              <!-- ISA Type -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                  ISA Type <span class="text-red-500">*</span>
                </label>
                <select
                  v-model="formData.isa_type"
                  :required="formData.is_isa"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                >
                  <option value="">Select ISA type...</option>
                  <option value="cash">Cash ISA</option>
                  <option value="stocks_shares">Stocks & Shares ISA</option>
                  <option value="LISA">Lifetime ISA</option>
                </select>
              </div>

              <!-- ISA Subscription Year -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                  Tax Year <span class="text-red-500">*</span>
                </label>
                <select
                  v-model="formData.isa_subscription_year"
                  :required="formData.is_isa"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                >
                  <option value="">Select tax year...</option>
                  <option value="2025/26">2025/26</option>
                  <option value="2024/25">2024/25</option>
                  <option value="2023/24">2023/24</option>
                </select>
              </div>

              <!-- ISA Subscription Amount -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                  Subscription Amount (Current Tax Year)
                </label>
                <div class="relative">
                  <span class="absolute left-3 top-2.5 text-gray-500">£</span>
                  <input
                    v-model.number="formData.isa_subscription_amount"
                    type="number"
                    step="0.01"
                    min="0"
                    max="20000"
                    class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="0.00"
                  />
                </div>
                <p class="text-xs text-gray-500 mt-1">Max £20,000 per tax year (2025/26)</p>
              </div>
            </div>

            <!-- Joint Ownership Section -->
            <div class="space-y-4 pt-4 border-t border-gray-200">
              <h4 class="text-sm font-semibold text-gray-900">Ownership</h4>

              <!-- Ownership Type -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                  Ownership Type <span class="text-red-500">*</span>
                </label>
                <select
                  v-model="formData.ownership_type"
                  required
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                >
                  <option value="individual">Individual Owner</option>
                  <option value="joint">Joint Owner</option>
                </select>
              </div>

              <!-- Joint Owner (if ownership_type is joint) -->
              <div v-if="formData.ownership_type === 'joint'">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                  Joint Owner <span class="text-red-500">*</span>
                </label>
                <select
                  v-model="formData.joint_owner_id"
                  :required="formData.ownership_type === 'joint'"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                >
                  <option value="">Select joint owner</option>
                  <option v-if="spouse" :value="spouse.id">{{ spouse.name }} (Spouse - Linked Account)</option>
                  <option v-if="!spouse" value="" disabled>No spouse linked - add spouse in Family Members</option>
                </select>
                <p class="text-sm text-gray-500 mt-1">
                  Joint accounts will appear in both your and your spouse's accounts.
                </p>
              </div>
            </div>

            <!-- Account Number (optional) -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Account Number (last 4 digits)
              </label>
              <input
                v-model="formData.account_number"
                type="text"
                maxlength="4"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                placeholder="Optional - for reference only"
              />
            </div>
          </div>

          <!-- Form Actions -->
          <div class="mt-6 flex gap-3">
            <button
              type="submit"
              :disabled="submitting"
              class="flex-1 px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors"
            >
              {{ submitting ? 'Saving...' : (isEditing ? 'Update Account' : 'Add Account') }}
            </button>
            <button
              type="button"
              @click="handleClose"
              class="px-6 py-3 bg-gray-100 text-gray-700 font-medium rounded-lg hover:bg-gray-200 transition-colors"
            >
              Cancel
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import CountrySelector from '@/components/Shared/CountrySelector.vue';

export default {
  name: 'SaveAccountModal',

  components: {
    CountrySelector,
  },

  props: {
    account: {
      type: Object,
      default: null,
    },
    isEditing: {
      type: Boolean,
      default: false,
    },
  },

  data() {
    return {
      submitting: false,
      formData: {
        institution: '',
        account_type: '',
        account_number: '',
        current_balance: 0,
        interest_rate: 0,
        access_type: 'immediate',
        notice_period_days: null,
        maturity_date: '',
        is_emergency_fund: false,
        is_isa: false,
        country: 'United Kingdom',
        isa_type: '',
        isa_subscription_year: '2025/26',
        isa_subscription_amount: null,
        ownership_type: 'individual',
        joint_owner_id: null,
      },
    };
  },

  computed: {
    spouse() {
      return this.$store.getters['userProfile/spouse'];
    },
  },

  mounted() {
    if (this.isEditing && this.account) {
      this.loadAccountData();
    }
  },

  methods: {
    loadAccountData() {
      this.formData = {
        institution: this.account.institution || '',
        account_type: this.account.account_type || '',
        account_number: this.account.account_number || '',
        current_balance: parseFloat(this.account.current_balance) || 0,
        interest_rate: (parseFloat(this.account.interest_rate) || 0) * 100, // Convert decimal to percentage for display
        access_type: this.account.access_type || 'immediate',
        notice_period_days: this.account.notice_period_days || null,
        maturity_date: this.formatDateForInput(this.account.maturity_date),
        is_emergency_fund: this.account.is_emergency_fund || false,
        is_isa: this.account.is_isa || false,
        country: this.account.country || 'United Kingdom',
        isa_type: this.account.isa_type || '',
        isa_subscription_year: this.account.isa_subscription_year || '2025/26',
        isa_subscription_amount: this.account.isa_subscription_amount ? parseFloat(this.account.isa_subscription_amount) : null,
        ownership_type: this.account.ownership_type || 'individual',
        joint_owner_id: this.account.joint_owner_id || null,
      };
    },

    formatDateForInput(date) {
      if (!date) return '';
      if (typeof date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(date)) {
        return date;
      }
      const dateObj = new Date(date);
      if (isNaN(dateObj.getTime())) return '';
      const year = dateObj.getFullYear();
      const month = String(dateObj.getMonth() + 1).padStart(2, '0');
      const day = String(dateObj.getDate()).padStart(2, '0');
      return `${year}-${month}-${day}`;
    },

    async handleSubmit() {
      this.submitting = true;

      try {
        const accountData = this.prepareAccountData();
        this.$emit('save', accountData);
      } catch (error) {
        console.error('Form submission error:', error);
      } finally {
        this.submitting = false;
      }
    },

    prepareAccountData() {
      const data = {
        institution: this.formData.institution,
        account_type: this.formData.account_type,
        account_number: this.formData.account_number || null,
        current_balance: this.formData.current_balance,
        interest_rate: this.formData.interest_rate / 100, // Convert percentage to decimal
        access_type: this.formData.access_type,
        notice_period_days: this.formData.access_type === 'notice' ? this.formData.notice_period_days : null,
        maturity_date: this.formData.access_type === 'fixed' ? this.formData.maturity_date : null,
        is_emergency_fund: this.formData.is_emergency_fund,
        is_isa: this.formData.is_isa,
        country: this.formData.is_isa ? 'United Kingdom' : this.formData.country,
        isa_type: this.formData.is_isa ? this.formData.isa_type : null,
        isa_subscription_year: this.formData.is_isa ? this.formData.isa_subscription_year : null,
        isa_subscription_amount: this.formData.is_isa ? this.formData.isa_subscription_amount : null,
        ownership_type: this.formData.ownership_type,
        joint_owner_id: this.formData.ownership_type === 'joint' ? this.formData.joint_owner_id : null,
      };

      return data;
    },

    handleClose() {
      this.$emit('close');
    },
  },
};
</script>

<style scoped>
/* Custom scrollbar for form content */
.overflow-y-auto {
  scrollbar-width: thin;
  scrollbar-color: #CBD5E0 #F7FAFC;
}

.overflow-y-auto::-webkit-scrollbar {
  width: 6px;
}

.overflow-y-auto::-webkit-scrollbar-track {
  background: #F7FAFC;
  border-radius: 3px;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
  background: #CBD5E0;
  border-radius: 3px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
  background: #A0AEC0;
}
</style>
