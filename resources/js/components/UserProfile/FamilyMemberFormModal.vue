<template>
  <!-- Onboarding: inline form, no modal. Regular: full modal wrapper. -->
  <div :class="context === 'onboarding' ? '' : 'fixed z-10 inset-0 overflow-y-auto'" :aria-labelledby="context === 'onboarding' ? undefined : 'modal-title'" :role="context === 'onboarding' ? undefined : 'dialog'" :aria-modal="context === 'onboarding' ? undefined : 'true'">
    <div :class="context === 'onboarding' ? '' : 'flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0'">
      <!-- Background overlay (modal only) -->
      <div v-if="context !== 'onboarding'" class="fixed inset-0 bg-neutral-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>

      <!-- Centre modal -->
      <span v-if="context !== 'onboarding'" class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

      <div :class="context === 'onboarding' ? '' : 'inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full sm:p-6 max-h-[90vh] overflow-y-auto'">
        <div>
          <div class="mb-4">
            <h3 class="text-h4 font-semibold text-horizon-500" id="modal-title">
              {{ isEditing ? 'Edit Family Member' : 'Add Family Member' }}
            </h3>
          </div>

          <form @submit.prevent="handleSubmit" class="space-y-4">
            <!-- Relationship -->
            <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'relationship' }">
              <label for="relationship" class="block text-body-sm font-medium text-neutral-500 mb-1">
                Relationship <span class="text-raspberry-500">*</span>
              </label>
              <select
                id="relationship"
                v-model="form.relationship"
                class="input-field"
              >
                <option value="">Select relationship</option>
                <option value="spouse">Spouse</option>
                <option value="partner">Partner</option>
                <option value="child">Child</option>
                <option value="step_child">Step Child</option>
                <option value="parent">Parent</option>
                <option value="other_dependent">Other Dependent</option>
              </select>
              <p v-if="form.relationship === 'spouse'" class="mt-1 text-body-xs text-raspberry-500">
                A user account will be created for your spouse if they don't have one yet. If they already have an account, it will be linked.
              </p>
              <p v-if="form.relationship === 'spouse'" class="mt-1 text-body-xs text-blue-600">
                Please ensure details are correct — once added, this linked account can only be edited or deleted by logging into the spouse's account.
              </p>
              <p v-if="form.relationship === 'partner'" class="mt-1 text-body-xs text-violet-600">
                A partner is not a legally recognised relationship for UK tax purposes. Unmarried partners cannot share tax allowances, transfer the nil rate band for Inheritance Tax, or benefit from the spouse exemption. Consider seeking legal advice about your financial arrangements.
              </p>
            </div>

            <!-- Email (for spouse or partner — account will be created/linked) -->
            <div v-if="form.relationship === 'spouse' || form.relationship === 'partner'">
              <label for="email" class="block text-body-sm font-medium text-neutral-500 mb-1">
                Email Address <span class="text-raspberry-500">*</span>
              </label>
              <input
                id="email"
                v-model="form.email"
                type="email"
                class="input-field"
                placeholder="spouse@example.com"
              />
              <p class="mt-1 text-body-xs text-neutral-500">
                Used to create or link their account
              </p>
            </div>

            <!-- Name Fields -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
              <!-- First Name -->
              <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'first_name' }">
                <label for="first_name" class="block text-body-sm font-medium text-neutral-500 mb-1">
                  First Name <span class="text-raspberry-500">*</span>
                </label>
                <input
                  id="first_name"
                  v-model="form.first_name"
                  type="text"
                  class="input-field"
                />
              </div>

              <!-- Middle Name -->
              <div>
                <label for="middle_name" class="block text-body-sm font-medium text-neutral-500 mb-1">
                  Middle Name
                </label>
                <input
                  id="middle_name"
                  v-model="form.middle_name"
                  type="text"
                  class="input-field"
                />
              </div>

              <!-- Last Name -->
              <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'last_name' }">
                <label for="last_name" class="block text-body-sm font-medium text-neutral-500 mb-1">
                  Last Name <span class="text-raspberry-500">*</span>
                </label>
                <input
                  id="last_name"
                  v-model="form.last_name"
                  type="text"
                  class="input-field"
                />
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <!-- Date of Birth -->
              <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'date_of_birth' }">
                <label for="date_of_birth" class="block text-body-sm font-medium text-neutral-500 mb-1">
                  Date of Birth
                </label>
                <input
                  id="date_of_birth"
                  v-model="form.date_of_birth"
                  type="date"
                  class="input-field"
                  :max="maxDobForRelationship"
                  :min="minDob"
                />
              </div>

              <!-- Gender -->
              <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'gender' }">
                <label for="gender" class="block text-body-sm font-medium text-neutral-500 mb-1">
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
                  <option value="prefer_not_to_say">Prefer not to say</option>
                </select>
              </div>
            </div>

            <!-- Is Dependent -->
            <div class="flex items-start">
              <div class="flex items-center h-5">
                <input
                  id="is_dependent"
                  v-model="form.is_dependent"
                  type="checkbox"
                  class="form-checkbox"
                />
              </div>
              <div class="ml-3 text-sm">
                <label for="is_dependent" class="font-medium text-neutral-500">
                  Is this person financially dependent on you?
                </label>
                <p class="text-neutral-500">
                  Check this if they rely on you for financial support
                </p>
              </div>
            </div>

            <!-- Education Status (if child or step_child) -->
            <div v-if="['child', 'step_child'].includes(form.relationship)">
              <label for="education_status" class="block text-body-sm font-medium text-neutral-500 mb-1">
                Education Status
              </label>
              <select
                id="education_status"
                v-model="form.education_status"
                class="input-field"
              >
                <option value="">Select status</option>
                <option value="pre_school">Pre-School/Nursery</option>
                <option value="primary">Primary</option>
                <option value="secondary">Secondary</option>
                <option value="further_education">Further Education (Sixth Form/College)</option>
                <option value="higher_education">Higher Education (University)</option>
                <option value="graduated">Graduated</option>
                <option value="not_applicable">Not in Education</option>
              </select>
            </div>

            <!-- Child Benefit (for children) -->
            <div v-if="['child', 'step_child'].includes(form.relationship)" class="flex items-start">
              <div class="flex items-center h-5">
                <input
                  id="receives_child_benefit"
                  v-model="form.receives_child_benefit"
                  type="checkbox"
                  class="form-checkbox"
                />
              </div>
              <div class="ml-3 text-sm">
                <label for="receives_child_benefit" class="font-medium text-neutral-500">
                  Receives Child Benefit
                </label>
                <p class="text-neutral-500">
                  Check if you claim Child Benefit for this child
                </p>
              </div>
            </div>

            <!-- Notes -->
            <div>
              <label for="notes" class="block text-body-sm font-medium text-neutral-500 mb-1">
                Notes
              </label>
              <textarea
                id="notes"
                v-model="form.notes"
                rows="3"
                class="form-textarea"
                placeholder="Any additional information..."
              ></textarea>
            </div>

            <!-- Error Message -->
            <div v-if="errorMessage" class="rounded-md bg-raspberry-50 p-4">
              <div class="flex">
                <div class="ml-3">
                  <h3 class="text-body-sm font-medium text-raspberry-800">Error</h3>
                  <div class="mt-2 text-body-sm text-raspberry-700">
                    <p>{{ errorMessage }}</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Action Buttons -->
            <div :class="context === 'onboarding' ? 'mt-5 flex items-center justify-center gap-3' : 'mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense'">
              <button
                v-if="context === 'onboarding'"
                type="button"
                @click="handleClose"
                :disabled="submitting"
                class="inline-flex items-center px-5 py-2.5 bg-light-pink-100 hover:bg-light-pink-200 text-horizon-500 text-sm font-medium rounded-lg transition-colors"
              >
                Cancel
              </button>
              <button
                type="submit"
                :disabled="submitting"
                :class="context === 'onboarding' ? 'inline-flex items-center px-5 py-2.5 bg-raspberry-500 hover:bg-raspberry-600 text-white text-sm font-medium rounded-lg transition-colors' : 'btn-primary w-full sm:col-start-2'"
              >
                <span v-if="!submitting">{{ context === 'onboarding' ? 'Save' : (isEditing ? 'Update' : 'Add') + ' Family Member' }}</span>
                <span v-else>{{ isEditing ? 'Updating...' : 'Saving...' }}</span>
              </button>
              <button
                v-if="context !== 'onboarding'"
                type="button"
                @click="handleClose"
                :disabled="submitting"
                class="btn-secondary w-full mt-3 sm:mt-0 sm:col-start-1"
              >
                Cancel
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, watch, nextTick } from 'vue';
import { useStore } from 'vuex';

