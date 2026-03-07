<template>
  <div class="min-h-screen bg-eggshell-500 py-8 px-4 sm:px-6 lg:px-8">
    <!-- Progress Indicator -->
    <div v-if="focusArea && displaySteps.length > 0" class="max-w-5xl mx-auto mb-8">
      <div class="bg-white rounded-lg shadow-sm border border-light-gray p-4">
        <div class="overflow-x-auto">
          <div class="flex items-start justify-between min-w-max px-2">
            <div
              v-for="(step, index) in displaySteps"
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
                v-if="index < displaySteps.length - 1"
                class="absolute h-0.5 top-[18px] left-1/2 -z-10"
                :style="{ width: 'calc(100% - 20px)' }"
                :class="getConnectingLineClass(step, index)"
              ></div>
            </div>
          </div>
        </div>
        <!-- Skip to Dashboard link (full mode only) -->
        <div v-if="!isQuickMode && !isCompletionStep" class="mt-3 text-center">
          <button
            type="button"
            class="text-sm text-neutral-500 hover:text-raspberry-500 transition-colors underline"
            @click="showSkipToDashboardModal = true"
          >
            Skip to Dashboard
          </button>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-5xl mx-auto">
      <!-- Focus Area Selection (welcome screen) -->
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

    <!-- Skip Confirmation Modal (full mode only) -->
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

    <!-- Skip to Dashboard Modal (full mode only) -->
    <SkipToDashboardModal
      :show="showSkipToDashboardModal"
      @continue="showSkipToDashboardModal = false"
      @skip-to-dashboard="handleSkipToDashboard"
    />
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import { useStore } from 'vuex';
import { useRouter, useRoute } from 'vue-router';
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
import QuickAssetsStep from './steps/QuickAssetsStep.vue';

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
    QuickAssetsStep,
  },

  props: {
    mode: {
      type: String,
      default: null,
      validator: (v) => v === null || ['quick', 'full', 'module'].includes(v),
    },
    moduleSteps: {
      type: Array,
      default: () => [],
    },
  },

  setup(props) {
    const store = useStore();
    const router = useRouter();
    const route = useRoute();

    const showSkipModal = ref(false);
    const skipReason = ref('');
    const pendingSkipStep = ref(null);
    const showSkipToDashboardModal = ref(false);

    // Determine onboarding mode
    // Module mode: used for mini-onboarding routes
    // Quick mode: default for new users (3 steps)
    // Full mode: legacy full onboarding (11 steps)
    const isModuleMode = computed(() => props.mode === 'module');
    const isQuickMode = computed(() => {
      if (props.mode === 'full') return false;
      if (props.mode === 'module') return false;
      // Default to quick mode unless explicitly full
      return props.mode === 'quick' || props.mode === null;
    });

    // Quick mode steps definition (client-side only, no backend fetch needed)
    const quickSteps = [
      { name: 'personal_info', title: 'Personal Information' },
      { name: 'income', title: 'Employment & Income' },
      { name: 'quick_assets', title: 'Your Financial Picture' },
    ];

    const focusArea = computed(() => store.state.onboarding.focusArea);
    const currentStep = computed(() => {
      if (isQuickMode.value || isModuleMode.value) {
        const stepsToUse = isModuleMode.value ? props.moduleSteps : quickSteps;
        return stepsToUse[currentStepIndex.value] || null;
      }
      return store.getters['onboarding/currentStep'];
    });
    const currentStepIndex = computed(() => store.state.onboarding.currentStepIndex);
    const totalSteps = computed(() => store.state.onboarding.totalSteps);
    const progressPercentage = computed(() => store.state.onboarding.progressPercentage);

    // Steps to display in the progress bar
    const displaySteps = computed(() => {
      if (isQuickMode.value) return quickSteps;
      if (isModuleMode.value) return props.moduleSteps;
      return store.state.onboarding.steps || [];
    });

    // Full steps from store (for full mode)
    const steps = computed(() => store.state.onboarding.steps || []);
    const skippedSteps = computed(() => store.state.onboarding.skippedSteps || []);

    const isStepCompleted = (step, index) => {
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
        return 'bg-raspberry-500 border-raspberry-500 text-white';
      }
      if (isStepSkipped(step)) {
        return 'bg-violet-500 border-violet-500 text-white';
      }
      if (isStepCompleted(step, index)) {
        return 'bg-spring-600 border-spring-600 text-white';
      }
      return 'bg-white border-horizon-300 text-horizon-400';
    };

    const getStepLabelClass = (step, index) => {
      if (isCurrentStep(index)) {
        return 'text-raspberry-500 font-semibold';
      }
      if (isStepSkipped(step)) {
        return 'text-violet-600';
      }
      if (isStepCompleted(step, index)) {
        return 'text-spring-600';
      }
      return 'text-neutral-500';
    };

    const getConnectingLineClass = (step, index) => {
      if (index < currentStepIndex.value) {
        return 'bg-spring-600';
      }
      return 'bg-horizon-300';
    };

    const getStepShortLabel = (step) => {
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
        'quick_assets': 'Overview',
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
        quick_assets: 'QuickAssetsStep',
      };

      return componentMap[currentStep.value.name] || null;
    });

    const handleFocusAreaSelected = async (area) => {
      if (isQuickMode.value) {
        // In quick mode, set steps locally instead of fetching from backend
        store.commit('onboarding/SET_STEPS', quickSteps);
        store.commit('onboarding/SET_CURRENT_STEP_INDEX', 0);
        store.commit('onboarding/SET_CURRENT_STEP', quickSteps[0].name);
      } else {
        await store.dispatch('onboarding/fetchSteps');
      }
    };

    const handleNext = async () => {
      const stepsToUse = isQuickMode.value ? quickSteps : (isModuleMode.value ? props.moduleSteps : steps.value);
      const nextIndex = currentStepIndex.value + 1;

      if (isQuickMode.value && nextIndex >= quickSteps.length) {
        // Quick mode complete - mark onboarding as done and go to dashboard
        await store.dispatch('onboarding/completeQuickOnboarding');
        await store.dispatch('auth/fetchUser', null, { root: true });
        router.push({ name: 'Dashboard' });
        return;
      }

      if (isModuleMode.value && nextIndex >= props.moduleSteps.length) {
        // Module mini-onboarding complete - go back to dashboard
        router.push({ name: 'Dashboard' });
        return;
      }

      if (isQuickMode.value || isModuleMode.value) {
        // Local step navigation (no backend step fetching)
        store.commit('onboarding/SET_CURRENT_STEP_INDEX', nextIndex);
        store.commit('onboarding/SET_CURRENT_STEP', stepsToUse[nextIndex].name);
      } else {
        await store.dispatch('onboarding/goToNextStep');
      }
    };

    const handleBack = async () => {
      if (isQuickMode.value || isModuleMode.value) {
        const prevIndex = currentStepIndex.value - 1;
        if (prevIndex >= 0) {
          const stepsToUse = isQuickMode.value ? quickSteps : props.moduleSteps;
          store.commit('onboarding/SET_CURRENT_STEP_INDEX', prevIndex);
          store.commit('onboarding/SET_CURRENT_STEP', stepsToUse[prevIndex].name);
        }
      } else {
        await store.dispatch('onboarding/goToPreviousStep');
      }
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

      if (isModuleMode.value) {
        // Module mode: set up steps from props
        store.commit('onboarding/SET_STEPS', props.moduleSteps);
        store.commit('onboarding/SET_CURRENT_STEP_INDEX', 0);
        store.commit('onboarding/SET_CURRENT_STEP', props.moduleSteps[0]?.name);
        // Auto-set focus area so step components work
        if (!focusArea.value) {
          store.commit('onboarding/SET_FOCUS_AREA', 'estate');
        }
      } else {
        // Always reset to welcome screen when user navigates to onboarding
        store.commit('onboarding/SET_FOCUS_AREA', null);
        store.commit('onboarding/SET_CURRENT_STEP_INDEX', 0);
        store.commit('onboarding/SET_CURRENT_STEP', null);
      }
    });

    return {
      focusArea,
      currentStep,
      currentStepIndex,
      totalSteps,
      progressPercentage,
      steps,
      displaySteps,
      skippedSteps,
      currentStepComponent,
      showSkipModal,
      skipReason,
      showSkipToDashboardModal,
      isCompletionStep,
      isQuickMode,
      isModuleMode,
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
