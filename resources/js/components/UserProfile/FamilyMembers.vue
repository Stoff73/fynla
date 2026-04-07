<template>
  <div class="space-y-6">
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
    <div v-if="errorMessage" class="rounded-md bg-raspberry-50 border border-raspberry-200 p-4 mb-6">
      <div class="flex">
        <div class="flex-shrink-0">
          <svg class="h-5 w-5 text-raspberry-400" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
          </svg>
        </div>
        <div class="ml-3">
          <p class="text-body-sm font-medium text-raspberry-800">
            {{ errorMessage }}
          </p>
        </div>
      </div>
    </div>

    <!-- Family Members Card -->
    <div class="bg-white rounded-lg border border-light-gray p-6">
      <div class="flex justify-between items-start mb-6">
        <div>
          <h3 class="text-h4 font-semibold text-horizon-500">Family Members</h3>
          <p class="mt-1 text-body-sm text-neutral-500">
            Manage your family members and dependents
          </p>
        </div>
        <button
          v-preview-disabled="'add'"
          @click="openAddModal"
          class="btn-secondary flex-shrink-0"
        >
          Add
        </button>
      </div>

      <!-- Family Members List -->
      <div v-if="familyMembers.length > 0" class="space-y-4">
      <div
        v-for="member in familyMembers"
        :key="member.id"
        class="card p-4"
      >
        <!-- Header: Name, badges, and actions -->
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-3">
          <div>
            <h3 class="text-h5 font-semibold text-horizon-500">{{ member.name }}</h3>
            <div class="flex flex-wrap items-center gap-2 mt-1">
              <span
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize"
                :class="getRelationshipBadgeClass(member.relationship)"
              >
                {{ formatRelationship(member.relationship) }}
              </span>
              <span
                v-if="member.is_dependent"
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-raspberry-100 text-raspberry-800"
              >
                Dependent
              </span>
              <span
                v-if="member.is_shared && member.owner === 'spouse'"
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-light-blue-100 text-horizon-600"
              >
                Shared from Spouse
              </span>
              <span
                v-if="member.receives_child_benefit"
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-spring-100 text-spring-800"
              >
                Child Benefit
              </span>
              <span
                v-if="member.relationship === 'spouse' && member.email"
                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-spring-100 text-spring-800"
              >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                </svg>
                Account Linked
              </span>
            </div>
          </div>

          <div v-if="!member.is_shared && member.relationship !== 'spouse'" class="flex space-x-2 flex-shrink-0">
            <button
              v-preview-disabled="'edit'"
              @click="openEditModal(member)"
              class="btn-secondary text-sm"
            >
              Edit
            </button>
            <button
              v-preview-disabled="'delete'"
              @click="confirmDelete(member)"
              class="btn-danger-sm"
            >
              Delete
            </button>
          </div>
        </div>

        <!-- Details grid -->
        <div class="mt-3 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
          <div v-if="member.date_of_birth">
            <p class="text-body-xs text-neutral-500">Date of Birth</p>
            <p class="text-body-sm text-horizon-500">{{ formatDate(member.date_of_birth) }}</p>
            <p class="text-body-xs text-neutral-500">Age: {{ calculateAge(member.date_of_birth) }}</p>
          </div>

          <div v-if="member.gender">
            <p class="text-body-xs text-neutral-500">Gender</p>
            <p class="text-body-sm text-horizon-500 capitalize">{{ member.gender }}</p>
          </div>

          <div v-if="member.annual_income">
            <p class="text-body-xs text-neutral-500">Annual Income</p>
            <p class="text-body-sm text-horizon-500">{{ formatCurrency(member.annual_income) }}</p>
          </div>
        </div>

        <div v-if="member.notes" class="mt-3">
          <p class="text-body-xs text-neutral-500">Notes</p>
          <p class="text-body-sm text-horizon-500">{{ member.notes }}</p>
        </div>

        <!-- Linked account notice -->
        <p v-if="member.relationship === 'spouse'" class="mt-3 text-body-xs text-neutral-500 italic border-t border-light-gray pt-3">
          Linked account — can only be edited or deleted by logging into the spouse's account
        </p>
        <p v-else-if="member.is_shared" class="mt-3 text-body-xs text-neutral-500 italic border-t border-light-gray pt-3">
          Managed by spouse
        </p>
      </div>
      </div>

      <!-- Empty State -->
      <div v-else class="text-center py-8">
        <p class="text-body-base text-neutral-500">No family members added yet</p>
        <button
          v-preview-disabled="'add'"
          @click="openAddModal"
          class="btn-primary mt-4"
        >
          Add Your First Family Member
        </button>
      </div>
    </div>

    <!-- Charitable Bequest -->
    <div class="card p-6 mt-6">
      <h3 class="text-h5 font-semibold text-horizon-500 mb-4">Charitable Bequest</h3>
      <div class="flex items-center justify-between">
        <div>
          <p class="text-body text-neutral-500 mb-1">Do you wish to leave anything to charity?</p>
          <p class="text-body-sm text-neutral-500">
            Leaving 10% or more to charity can reduce your Inheritance Tax rate from 40% to 36%
          </p>
        </div>
        <div class="text-body font-medium" :class="charitableBequest ? 'text-spring-600' : 'text-neutral-500'">
          {{ charitableBequest ? 'Yes' : charitableBequest === false ? 'No' : 'Not set' }}
        </div>
      </div>
    </div>

    <!-- Family Member Form Modal -->
    <FamilyMemberFormModal
      v-if="showModal"
      :member="selectedMember"
      @save="handleSave"
      @close="closeModal"
    />

    <!-- Delete Confirmation Modal -->
    <ConfirmDialog
      :show="showDeleteConfirm"
      title="Delete Family Member"
      :message="`Are you sure you want to delete ${memberToDelete?.name}? This action cannot be undone.`"
      confirm-text="Delete"
      cancel-text="Cancel"
      @confirm="handleDelete"
      @cancel="showDeleteConfirm = false"
    />

    <!-- Marital Status Update (after removing spouse) -->
    <div v-if="showMaritalStatusPrompt" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-xl shadow-xl p-6 max-w-md mx-4">
        <h3 class="text-h5 font-bold text-horizon-500 mb-2">Update Marital Status</h3>
        <p class="text-body-sm text-neutral-500 mb-5">You have removed your spouse. What would you like to update your marital status to?</p>
        <div class="flex flex-col gap-2">
          <button
            class="w-full px-4 py-2.5 rounded-lg bg-raspberry-500 text-white text-body-sm font-medium hover:bg-raspberry-600 transition-colors"
            @click="updateMaritalStatus('divorced')"
          >
            Divorced
          </button>
          <button
            class="w-full px-4 py-2.5 rounded-lg bg-horizon-500 text-white text-body-sm font-medium hover:bg-horizon-600 transition-colors"
            @click="updateMaritalStatus('widowed')"
          >
            Widowed
          </button>
          <button
            class="w-full px-4 py-2.5 rounded-lg border border-light-gray text-neutral-500 text-body-sm font-medium hover:bg-eggshell-500 transition-colors"
            @click="showMaritalStatusPrompt = false"
          >
            Keep current status
          </button>
        </div>
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
  </div>
