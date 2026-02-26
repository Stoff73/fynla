<template>
  <div class="min-h-screen bg-gray-50 py-8 px-4 sm:px-6 lg:px-8">
    <!-- Progress Indicator -->
    <div v-if="focusArea && steps.length > 0" class="max-w-5xl mx-auto mb-8">
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <div class="overflow-x-auto">
          <div class="flex items-start justify-between min-w-max px-2">
            <div
              v-for="(step, index) in steps"
              :key="step.name"
              class="flex-1 flex flex-col items-center relative min-w-[80px]"
            >
              <!-- Step Circle -->
              <div
                class="w-9 h-9 rounded-full flex items-center justify-center border-2 transition-all"
                :class="getStepCircleClass(step, index)"
              >
                <!-- Checkmark for completed -->
                <svg v-if="isStepCompleted(step, index)" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                </svg>
                <!-- Skip icon for skipped -->
                <svg v-else-if="isStepSkipped(step)" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
                <!-- Step number for current/pending -->
                <span v-else class="text-sm font-semibold">{{ index + 1 }}</span>
              </div>
              <!-- Step Label -->
              <span
                class="text-xs mt-1.5 text-center leading-tight max-w-[70px]"
                :class="getStepLabelClass(step, index)"
              >
                {{ getStepShortLabel(step) }}
              </span>
              <!-- Connecting Line -->
              <div
                v-if="index < steps.length - 1"
                class="absolute h-0.5 top-[18px] left-1/2 -z-10"
                :style="{ width: 'calc(100% - 20px)' }"
                :class="getConnectingLineClass(step, index)"
              ></div>
            </div>
          </div>
        </div>
        <!-- Skip to Dashboard link -->
        <div v-if="!isCompletionStep" class="mt-3 text-center">
          <button
            type="button"
            class="text-sm text-gray-500 hover:text-primary-600 transition-colors underline"
            @click="showSkipToDashboardModal = true"
          >
            Skip to Dashboard
          </button>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-5xl mx-auto">
      <!-- Focus Area Selection -->
      <FocusAreaSelection
        v-if="!focusArea"
        @selected="handleFocusAreaSelected"
      />

      <!-- Step Content -->
      <Transition name="fade" mode="out-in">
        <component
          v-if="focusArea && currentStep"
          :is="currentStepComponent"
          :key="currentStep.name"
          @next="handleNext"
          @back="handleBack"
          @skip="handleSkipRequest"
        />
      </Transition>
    </div>

    <!-- Skip Confirmation Modal -->
    <ConfirmDialog
      :show="showSkipModal"
      title="This information is important"
      :message="skipReason"
      type="warning"
      confirm-text="Skip Anyway"
      cancel-text="Go Back"
      @confirm="confirmSkip"
      @cancel="hideSkipModal"
    />

    <!-- Skip to Dashboard Modal -->
    <SkipToDashboardModal
      :show="showSkipToDashboardModal"
      @continue="showSkipToDashboardModal = false"
      @skip-to-dashboard="handleSkipToDashboard"
    />
  </div>
</template>

<script>
import { ref, computed, onMounted, watch } from 'vue';
import { useStore } from 'vuex';
import { useRouter } from 'vue-router';
import FocusAreaSelection from './FocusAreaSelection.vue';
import ConfirmDialog from '@/components/Common/ConfirmDialog.vue';
import SkipToDashboardModal from './SkipToDashboardModal.vue';
import PersonalInfoStep from './steps/PersonalInfoStep.vue';
import IncomeStep from './steps/IncomeStep.vue';
import ExpenditureStep from './steps/ExpenditureStep.vue';
import DomicileInformationStep from './steps/DomicileInformationStep.vue';
import ProtectionPoliciesStep from './steps/ProtectionPoliciesStep.vue';
import AssetsStep from './steps/AssetsStep.vue';
import LiabilitiesStep from './steps/LiabilitiesStep.vue';
import FamilyInfoStep from './steps/FamilyInfoStep.vue';
import WillInfoStep from './steps/WillInfoStep.vue';
import TrustInfoStep from './steps/TrustInfoStep.vue';
import CompletionStep from './steps/CompletionStep.vue';