export default {
  name: 'FamilyMemberFormModal',

  props: {
    member: {
      type: Object,
      default: null,
    },
    context: {
      type: String,
      default: 'standalone',
      validator: (value) => ['standalone', 'onboarding'].includes(value),
    },
  },

  emits: ['save', 'close'],

  setup(props, { emit }) {
    const store = useStore();
    const submitting = ref(false);
    const errorMessage = ref('');

    const isEditing = computed(() => !!props.member);

    // User's marital status determines whether "Spouse" option is available
    const userMaritalStatus = computed(() => {
      return store.getters['auth/user']?.marital_status || null;
    });

    const isMarriedOrCivilPartnership = computed(() => {
      return userMaritalStatus.value === 'married';
    });

    // AI Form Fill state
    const pendingFill = computed(() => store.state.aiFormFill?.pendingFill);
    const highlightedField = computed(() => store.state.aiFormFill?.highlightedField);
    const filling = computed(() => store.state.aiFormFill?.filling);

    // Date constraints - both min and max depend on relationship
    const minDob = computed(() => {
      const date = new Date();

      if (form.value.relationship === 'child' || form.value.relationship === 'step_child') {
        // Child: oldest allowed is 18 or 22 years ago depending on education
        const educationStatuses = ['pre_school', 'primary', 'secondary', 'further_education', 'higher_education'];
        const isInEducation = educationStatuses.includes(form.value.education_status);
        const maxAge = isInEducation ? 22 : 18;
        date.setFullYear(date.getFullYear() - maxAge);
        return date.toISOString().split('T')[0];
      }

      // All others: max age 105 years
      date.setFullYear(date.getFullYear() - 105);
      return date.toISOString().split('T')[0];
    });

    const maxDobForRelationship = computed(() => {
      const today = new Date();

      if (form.value.relationship === 'spouse') {
        // Spouse must be 16+ (born at least 16 years ago)
        const date = new Date();
        date.setFullYear(date.getFullYear() - 16);
        return date.toISOString().split('T')[0];
      }

      // Children and others: can be born up to today
      return today.toISOString().split('T')[0];
    });

    const form = ref({
      relationship: '',
      email: '',
      first_name: '',
      middle_name: '',
      last_name: '',
      date_of_birth: '',
      gender: '',
      is_dependent: false,
      education_status: '',
      receives_child_benefit: false,
      notes: '',
    });

    // Helper function to format date to yyyy-MM-dd
    const formatDateForInput = (dateString) => {
      if (!dateString) return '';
      try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return '';
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
      } catch (e) {
        return '';
      }
    };

    // Initialize form when member prop changes
    watch(() => props.member, (member) => {
      if (member) {
        form.value = {
          relationship: member.relationship || '',
          email: member.email || '',
          first_name: member.first_name || '',
          middle_name: member.middle_name || '',
          last_name: member.last_name || '',
          date_of_birth: formatDateForInput(member.date_of_birth),
          gender: member.gender || '',
          is_dependent: member.is_dependent || false,
          education_status: member.education_status || '',
          receives_child_benefit: member.receives_child_benefit || false,
          notes: member.notes || '',
        };
      } else {
        // Reset form for new member
        form.value = {
          relationship: '',
          email: '',
          first_name: '',
          middle_name: '',
          last_name: '',
          date_of_birth: '',
          gender: '',
          is_dependent: false,
          education_status: '',
          receives_child_benefit: false,
          notes: '',
        };
      }
    }, { immediate: true });

    // Validate date of birth based on relationship
    const validateDob = () => {
      if (!form.value.date_of_birth) return true;

      const dob = new Date(form.value.date_of_birth);
      const today = new Date();
      const age = Math.floor((today - dob) / (365.25 * 24 * 60 * 60 * 1000));

      if (dob > today) {
        errorMessage.value = 'Date of birth cannot be in the future.';
        return false;
      }

      if (age > 105) {
        errorMessage.value = 'Date of birth cannot be more than 105 years ago.';
        return false;
      }

      if (form.value.relationship === 'spouse' && age < 16) {
        errorMessage.value = 'Please enter a valid date of birth. Spouse must be at least 16 years old.';
        return false;
      }

      if (['child', 'step_child'].includes(form.value.relationship)) {
        const educationStatuses = ['pre_school', 'primary', 'secondary', 'further_education', 'higher_education'];
        const isInEducation = educationStatuses.includes(form.value.education_status);
        const maxAge = isInEducation ? 22 : 18;

        if (age > maxAge) {
          errorMessage.value = isInEducation
            ? 'Child in education must be 22 years old or younger.'
            : 'Child not in education must be 18 years old or younger.';
          return false;
        }
      }

      return true;
    };

    const handleSubmit = async () => {
      submitting.value = true;
      errorMessage.value = '';

      // Validate DOB before submitting
      if (!validateDob()) {
        submitting.value = false;
        return;
      }

      try {
        // Clean up form data - remove empty strings
        const formData = { ...form.value };

        // Construct full name from parts for backward compatibility
        const nameParts = [
          formData.first_name,
          formData.middle_name,
          formData.last_name
        ].filter(part => part && part.trim() !== '');

        formData.name = nameParts.join(' ');

        Object.keys(formData).forEach(key => {
          if (formData[key] === '' || formData[key] === null) {
            delete formData[key];
          }
        });

        emit('save', formData);
      } catch (error) {
        errorMessage.value = error.response?.data?.message || 'Failed to save family member';
        submitting.value = false;
      }
    };

    // AI Form Fill watchers
    watch(pendingFill, async (fill) => {
      if (fill && fill.entityType === 'family_member' && fill.fields) {
        // Pre-set relationship before field sequence (controls conditional fields)
        if (fill.fields.relationship) {
          form.value.relationship = fill.fields.relationship;
        }
        if (fill.fields.first_name) {
          form.value.first_name = fill.fields.first_name;
        }
        if (fill.fields.last_name) {
          form.value.last_name = fill.fields.last_name;
        }
        await nextTick();
        const fieldOrder = Object.keys(fill.fields).filter(k => fill.fields[k] !== null && fill.fields[k] !== '');
        store.dispatch('aiFormFill/beginFieldSequence', fieldOrder);
      }
    }, { immediate: true });

    watch(highlightedField, (fieldKey) => {
      if (fieldKey && pendingFill.value?.fields) {
        const value = pendingFill.value.fields[fieldKey];
        if (value !== undefined) {
          form.value[fieldKey] = value;
        }
      }
    });

    watch(filling, (isFilling) => {
      if (!isFilling && pendingFill.value) {
        setTimeout(() => {
          handleSubmit();
        }, 250);
      }
    });

    const handleClose = () => {
      if (pendingFill.value) {
        store.dispatch('aiFormFill/cancelFill');
      }
      emit('close');
    };

    return {
      form,
      isEditing,
      isMarriedOrCivilPartnership,
      submitting,
      errorMessage,
      minDob,
      maxDobForRelationship,
      handleSubmit,
      handleClose,
      pendingFill,
      highlightedField,
      filling,
    };
  },
};
</script>
