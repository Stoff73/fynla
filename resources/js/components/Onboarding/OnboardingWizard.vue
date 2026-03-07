<template>
  <div class="min-h-screen bg-eggshell-500 py-8 px-4 sm:px-6 lg:px-8">
    <!-- Journey Context Header (journey mode only) -->
    <div v-if="isJourneyMode && journeyContextLabel" class="max-w-5xl mx-auto mb-4">
      <div class="bg-white rounded-lg shadow-sm border border-light-gray px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-2">
          <span class="text-body-sm text-neutral-500">Setting up:</span>
          <span class="text-body font-medium text-horizon-500">{{ journeyContextLabel }}</span>
        </div>
        <span v-if="journeyProgressPercentage > 0" class="text-body-sm text-neutral-500">
          {{ journeyProgressPercentage }}% complete
        </span>
      </div>
    </div>

    <!-- Progress Indicator -->
    <div v-if="showProgressBar" class="max-w-5xl mx-auto mb-8">
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
        <div v-if="showSkipToDashboardLink" class="mt-3 text-center">
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
      <!-- Focus Area Selection (welcome screen - non-journey modes only) -->
      <FocusAreaSelection
        v-if="!focusArea && !isJourneyMode"
        @selected="handleFocusAreaSelected"
      />

      <!-- Journey Completion Step -->
      <JourneyCompletionStep
        v-if="isJourneyMode && showJourneyCompletion"
        :journey-name="currentJourneyName"
        :completed-steps="journeySteps"
        @next="handleJourneyCompletionNext"
      />

      <!-- Step Content -->
      <Transition name="fade" mode="out-in">
        <component
          v-if="showStepContent"
          :is="currentStepComponent"
          :key="currentStepKey"
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
import { ref, computed, onMounted, watch } from 'vue';
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
import BudgetingSteps from './steps/BudgetingSteps.vue';
import GoalSetupStep from './steps/GoalSetupStep.vue';
import JourneyCompletionStep from './steps/JourneyCompletionStep.vue';

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
    BudgetingSteps,
    GoalSetupStep,
    JourneyCompletionStep,
  },

  props: {
    mode: {
      type: String,
      default: null,
      validator: (v) => v === null || ['quick', 'full', 'module', 'journey'].includes(v),
    },
    moduleSteps: {
      type: Array,
      default: () => [],
    },
    journeyName: {
      type: String,
      default: null,
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
    const showJourneyCompletion = ref(false);

    // Mode flags
    const isJourneyMode = computed(() => props.mode === 'journey');
    const isModuleMode = computed(() => props.mode === 'module');
    const isQuickMode = computed(() => {
      if (props.mode === 'full') return false;
      if (props.mode === 'module') return false;
      if (props.mode === 'journey') return false;
      return props.mode === 'quick' || props.mode === null;
    });

    // Quick mode steps definition (client-side only, no backend fetch needed)
    const quickSteps = [
      { name: 'personal_info', title: 'Personal Information' },
      { name: 'income', title: 'Employment & Income' },
      { name: 'quick_assets', title: 'Your Financial Picture' },
    ];

    // Journey mode state
    const currentJourneyName = computed(() => {
      if (isJourneyMode.value) {
        return props.journeyName || route.params?.journey || store.state.journeys.currentJourney;
      }
      return null;
    });

    const journeySteps = computed(() => {
      return store.state.journeys.currentSteps || [];
    });

    const journeyStepIndex = computed(() => {
      return store.state.journeys.currentStepIndex;
    });

    const journeyProgressPercentage = computed(() => {
      return store.getters['journeys/progressPercentage'];
    });

    const journeyLabels = {
      budgeting: 'Budgeting',
      protection: 'Protection',
      investment: 'Investment',
      retirement: 'Retirement',
      estate: 'Estate Planning',
      family: 'Family Planning',
      business: 'Business Planning',
      goals: 'Goal Tracking',
    };

    const journeyContextLabel = computed(() => {
      if (!isJourneyMode.value) return '';

      const selections = store.state.journeys.selections;
      if (selections.length === 0 && currentJourneyName.value) {
        return journeyLabels[currentJourneyName.value] || currentJourneyName.value;
      }

      return selections
        .map((j) => journeyLabels[j] || j)
        .join(', ');
    });

    // Existing onboarding state
    const focusArea = computed(() => store.state.onboarding.focusArea);

    const currentStepIndex = computed(() => {
      if (isJourneyMode.value) return journeyStepIndex.value;
      return store.state.onboarding.currentStepIndex;
    });

    const currentStep = computed(() => {
      if (isJourneyMode.value) {
        if (showJourneyCompletion.value) return null;
        return store.getters['journeys/currentStep'];
      }
      if (isQuickMode.value || isModuleMode.value) {
        const stepsToUse = isModuleMode.value ? props.moduleSteps : quickSteps;
        return stepsToUse[currentStepIndex.value] || null;
      }
      return store.getters['onboarding/currentStep'];
    });

    const currentStepKey = computed(() => {
      if (!currentStep.value) return null;
      if (isJourneyMode.value) {
        return `journey-${currentJourneyName.value}-${journeyStepIndex.value}`;
      }
      return currentStep.value.name;
    });

    const totalSteps = computed(() => store.state.onboarding.totalSteps);
    const progressPercentage = computed(() => store.state.onboarding.progressPercentage);

    // Steps to display in the progress bar
    const displaySteps = computed(() => {
      if (isJourneyMode.value) return journeySteps.value;
      if (isQuickMode.value) return quickSteps;
      if (isModuleMode.value) return props.moduleSteps;
      return store.state.onboarding.steps || [];
    });

    const showProgressBar = computed(() => {
      if (isJourneyMode.value) {
        return !showJourneyCompletion.value && journeySteps.value.length > 0;
      }
      return focusArea.value && displaySteps.value.length > 0;
    });

    const showStepContent = computed(() => {
      if (isJourneyMode.value) {
        return !showJourneyCompletion.value && currentStep.value;
      }
      return focusArea.value && currentStep.value;
    });

    const showSkipToDashboardLink = computed(() => {
      if (isJourneyMode.value) return !showJourneyCompletion.value;
      return !isQuickMode.value && !isCompletionStep.value;
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
        'budgeting': 'Budget',
        'goals': 'Goals',
      };
      return labelMap[step.name] || step.title || step.name;
    };

    const isCompletionStep = computed(() => {
      return currentStep.value?.name === 'completion';
    });

    // Map journey backend steps to actual Vue components
    const resolveJourneyComponent = (step) => {
      if (!step) return null;

      const componentName = step.component;
      const fields = step.fields || [];

      // Direct component mappings
      if (componentName === 'JourneyPersonalStep') {
        return 'PersonalInfoStep';
      }

      if (componentName === 'BudgetingStep') {
        return 'BudgetingSteps';
      }

      // Financial steps mapped by field
      if (componentName === 'JourneyFinancialStep') {
        if (fields.includes('family_members') || fields.includes('spouse')) {
          return 'FamilyInfoStep';
        }
        if (fields.includes('protection_policies')) {
          return 'ProtectionPoliciesStep';
        }
        if (fields.includes('mortgages') || fields.includes('properties')) {
          return 'AssetsStep';
        }
        if (fields.includes('liabilities')) {
          return 'LiabilitiesStep';
        }
        if (fields.includes('savings_accounts')) {
          return 'QuickAssetsStep';
        }
        if (fields.includes('wills')) {
          return 'WillInfoStep';
        }
        if (fields.includes('trusts')) {
          return 'TrustInfoStep';
        }
        if (fields.includes('pensions') || fields.includes('dc_pensions') || fields.includes('db_pensions') || fields.includes('state_pension')) {
          return 'AssetsStep';
        }
        if (fields.includes('investment_accounts') || fields.includes('investments')) {
          return 'AssetsStep';
        }
        if (fields.includes('business_interests')) {
          return 'AssetsStep';
        }
        if (fields.includes('goals')) {
          return 'GoalSetupStep';
        }
      }

      return null;
    };

    const currentStepComponent = computed(() => {
      if (!currentStep.value) return null;

      if (isJourneyMode.value) {
        return resolveJourneyComponent(currentStep.value);
      }

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
      if (isJourneyMode.value) {
        return handleJourneyNext();
      }

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

    const handleJourneyNext = async () => {
      const isLast = store.getters['journeys/isLastStep'];

      if (isLast) {
        // Complete the journey and show completion screen
        await store.dispatch('journeys/completeJourney', currentJourneyName.value);
        showJourneyCompletion.value = true;
      } else {
        store.dispatch('journeys/nextStep');
      }
    };

    const handleJourneyCompletionNext = async () => {
      // This is called when user clicks "Continue to next journey" from completion
      // The JourneyCompletionStep handles its own navigation
    };

    const handleBack = async () => {
      if (isJourneyMode.value) {
        if (journeyStepIndex.value > 0) {
          store.dispatch('journeys/previousStep');
        }
        return;
      }

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
      if (isJourneyMode.value) {
        // In journey mode, skip just advances to next step
        handleNext();
        return;
      }

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
      if (isJourneyMode.value) {
        await store.dispatch('auth/fetchUser', null, { root: true });
        router.push({ name: 'Dashboard' });
      } else {
        await store.dispatch('onboarding/skipToDashboard');
        router.push('/dashboard');
      }
    };

    onMounted(async () => {
      if (isJourneyMode.value) {
        // Journey mode: load steps from journey API
        const journey = currentJourneyName.value;
        if (journey) {
          // Set focus area for compatibility with existing step components
          store.commit('onboarding/SET_FOCUS_AREA', journey);

          // Fetch journey steps if not already loaded
          if (store.state.journeys.currentSteps.length === 0 || store.state.journeys.currentJourney !== journey) {
            await store.dispatch('journeys/fetchSteps', journey);
          }

          // Start the journey if not already in progress
          const journeyState = store.state.journeys.journeyStates[journey];
          if (!journeyState || journeyState === 'not_started') {
            await store.dispatch('journeys/startJourney', journey);
          }
        }
        return;
      }

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

    // Watch for route changes in journey mode
    watch(() => route.params?.journey, async (newJourney) => {
      if (isJourneyMode.value && newJourney) {
        showJourneyCompletion.value = false;
        store.commit('onboarding/SET_FOCUS_AREA', newJourney);
        await store.dispatch('journeys/fetchSteps', newJourney);
      }
    });

    return {
      focusArea,
      currentStep,
      currentStepIndex,
      currentStepKey,
      totalSteps,
      progressPercentage,
      steps,
      displaySteps,
      skippedSteps,
      currentStepComponent,
      showSkipModal,
      skipReason,
      showSkipToDashboardModal,
      showJourneyCompletion,
      isCompletionStep,
      isQuickMode,
      isModuleMode,
      isJourneyMode,
      currentJourneyName,
      journeySteps,
      journeyContextLabel,
      journeyProgressPercentage,
      showProgressBar,
      showStepContent,
      showSkipToDashboardLink,
      handleFocusAreaSelected,
      handleNext,
      handleBack,
      handleSkipRequest,
      handleJourneyCompletionNext,
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
