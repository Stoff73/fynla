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
      <!-- Success Message -->
      <div v-if="successMessage" class="bg-spring-50 border border-spring-200 rounded-lg p-4">
        <div class="flex">
          <div class="flex-shrink-0">
            <svg class="h-5 w-5 text-spring-400" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
          </div>
          <div class="ml-3">
            <p class="text-sm text-spring-700">{{ successMessage }}</p>
          </div>
        </div>
      </div>

      <!-- Family Members List -->
      <div v-if="familyMembers.length > 0" class="space-y-3">
        <h4 class="text-body font-medium text-horizon-500">
          Family Members ({{ familyMembers.length }})
        </h4>

        <div
          v-for="member in familyMembers"
          :key="member.id"
          class="border border-light-gray rounded-lg p-4 bg-eggshell-500"
        >
          <div class="flex justify-between items-start">
            <div class="flex-1">
              <div class="flex items-center gap-2 mb-2">
                <h5 class="text-body font-medium text-horizon-500">{{ member.name }}</h5>
                <span class="text-body-sm px-2 py-0.5 bg-violet-100 text-violet-700 rounded capitalize">
                  {{ formatRelationship(member.relationship) }}
                </span>
                <!-- Linked Account Indicator for Spouse -->
                <span v-if="member.relationship === 'spouse' && member.email" class="inline-flex items-center gap-1 text-body-sm px-2 py-0.5 bg-spring-100 text-spring-700 rounded" title="Account Linked">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                  </svg>
                  Linked
                </span>
              </div>
              <p v-if="member.date_of_birth" class="text-body-sm text-neutral-500">
                Age: {{ calculateAge(member.date_of_birth) }} years
              </p>
              <p v-if="member.is_dependent" class="text-body-sm text-neutral-500">
                <span class="text-violet-600">● Financially dependent</span>
              </p>
            </div>
            <div v-if="member.relationship !== 'spouse'" class="flex gap-2 ml-4">
              <button
                type="button"
                class="text-raspberry-500 hover:text-raspberry-700 text-body-sm"
                @click="editMember(member)"
              >
                Edit
              </button>
              <button
                type="button"
                class="text-raspberry-500 hover:text-raspberry-700 text-body-sm"
                @click="deleteMember(member.id)"
              >
                Delete
              </button>
            </div>
            <div v-else class="ml-4">
              <p class="text-body-xs text-neutral-500 italic max-w-[180px] text-right">
                Linked account — edit or delete by logging into the spouse's account
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Add Family Member Button (hidden when inline form is open) -->
      <button
        v-if="!showModal"
        type="button"
        class="inline-flex items-center px-5 py-2.5 bg-horizon-500 hover:bg-horizon-600 text-white text-sm font-medium rounded-lg transition-colors w-full md:w-auto justify-center"
        @click="showAddModal"
      >
        + Add Family Member
      </button>

      <!-- Family Member Form (inline, no modal overlay) -->
      <div v-if="showModal" class="border border-light-gray rounded-lg p-4 bg-white">
        <FamilyMemberFormModal
          :member="selectedMember"
          context="onboarding"
          @save="handleSave"
          @close="closeModal"
        />
      </div>

    </div>

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
// DEPRECATED: Will be replaced by unified form with context="onboarding". See life-stage-journey-design.md §11.7
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { useStore } from 'vuex';
import OnboardingStep from '../OnboardingStep.vue';
import UsefulResources from '../UsefulResources.vue';
import { STEP_RESOURCES } from '@/constants/onboardingLinks';
import FamilyMemberFormModal from '@/components/UserProfile/FamilyMemberFormModal.vue';
import SpouseSuccessModal from '@/components/Shared/SpouseSuccessModal.vue';
import familyMembersService from '@/services/familyMembersService';

import logger from '@/utils/logger';
export default {
  name: 'FamilyInfoStep',

  components: {
    OnboardingStep,
    UsefulResources,
    FamilyMemberFormModal,
    SpouseSuccessModal,
  },

  emits: ['next', 'back', 'skip', 'sidebar-update'],

  setup(props, { emit }) {
    const store = useStore();

    const familyMembers = ref([]);
    const showModal = ref(false);
    const selectedMember = ref(null);
    const successMessage = ref('');
    const showSpouseSuccess = ref(false);
    const spouseCreated = ref(false);
    const spouseEmail = ref(null);
    const temporaryPassword = ref(null);

    const loading = ref(false);
    const error = ref(null);
    let successTimeout = null;
    let errorTimeout = null;

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
        logger.error('Failed to load family members:', err);
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
          // Note: response is already the API body (service unwraps axios response)
          const responseData = response?.data || response;
          const isSpouse = formData.relationship === 'spouse';

          if (isSpouse && responseData) {
            if (responseData.created) {
              // Show spouse success modal with credentials
              spouseCreated.value = true;
              spouseEmail.value = responseData.spouse_email || formData.email;
              temporaryPassword.value = responseData.temporary_password || null;
              showSpouseSuccess.value = true;
              // Refresh user data to reflect spouse linkage
              await store.dispatch('auth/fetchUser');
            } else if (responseData.linked) {
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
          if (successTimeout) clearTimeout(successTimeout);
          successTimeout = setTimeout(() => {
            successMessage.value = '';
          }, 5000);
        }
      } catch (err) {
        logger.error('Failed to save family member:', err);
        const errorMsg = err.response?.data?.message || err.message || 'Unknown error';
        error.value = `Failed to save family member: ${errorMsg}`;
        closeModal();

        // Clear error after 8 seconds
        if (errorTimeout) clearTimeout(errorTimeout);
        errorTimeout = setTimeout(() => {
          error.value = null;
        }, 8000);
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
          if (successTimeout) clearTimeout(successTimeout);
          successTimeout = setTimeout(() => {
            successMessage.value = '';
          }, 3000);
        } catch (err) {
          logger.error('Failed to delete family member:', err);
          error.value = 'Failed to delete family member.';
        }
      }
    };

    const handleNext = () => {
      emit('next');
    };

    const handleBack = () => {
      emit('back');
    };

    const handleSkip = () => {
      emit('skip');
    };

    onBeforeUnmount(() => {
      if (successTimeout) clearTimeout(successTimeout);
      if (errorTimeout) clearTimeout(errorTimeout);
    });

    onMounted(async () => {
      // Ensure we have latest user data from backend
      try {
        await store.dispatch('auth/fetchUser');
      } catch (err) {
        logger.error('Failed to fetch current user:', err);
      }

      await loadFamilyMembers();
    });

    return {
      familyMembers,
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
      STEP_RESOURCES,
    };
  },
};
</script>
