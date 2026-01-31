<template>
  <div>
    <div class="mb-6 flex justify-between items-start">
      <div>
        <h2 class="text-h4 font-semibold text-gray-900">{{ pageTitle }}</h2>
        <p class="mt-1 text-body-sm text-gray-600">
          {{ pageDescription }}
        </p>
      </div>
      <div class="flex gap-3">
        <template v-if="!isReadOnly">
          <button
            v-if="!isEditing"
            @click="isEditing = true"
            class="btn-secondary"
          >
            Edit
          </button>
          <template v-else>
            <button
              @click="cancelEditing"
              class="btn-secondary"
              :disabled="saving"
            >
              Cancel
            </button>
            <button
              @click="saveLetter"
              :disabled="saving"
              class="btn-primary"
            >
              {{ saving ? 'Saving...' : 'Save' }}
            </button>
          </template>
        </template>
      </div>
    </div>

    <!-- Info Banner -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
      <p class="text-body-sm text-blue-800">
        <strong>Why this matters:</strong> {{ infoBannerText }}
      </p>
    </div>

    <!-- View Toggle -->
    <div v-if="hasSpouse" class="flex space-x-4 mb-6">
      <button
        @click="switchToMyLetter"
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

    <!-- Read-only Banner for Spouse View -->
    <div v-if="viewMode === 'spouse'" class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
      <div class="flex items-center">
        <svg class="h-5 w-5 text-blue-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
        </svg>
        <p class="text-body-sm text-blue-800 font-medium">
          Viewing {{ spouseName }}'s letter (read-only)
        </p>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center items-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600"></div>
    </div>

    <!-- Letter Content -->
    <div v-else class="space-y-6">
      <!-- Part 1: What to Do Immediately -->
      <div class="bg-white rounded-lg shadow border border-gray-200">
        <div class="border-b border-gray-200 px-6 py-4">
          <h3 class="text-lg font-semibold text-gray-900">Part 1: What to Do Immediately</h3>
        </div>
        <div class="p-6 space-y-6">
          <!-- VIEW MODE -->
          <template v-if="!isEditing || isReadOnly">
            <!-- Immediate Actions -->
            <div>
              <div class="text-body-sm font-medium text-gray-700 mb-2">Immediate Actions Checklist</div>
              <p class="text-body-sm text-gray-900 whitespace-pre-line">{{ formData.immediate_actions || 'Not specified' }}</p>
            </div>

            <!-- Key Contacts Grid -->
            <div>
              <h4 class="text-body font-semibold text-gray-800 mb-4">Key Contacts</h4>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                  <div class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Executor</div>
                  <p class="text-body-sm text-gray-900">{{ formData.executor_name || 'Not specified' }}</p>
                  <p class="text-body-sm text-gray-600">{{ formData.executor_contact || '-' }}</p>
                </div>
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                  <div class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Solicitor</div>
                  <p class="text-body-sm text-gray-900">{{ formData.attorney_name || 'Not specified' }}</p>
                  <p class="text-body-sm text-gray-600">{{ formData.attorney_contact || '-' }}</p>
                </div>
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                  <div class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Financial Adviser</div>
                  <p class="text-body-sm text-gray-900">{{ formData.financial_advisor_name || 'Not specified' }}</p>
                  <p class="text-body-sm text-gray-600">{{ formData.financial_advisor_contact || '-' }}</p>
                </div>
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                  <div class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Accountant</div>
                  <p class="text-body-sm text-gray-900">{{ formData.accountant_name || 'Not specified' }}</p>
                  <p class="text-body-sm text-gray-600">{{ formData.accountant_contact || '-' }}</p>
                </div>
              </div>
            </div>

            <!-- Immediate Funds & Employer -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <div class="text-body-sm font-medium text-gray-700 mb-2">Accessing Immediate Funds</div>
                <p class="text-body-sm text-gray-900 whitespace-pre-line">{{ formData.immediate_funds_access || 'Not specified' }}</p>
              </div>
              <div>
                <div class="text-body-sm font-medium text-gray-700 mb-2">Employer HR Contact</div>
                <p class="text-body-sm text-gray-900">{{ formData.employer_hr_contact || 'Not specified' }}</p>
                <p class="text-body-sm text-gray-600">{{ formData.employer_benefits_info || '-' }}</p>
              </div>
            </div>
          </template>

          <!-- EDIT MODE -->
          <template v-else>
            <!-- Immediate Actions -->
            <div>
              <label class="block text-body-sm font-medium text-gray-700 mb-2">
                Immediate Actions Checklist
              </label>
              <textarea
                v-model="formData.immediate_actions"
                rows="5"
                class="input-field"
                placeholder="1. Contact executor immediately&#10;2. Notify employer HR&#10;3. Access joint bank accounts for immediate expenses..."
              ></textarea>
            </div>

            <!-- Key Contacts Grid -->
            <div>
              <h4 class="text-body font-semibold text-gray-800 mb-4">Key Contacts</h4>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                  <div class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Executor</div>
                  <input
                    v-model="formData.executor_name"
                    type="text"
                    class="input-field mb-2"
                    placeholder="Name"
                  />
                  <input
                    v-model="formData.executor_contact"
                    type="text"
                    class="input-field"
                    placeholder="Phone / Email"
                  />
                </div>
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                  <div class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Solicitor</div>
                  <input
                    v-model="formData.attorney_name"
                    type="text"
                    class="input-field mb-2"
                    placeholder="Name"
                  />
                  <input
                    v-model="formData.attorney_contact"
                    type="text"
                    class="input-field"
                    placeholder="Phone / Email"
                  />
                </div>
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                  <div class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Financial Adviser</div>
                  <input
                    v-model="formData.financial_advisor_name"
                    type="text"
                    class="input-field mb-2"
                    placeholder="Name"
                  />
                  <input
                    v-model="formData.financial_advisor_contact"
                    type="text"
                    class="input-field"
                    placeholder="Phone / Email"
                  />
                </div>
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                  <div class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Accountant</div>
                  <input
                    v-model="formData.accountant_name"
                    type="text"
                    class="input-field mb-2"
                    placeholder="Name"
                  />
                  <input
                    v-model="formData.accountant_contact"
                    type="text"
                    class="input-field"
                    placeholder="Phone / Email"
                  />
                </div>
              </div>
            </div>

            <!-- Immediate Funds & Employer -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-body-sm font-medium text-gray-700 mb-2">Accessing Immediate Funds</label>
                <textarea
                  v-model="formData.immediate_funds_access"
                  rows="3"
                  class="input-field"
                  placeholder="Which accounts can be accessed immediately..."
                ></textarea>
              </div>
              <div>
                <label class="block text-body-sm font-medium text-gray-700 mb-2">Employer HR Contact</label>
                <input
                  v-model="formData.employer_hr_contact"
                  type="text"
                  class="input-field mb-2"
                  placeholder="HR phone / email"
                />
                <input
                  v-model="formData.employer_benefits_info"
                  type="text"
                  class="input-field"
                  placeholder="Benefits info (life insurance, pension)"
                />
              </div>
            </div>
          </template>
        </div>
      </div>

      <!-- Part 2: Financial Overview (Auto-populated) -->
      <div class="bg-white rounded-lg shadow border border-gray-200">
        <div class="border-b border-gray-200 px-6 py-4 flex justify-between items-center">
          <h3 class="text-lg font-semibold text-gray-900">Part 2: Financial Overview</h3>
          <span class="text-xs font-medium text-blue-600 bg-blue-100 px-2 py-1 rounded">Auto-populated</span>
        </div>
        <div class="p-6 space-y-6">
          <!-- Bank Accounts / Savings -->
          <div>
            <div class="flex justify-between items-center mb-3">
              <h4 class="text-body font-semibold text-gray-800">Bank Accounts & Savings</h4>
              <span class="text-lg font-bold text-gray-900">{{ formatCurrency(profileData.totalSavings) }}</span>
            </div>
            <div v-if="profileData.savings.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
              <div
                v-for="account in profileData.savings"
                :key="account.id"
                class="bg-gray-50 rounded-lg p-4 border border-gray-200"
              >
                <div class="flex justify-between items-start">
                  <div>
                    <div class="font-medium text-gray-900">{{ account.account_name }}</div>
                    <div class="text-sm text-gray-500">{{ account.provider }}</div>
                  </div>
                  <span v-if="account.is_isa" class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded">ISA</span>
                </div>
                <div class="mt-2 text-lg font-semibold text-gray-900">{{ formatCurrency(account.current_balance) }}</div>
              </div>
            </div>
            <div v-else class="text-sm text-gray-500 italic">No savings accounts recorded</div>
          </div>

          <!-- Investment Accounts -->
          <div>
            <div class="flex justify-between items-center mb-3">
              <h4 class="text-body font-semibold text-gray-800">Investments</h4>
              <span class="text-lg font-bold text-gray-900">{{ formatCurrency(profileData.totalInvestments) }}</span>
            </div>
            <div v-if="profileData.investments.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
              <div
                v-for="account in profileData.investments"
                :key="account.id"
                class="bg-gray-50 rounded-lg p-4 border border-gray-200"
              >
                <div class="flex justify-between items-start">
                  <div>
                    <div class="font-medium text-gray-900">{{ account.account_name }}</div>
                    <div class="text-sm text-gray-500">{{ account.provider }}</div>
                  </div>
                  <span v-if="account.account_type === 'isa'" class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded">ISA</span>
                  <span v-else-if="account.account_type === 'sipp'" class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded">SIPP</span>
                </div>
                <div class="mt-2 text-lg font-semibold text-gray-900">{{ formatCurrency(account.current_value) }}</div>
              </div>
            </div>
            <div v-else class="text-sm text-gray-500 italic">No investment accounts recorded</div>
          </div>

          <!-- Properties -->
          <div>
            <div class="flex justify-between items-center mb-3">
              <h4 class="text-body font-semibold text-gray-800">Properties</h4>
              <span class="text-lg font-bold text-gray-900">{{ formatCurrency(profileData.totalPropertyValue) }}</span>
            </div>
            <div v-if="profileData.properties.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <div
                v-for="property in profileData.properties"
                :key="property.id"
                class="bg-gray-50 rounded-lg p-4 border border-gray-200"
              >
                <div class="flex justify-between items-start">
                  <div>
                    <div class="font-medium text-gray-900">{{ property.property_name || property.address_line_1 }}</div>
                    <div class="text-sm text-gray-500">{{ formatPropertyType(property.property_type) }}</div>
                  </div>
                  <span :class="ownershipBadgeClass(property.ownership_type)" class="text-xs px-2 py-0.5 rounded">
                    {{ formatOwnershipType(property.ownership_type) }}
                  </span>
                </div>
                <div class="mt-2 flex justify-between">
                  <div>
                    <div class="text-sm text-gray-500">Value</div>
                    <div class="font-semibold text-gray-900">{{ formatCurrency(property.current_value) }}</div>
                  </div>
                  <div v-if="property.mortgage_balance">
                    <div class="text-sm text-gray-500">Mortgage</div>
                    <div class="font-semibold text-red-600">{{ formatCurrency(property.mortgage_balance) }}</div>
                  </div>
                </div>
              </div>
            </div>
            <div v-else class="text-sm text-gray-500 italic">No properties recorded</div>
          </div>

          <!-- Insurance Policies -->
          <div>
            <div class="flex justify-between items-center mb-3">
              <h4 class="text-body font-semibold text-gray-800">Life Insurance & Protection</h4>
              <span class="text-lg font-bold text-green-600">{{ formatCurrency(profileData.totalCoverage) }}</span>
            </div>
            <div v-if="profileData.policies.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
              <div
                v-for="policy in profileData.policies"
                :key="`${policy.policy_type}-${policy.id}`"
                class="bg-gray-50 rounded-lg p-4 border border-gray-200"
              >
                <div class="flex justify-between items-start">
                  <div>
                    <div class="font-medium text-gray-900">{{ policy.provider }}</div>
                    <div class="text-sm text-gray-500">{{ formatPolicyType(policy.policy_type) }}</div>
                  </div>
                </div>
                <div class="mt-2">
                  <div class="text-sm text-gray-500">Sum Assured</div>
                  <div class="font-semibold text-green-600">{{ formatCurrency(policy.sum_assured || policy.benefit_amount) }}</div>
                </div>
              </div>
            </div>
            <div v-else class="text-sm text-gray-500 italic">No protection policies recorded</div>
          </div>

          <!-- Liabilities -->
          <div>
            <div class="flex justify-between items-center mb-3">
              <h4 class="text-body font-semibold text-gray-800">Liabilities & Debts</h4>
              <span class="text-lg font-bold text-red-600">{{ formatCurrency(profileData.totalLiabilities) }}</span>
            </div>
            <div v-if="profileData.liabilities.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
              <div
                v-for="liability in profileData.liabilities"
                :key="liability.id"
                class="bg-gray-50 rounded-lg p-4 border border-gray-200"
              >
                <div class="font-medium text-gray-900">{{ liability.liability_name }}</div>
                <div class="text-sm text-gray-500">{{ formatLiabilityType(liability.liability_type) }}</div>
                <div class="mt-2 font-semibold text-red-600">{{ formatCurrency(liability.current_balance) }}</div>
              </div>
            </div>
            <div v-else class="text-sm text-gray-500 italic">No liabilities recorded</div>
          </div>
        </div>
      </div>

      <!-- Part 3: Additional Information (Manual Entry) -->
      <div class="bg-white rounded-lg shadow border border-gray-200">
        <div class="border-b border-gray-200 px-6 py-4">
          <h3 class="text-lg font-semibold text-gray-900">Part 3: Additional Information</h3>
        </div>
        <div class="p-6 space-y-4">
          <!-- VIEW MODE -->
          <template v-if="!isEditing || isReadOnly">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <div class="text-body-sm font-medium text-gray-700 mb-2">Password Manager / Online Access</div>
                <p class="text-body-sm text-gray-900 whitespace-pre-line">{{ formData.password_manager_info || 'Not specified' }}</p>
              </div>
              <div>
                <div class="text-body-sm font-medium text-gray-700 mb-2">Estate Documents Location</div>
                <p class="text-body-sm text-gray-900 whitespace-pre-line">{{ formData.estate_documents_location || 'Not specified' }}</p>
              </div>
              <div>
                <div class="text-body-sm font-medium text-gray-700 mb-2">Vehicles</div>
                <p class="text-body-sm text-gray-900 whitespace-pre-line">{{ formData.vehicles_info || 'Not specified' }}</p>
              </div>
              <div>
                <div class="text-body-sm font-medium text-gray-700 mb-2">Valuable Items</div>
                <p class="text-body-sm text-gray-900 whitespace-pre-line">{{ formData.valuable_items_info || 'Not specified' }}</p>
              </div>
              <div>
                <div class="text-body-sm font-medium text-gray-700 mb-2">Cryptocurrency</div>
                <p class="text-body-sm text-gray-900 whitespace-pre-line">{{ formData.cryptocurrency_info || 'Not specified' }}</p>
              </div>
              <div>
                <div class="text-body-sm font-medium text-gray-700 mb-2">Recurring Bills</div>
                <p class="text-body-sm text-gray-900 whitespace-pre-line">{{ formData.recurring_bills_info || 'Not specified' }}</p>
              </div>
            </div>
          </template>

          <!-- EDIT MODE -->
          <template v-else>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-body-sm font-medium text-gray-700 mb-2">Password Manager / Online Access</label>
                <textarea
                  v-model="formData.password_manager_info"
                  rows="3"
                  class="input-field"
                  placeholder="1Password details, emergency access..."
                ></textarea>
              </div>
              <div>
                <label class="block text-body-sm font-medium text-gray-700 mb-2">Estate Documents Location</label>
                <textarea
                  v-model="formData.estate_documents_location"
                  rows="3"
                  class="input-field"
                  placeholder="Will, trust documents, power of attorney..."
                ></textarea>
              </div>
              <div>
                <label class="block text-body-sm font-medium text-gray-700 mb-2">Vehicles</label>
                <textarea
                  v-model="formData.vehicles_info"
                  rows="2"
                  class="input-field"
                  placeholder="Car details, V5C location..."
                ></textarea>
              </div>
              <div>
                <label class="block text-body-sm font-medium text-gray-700 mb-2">Valuable Items</label>
                <textarea
                  v-model="formData.valuable_items_info"
                  rows="2"
                  class="input-field"
                  placeholder="Jewellery, art, antiques..."
                ></textarea>
              </div>
              <div>
                <label class="block text-body-sm font-medium text-gray-700 mb-2">Cryptocurrency</label>
                <textarea
                  v-model="formData.cryptocurrency_info"
                  rows="2"
                  class="input-field"
                  placeholder="Wallet addresses, recovery seeds..."
                ></textarea>
              </div>
              <div>
                <label class="block text-body-sm font-medium text-gray-700 mb-2">Recurring Bills</label>
                <textarea
                  v-model="formData.recurring_bills_info"
                  rows="2"
                  class="input-field"
                  placeholder="Council tax, utilities, subscriptions..."
                ></textarea>
              </div>
            </div>
          </template>
        </div>
      </div>

      <!-- Part 4: Funeral and Final Wishes -->
      <div class="bg-white rounded-lg shadow border border-gray-200">
        <div class="border-b border-gray-200 px-6 py-4">
          <h3 class="text-lg font-semibold text-gray-900">Part 4: Funeral and Final Wishes</h3>
        </div>
        <div class="p-6 space-y-4">
          <!-- VIEW MODE -->
          <template v-if="!isEditing || isReadOnly">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <div class="text-body-sm font-medium text-gray-700 mb-2">Funeral Preference</div>
                <p class="text-body-sm text-gray-900">{{ formatFuneralPreference(formData.funeral_preference) }}</p>
              </div>
              <div>
                <div class="text-body-sm font-medium text-gray-700 mb-2">Funeral Service Details</div>
                <p class="text-body-sm text-gray-900 whitespace-pre-line">{{ formData.funeral_service_details || 'Not specified' }}</p>
              </div>
              <div>
                <div class="text-body-sm font-medium text-gray-700 mb-2">Obituary Wishes</div>
                <p class="text-body-sm text-gray-900 whitespace-pre-line">{{ formData.obituary_wishes || 'Not specified' }}</p>
              </div>
              <div>
                <div class="text-body-sm font-medium text-gray-700 mb-2">Additional Wishes</div>
                <p class="text-body-sm text-gray-900 whitespace-pre-line">{{ formData.additional_wishes || 'Not specified' }}</p>
              </div>
            </div>
          </template>

          <!-- EDIT MODE -->
          <template v-else>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-body-sm font-medium text-gray-700 mb-2">Funeral Preference</label>
                <select
                  v-model="formData.funeral_preference"
                  class="input-field"
                >
                  <option value="not_specified">Not Specified</option>
                  <option value="burial">Burial</option>
                  <option value="cremation">Cremation</option>
                </select>
              </div>
              <div>
                <label class="block text-body-sm font-medium text-gray-700 mb-2">Funeral Service Details</label>
                <textarea
                  v-model="formData.funeral_service_details"
                  rows="2"
                  class="input-field"
                  placeholder="Service preferences, music, location..."
                ></textarea>
              </div>
              <div>
                <label class="block text-body-sm font-medium text-gray-700 mb-2">Obituary Wishes</label>
                <textarea
                  v-model="formData.obituary_wishes"
                  rows="2"
                  class="input-field"
                  placeholder="Key accomplishments, charities..."
                ></textarea>
              </div>
              <div>
                <label class="block text-body-sm font-medium text-gray-700 mb-2">Additional Wishes</label>
                <textarea
                  v-model="formData.additional_wishes"
                  rows="2"
                  class="input-field"
                  placeholder="Messages to loved ones..."
                ></textarea>
              </div>
            </div>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import api from '@/services/api';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'LetterToSpouse',
  mixins: [currencyMixin],

  data() {
    return {
      loading: true,
      saving: false,
      isEditing: false,
      viewMode: 'my',
      hasSpouse: false,
      spouseName: '',
      isExpressionOfWishes: false,
      originalFormData: null,
      formData: {
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
        password_manager_info: '',
        estate_documents_location: '',
        vehicles_info: '',
        valuable_items_info: '',
        cryptocurrency_info: '',
        recurring_bills_info: '',
        funeral_preference: 'not_specified',
        funeral_service_details: '',
        obituary_wishes: '',
        additional_wishes: '',
      },
      profileData: {
        savings: [],
        investments: [],
        properties: [],
        policies: [],
        liabilities: [],
        totalSavings: 0,
        totalInvestments: 0,
        totalPropertyValue: 0,
        totalCoverage: 0,
        totalLiabilities: 0,
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

    pageTitle() {
      return this.isExpressionOfWishes ? 'Expression of Wishes' : 'Letter to Spouse';
    },

    pageDescription() {
      return this.isExpressionOfWishes
        ? 'Important information for your loved ones in the event of your death'
        : 'Important information for your spouse in the event of your death';
    },

    infoBannerText() {
      return this.isExpressionOfWishes
        ? 'This document provides crucial information for your loved ones to manage your affairs after your death. Auto-populated sections show your current profile data.'
        : 'This letter provides crucial information for your spouse to manage financial affairs after your death. Auto-populated sections show your current profile data.';
    },
  },

  async mounted() {
    await Promise.all([
      this.loadMyLetter(),
      this.loadProfileData(),
      this.checkSpouse(),
    ]);
  },

  methods: {
    async loadMyLetter() {
      try {
        const response = await api.get('/user/letter-to-spouse');
        this.myLetterData = response.data.data;
        if (this.viewMode === 'my') {
          this.populateForm(this.myLetterData);
        }
      } catch (error) {
        console.error('Error loading letter:', error);
      }
    },

    async loadProfileData() {
      this.loading = true;
      try {
        const [savingsRes, investmentsRes, propertiesRes, protectionRes, estateRes] = await Promise.all([
          api.get('/savings').catch(() => ({ data: { data: [] } })),
          api.get('/investment').catch(() => ({ data: { data: [] } })),
          api.get('/properties').catch(() => ({ data: { data: [] } })),
          api.get('/protection').catch(() => ({ data: { data: {} } })),
          api.get('/estate').catch(() => ({ data: { data: { liabilities: [] } } })),
        ]);

        // Extract savings accounts from nested structure
        this.profileData.savings = savingsRes.data.data?.accounts || savingsRes.data?.accounts || [];
        this.profileData.investments = investmentsRes.data.data?.accounts || investmentsRes.data?.accounts || [];
        this.profileData.properties = propertiesRes.data.data || propertiesRes.data || [];
        // Liabilities come from estate endpoint
        const estate = estateRes.data.data || estateRes.data || {};
        this.profileData.liabilities = estate.liabilities || [];

        // Combine all protection policies - handle nested policies structure
        const protection = protectionRes.data.data || protectionRes.data || {};
        const policies = protection.policies || protection;
        this.profileData.policies = [
          ...(policies.life_insurance || []).map(p => ({ ...p, policy_type: 'life' })),
          ...(policies.critical_illness || []).map(p => ({ ...p, policy_type: 'critical_illness' })),
          ...(policies.income_protection || []).map(p => ({ ...p, policy_type: 'income_protection' })),
        ];

        // Calculate totals
        this.profileData.totalSavings = this.profileData.savings.reduce((sum, a) => sum + (parseFloat(a.current_balance) || 0), 0);
        this.profileData.totalInvestments = this.profileData.investments.reduce((sum, a) => sum + (parseFloat(a.current_value) || 0), 0);
        this.profileData.totalPropertyValue = this.profileData.properties.reduce((sum, p) => sum + (parseFloat(p.current_value) || 0), 0);
        this.profileData.totalCoverage = this.profileData.policies.reduce((sum, p) => sum + (parseFloat(p.sum_assured) || parseFloat(p.benefit_amount) || 0), 0);
        this.profileData.totalLiabilities = this.profileData.liabilities.reduce((sum, l) => sum + (parseFloat(l.current_balance) || 0), 0);
      } catch (error) {
        console.error('Error loading profile data:', error);
      } finally {
        this.loading = false;
      }
    },

    async checkSpouse() {
      try {
        const userResponse = await api.get('/auth/user');
        const user = userResponse.data.data?.user || userResponse.data;

        // Single, widowed and divorced users see "Expression of Wishes" instead
        const expressionOfWishesStatuses = ['single', 'widowed', 'divorced'];
        this.isExpressionOfWishes = expressionOfWishesStatuses.includes(user.marital_status);

        // Only show spouse toggle for users with a spouse
        if (!this.isExpressionOfWishes && user.spouse_id && user.spouse) {
          this.spouseName = user.spouse.name;
          this.hasSpouse = true;
        }
      } catch (error) {
        console.error('Error checking spouse:', error);
      }
    },

    switchToMyLetter() {
      this.viewMode = 'my';
      this.populateForm(this.myLetterData);
    },

    async loadSpouseLetter() {
      if (this.viewMode === 'spouse' && this.spouseLetterData) {
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
      this.originalFormData = JSON.parse(JSON.stringify(this.formData));
    },

    async saveLetter() {
      if (this.isPreviewMode || this.isReadOnly) return;

      this.saving = true;
      try {
        const response = await api.put('/user/letter-to-spouse', this.formData);
        this.myLetterData = response.data.data;
        this.originalFormData = JSON.parse(JSON.stringify(this.formData));
        this.isEditing = false;
        this.$emit('success', 'Letter saved successfully');
      } catch (error) {
        console.error('Error saving letter:', error);
        this.$emit('error', 'Failed to save letter');
      } finally {
        this.saving = false;
      }
    },

    cancelEditing() {
      if (this.originalFormData) {
        this.formData = JSON.parse(JSON.stringify(this.originalFormData));
      }
      this.isEditing = false;
    },

    formatFuneralPreference(value) {
      const options = {
        not_specified: 'Not Specified',
        burial: 'Burial',
        cremation: 'Cremation',
      };
      return options[value] || 'Not Specified';
    },

    formatPropertyType(type) {
      const types = { main_residence: 'Main Residence', secondary_residence: 'Secondary', buy_to_let: 'Buy to Let' };
      return types[type] || type;
    },

    formatOwnershipType(type) {
      const types = { individual: 'Sole', joint: 'Joint', tenants_in_common: 'TIC' };
      return types[type] || type;
    },

    ownershipBadgeClass(type) {
      if (type === 'joint') return 'bg-blue-100 text-blue-700';
      if (type === 'tenants_in_common') return 'bg-purple-100 text-purple-700';
      return 'bg-gray-100 text-gray-700';
    },

    formatPolicyType(type) {
      const types = { life: 'Life Insurance', critical_illness: 'Critical Illness', income_protection: 'Income Protection' };
      return types[type] || type;
    },

    formatLiabilityType(type) {
      const types = {
        mortgage: 'Mortgage', personal_loan: 'Personal Loan', credit_card: 'Credit Card',
        student_loan: 'Student Loan', car_loan: 'Car Loan', other: 'Other'
      };
      return types[type] || type;
    },
  },
};
</script>
