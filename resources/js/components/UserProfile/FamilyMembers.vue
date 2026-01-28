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
    <div v-if="errorMessage" class="rounded-md bg-red-50 border border-red-200 p-4 mb-6">
      <div class="flex">
        <div class="flex-shrink-0">
          <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
          </svg>
        </div>
        <div class="ml-3">
          <p class="text-body-sm font-medium text-red-800">
            {{ errorMessage }}
          </p>
        </div>
      </div>
    </div>

    <!-- Family Members Card -->
    <div class="bg-white rounded-lg border border-gray-200 p-6">
      <div class="flex justify-between items-start mb-6">
        <div>
          <h3 class="text-h4 font-semibold text-gray-900">Family Members</h3>
          <p class="mt-1 text-body-sm text-gray-600">
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
        <div class="flex justify-between items-start">
          <div class="flex-1">
            <div class="flex flex-wrap items-center gap-2 sm:gap-3">
              <h3 class="text-h5 font-semibold text-gray-900">{{ member.name }}</h3>
              <span
                class="inline-flex items-center px-2 sm:px-2.5 py-0.5 rounded-full text-xs font-medium capitalize"
                :class="getRelationshipBadgeClass(member.relationship)"
              >
                {{ formatRelationship(member.relationship) }}
              </span>
              <span
                v-if="member.is_dependent"
                class="inline-flex items-center px-2 sm:px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-800"
              >
                Dependent
              </span>
              <span
                v-if="member.is_shared && member.owner === 'spouse'"
                class="inline-flex items-center px-2 sm:px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800"
                title="This family member is managed by your spouse"
              >
                <span class="hidden sm:inline">Shared from Spouse</span>
                <span class="sm:hidden">Shared</span>
              </span>
              <!-- Linked Account Indicator for Spouse -->
              <span
                v-if="member.relationship === 'spouse' && member.email"
                class="inline-flex items-center gap-1 px-2 sm:px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800"
                title="Spouse account is linked"
              >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                </svg>
                <span class="hidden sm:inline">Account Linked</span>
                <span class="sm:hidden">Linked</span>
              </span>
            </div>

            <div class="mt-3 grid grid-cols-2 md:grid-cols-4 gap-4">
              <div v-if="member.date_of_birth">
                <p class="text-body-xs text-gray-500">Date of Birth</p>
                <p class="text-body-sm text-gray-900">{{ formatDate(member.date_of_birth) }}</p>
                <p class="text-body-xs text-gray-500">Age: {{ calculateAge(member.date_of_birth) }}</p>
              </div>

              <div v-if="member.gender">
                <p class="text-body-xs text-gray-500">Gender</p>
                <p class="text-body-sm text-gray-900 capitalize">{{ member.gender }}</p>
              </div>

              <div v-if="member.annual_income">
                <p class="text-body-xs text-gray-500">Annual Income</p>
                <p class="text-body-sm text-gray-900">{{ formatCurrency(member.annual_income) }}</p>
              </div>

              <div v-if="member.education_status">
                <p class="text-body-xs text-gray-500">Education</p>
                <p class="text-body-sm text-gray-900 capitalize">{{ member.education_status.replace('_', ' ') }}</p>
              </div>
            </div>

            <div v-if="member.notes" class="mt-3">
              <p class="text-body-xs text-gray-500">Notes</p>
              <p class="text-body-sm text-gray-900">{{ member.notes }}</p>
            </div>
          </div>

          <div v-if="!member.is_shared && member.relationship !== 'spouse'" class="flex space-x-2 ml-4">
            <button
              v-preview-disabled="'edit'"
              @click="openEditModal(member)"
              class="btn-secondary-sm"
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
          <div v-else-if="member.relationship === 'spouse'" class="ml-4">
            <p class="text-body-xs text-gray-500 italic">
              Linked account — can only be edited or deleted by logging into the spouse's account
            </p>
          </div>
          <div v-else class="ml-4">
            <p class="text-body-xs text-gray-500 italic">
              Managed by spouse
            </p>
          </div>
        </div>
      </div>
      </div>

      <!-- Empty State -->
      <div v-else class="text-center py-8">
        <p class="text-body-base text-gray-500">No family members added yet</p>
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
      <h3 class="text-h5 font-semibold text-gray-900 mb-4">Charitable Bequest</h3>
      <div class="flex items-center justify-between">
        <div>
          <p class="text-body text-gray-700 mb-1">Do you wish to leave anything to charity?</p>
          <p class="text-body-sm text-gray-500">
            Leaving 10% or more to charity can reduce your IHT rate from 40% to 36%
          </p>
        </div>
        <div class="text-body font-medium" :class="charitableBequest ? 'text-green-600' : 'text-gray-600'">
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
    <ConfirmationModal
      v-if="showDeleteConfirm"
      title="Delete Family Member"
      :message="`Are you sure you want to delete ${memberToDelete?.name}? This action cannot be undone.`"
      confirm-text="Delete"
      cancel-text="Cancel"
      @confirm="handleDelete"
      @cancel="showDeleteConfirm = false"
    />

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
import { ref, computed, onMounted, watch } from 'vue';
import { useStore } from 'vuex';
import FamilyMemberFormModal from './FamilyMemberFormModal.vue';
import ConfirmationModal from '@/components/Common/ConfirmationModal.vue';
import SpouseSuccessModal from '@/components/Shared/SpouseSuccessModal.vue';
import familyMembersService from '@/services/familyMembersService';

