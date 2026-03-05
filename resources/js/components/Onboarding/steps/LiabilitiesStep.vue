<template>
  <OnboardingStep
    title="Other Liabilities"
    description="Add details about loans, credit cards, and other debts"
    :can-go-back="true"
    :can-skip="true"
    :loading="loading"
    :error="error"
    @next="handleNext"
    @back="handleBack"
    @skip="handleSkip"
  >
    <div class="space-y-6">
      <div class="bg-violet-50 border border-violet-200 rounded-lg p-4">
        <p class="text-body-sm text-violet-800">
          <strong>Why this matters:</strong> Liabilities reduce your taxable estate for Inheritance Tax purposes. We've already captured mortgages with your properties - here you can add other debts like personal loans, car finance, or credit cards.
        </p>
      </div>

      <!-- Added Liabilities List -->
      <div v-if="liabilities.length > 0" class="space-y-3">
        <h4 class="text-body font-medium text-horizon-500">
          Liabilities ({{ liabilities.length }})
        </h4>

        <div
          v-for="liability in liabilities"
          :key="liability.id"
          class="border border-light-gray rounded-lg p-4 bg-eggshell-500"
        >
          <div class="flex justify-between items-start">
            <div class="flex-1">
              <div class="flex items-center gap-2 mb-2">
                <h5 class="text-body font-medium text-horizon-500 capitalize">
                  {{ liability.liability_type?.replace(/_/g, ' ') }}
                </h5>
              </div>
              <p class="text-body-sm text-neutral-500">{{ liability.liability_name }}</p>
              <div class="mt-2">
                <p class="text-body-sm text-neutral-500">Balance</p>
                <p class="text-body font-medium text-horizon-500">{{ formatCurrency(liability.current_balance) }}</p>
              </div>
            </div>
            <div class="flex gap-2 ml-4">
              <button
                type="button"
                class="text-raspberry-500 hover:text-raspberry-700 text-body-sm"
                @click="editLiability(liability)"
              >
                Edit
              </button>
              <button
                type="button"
                class="text-raspberry-500 hover:text-raspberry-700 text-body-sm"
                @click="deleteLiability(liability.id)"
              >
                Delete
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Add Liability Button -->
      <div>
        <button
          type="button"
          class="btn-secondary w-full md:w-auto"
          @click="showAddForm"
        >
          + Add Liability
        </button>
      </div>

      <p v-if="liabilities.length === 0" class="text-body-sm text-neutral-500 italic">
        You can skip this step if you don't have any loans or credit card debt.
      </p>
    </div>

    <!-- Liability Form Modal -->
    <div v-if="showForm" class="fixed inset-0 bg-eggshell-5000 bg-opacity-75 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-lg max-w-3xl w-full max-h-[90vh] overflow-hidden">
        <LiabilityForm
          :liability="editingLiability"
          :mode="editingLiability ? 'edit' : 'create'"
          @save="handleLiabilitySave"
          @cancel="closeLiabilityForm"
        />
      </div>
    </div>
  </OnboardingStep>
</template>

<script>
import { ref, onMounted } from 'vue';
import OnboardingStep from '../OnboardingStep.vue';
import LiabilityForm from '@/components/Estate/LiabilityForm.vue';
import estateService from '@/services/estateService';
import { formatCurrency } from '@/utils/currency';

export default {
  name: 'LiabilitiesStep',

  components: {
    OnboardingStep,
    LiabilityForm,
  },

  emits: ['next', 'back', 'skip'],

  setup(props, { emit }) {
    const liabilities = ref([]);
    const showForm = ref(false);
    const editingLiability = ref(null);
    const loading = ref(false);
    const error = ref(null);

    onMounted(async () => {
      await loadLiabilities();
    });

    async function loadLiabilities() {
      try {
        const response = await estateService.getEstateData();
        liabilities.value = response.data?.liabilities || [];
      } catch (err) {
        console.error('Failed to load liabilities', err);
      }
    }

    const showAddForm = () => {
      showForm.value = true;
      editingLiability.value = null;
    };

    const handleLiabilitySave = async (formData) => {
      try {
        if (editingLiability.value) {
          // Update existing liability
          await estateService.updateLiability(editingLiability.value.id, formData);
        } else {
          // Create new liability
          await estateService.createLiability(formData);
        }

        closeLiabilityForm();
        await loadLiabilities();
      } catch (err) {
        error.value = 'Failed to save liability';
        console.error('Failed to save liability:', err);
      }
    };

    const editLiability = (liability) => {
      editingLiability.value = liability;
      showForm.value = true;
    };

    const deleteLiability = async (id) => {
      if (confirm('Are you sure you want to remove this liability?')) {
        try {
          await estateService.deleteLiability(id);
          await loadLiabilities();
        } catch (err) {
          error.value = 'Failed to delete liability';
        }
      }
    };

    const closeLiabilityForm = () => {
      showForm.value = false;
      editingLiability.value = null;
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

    return {
      liabilities,
      showForm,
      editingLiability,
      loading,
      error,
      showAddForm,
      handleLiabilitySave,
      editLiability,
      deleteLiability,
      closeLiabilityForm,
      handleNext,
      handleBack,
      handleSkip,
      formatCurrency,
    };
  },
};
</script>
