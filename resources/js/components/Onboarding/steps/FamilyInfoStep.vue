<template>
  <OnboardingStep
    title="Family & Dependents"
    description="Add details about your family members and dependents"
    :can-go-back="true"
    :can-skip="true"
    :loading="loading"
    :error="error"
    @next="handleNext"
    @back="handleBack"
    @skip="handleSkip"
  >
    <div class="space-y-6">
      <p class="text-body-sm text-gray-600 bg-gray-50 p-3 rounded-lg">
        This information helps us understand your family structure for estate planning and protection needs analysis.
      </p>

      <!-- Success Message -->
      <div v-if="successMessage" class="bg-green-50 border border-green-200 rounded-lg p-4">
        <div class="flex">
          <div class="flex-shrink-0">
            <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
          </div>
          <div class="ml-3">
            <p class="text-sm text-green-700">{{ successMessage }}</p>
          </div>
        </div>
      </div>

      <!-- Family Members List -->
      <div v-if="familyMembers.length > 0" class="space-y-3">
        <h4 class="text-body font-medium text-gray-900">
          Family Members ({{ familyMembers.length }})
        </h4>

        <div
          v-for="member in familyMembers"
          :key="member.id"
          class="border border-gray-200 rounded-lg p-4 bg-gray-50"
        >
          <div class="flex justify-between items-start">
            <div class="flex-1">
              <div class="flex items-center gap-2 mb-2">
                <h5 class="text-body font-medium text-gray-900">{{ member.name }}</h5>
                <span class="text-body-sm px-2 py-0.5 bg-blue-100 text-blue-700 rounded capitalize">
                  {{ formatRelationship(member.relationship) }}
                </span>
                <!-- Linked Account Indicator for Spouse -->
                <span v-if="member.relationship === 'spouse' && member.email" class="inline-flex items-center gap-1 text-body-sm px-2 py-0.5 bg-green-100 text-green-700 rounded" title="Account Linked">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                  </svg>
                  Linked
                </span>
              </div>
              <p v-if="member.date_of_birth" class="text-body-sm text-gray-600">
                Age: {{ calculateAge(member.date_of_birth) }} years
              </p>
              <p v-if="member.is_dependent" class="text-body-sm text-gray-600">
                <span class="text-orange-600">● Financially dependent</span>
              </p>
            </div>
            <div class="flex gap-2 ml-4">
              <button
                type="button"
                class="text-primary-600 hover:text-primary-700 text-body-sm"
                @click="editMember(member)"
              >
                Edit
              </button>
              <button
                type="button"
                class="text-red-600 hover:text-red-700 text-body-sm"
                @click="deleteMember(member.id)"
              >
                Delete
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Add Family Member Button -->
      <button
        type="button"
        class="btn-secondary w-full md:w-auto"
        @click="showAddModal"
      >
        + Add Family Member
      </button>

      <!-- Charitable Bequest -->
      <div class="border-t pt-6">
        <label class="label">
          Do you wish to leave anything to charity?
        </label>
        <div class="mt-2 space-x-4">
          <label class="inline-flex items-center">
            <input
              v-model="charitableBequest"
              type="radio"
              :value="true"
              class="form-radio text-primary-600"
            >
            <span class="ml-2 text-body text-gray-700">Yes</span>
          </label>
          <label class="inline-flex items-center">
            <input
              v-model="charitableBequest"
              type="radio"
              :value="false"
              class="form-radio text-primary-600"
            >
            <span class="ml-2 text-body text-gray-700">No</span>
          </label>
        </div>
        <p class="mt-1 text-body-sm text-gray-500">
          Leaving 10% or more to charity can reduce your IHT rate from 40% to 36%
        </p>
      </div>
    </div>

    <!-- Family Member Form Modal -->
    <FamilyMemberFormModal
      v-if="showModal"
      :member="selectedMember"
      @save="handleSave"
      @close="closeModal"
    />

    <!-- Spouse Success Modal -->
    <SpouseSuccessModal
      :show="showSpouseSuccess"
      :is-created="spouseCreated"
      :spouse-email="spouseEmail"
      :temporary-password="temporaryPassword"
      @close="closeSpouseSuccess"
    />
  </OnboardingStep>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import { useStore } from 'vuex';
import OnboardingStep from '../OnboardingStep.vue';
import FamilyMemberFormModal from '@/components/UserProfile/FamilyMemberFormModal.vue';
import SpouseSuccessModal from '@/components/Shared/SpouseSuccessModal.vue';
import familyMembersService from '@/services/familyMembersService';

