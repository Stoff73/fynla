<template>
  <div class="space-y-6">
    <!-- Success Message -->
    <div v-if="successMessage" class="rounded-md bg-success-50 p-4">
      <div class="flex">
        <div class="ml-3">
          <p class="text-body-sm font-medium text-success-800">
            {{ successMessage }}
          </p>
        </div>
      </div>
    </div>

    <!-- Error Message -->
    <div v-if="errorMessage" class="rounded-md bg-raspberry-50 p-4">
      <div class="flex">
        <div class="ml-3">
          <h3 class="text-body-sm font-medium text-raspberry-800">Error updating information</h3>
          <div class="mt-2 text-body-sm text-raspberry-700">
            <p>{{ errorMessage }}</p>
          </div>
        </div>
      </div>
    </div>

    <form @submit.prevent="handleSubmit">
      <!-- VIEW MODE -->
      <div v-if="!isEditing" class="bg-white rounded-lg border border-light-gray p-6">
        <div class="flex justify-between items-start mb-6">
          <div>
            <h3 class="text-h4 font-semibold text-horizon-500">Personal Information</h3>
            <p class="mt-1 text-body-sm text-neutral-500">
              Your personal details, contact information, occupation, and domicile status
            </p>
          </div>
          <button
            type="button"
            @click="isEditing = true"
            class="btn-secondary"
          >
            Edit
          </button>
        </div>

        <!-- Row 1: Personal Details & Address -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-12 gap-y-8">
          <!-- Personal Details Section -->
          <div>
            <h3 class="text-body-base font-semibold text-horizon-500 mb-4">Personal Details</h3>
            <div class="space-y-3">
              <div class="flex justify-between">
                <span class="text-body-sm text-neutral-500">Full Name:</span>
                <span class="text-body-sm text-horizon-500 text-right">{{ [form.first_name, form.surname].filter(Boolean).join(' ') || '—' }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-body-sm text-neutral-500">Email:</span>
                <span class="text-body-sm text-horizon-500 text-right">{{ form.email || '—' }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-body-sm text-neutral-500">Date of Birth:</span>
                <span class="text-body-sm text-horizon-500 text-right">{{ formatDisplayDate(form.date_of_birth) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-body-sm text-neutral-500">Gender:</span>
                <span class="text-body-sm text-horizon-500 text-right capitalize">{{ form.gender || '—' }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-body-sm text-neutral-500">Marital Status:</span>
                <span class="text-body-sm text-horizon-500 text-right capitalize">{{ form.marital_status || '—' }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-body-sm text-neutral-500">Phone:</span>
                <span class="text-body-sm text-horizon-500 text-right">{{ form.phone || '—' }}</span>
              </div>
            </div>
          </div>

          <!-- Address Section -->
          <div>
            <h3 class="text-body-base font-semibold text-horizon-500 mb-4">Address</h3>
            <div class="space-y-3">
              <div class="flex justify-between">
                <span class="text-body-sm text-neutral-500">Address Line 1:</span>
                <span class="text-body-sm text-horizon-500 text-right">{{ form.address_line_1 || '—' }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-body-sm text-neutral-500">Address Line 2:</span>
                <span class="text-body-sm text-horizon-500 text-right">{{ form.address_line_2 || '—' }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-body-sm text-neutral-500">City:</span>
                <span class="text-body-sm text-horizon-500 text-right">{{ form.city || '—' }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-body-sm text-neutral-500">County:</span>
                <span class="text-body-sm text-horizon-500 text-right">{{ form.county || '—' }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-body-sm text-neutral-500">Postcode:</span>
                <span class="text-body-sm text-horizon-500 text-right uppercase">{{ form.postcode || '—' }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Row 2: Occupation & Domicile Status (aligned) -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-12 gap-y-8 mt-8">
          <!-- Occupation Section -->
          <div>
            <h3 class="text-body-base font-semibold text-horizon-500 mb-4">Occupation</h3>
            <div class="space-y-3">
              <div class="flex justify-between">
                <span class="text-body-sm text-neutral-500">Job Title:</span>
                <span class="text-body-sm text-horizon-500 text-right">{{ form.occupation || '—' }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-body-sm text-neutral-500">Employer:</span>
                <span class="text-body-sm text-horizon-500 text-right">{{ form.employer || '—' }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-body-sm text-neutral-500">Industry:</span>
                <span class="text-body-sm text-horizon-500 text-right">{{ form.industry || '—' }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-body-sm text-neutral-500">Status:</span>
                <span class="text-body-sm text-horizon-500 text-right">{{ formatEmploymentStatus(form.employment_status) }}</span>
              </div>
              <div v-if="form.employment_status && form.employment_status !== 'retired'" class="flex justify-between">
                <span class="text-body-sm text-neutral-500">Retirement Age:</span>
                <span class="text-body-sm text-horizon-500 text-right">{{ form.target_retirement_age || '—' }}</span>
              </div>
            </div>
          </div>

          <!-- Domicile Section -->
          <div>
            <h3 class="text-body-base font-semibold text-horizon-500 mb-4">Domicile Status</h3>
            <div class="space-y-3">
              <div class="flex justify-between">
                <span class="text-body-sm text-neutral-500">Country of Birth:</span>
                <span class="text-body-sm text-horizon-500 text-right">{{ form.country_of_birth || '—' }}</span>
              </div>
              <div v-if="shouldShowUKArrivalDate" class="flex justify-between">
                <span class="text-body-sm text-neutral-500">Date Moved to UK:</span>
                <span class="text-body-sm text-horizon-500 text-right">{{ formatDisplayDate(form.uk_arrival_date) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-body-sm text-neutral-500">Domicile Status:</span>
                <span class="text-body-sm text-horizon-500 text-right">{{ domicileStatusLabel }}</span>
              </div>
              <div v-if="yearsResident !== null" class="flex justify-between">
                <span class="text-body-sm text-neutral-500">Years UK Resident:</span>
                <span class="text-body-sm text-horizon-500 text-right">{{ yearsResident }} years</span>
              </div>
            </div>
            <!-- Domicile Info Box -->
            <div v-if="isDeemedDomiciled" class="mt-4 p-3 bg-violet-50 rounded-lg">
              <p class="text-body-xs text-violet-700">
                You are considered deemed domiciled in the UK because you have been resident for at least 15 of the last 20 tax years.
              </p>
            </div>
            <div v-else-if="yearsResident !== null && yearsResident < 15" class="mt-4 p-3 bg-violet-50 rounded-lg">
              <p class="text-body-xs text-violet-700">
                You will become deemed domiciled after {{ 15 - yearsResident }} more year(s) of UK residence.
              </p>
            </div>
          </div>
        </div>
    </div>

      <!-- EDIT MODE - Form inputs -->
      <div v-else class="bg-white rounded-lg border border-light-gray p-6">
        <h3 class="text-h4 font-semibold text-horizon-500 mb-6">{{ context === 'onboarding' ? 'About You' : 'Edit Personal Information' }}</h3>

        <!-- Onboarding info bar — only for students where address/occupation are hidden -->
        <div v-if="context === 'onboarding' && !isFieldVisible('address_line_1')" class="mb-6 p-4 bg-violet-50 border border-violet-200 rounded-lg">
          <p class="text-body-sm text-violet-800">
            You can add your address, occupation and other details later in your profile settings.
          </p>
        </div>

        <div class="space-y-6">
        <!-- Basic Details Section -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
          <!-- First Name -->
          <div v-if="isFieldVisible('first_name')">
            <label class="block text-body-sm font-medium text-neutral-500 mb-1">
              First Name
            </label>
            <input
              id="first_name"
              v-model="form.first_name"
              type="text"
              class="input-field"
            />
          </div>

          <!-- Surname -->
          <div v-if="isFieldVisible('first_name')">
            <label class="block text-body-sm font-medium text-neutral-500 mb-1">
              Surname
            </label>
            <input
              id="surname"
              v-model="form.surname"
              type="text"
              class="input-field"
            />
          </div>

          <!-- Email -->
          <div v-if="isFieldVisible('email')">
            <label class="block text-body-sm font-medium text-neutral-500 mb-1">
              Email Address
            </label>
            <input
              id="email"
              v-model="form.email"
              type="email"
              class="input-field"
            />
          </div>

          <!-- Date of Birth -->
          <div v-if="isFieldVisible('date_of_birth')">
            <label class="block text-body-sm font-medium text-neutral-500 mb-1">
              Date of Birth
            </label>
            <input
              id="date_of_birth"
              v-model="form.date_of_birth"
              type="date"
              class="input-field"
              :max="maxDob"
              :min="minDob"
            />
          </div>

          <!-- Gender -->
          <div v-if="isFieldVisible('gender')">
            <label class="block text-body-sm font-medium text-neutral-500 mb-1">
              Gender
            </label>
            <select
              id="gender"
              v-model="form.gender"
              class="input-field"
            >
              <option value="">Select gender</option>
              <option value="male">Male</option>
              <option value="female">Female</option>
              <option value="other">Other</option>
            </select>
          </div>

          <!-- Marital Status -->
          <div v-if="isFieldVisible('marital_status')">
            <label class="block text-body-sm font-medium text-neutral-500 mb-1">
              Marital Status
            </label>
            <select
              id="marital_status"
              v-model="form.marital_status"
              class="input-field"
            >
              <option value="">Select status</option>
              <option value="single">Single</option>
              <option value="married">Married</option>
              <option value="divorced">Divorced</option>
              <option value="widowed">Widowed</option>
            </select>
          </div>

          <!-- Phone -->
          <div v-if="isFieldVisible('phone')">
            <label class="block text-body-sm font-medium text-neutral-500 mb-1">
              Phone Number
            </label>
            <input
              id="phone"
              v-model="form.phone"
              type="tel"
              placeholder="+44 or 0"
              class="input-field"
            />
          </div>

          <!-- Student-specific fields -->
          <!-- University -->
          <div v-if="isFieldVisible('university')">
            <label class="block text-body-sm font-medium text-neutral-500 mb-1">
              University
            </label>
            <input
              id="university"
              v-model="form.university"
              type="text"
              class="input-field"
              placeholder="e.g., University of Manchester"
            />
          </div>

          <!-- Student Number -->
          <div v-if="isFieldVisible('student_number')">
            <label class="block text-body-sm font-medium text-neutral-500 mb-1">
              Student Number
            </label>
            <input
              id="student_number"
              v-model="form.student_number"
              type="text"
              class="input-field"
              placeholder="Your student ID number"
            />
          </div>

          <!-- Education Level -->
          <div v-if="isFieldVisible('education_level')">
            <label class="block text-body-sm font-medium text-neutral-500 mb-1">
              Education Level
            </label>
            <select
              id="education_level"
              v-model="form.education_level"
              class="input-field"
            >
              <option value="">Select level</option>
              <option value="undergraduate">Undergraduate</option>
              <option value="postgraduate">Postgraduate</option>
              <option value="doctorate">Doctorate</option>
              <option value="foundation">Foundation Year</option>
              <option value="hnd">Higher National Diploma</option>
              <option value="other">Other</option>
            </select>
          </div>
        </div>

        <!-- Address Section -->
        <div v-if="isFieldVisible('address_line_1')" class="border-t border-light-gray pt-6">
          <h3 class="text-h5 font-semibold text-horizon-500 mb-4">Address</h3>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
            <!-- Address Line 1 -->
            <div class="sm:col-span-2">
              <label class="block text-body-sm font-medium text-neutral-500 mb-1">
                Address Line 1
              </label>
              <input
                id="address_line_1"
                v-model="form.address_line_1"
                type="text"
                class="input-field"
              />
            </div>

            <!-- Address Line 2 -->
            <div class="sm:col-span-2">
              <label class="block text-body-sm font-medium text-neutral-500 mb-1">
                Address Line 2
              </label>
              <input
                id="address_line_2"
                v-model="form.address_line_2"
                type="text"
                class="input-field"
              />
            </div>

            <!-- City -->
            <div>
              <label class="block text-body-sm font-medium text-neutral-500 mb-1">
                City
              </label>
              <input
                id="city"
                v-model="form.city"
                type="text"
                class="input-field"
              />
            </div>

            <!-- County -->
            <div>
              <label class="block text-body-sm font-medium text-neutral-500 mb-1">
                County
              </label>
              <input
                id="county"
                v-model="form.county"
                type="text"
                class="input-field"
              />
            </div>

            <!-- Postcode -->
            <div>
              <label class="block text-body-sm font-medium text-neutral-500 mb-1">
                Postcode
              </label>
              <input
                id="postcode"
                v-model="form.postcode"
                type="text"
                placeholder="SW1A 1AA"
                class="input-field uppercase"
              />
            </div>
          </div>
        </div>

        <!-- Occupation Section (hidden in onboarding — asked separately on Income step) -->
        <div v-if="context !== 'onboarding' && (isFieldVisible('occupation') || isFieldVisible('employment_status'))" class="border-t border-light-gray pt-6">
          <h3 class="text-h5 font-semibold text-horizon-500 mb-4">Occupation</h3>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
            <!-- Occupation -->
            <div v-if="isFieldVisible('occupation')">
              <label class="block text-body-sm font-medium text-neutral-500 mb-1">
                Job Title
              </label>
              <OccupationAutocomplete
                id="occupation"
                v-model="form.occupation"
                placeholder="Start typing your job title..."
              />
            </div>

            <!-- Employer -->
            <div v-if="isFieldVisible('employer')">
              <label class="block text-body-sm font-medium text-neutral-500 mb-1">
                Employer
              </label>
              <input
                id="employer"
                v-model="form.employer"
                type="text"
                class="input-field"
                placeholder="e.g., Tech Corp Ltd"
              />
            </div>

            <!-- Industry -->
            <div v-if="isFieldVisible('industry')">
              <label class="block text-body-sm font-medium text-neutral-500 mb-1">
                Industry
              </label>
              <input
                id="industry"
                v-model="form.industry"
                type="text"
                class="input-field"
                placeholder="e.g., Technology"
              />
            </div>

            <!-- Employment Status -->
            <div v-if="isFieldVisible('employment_status')">
              <label class="block text-body-sm font-medium text-neutral-500 mb-1">
                Employment Status
              </label>
              <select
                id="employment_status"
                v-model="form.employment_status"
                class="input-field"
              >
                <option value="">Select status</option>
                <option value="employed">Employed</option>
                <option value="part_time">Part-Time</option>
                <option value="self_employed">Self-Employed</option>
                <option value="student">Student</option>
                <option value="retired">Retired</option>
                <option value="unemployed">Unemployed</option>
                <option value="other">Other</option>
              </select>
            </div>

            <!-- Retirement Age (for non-retired) -->
            <div v-if="isFieldVisible('target_retirement_age') && form.employment_status && form.employment_status !== 'retired'">
              <label class="block text-body-sm font-medium text-neutral-500 mb-1">
                Retirement Age
              </label>
              <input
                id="target_retirement_age"
                v-model.number="form.target_retirement_age"
                type="number"
                min="55"
                max="75"
                class="input-field"
                placeholder="65"
              />
              <p class="mt-1 text-body-sm text-neutral-500">
                Planned retirement age, used for all pension forecast calculations.
              </p>
            </div>
          </div>
        </div>

        <!-- Domicile Section -->
        <div v-if="isFieldVisible('country_of_birth')" class="border-t border-light-gray pt-6">
          <h3 class="text-h5 font-semibold text-horizon-500 mb-4">Domicile Status</h3>
          <p class="text-body-sm text-neutral-500 mb-4">
            Your domicile status affects UK inheritance tax on your worldwide assets
          </p>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
            <!-- Country of Birth -->
            <div>
              <label class="block text-body-sm font-medium text-neutral-500 mb-1">
                Country of Birth
              </label>
              <CountrySelector
                v-model="form.country_of_birth"
                :required="true"
                placeholder="Search for your country of birth..."
                @update:model-value="handleCountryChange"
              />
            </div>

            <!-- UK Arrival Date (conditional - shown only for non-UK born) -->
            <div v-if="shouldShowUKArrivalDate">
              <label class="block text-body-sm font-medium text-neutral-500 mb-1">
                Date Moved to UK
              </label>
              <input
                id="uk_arrival_date"
                v-model="form.uk_arrival_date"
                type="date"
                class="input-field"
                :max="today"
                @change="calculateYearsResident"
              />
            </div>

            <!-- Domicile Status Display -->
            <div class="sm:col-span-2" v-if="form.country_of_birth">
              <div class="bg-eggshell-500 rounded-lg p-4">
                <p class="text-body-sm text-neutral-500">
                  <strong>Domicile Status:</strong> {{ domicileStatusLabel }}
                </p>
                <p v-if="yearsResident !== null" class="text-body-sm text-neutral-500 mt-1">
                  <strong>Years UK Resident:</strong> {{ yearsResident }} years
                </p>
                <p v-if="isDeemedDomiciled" class="mt-2 text-body-sm text-violet-700">
                  You are considered deemed domiciled in the UK because you have been resident for at least 15 of the last 20 tax years.
                </p>
                <p v-else-if="yearsResident !== null && yearsResident < 15" class="mt-2 text-body-sm text-violet-700">
                  You will become deemed domiciled after {{ 15 - yearsResident }} more year(s) of UK residence.
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Action Buttons: standalone mode -->
        <div v-if="context !== 'onboarding'" class="flex justify-end space-x-4 pt-6 border-t border-light-gray">
            <button
              type="button"
              @click="handleCancel"
              class="btn-secondary"
              :disabled="submitting"
            >
              Cancel
            </button>
            <button
              type="submit"
              class="btn-primary"
              :disabled="submitting"
            >
              <span v-if="!submitting">Save Changes</span>
              <span v-else>Saving...</span>
            </button>
        </div>
        <!-- Action Buttons: onboarding mode — Submit triggers @save which wizard handles -->
        <div v-else class="flex items-center justify-end gap-3 pt-6 border-t border-light-gray">
          <button
            type="button"
            class="text-body-sm text-neutral-500 hover:text-horizon-500 underline"
            @click="$emit('skip')"
          >
            Skip this step
          </button>
          <button
            type="submit"
            class="inline-flex items-center px-6 py-2.5 border border-transparent text-sm font-medium rounded-button text-white bg-raspberry-500 hover:bg-raspberry-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-violet-500 transition-colors"
            :disabled="submitting"
          >
            <span v-if="!submitting">Continue</span>
            <span v-else>Saving...</span>
            <svg v-if="!submitting" class="w-4 h-4 ml-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
          </button>
        </div>
        </div>
      </div>
    </form>
  </div>
</template>

<script>
import { ref, computed, watch, onBeforeUnmount } from 'vue';
import { useStore } from 'vuex';
import CountrySelector from '@/components/Shared/CountrySelector.vue';
import OccupationAutocomplete from '@/components/Shared/OccupationAutocomplete.vue';

import logger from '@/utils/logger';
// Preview mode message
const PREVIEW_SUCCESS_MESSAGE = 'Changes saved for this session only (preview mode).';

export default {
  name: 'PersonalInformation',

  components: {
    CountrySelector,
    OccupationAutocomplete,
  },

  props: {
    context: {
      type: String,
      default: 'standalone',
      validator: (value) => ['standalone', 'onboarding'].includes(value),
    },
    savedData: {
      type: Object,
      default: null,
    },
  },

  emits: ['save', 'skip', 'next', 'back', 'close'],

  setup(props, { emit }) {
    const store = useStore();
    const isEditing = ref(props.context === 'onboarding');
    const submitting = ref(false);
    const successMessage = ref('');
    const errorMessage = ref('');
    const yearsResident = ref(null);
    const originalEmploymentStatus = ref(null); // Track original status for change detection
    let messageTimeout = null;

    const profile = computed(() => store.getters['userProfile/profile']);
    const personalInfo = computed(() => store.getters['userProfile/personalInfo']);
    const incomeOccupation = computed(() => store.getters['userProfile/incomeOccupation']);
    const user = computed(() => store.getters['userProfile/user']);

    const today = computed(() => new Date().toISOString().split('T')[0]);

    // Date constraints: user must be 18-105 years old
    const maxDob = computed(() => {
      const date = new Date();
      date.setFullYear(date.getFullYear() - 18);
      return date.toISOString().split('T')[0];
    });

    const minDob = computed(() => {
      const date = new Date();
      date.setFullYear(date.getFullYear() - 105);
      return date.toISOString().split('T')[0];
    });

    // Life stage field visibility
    const isFieldVisible = (fieldName) => {
      return store.getters['lifeStage/isFieldVisible']('personalInfo', fieldName, props.context);
    };

    const form = ref({
      // Personal info
      first_name: '',
      surname: '',
      email: '',
      date_of_birth: '',
      gender: '',
      marital_status: '',
      phone: '',
      address_line_1: '',
      address_line_2: '',
      city: '',
      county: '',
      postcode: '',
      // Occupation
      occupation: '',
      employer: '',
      industry: '',
      employment_status: '',
      target_retirement_age: null,
      retirement_date: '',
      // Domicile
      country_of_birth: '',
      uk_arrival_date: '',
      domicile_status: 'uk_domiciled',
      // Student-specific fields
      university: '',
      student_number: '',
      education_level: '',
    });

    const shouldShowUKArrivalDate = computed(() => {
      return form.value.country_of_birth &&
             form.value.country_of_birth !== 'United Kingdom';
    });

    const isDeemedDomiciled = computed(() => {
      return yearsResident.value !== null && yearsResident.value >= 15;
    });

    const domicileStatusLabel = computed(() => {
      if (form.value.country_of_birth === 'United Kingdom') {
        return 'UK Domiciled';
      }
      if (isDeemedDomiciled.value) {
        return 'Deemed UK Domiciled';
      }
      return 'Non-UK Domiciled';
    });

    // Format date for HTML5 date input (yyyy-MM-dd)
    const formatDateForInput = (date) => {
      if (!date) return '';
      try {
        if (typeof date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(date)) {
          return date;
        }
        const dateObj = new Date(date);
        if (isNaN(dateObj.getTime())) return '';
        const year = dateObj.getFullYear();
        const month = String(dateObj.getMonth() + 1).padStart(2, '0');
        const day = String(dateObj.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
      } catch (e) {
        return '';
      }
    };

    // Format date for display (e.g., "15 January 2024")
    const formatDisplayDate = (date) => {
      if (!date) return '—';
      try {
        const dateObj = new Date(date);
        if (isNaN(dateObj.getTime())) return '—';
        return dateObj.toLocaleDateString('en-GB', {
          day: 'numeric',
          month: 'long',
          year: 'numeric',
        });
      } catch (e) {
        return '—';
      }
    };

    const formatEmploymentStatus = (status) => {
      if (!status) return '—';
      const statusMap = {
        'employed': 'Employed',
        'part_time': 'Part-Time',
        'self_employed': 'Self-Employed',
        'student': 'Student',
        'retired': 'Retired',
        'unemployed': 'Unemployed',
        'other': 'Other',
      };
      return statusMap[status] || status;
    };

    const calculateYearsResident = () => {
      if (!form.value.uk_arrival_date) {
        yearsResident.value = null;
        return;
      }

      const arrival = new Date(form.value.uk_arrival_date);
      const now = new Date();
      const years = Math.floor((now - arrival) / (365.25 * 24 * 60 * 60 * 1000));

      yearsResident.value = Math.max(0, years);

      // Auto-determine domicile status
      updateDomicileStatus();
    };

    const handleCountryChange = () => {
      if (form.value.country_of_birth === 'United Kingdom') {
        form.value.uk_arrival_date = '';
        yearsResident.value = null;
        form.value.domicile_status = 'uk_domiciled';
      } else {
        updateDomicileStatus();
      }
    };

    const updateDomicileStatus = () => {
      if (form.value.country_of_birth === 'United Kingdom') {
        form.value.domicile_status = 'uk_domiciled';
      } else if (yearsResident.value !== null && yearsResident.value >= 15) {
        form.value.domicile_status = 'uk_domiciled';
      } else {
        form.value.domicile_status = 'non_uk_domiciled';
      }
    };

    // Initialize form from data
    const initializeForm = () => {
      // Always try to get name/email from auth store (works for new users)
      const currentUser = store.getters['auth/currentUser'];
      if (currentUser && !form.value.first_name) {
        form.value.first_name = currentUser.first_name || '';
        form.value.surname = currentUser.last_name || currentUser.surname || '';
        form.value.email = form.value.email || currentUser.email || '';
      }

      if (personalInfo.value) {
        form.value.first_name = personalInfo.value.first_name || form.value.first_name || '';
        form.value.surname = personalInfo.value.surname || form.value.surname || '';
        form.value.email = personalInfo.value.email || form.value.email || '';
        form.value.date_of_birth = formatDateForInput(personalInfo.value.date_of_birth) || form.value.date_of_birth;
        form.value.gender = personalInfo.value.gender || form.value.gender || '';
        form.value.marital_status = personalInfo.value.marital_status || form.value.marital_status || '';
        form.value.phone = personalInfo.value.phone || form.value.phone || '';
        form.value.address_line_1 = personalInfo.value.address?.line_1 || form.value.address_line_1 || '';
        form.value.address_line_2 = personalInfo.value.address?.line_2 || form.value.address_line_2 || '';
        form.value.city = personalInfo.value.address?.city || form.value.city || '';
        form.value.county = personalInfo.value.address?.county || form.value.county || '';
        form.value.postcode = personalInfo.value.address?.postcode || form.value.postcode || '';
      }

      if (incomeOccupation.value) {
        form.value.occupation = incomeOccupation.value.occupation || '';
        form.value.employer = incomeOccupation.value.employer || '';
        form.value.industry = incomeOccupation.value.industry || '';
        form.value.employment_status = incomeOccupation.value.employment_status || '';
        form.value.target_retirement_age = incomeOccupation.value.target_retirement_age || null;
        form.value.retirement_date = incomeOccupation.value.retirement_date || '';
        // Store original employment status for change detection
        originalEmploymentStatus.value = incomeOccupation.value.employment_status || '';
      }

      if (user.value) {
        form.value.country_of_birth = user.value.country_of_birth || 'United Kingdom';
        form.value.uk_arrival_date = formatDateForInput(user.value.uk_arrival_date) || '';
        form.value.domicile_status = user.value.domicile_status || 'uk_domiciled';

        // Calculate years resident if uk_arrival_date exists
        if (form.value.uk_arrival_date) {
          calculateYearsResident();
        }
      }
    };

    // Watch for changes in data and reinitialize form
    watch([personalInfo, incomeOccupation, user], () => {
      initializeForm();
    }, { immediate: true });

    /**
     * Determine if employment status has changed in a way that requires income update.
     * Returns object with flags for what changed and what income to reset.
     */
    const detectEmploymentStatusChange = (oldStatus, newStatus) => {
      // Status groups that have distinct income types
      const employedStatuses = ['employed', 'part_time'];
      const selfEmployedStatuses = ['self_employed'];
      const retiredStatuses = ['retired'];

      const wasEmployed = employedStatuses.includes(oldStatus);
      const wasSelfEmployed = selfEmployedStatuses.includes(oldStatus);
      const wasRetired = retiredStatuses.includes(oldStatus);

      const isNowEmployed = employedStatuses.includes(newStatus);
      const isNowSelfEmployed = selfEmployedStatuses.includes(newStatus);
      const isNowRetired = retiredStatuses.includes(newStatus);

      // Detect significant status changes
      const changedFromEmployedToSelfEmployed = wasEmployed && isNowSelfEmployed;
      const changedFromSelfEmployedToEmployed = wasSelfEmployed && isNowEmployed;
      const changedToRetired = !wasRetired && isNowRetired;
      const changedFromRetired = wasRetired && !isNowRetired;

      return {
        hasSignificantChange: changedFromEmployedToSelfEmployed || changedFromSelfEmployedToEmployed || changedToRetired || changedFromRetired,
        resetEmploymentIncome: changedFromEmployedToSelfEmployed || changedToRetired,
        resetSelfEmploymentIncome: changedFromSelfEmployedToEmployed || changedToRetired,
        newStatus,
        previousStatus: oldStatus,
      };
    };

    const handleSubmit = async () => {
      // In onboarding context, emit form data instead of making API calls
      if (props.context === 'onboarding') {
        emit('save', { ...form.value });
        return;
      }

      submitting.value = true;
      successMessage.value = '';
      errorMessage.value = '';

      try {
        const personalData = {
          first_name: form.value.first_name || null,
          surname: form.value.surname || null,
          email: form.value.email,
          date_of_birth: form.value.date_of_birth || null,
          gender: form.value.gender || null,
          marital_status: form.value.marital_status || null,
          phone: form.value.phone || null,
          address_line_1: form.value.address_line_1 || null,
          address_line_2: form.value.address_line_2 || null,
          city: form.value.city || null,
          county: form.value.county || null,
          postcode: form.value.postcode || null,
        };

        // Detect employment status change
        const statusChange = detectEmploymentStatusChange(
          originalEmploymentStatus.value,
          form.value.employment_status
        );

        // Prepare income values - reset if status changed significantly
        let employmentIncome = incomeOccupation.value?.annual_employment_income || 0;
        let selfEmploymentIncome = incomeOccupation.value?.annual_self_employment_income || 0;

        if (statusChange.resetEmploymentIncome) {
          employmentIncome = 0;
        }
        if (statusChange.resetSelfEmploymentIncome) {
          selfEmploymentIncome = 0;
        }

        // Prepare occupation data
        const occupationData = {
          occupation: form.value.occupation || null,
          employer: form.value.employer || null,
          industry: form.value.industry || null,
          employment_status: form.value.employment_status || null,
          target_retirement_age: form.value.target_retirement_age || null,
          retirement_date: form.value.retirement_date || null,
          // Flag to indicate income needs updating if status changed
          income_needs_update: statusChange.hasSignificantChange,
          previous_employment_status: statusChange.hasSignificantChange ? statusChange.previousStatus : null,
        };

        // Prepare domicile data
        const domicileData = {
          country_of_birth: form.value.country_of_birth || null,
          uk_arrival_date: form.value.uk_arrival_date || null,
          domicile_status: form.value.domicile_status,
        };

        // Update all three in parallel
        await Promise.all([
          store.dispatch('userProfile/updatePersonalInfo', personalData),
          store.dispatch('userProfile/updateIncomeOccupation', {
            ...occupationData,
            // Use potentially reset income values
            annual_employment_income: employmentIncome,
            annual_self_employment_income: selfEmploymentIncome,
            annual_dividend_income: incomeOccupation.value?.annual_dividend_income || 0,
            annual_interest_income: incomeOccupation.value?.annual_interest_income || 0,
            annual_trust_income: incomeOccupation.value?.annual_trust_income || 0,
          }),
          store.dispatch('userProfile/updateDomicile', domicileData),
        ]);

        // Check if we're in preview mode
        const isPreviewMode = store.getters['preview/isPreviewMode'];

        // Build success message - include note about income update if status changed
        let message = isPreviewMode
          ? PREVIEW_SUCCESS_MESSAGE
          : 'Personal information updated successfully!';

        if (statusChange.hasSignificantChange) {
          message += ' Your employment status has changed - please update your income in the Valuable Info tab.';
        }

        successMessage.value = message;
        isEditing.value = false;

        // Update original status to reflect the new saved value
        originalEmploymentStatus.value = form.value.employment_status;

        // Clear success message after delay (longer if status changed)
        if (messageTimeout) clearTimeout(messageTimeout);
        messageTimeout = setTimeout(() => {
          successMessage.value = '';
        }, statusChange.hasSignificantChange ? 8000 : (isPreviewMode ? 5000 : 3000));
      } catch (error) {
        logger.error('Update error:', error);
        if (error.errors) {
          const errors = Object.values(error.errors).flat();
          errorMessage.value = errors.join('. ');
        } else {
          errorMessage.value = error.message || 'Failed to update personal information';
        }
      } finally {
        submitting.value = false;
      }
    };

    const handleCancel = () => {
      initializeForm();
      isEditing.value = false;
      errorMessage.value = '';
    };

    onBeforeUnmount(() => {
      if (messageTimeout) clearTimeout(messageTimeout);
    });

    const handleAddressSelected = (address) => {
      form.value.address_line_1 = address.line_1 || '';
      form.value.address_line_2 = address.line_2 || '';
      form.value.city = address.city || '';
      form.value.county = address.county || '';
      form.value.postcode = address.postcode || '';
    };

    return {
      form,
      isEditing,
      submitting,
      successMessage,
      errorMessage,
      maxDob,
      minDob,
      today,
      yearsResident,
      shouldShowUKArrivalDate,
      isDeemedDomiciled,
      domicileStatusLabel,
      handleSubmit,
      handleCancel,
      handleAddressSelected,
      handleCountryChange,
      calculateYearsResident,
      formatDisplayDate,
      formatEmploymentStatus,
      isFieldVisible,
      context: props.context,
    };
  },
};
</script>