export default {
  name: 'OnboardingWizard',

  components: {
    FocusAreaSelection,
    ConfirmDialog,
    SkipToDashboardModal,
    PersonalInfoStep,
    IncomeStep,
    ExpenditureStep,
    DomicileInformationStep,
    ProtectionPoliciesStep,
    AssetsStep,
    LiabilitiesStep,
    FamilyInfoStep,
    WillInfoStep,
    TrustInfoStep,
    CompletionStep,
  },

  setup() {
    const store = useStore();
    const router = useRouter();

    const showSkipModal = ref(false);
    const skipReason = ref('');
    const pendingSkipStep = ref(null);
    const showSkipToDashboardModal = ref(false);

    const focusArea = computed(() => store.state.onboarding.focusArea);
    const currentStep = computed(() => store.getters['onboarding/currentStep']);
    const currentStepIndex = computed(() => store.state.onboarding.currentStepIndex);
    const totalSteps = computed(() => store.state.onboarding.totalSteps);
    const progressPercentage = computed(() => store.state.onboarding.progressPercentage);
    const steps = computed(() => store.state.onboarding.steps || []);
    const skippedSteps = computed(() => store.state.onboarding.skippedSteps || []);

    const isStepCompleted = (step, index) => {
      // Step is completed if we're past it and it wasn't skipped
      return index < currentStepIndex.value && !skippedSteps.value.includes(step.name);
    };

    const isStepSkipped = (step) => {
      return skippedSteps.value.includes(step.name);
    };

    const isCurrentStep = (index) => {
      return index === currentStepIndex.value;
    };

    const getStepCircleClass = (step, index) => {
      if (isCurrentStep(index)) {
        return 'bg-teal-600 border-teal-600 text-white';
      }
      if (isStepSkipped(step)) {
        return 'bg-blue-500 border-blue-500 text-white';
      }
      if (isStepCompleted(step, index)) {
        return 'bg-green-600 border-green-600 text-white';
      }
      return 'bg-white border-gray-300 text-gray-400';
    };

    const getStepLabelClass = (step, index) => {
      if (isCurrentStep(index)) {
        return 'text-teal-600 font-semibold';
      }
      if (isStepSkipped(step)) {
        return 'text-blue-600';
      }
      if (isStepCompleted(step, index)) {
        return 'text-green-600';
      }
      return 'text-gray-500';
    };

    const getConnectingLineClass = (step, index) => {
      // Line is green if the next step is completed or current
      if (index < currentStepIndex.value) {
        return 'bg-green-600';
      }
      return 'bg-gray-300';
    };

    const getStepShortLabel = (step) => {
      // Shorten labels for mobile/display
      const labelMap = {
        'personal_info': 'Personal',
        'family_info': 'Family',
        'domicile_info': 'Domicile',
        'income': 'Income',
        'expenditure': 'Expenses',
        'assets': 'Assets',
        'liabilities': 'Debts',
        'protection_policies': 'Protection',
        'will_info': 'Will',
        'trust_info': 'Trusts',
        'completion': 'Complete',
      };
      return labelMap[step.name] || step.title || step.name;
    };

    const isCompletionStep = computed(() => {
      return currentStep.value?.name === 'completion';
    });

    const currentStepComponent = computed(() => {
      if (!currentStep.value) return null;

      const componentMap = {
        personal_info: 'PersonalInfoStep',
        income: 'IncomeStep',
        expenditure: 'ExpenditureStep',
        domicile_info: 'DomicileInformationStep',
        protection_policies: 'ProtectionPoliciesStep',
        assets: 'AssetsStep',
        liabilities: 'LiabilitiesStep',
        family_info: 'FamilyInfoStep',
        will_info: 'WillInfoStep',
        trust_info: 'TrustInfoStep',
        completion: 'CompletionStep',
      };

      return componentMap[currentStep.value.name] || null;
    });

    const handleFocusAreaSelected = async (area) => {
      // Focus area is set in FocusAreaSelection component
      // Just fetch the steps
      await store.dispatch('onboarding/fetchSteps');
    };

    const handleNext = async () => {
      await store.dispatch('onboarding/goToNextStep');
    };

    const handleBack = async () => {
      await store.dispatch('onboarding/goToPreviousStep');
    };

    const handleSkipRequest = async (stepName) => {
      pendingSkipStep.value = stepName || currentStep.value?.name;
      await store.dispatch('onboarding/showSkipConfirmation', pendingSkipStep.value);
      showSkipModal.value = true;
      skipReason.value = store.state.onboarding.currentSkipReason;
    };

    const hideSkipModal = () => {
      showSkipModal.value = false;
      skipReason.value = '';
      pendingSkipStep.value = null;
      store.dispatch('onboarding/hideSkipConfirmation');
    };

    const confirmSkip = async () => {
      if (pendingSkipStep.value) {
        await store.dispatch('onboarding/skipStep', pendingSkipStep.value);
        await store.dispatch('onboarding/goToNextStep');
      }
      hideSkipModal();
    };

    const handleSkipToDashboard = async () => {
      showSkipToDashboardModal.value = false;
      await store.dispatch('onboarding/skipToDashboard');
      router.push('/dashboard');
    };

    onMounted(async () => {
      // Fetch onboarding status on mount
      await store.dispatch('onboarding/fetchOnboardingStatus');

      // Always reset to welcome screen when user navigates to onboarding
      // This ensures users see the welcome screen whether:
      // 1. They just registered (new user)
      // 2. They clicked "Complete Setup" (returning user)
      // 3. Onboarding is already completed (revisiting)
      store.commit('onboarding/SET_FOCUS_AREA', null);
      store.commit('onboarding/SET_CURRENT_STEP_INDEX', 0);
      store.commit('onboarding/SET_CURRENT_STEP', null);
    });

    return {
      focusArea,
      currentStep,
      currentStepIndex,
      totalSteps,
      progressPercentage,
      steps,
      skippedSteps,
      currentStepComponent,
      showSkipModal,
      skipReason,
      showSkipToDashboardModal,
      isCompletionStep,
      handleFocusAreaSelected,
      handleNext,
      handleBack,
      handleSkipRequest,
      hideSkipModal,
      confirmSkip,
      handleSkipToDashboard,
      isStepCompleted,
      isStepSkipped,
      isCurrentStep,
      getStepCircleClass,
      getStepLabelClass,
      getConnectingLineClass,
      getStepShortLabel,
    };
  },
};
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