export default {
  name: 'FamilyInfoStep',

  components: {
    OnboardingStep,
    FamilyMemberFormModal,
    SpouseSuccessModal,
  },

  emits: ['next', 'back', 'skip'],

  setup(props, { emit }) {
    const store = useStore();

    const familyMembers = ref([]);
    const charitableBequest = ref(null);
    const showModal = ref(false);
    const selectedMember = ref(null);
    const successMessage = ref('');
    const showSpouseSuccess = ref(false);
    const spouseCreated = ref(false);
    const spouseEmail = ref(null);
    const temporaryPassword = ref(null);

    const loading = ref(false);
    const error = ref(null);

    const calculateAge = (dateOfBirth) => {
      if (!dateOfBirth) return 0;
      const today = new Date();
      const birthDate = new Date(dateOfBirth);
      let age = today.getFullYear() - birthDate.getFullYear();
      const monthDiff = today.getMonth() - birthDate.getMonth();
      if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
        age--;
      }
      return age;
    };

    const formatRelationship = (relationship) => {
      const map = {
        'spouse': 'Spouse',
        'child': 'Child',
        'step_child': 'Step Child',
        'parent': 'Parent',
        'other_dependent': 'Other Dependent',
      };
      return map[relationship] || relationship;
    };

    const loadFamilyMembers = async () => {
      try {
        const response = await familyMembersService.getFamilyMembers();
        familyMembers.value = response.data?.family_members || [];
      } catch (err) {
        console.error('Failed to load family members:', err);
      }
    };

    const showAddModal = () => {
      selectedMember.value = null;
      showModal.value = true;
    };

    const editMember = (member) => {
      selectedMember.value = member;
      showModal.value = true;
    };

    const closeModal = () => {
      showModal.value = false;
      selectedMember.value = null;
    };

    const handleSave = async (formData) => {
      try {
        if (selectedMember.value) {
          // Update existing member
          await familyMembersService.updateFamilyMember(selectedMember.value.id, formData);
          successMessage.value = 'Family member updated successfully!';
        } else {
          // Add new member
          const response = await familyMembersService.createFamilyMember(formData);

          // Check if spouse account was created or linked
          if (formData.relationship === 'spouse' && response.data) {
            if (response.data.created) {
              // Show spouse success modal with credentials
              spouseCreated.value = true;
              spouseEmail.value = response.data.spouse_email;
              temporaryPassword.value = response.data.temporary_password;
              showSpouseSuccess.value = true;
              // Refresh user data to reflect spouse linkage
              await store.dispatch('auth/fetchUser');
            } else if (response.data.linked) {
              // Show spouse success modal for linking
              spouseCreated.value = false;
              spouseEmail.value = formData.email;
              temporaryPassword.value = null;
              showSpouseSuccess.value = true;
              // Refresh user data to reflect spouse linkage
              await store.dispatch('auth/fetchUser');
            } else {
              successMessage.value = 'Family member added successfully!';
            }
          } else {
            successMessage.value = 'Family member added successfully!';
          }
        }

        closeModal();
        await loadFamilyMembers();

        // Clear success message after 5 seconds
        if (successMessage.value) {
          setTimeout(() => {
            successMessage.value = '';
          }, 5000);
        }
      } catch (err) {
        console.error('Failed to save family member:', err);
        const errorMsg = err.response?.data?.message || err.message || 'Unknown error';
        error.value = `Failed to save family member: ${errorMsg}`;
      }
    };

    const closeSpouseSuccess = () => {
      showSpouseSuccess.value = false;
      spouseCreated.value = false;
      spouseEmail.value = null;
      temporaryPassword.value = null;
    };

    const deleteMember = async (id) => {
      if (confirm('Are you sure you want to delete this family member?')) {
        try {
          await familyMembersService.deleteFamilyMember(id);
          await loadFamilyMembers();
          successMessage.value = 'Family member deleted successfully!';
          setTimeout(() => {
            successMessage.value = '';
          }, 3000);
        } catch (err) {
          console.error('Failed to delete family member:', err);
          error.value = 'Failed to delete family member.';
        }
      }
    };

    const handleNext = async () => {
      loading.value = true;
      error.value = null;

      try {
        // Save charitable bequest preference
        await store.dispatch('onboarding/saveStepData', {
          stepName: 'family_info',
          data: {
            charitable_bequest: charitableBequest.value,
          },
        });

        // Refresh user data to ensure it's available when navigating back
        await store.dispatch('auth/fetchUser');

        emit('next');
      } catch (err) {
        error.value = 'Failed to save family information. Please try again.';
      } finally {
        loading.value = false;
      }
    };

    const handleBack = () => {
      emit('back');
    };

    const handleSkip = () => {
      emit('skip');
    };

    onMounted(async () => {
      // Ensure we have latest user data from backend
      try {
        await store.dispatch('auth/fetchUser');
      } catch (err) {
        console.error('Failed to fetch current user:', err);
      }

      await loadFamilyMembers();

      // Load existing charitable bequest value from user profile
      const user = store.state.auth.user;
      if (user && user.charitable_bequest !== undefined) {
        charitableBequest.value = user.charitable_bequest;
      }
    });

    return {
      familyMembers,
      charitableBequest,
      showModal,
      selectedMember,
      successMessage,
      showSpouseSuccess,
      spouseCreated,
      spouseEmail,
      temporaryPassword,
      loading,
      error,
      calculateAge,
      formatRelationship,
      showAddModal,
      editMember,
      closeModal,
      handleSave,
      closeSpouseSuccess,
      deleteMember,
      handleNext,
      handleBack,
      handleSkip,
    };
  },
};
</script>