</template>

<script>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import { useStore } from 'vuex';
import FamilyMemberFormModal from './FamilyMemberFormModal.vue';
import ConfirmDialog from '@/components/Common/ConfirmDialog.vue';
import SpouseSuccessModal from '@/components/Shared/SpouseSuccessModal.vue';
import familyMembersService from '@/services/familyMembersService';
import { formatCurrency } from '@/utils/currency';

import logger from '@/utils/logger';
// Preview mode messages
const PREVIEW_ADD_MESSAGE = 'Family member added for this session only (preview mode).';
const PREVIEW_UPDATE_MESSAGE = 'Family member updated for this session only (preview mode).';
const PREVIEW_DELETE_MESSAGE = 'Family member removed for this session only (preview mode).';

export default {
  name: 'FamilyMembers',

  components: {
    FamilyMemberFormModal,
    ConfirmDialog,
    SpouseSuccessModal,
  },

  setup() {
    const store = useStore();
    const showModal = ref(false);
    const selectedMember = ref(null);
    const successMessage = ref('');
    const errorMessage = ref('');
    const showDeleteConfirm = ref(false);
    const memberToDelete = ref(null);
    const showSpouseSuccess = ref(false);
    const showMaritalStatusPrompt = ref(false);
    const spouseCreated = ref(false);
    const spouseEmail = ref(null);
    const temporaryPassword = ref(null);
    const familyMembers = ref([]);
    let successTimeout = null;
    let errorTimeout = null;
    let deleteSuccessTimeout = null;

    // Watch for changes in the store's familyMembers and update local ref
    const storeFamilyMembers = computed(() => store.state.userProfile.familyMembers);
    watch(storeFamilyMembers, (newMembers) => {
      if (newMembers && newMembers.length > 0) {
        familyMembers.value = newMembers;
      }
    }, { immediate: true });

    const charitableBequest = computed(() => store.state.auth.user?.charitable_bequest);

    const loadFamilyMembers = async (forceRefresh = false) => {
      // First try to use store data (from fetchProfile) which includes spouse
      // Skip store if forceRefresh is true (e.g., after adding a family member)
      if (!forceRefresh) {
        const storeMembers = store.state.userProfile.familyMembers;
        if (storeMembers && storeMembers.length > 0) {
          familyMembers.value = storeMembers;
          return;
        }
      }

      // Fetch fresh data from API
      try {
        const response = await familyMembersService.getFamilyMembers();
        familyMembers.value = response.data?.family_members || [];
      } catch (err) {
        logger.error('Failed to load family members:', err);
      }
    };

    const formatDate = (dateString) => {
      if (!dateString) return 'N/A';
      const date = new Date(dateString);
      const day = String(date.getDate()).padStart(2, '0');
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const year = date.getFullYear();
      return `${day}/${month}/${year}`;
    };

    const calculateAge = (dateString) => {
      if (!dateString) return 'N/A';
      const birthDate = new Date(dateString);
      const today = new Date();
      let age = today.getFullYear() - birthDate.getFullYear();
      const monthDiff = today.getMonth() - birthDate.getMonth();
      if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
        age--;
      }
      return age;
    };

    const formatRelationship = (relationship) => {
      if (!relationship) return '';
      return relationship.replace('_', ' ');
    };

    const getRelationshipBadgeClass = (relationship) => {
      const classes = {
        spouse: 'bg-purple-100 text-purple-800',
        child: 'bg-light-blue-100 text-horizon-600',
        step_child: 'bg-light-blue-100 text-horizon-600',
        parent: 'bg-spring-100 text-spring-800',
        other_dependent: 'bg-light-blue-100 text-horizon-600',
      };
      return classes[relationship] || 'bg-savannah-100 text-horizon-500';
    };

    const openAddModal = () => {
      selectedMember.value = null;
      showModal.value = true;
    };

    const openEditModal = (member) => {
      selectedMember.value = member;
      showModal.value = true;
    };

    const closeModal = () => {
      showModal.value = false;
      selectedMember.value = null;
    };

    const handleSave = async (formData) => {
      try {
        const isPreviewMode = store.getters['preview/isPreviewMode'];

        if (selectedMember.value) {
          // Update existing member
          await familyMembersService.updateFamilyMember(selectedMember.value.id, formData);
          successMessage.value = isPreviewMode
            ? PREVIEW_UPDATE_MESSAGE
            : 'Family member updated successfully!';
        } else {
          // Add new member - use same service as onboarding
          const response = await familyMembersService.createFamilyMember(formData);

          // Check if spouse account was created or linked (not applicable in preview mode)
          // Note: response is already the API body (service unwraps axios response)
          const responseData = response?.data || response;
          const isSpouse = formData.relationship === 'spouse';

          if (!isPreviewMode && isSpouse && responseData) {
            if (responseData.created) {
              // Show spouse success modal with credentials
              spouseCreated.value = true;
              spouseEmail.value = responseData.spouse_email || formData.email;
              temporaryPassword.value = responseData.temporary_password || null;
              showSpouseSuccess.value = true;
              // Refresh user data to reflect spouse linkage (silently - don't block modal)
              store.dispatch('auth/fetchUser').catch((err) => {
                console.warn('Failed to refresh user data after spouse creation:', err);
              });
            } else if (responseData.linked) {
              // Show spouse success modal for linking
              spouseCreated.value = false;
              spouseEmail.value = formData.email;
              temporaryPassword.value = null;
              showSpouseSuccess.value = true;
              // Refresh user data to reflect spouse linkage (silently - don't block modal)
              store.dispatch('auth/fetchUser').catch((err) => {
                console.warn('Failed to refresh user data after spouse linking:', err);
              });
            } else {
              successMessage.value = 'Family member added successfully!';
            }
          } else {
            successMessage.value = isPreviewMode
              ? PREVIEW_ADD_MESSAGE
              : 'Family member added successfully!';
          }
        }

        // Auto-update marital status to 'married' when adding a spouse
        // Handles: single→married, divorced→married, widowed→married
        const isSpouseAdd = !selectedMember.value && formData.relationship === 'spouse';
        const currentMaritalStatus = store.getters['auth/user']?.marital_status;
        if (isSpouseAdd && currentMaritalStatus !== 'married' && currentMaritalStatus !== 'civil_partnership') {
          try {
            await store.dispatch('userProfile/updatePersonalInfo', { marital_status: 'married' });
          } catch (err) {
            logger.warn('Failed to auto-update marital status:', err);
          }
        }

        // Refresh spouse permission state so sidebar updates (Expression of Wishes ↔ Letter to Spouse)
        if (isSpouseAdd) {
          store.dispatch('spousePermission/fetchPermissionStatus').catch(() => {});
        }

        if (store.state.aiFormFill?.pendingFill) {
          store.dispatch('aiFormFill/cancelFill');
        }
        closeModal();
        // Refresh family members list directly via API (not fetchProfile)
        // Using fetchProfile would set loading=true, which unmounts this component
        // and resets showSpouseSuccess, preventing the modal from appearing
        await loadFamilyMembers(true); // forceRefresh = true

        // Clear success message after 5 seconds
        if (successMessage.value) {
          if (successTimeout) clearTimeout(successTimeout);
          successTimeout = setTimeout(() => {
            successMessage.value = '';
          }, 5000);
        }
      } catch (err) {
        logger.error('Failed to save family member:', err);
        const errorMsg = err.response?.data?.message || err.message || 'Failed to save family member';
        errorMessage.value = errorMsg;
        closeModal();

        // Clear error after 8 seconds
        if (errorTimeout) clearTimeout(errorTimeout);
        errorTimeout = setTimeout(() => {
          errorMessage.value = '';
        }, 8000);
      }
    };

    const closeSpouseSuccess = () => {
      showSpouseSuccess.value = false;
      spouseCreated.value = false;
      spouseEmail.value = null;
      temporaryPassword.value = null;
    };

    const confirmDelete = (member) => {
      memberToDelete.value = member;
      showDeleteConfirm.value = true;
    };

    const handleDelete = async () => {
      try {
        const isPreviewMode = store.getters['preview/isPreviewMode'];
        const wasSpouse = memberToDelete.value?.relationship === 'spouse';

        await familyMembersService.deleteFamilyMember(memberToDelete.value.id);
        successMessage.value = isPreviewMode
          ? PREVIEW_DELETE_MESSAGE
          : 'Family member deleted successfully!';
        showDeleteConfirm.value = false;
        memberToDelete.value = null;
        // Refresh family members list by refreshing the profile store
        await store.dispatch('userProfile/fetchProfile');

        // If a spouse was deleted, refresh spouse state and prompt for marital status
        if (wasSpouse && !isPreviewMode) {
          store.dispatch('spousePermission/fetchPermissionStatus').catch(() => {});
          showMaritalStatusPrompt.value = true;
        }

        // Clear success message after 3 seconds
        if (deleteSuccessTimeout) clearTimeout(deleteSuccessTimeout);
        deleteSuccessTimeout = setTimeout(() => {
          successMessage.value = '';
        }, 3000);
      } catch (error) {
        logger.error('Failed to delete family member:', error);
        showDeleteConfirm.value = false;
      }
    };

    const updateMaritalStatus = async (newStatus) => {
      try {
        await store.dispatch('userProfile/updatePersonalInfo', { marital_status: newStatus });
        // Refresh spouse permission so sidebar label updates (Letter to Spouse ↔ Expression of Wishes)
        store.dispatch('spousePermission/fetchPermissionStatus').catch(() => {});
        showMaritalStatusPrompt.value = false;
        const label = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
        successMessage.value = `Marital status updated to ${label}.`;
        if (deleteSuccessTimeout) clearTimeout(deleteSuccessTimeout);
        deleteSuccessTimeout = setTimeout(() => {
          successMessage.value = '';
        }, 5000);
      } catch (err) {
        logger.error('Failed to update marital status:', err);
        showMaritalStatusPrompt.value = false;
      }
    };

    onBeforeUnmount(() => {
      if (successTimeout) clearTimeout(successTimeout);
      if (errorTimeout) clearTimeout(errorTimeout);
      if (deleteSuccessTimeout) clearTimeout(deleteSuccessTimeout);
    });

    // Watch for AI form fill requests targeting family_member
    watch(
      () => store.state.aiFormFill?.pendingFill,
      (fill) => {
        if (fill && fill.entityType === 'family_member') {
          if (fill.mode === 'edit' && fill.entityId) {
            const member = familyMembers.value?.find(m => m.id === fill.entityId);
            if (member) openEditModal(member);
          } else {
            openAddModal();
          }
        }
      }
    );

    onMounted(async () => {
      await loadFamilyMembers();
    });

    return {
      familyMembers,
      charitableBequest,
      showModal,
      selectedMember,
      successMessage,
      errorMessage,
      showDeleteConfirm,
      memberToDelete,
      showSpouseSuccess,
      spouseCreated,
      spouseEmail,
      temporaryPassword,
      formatDate,
      calculateAge,
      formatCurrency,
      formatRelationship,
      getRelationshipBadgeClass,
      openAddModal,
      openEditModal,
      closeModal,
      handleSave,
      closeSpouseSuccess,
      confirmDelete,
      handleDelete,
      updateMaritalStatus,
      showMaritalStatusPrompt,
      loadFamilyMembers,
    };
  },
};
</script>