// Preview mode messages
const PREVIEW_ADD_MESSAGE = 'Family member added for this session only (preview mode).';
const PREVIEW_UPDATE_MESSAGE = 'Family member updated for this session only (preview mode).';
const PREVIEW_DELETE_MESSAGE = 'Family member removed for this session only (preview mode).';

export default {
  name: 'FamilyMembers',

  components: {
    FamilyMemberFormModal,
    ConfirmationModal,
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
    const spouseCreated = ref(false);
    const spouseEmail = ref(null);
    const temporaryPassword = ref(null);
    const familyMembers = ref([]);

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
        console.error('Failed to load family members:', err);
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

    const formatCurrency = (amount) => {
      if (!amount) return '£0';
      return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }).format(amount);
    };

    const formatRelationship = (relationship) => {
      if (!relationship) return '';
      return relationship.replace('_', ' ');
    };

    const getRelationshipBadgeClass = (relationship) => {
      const classes = {
        spouse: 'bg-purple-100 text-purple-800',
        child: 'bg-blue-100 text-blue-800',
        step_child: 'bg-blue-100 text-blue-800',
        parent: 'bg-green-100 text-green-800',
        other_dependent: 'bg-amber-100 text-amber-800',
      };
      return classes[relationship] || 'bg-gray-100 text-gray-800';
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

        closeModal();
        // Refresh family members list directly via API (not fetchProfile)
        // Using fetchProfile would set loading=true, which unmounts this component
        // and resets showSpouseSuccess, preventing the modal from appearing
        await loadFamilyMembers(true); // forceRefresh = true

        // Clear success message after 5 seconds
        if (successMessage.value) {
          setTimeout(() => {
            successMessage.value = '';
          }, 5000);
        }
      } catch (err) {
        console.error('Failed to save family member:', err);
        const errorMsg = err.response?.data?.message || err.message || 'Failed to save family member';
        errorMessage.value = errorMsg;
        closeModal();

        // Clear error after 8 seconds
        setTimeout(() => {
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

        await familyMembersService.deleteFamilyMember(memberToDelete.value.id);
        successMessage.value = isPreviewMode
          ? PREVIEW_DELETE_MESSAGE
          : 'Family member deleted successfully!';
        showDeleteConfirm.value = false;
        memberToDelete.value = null;
        // Refresh family members list by refreshing the profile store
        await store.dispatch('userProfile/fetchProfile');

        // Clear success message after 3 seconds
        setTimeout(() => {
          successMessage.value = '';
        }, 3000);
      } catch (error) {
        console.error('Failed to delete family member:', error);
        showDeleteConfirm.value = false;
      }
    };

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
      loadFamilyMembers,
    };
  },
};
</script>
