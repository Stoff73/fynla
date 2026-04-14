<template>
  <div class="min-h-screen onboarding-page">
    <!-- Top Navigation Bar -->
    <div class="bg-white border-b border-light-gray">
      <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 h-14 flex items-center justify-between">
        <router-link to="/" class="flex-shrink-0">
          <img src="/images/logos/LogoHiResFynlaDark.png" alt="Fynla" class="h-10" />
        </router-link>
        <router-link
          to="/dashboard"
          class="text-sm text-neutral-500 hover:text-horizon-500 transition-colors flex items-center gap-1"
        >
          <span>Go to dashboard</span>
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
          </svg>
        </router-link>
      </div>
    </div>

    <div class="py-8 px-4 sm:px-6 lg:px-8">

    <!-- ================================================================== -->
    <!-- LIFE STAGE MODE: Progress bar, two-column layout, learning sidebar -->
    <!-- ================================================================== -->
    <template v-if="isLifeStageMode && !showStageMap">
      <!-- Progress Bar -->
      <div v-if="lifeStageSteps.length > 0" class="max-w-6xl mx-auto mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-light-gray p-5">
          <div class="overflow-x-auto scrollbar-hide">
            <div class="progress-track flex items-start justify-between min-w-max px-2">
              <div
                v-for="(stepId, index) in lifeStageSteps"
                :key="stepId"
                class="flex-1 flex flex-col items-center relative min-w-[80px] cursor-pointer"
                @click="goToStep(index)"
              >
                <!-- Step Circle — larger, horizon blue -->
                <div
                  class="w-[52px] h-[52px] rounded-full flex items-center justify-center border-[3px] transition-all text-lg font-bold relative z-10"
                  :class="getLifeStageStepCircleClass(stepId, index)"
                >
                  <svg v-if="getLifeStageStepStatus(stepId, index) === 'complete'" class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                  </svg>
                  <svg v-else-if="getLifeStageStepStatus(stepId, index) === 'skipped'" class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" />
                  </svg>
                  <span v-else-if="getLifeStageStepStatus(stepId, index) === 'partial'" class="text-xs font-bold text-white">
                    {{ getStepCompletenessPercentage(stepId) }}%
                  </span>
                  <span v-else>{{ index + 1 }}</span>
                </div>
                <!-- Step Label -->
                <span
                  class="text-xs mt-2 text-center leading-tight max-w-[70px]"
                  :class="getLifeStageStepLabelClass(stepId, index)"
                >{{ getLifeStageStepLabel(stepId) }}</span>
                <!-- Connecting Line — blue for completed, grey for upcoming -->
                <div
                  v-if="index < lifeStageSteps.length - 1"
                  class="absolute h-1 top-[26px] left-1/2 z-0 rounded-full"
                  :class="index < lifeStageCurrentIndex ? 'bg-horizon-500' : 'bg-neutral-300'"
                  :style="{ width: 'calc(100% - 20px)' }"
                ></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Full-Width Card with Sidebar Inside -->
      <div class="max-w-6xl mx-auto">
        <div class="bg-white rounded-xl shadow-sm border border-light-gray overflow-hidden">
          <div class="flex flex-col lg:flex-row">
            <!-- Left: Form + Navigation -->
            <div class="flex-1 min-w-0 flex flex-col">
              <div class="flex-1 p-6 sm:p-8" @focusin="handleFormFieldFocus">
                <component
                  ref="currentStepRef"
                  v-if="lifeStageCurrentComponent"
                  :is="lifeStageCurrentComponent"
                  :key="lifeStageCurrentStepId"
                  :context="'onboarding'"
                  :saved-data="savedStepData[lifeStageCurrentStepId] || null"
                  @save="handleLifeStageStepSave"
                  @next="handleLifeStageNext"
                  @back="handleLifeStageBack"
                  @skip="handleLifeStageSkip"
                  @close="handleLifeStageNext"
                  @sidebar-update="sidebarOverride = $event"
                />
                <div v-else class="py-12 text-center">
                  <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin mx-auto mb-4"></div>
                  <p class="text-sm text-neutral-500">Loading step...</p>
                </div>
              </div>

              <!-- Navigation Buttons (inside white section) -->
              <div class="flex items-center justify-between px-6 sm:px-8 py-5 border-t border-light-gray">
                <button
                  type="button"
                  class="inline-flex items-center h-10 px-5 bg-light-pink-100 hover:bg-[#FFE0E6] text-horizon-500 rounded-lg font-bold text-sm transition-colors gap-1.5"
                  @click="lifeStageCurrentIndex > 0 ? handleLifeStageBack() : showStageMap = true"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                  </svg>
                  Back
                </button>

                <button
                  type="button"
                  class="inline-flex items-center h-10 px-6 text-sm font-bold rounded-lg text-white bg-raspberry-500 hover:bg-raspberry-600 transition-colors gap-1.5"
                  @click="triggerStepContinue"
                >
                  Continue
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                  </svg>
                </button>
              </div>
            </div>

            <!-- Right: Sidebar (inside card, light pink, extends to bottom) -->
            <div class="w-full lg:w-[320px] flex-shrink-0 bg-light-pink-100 border-t lg:border-t-0 lg:border-l border-light-gray flex flex-col">
              <div class="flex-1 p-6">
                <LearningMilestoneSidebar
                  :step="lifeStageCurrentStepId"
                  :stage="currentLifeStage"
                  :override="sidebarOverride"
                />
              </div>
              <!-- Skip to dashboard (bottom of pink sidebar) -->
              <div class="px-6 pb-6 pt-2 text-center">
                <router-link
                  to="/dashboard"
                  class="text-sm text-neutral-500 hover:text-horizon-500 underline transition-colors"
                >
                  Skip to dashboard and get help from Fyn
                </router-link>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Did You Know — full width below form card -->
      <div v-if="currentDidYouKnow" class="max-w-6xl mx-auto mt-4">
        <div class="bg-white rounded-xl shadow-sm border border-light-gray p-5 flex items-start gap-3">
          <div class="w-9 h-9 rounded-full bg-gradient-to-br from-raspberry-500 to-raspberry-400 flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div>
            <p class="text-sm font-bold text-horizon-500 mb-1">Did you know?</p>
            <p class="text-sm text-neutral-500 leading-relaxed">{{ currentDidYouKnow }}</p>
          </div>
        </div>
      </div>
    </template>

    <!-- ================================================================== -->
    <!-- LEGACY MODE: Journey context header, existing step flow            -->
    <!-- ================================================================== -->
    <template v-else>
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
        <!-- Focus Area Selection (welcome screen - non-journey modes only, or forced by ?stage= query) -->
        <FocusAreaSelection
          v-if="showStageMap || (!focusArea && !isJourneyMode && !isLifeStageMode)"
          @stage-selected="handleStageMapStart"
          @focus-selected="handleFocusAreaSelected"
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
    </template>

    <!-- ================================================================== -->
    <!-- SHARED: Journey Map Modal (shown when user selects a life stage)   -->
    <!-- ================================================================== -->
    <Teleport to="body">
      <transition name="fade">
        <div
          v-if="showJourneyMapModal"
          class="fixed inset-0 z-50 flex items-center justify-center p-4"
        >
          <!-- Backdrop -->
          <div class="absolute inset-0 bg-horizon-500 bg-opacity-50" @click="closeJourneyMapModal"></div>

          <!-- Modal Content -->
          <div class="relative bg-white rounded-xl shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto p-6 z-10">
            <!-- Close button -->
            <button
              type="button"
              class="absolute top-4 right-4 text-neutral-500 hover:text-horizon-500 transition-colors"
              @click="closeJourneyMapModal"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>

            <!-- Stage heading -->
            <div class="text-center mb-6">
              <h2 class="text-xl font-bold text-horizon-500">
                {{ selectedStageConfig ? selectedStageConfig.label : '' }}
              </h2>
              <p class="text-sm text-neutral-500 mt-1">
                {{ selectedStageConfig ? selectedStageConfig.tagline : '' }}
              </p>
            </div>

            <!-- Journey Map -->
            <JourneyMap
              v-if="selectedStageId"
              :stage="selectedStageId"
              :completed-steps="[]"
              mode="preview"
              @start="handleJourneyMapStart"
              @preview="handleJourneyMapPreview"
            />
          </div>
        </div>
      </transition>
    </Teleport>

    <!-- ================================================================== -->
    <!-- SHARED: Skip and Confirm Modals                                    -->
    <!-- ================================================================== -->
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

    <SkipToDashboardModal
      :show="showSkipToDashboardModal"
      @continue="showSkipToDashboardModal = false"
      @skip-to-dashboard="handleSkipToDashboard"
    />

    <!-- Journey Change Confirmation Modal -->
    <Teleport to="body">
      <transition name="fade">
        <div v-if="showJourneyChangeModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div class="absolute inset-0 bg-black/50" @click="showJourneyChangeModal = false"></div>
          <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6 z-10">
            <div class="flex items-start gap-3 mb-4">
              <div class="w-10 h-10 rounded-full bg-violet-100 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                </svg>
              </div>
              <div>
                <h3 class="text-lg font-bold text-horizon-500">Change journey?</h3>
                <p class="text-sm text-neutral-500 mt-1">
                  You will lose any data saved in your existing <strong>{{ currentLifeStageLabel }}</strong> journey. Would you like to continue?
                </p>
              </div>
            </div>
            <div class="flex justify-end gap-3">
              <button type="button" class="px-4 py-2 text-sm font-medium text-neutral-500 hover:text-horizon-500 transition-colors" @click="showJourneyChangeModal = false">No</button>
              <button type="button" class="px-4 py-2 text-sm font-medium text-white bg-raspberry-500 hover:bg-raspberry-600 rounded-button transition-colors" @click="confirmJourneyChange">Yes</button>
            </div>
          </div>
        </div>
      </transition>
    </Teleport>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted, watch, defineAsyncComponent, shallowRef, markRaw } from 'vue';
import { useStore } from 'vuex';
import { useRouter, useRoute } from 'vue-router';
import { LIFE_STAGES, STAGE_ORDER } from '@/constants/lifeStageConfig';
import savingsService from '@/services/savingsService';
import propertyService from '@/services/propertyService';
import protectionService from '@/services/protectionService';
import retirementService from '@/services/retirementService';
import investmentService from '@/services/investmentService';
import goalsService from '@/services/goalsService';
import netWorthService from '@/services/netWorthService';
import estateService from '@/services/estateService';
import userProfileService from '@/services/userProfileService';
import FocusAreaSelection from './FocusAreaSelection.vue';
import ConfirmDialog from '@/components/Common/ConfirmDialog.vue';
import SkipToDashboardModal from './SkipToDashboardModal.vue';
import LearningMilestoneSidebar from './LearningMilestoneSidebar.vue';
import JourneyMap from '@/components/Journey/JourneyMap.vue';

// Legacy step components (kept for backward compatibility with existing modes)
import PersonalInfoStep from './steps/PersonalInfoStep.vue';
import IncomeStep from './steps/IncomeStep.vue';
import ExpenditureStep from './steps/ExpenditureStep.vue';
import DomicileInformationStep from './steps/DomicileInformationStep.vue';
import ProtectionPoliciesStep from './steps/ProtectionPoliciesStep.vue';
import AssetsStep from './steps/AssetsStep.vue';
import LiabilitiesStep from './steps/LiabilitiesStep.vue';
import FamilyInfoStep from './steps/FamilyInfoStep.vue';
import WillInfoStep from './steps/WillInfoStep.vue';
import UsefulResources from '@/components/Onboarding/UsefulResources.vue';
import { STEP_RESOURCES } from '@/constants/onboardingLinks';
import TrustInfoStep from './steps/TrustInfoStep.vue';
import CompletionStep from './steps/CompletionStep.vue';
import GoalSetupStep from './steps/GoalSetupStep.vue';

import logger from '@/utils/logger';
// =====================================================================
// LIFE STAGE STEP → COMPONENT MAPPING
// Maps each step ID from lifeStageConfig onboarding.steps to the
// unified form component that should render for that step.
// =====================================================================
const STEP_COMPONENTS = {
  'personal-info': () => import('@/components/Onboarding/steps/PersonalInfoStep.vue'),
  'student-loan': () => import('@/components/Onboarding/steps/StudentLoanStep.vue'),
  'income': () => import('@/components/Onboarding/steps/IncomeStep.vue'),
  'income-career': () => import('@/components/Onboarding/steps/IncomeStep.vue'),
  'income-tax': () => import('@/components/Onboarding/steps/IncomeStep.vue'),
  'expenditure': () => import('@/components/Onboarding/steps/ExpenditureStep.vue'),
  'assets': () => import('@/components/Onboarding/steps/AssetsStep.vue'),
  'liabilities': () => import('@/components/Onboarding/steps/LiabilitiesStep.vue'),
  'protection-insurance': () => import('@/components/Onboarding/steps/ProtectionPoliciesStep.vue'),
  'family': () => import('@/components/Onboarding/steps/FamilyInfoStep.vue'),
  'will-estate': () => import('@/components/Onboarding/steps/WillInfoStep.vue'),
  'estate-iht': () => import('@/components/Onboarding/steps/WillInfoStep.vue'),
  'estate-legacy': () => import('@/components/Onboarding/steps/WillInfoStep.vue'),
  'goals': () => import('@/components/Onboarding/steps/GoalSetupStep.vue'),
};

// Step ID → STEP_RESOURCES key mapping for the sidebar useful resources card
const STEP_RESOURCE_MAP = {
  'personal-info': 'personalInfo',
  'student-loan': 'studentLoan',
  'income': 'income',
  'income-career': 'income',
  'income-tax': 'income',
  'expenditure': 'expenditure',
  'assets': 'assetsCash',
  'goals': 'goals',
  'family': 'family',
  'protection-insurance': 'protection',
  'liabilities': 'liabilities',
  'domicile': 'domicile',
  'will-estate': 'will',
  'estate-iht': 'will',
  'estate-legacy': 'will',
};

// Step label map for the progress bar
const STEP_LABELS = {
  'personal-info': 'About You',
  'student-loan': 'Student Loan',
  'income': 'Income',
  'income-career': 'Income',
  'income-tax': 'Income',
  'expenditure': 'Spending',
  'assets': 'Assets',
  'liabilities': 'Debts',
  'goals': 'Goals',
  'family': 'Family',
  'protection-insurance': 'Protection',
  'will-estate': 'Will',
  'estate-iht': 'Estate',
  'estate-legacy': 'Estate',
};

export default {
  name: 'OnboardingWizard',

  components: {
    FocusAreaSelection,
    ConfirmDialog,
    SkipToDashboardModal,
    LearningMilestoneSidebar,
    UsefulResources,
    JourneyMap,
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
    GoalSetupStep,
  },

  props: {
    mode: {
      type: String,
      default: null,
      validator: (v) => v === null || ['quick', 'full', 'module', 'journey', 'life-stage'].includes(v),
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
    const showJourneyChangeModal = ref(false);
    const pendingNewStageId = ref(null);
    const showJourneyCompletion = ref(false);
    const showJourneyMapModal = ref(false);
    const selectedStageId = ref(null);
    const showStageMap = ref(false);

    // ================================================================
    // LIFE STAGE MODE
    // ================================================================
    const currentLifeStage = computed(() => store.getters['lifeStage/currentStage']);
    const currentLifeStageLabel = computed(() => {
      const stage = currentLifeStage.value;
      return stage && LIFE_STAGES[stage] ? LIFE_STAGES[stage].label : 'current';
    });
    // Track whether the user has actively started their journey in this session
    // If they navigate to /onboarding/welcome, show stage picker first even if they have a stage
    const lifeStageStarted = ref(false);

    const isLifeStageMode = computed(() => {
      if (props.mode === 'life-stage') return true;
      // If user has a life stage set, always use life stage mode
      if (currentLifeStage.value) return true;
      return false;
    });
    const lifeStageSteps = computed(() => store.getters['lifeStage/onboardingSteps'] || []);
    const lifeStageCompletedSteps = computed(() => store.state.lifeStage?.completedSteps || []);

    const lifeStageCurrentIndex = ref(0);

    // Cache form data emitted by steps so back navigation can restore it
    const savedStepData = ref({});
    const sidebarOverride = ref(null);
    const currentStepRef = ref(null);

    const lifeStageCurrentStepId = computed(() => {
      return lifeStageSteps.value[lifeStageCurrentIndex.value] || null;
    });

    // Useful resources for the current step (shown in sidebar card)
    const currentStepResources = computed(() => {
      const stepId = lifeStageCurrentStepId.value;
      if (!stepId) return null;
      const resourceKey = STEP_RESOURCE_MAP[stepId];
      return resourceKey ? (STEP_RESOURCES[resourceKey] || null) : null;
    });

    const currentDidYouKnow = computed(() => {
      const stepId = lifeStageCurrentStepId.value;
      if (!stepId) return null;
      const milestone = store.getters['lifeStage/learningMilestone']?.(stepId);
      return milestone?.didYouKnow || null;
    });

    // Steps that use deprecated OnboardingStep wrapper (have their own Back/Skip/Continue)
    // Deprecated steps using OnboardingStep wrapper have their own Back/Skip/Continue nav
    const stepsWithOwnNav = [
      'student-loan',
      'income', 'income-career', 'income-tax', 'expenditure',
      'family', 'will-estate', 'estate-iht', 'estate-legacy',
      'goals', 'assets', 'liabilities', 'protection-insurance',
    ];
    const stepHasOwnNav = computed(() => stepsWithOwnNav.includes(lifeStageCurrentStepId.value));

    // Dynamically resolve the component for the current life stage step
    const lifeStageCurrentComponent = shallowRef(null);

    const loadLifeStageComponent = async (stepId) => {
      if (!stepId || !STEP_COMPONENTS[stepId]) {
        lifeStageCurrentComponent.value = null;
        return;
      }
      try {
        const module = await STEP_COMPONENTS[stepId]();
        lifeStageCurrentComponent.value = markRaw(module.default || module);
      } catch (err) {
        logger.error(`Failed to load component for step "${stepId}":`, err);
        lifeStageCurrentComponent.value = null;
      }
    };

    // Watch for step changes and load the appropriate component
    watch(lifeStageCurrentStepId, (newStepId) => {
      if (newStepId) {
        loadLifeStageComponent(newStepId);
      }
    }, { immediate: true });

    const stageColour = computed(() => {
      const config = LIFE_STAGES[currentLifeStage.value];
      return config?.colour || 'violet';
    });

    const stageColourClasses = computed(() => {
      const map = {
        violet: {
          bg: 'bg-violet-500 border-violet-500',
          text: 'text-violet-500',
          line: 'bg-violet-500',
          ring: 'ring-violet-500',
        },
        spring: {
          bg: 'bg-spring-500 border-spring-500',
          text: 'text-spring-500',
          line: 'bg-spring-500',
          ring: 'ring-spring-500',
        },
        raspberry: {
          bg: 'bg-raspberry-500 border-raspberry-500',
          text: 'text-raspberry-500',
          line: 'bg-raspberry-500',
          ring: 'ring-raspberry-500',
        },
        'light-blue': {
          bg: 'bg-light-blue-500 border-light-blue-500',
          text: 'text-light-blue-500',
          line: 'bg-light-blue-500',
          ring: 'ring-light-blue-500',
        },
        horizon: {
          bg: 'bg-horizon-500 border-horizon-500',
          text: 'text-horizon-500',
          line: 'bg-horizon-500',
          ring: 'ring-horizon-500',
        },
      };
      return map[stageColour.value] || map.violet;
    });

    const selectedStageConfig = computed(() => {
      return selectedStageId.value ? LIFE_STAGES[selectedStageId.value] : null;
    });

    // Get the display status for a step in the progress bar.
    // Returns: 'complete' | 'partial' | 'skipped' | 'current' | 'upcoming'
    const getLifeStageStepStatus = (stepId, index) => {
      if (index === lifeStageCurrentIndex.value) return 'current';
      if (index > lifeStageCurrentIndex.value) return 'upcoming';

      // Past step — check field-level completeness from backend
      const completeness = store.getters['lifeStage/stepCompleteness'];
      const stepInfo = completeness[stepId];
      if (stepInfo) return stepInfo.status; // 'complete' | 'partial' | 'skipped'

      // Fallback if no completeness data yet — check binary completed list
      if (lifeStageCompletedSteps.value.includes(stepId)) return 'complete';
      return 'skipped';
    };

    const getStepCompletenessPercentage = (stepId) => {
      const completeness = store.getters['lifeStage/stepCompleteness'];
      return completeness[stepId]?.percentage || 0;
    };

    const isLifeStageCurrentStep = (index) => {
      return index === lifeStageCurrentIndex.value;
    };

    const getLifeStageStepCircleClass = (stepId, index) => {
      const status = getLifeStageStepStatus(stepId, index);

      switch (status) {
        case 'current':
          return 'bg-white border-horizon-500 text-horizon-500';
        case 'complete':
          return 'bg-horizon-500 border-horizon-500 text-white';
        case 'partial':
          return 'bg-horizon-500 border-horizon-500 text-white';
        case 'skipped':
          return 'bg-horizon-500 border-horizon-500 text-white';
        default: // upcoming
          return 'bg-white border-neutral-300 text-neutral-500';
      }
    };

    const getLifeStageStepLabelClass = (stepId, index) => {
      const status = getLifeStageStepStatus(stepId, index);

      switch (status) {
        case 'current':
          return 'text-horizon-500 font-bold';
        case 'complete':
          return 'text-horizon-500';
        case 'partial':
          return 'text-horizon-500';
        case 'skipped':
          return 'text-neutral-500';
        default:
          return 'text-neutral-500';
      }
    };

    const getLifeStageStepLabel = (stepId) => {
      return STEP_LABELS[stepId] || stepId;
    };

    const getLifeStageConnectingLineClass = (index) => {
      if (index >= lifeStageCurrentIndex.value) return 'bg-light-gray';

      // Past step — colour based on completeness
      const stepId = lifeStageSteps.value[index];
      const status = getLifeStageStepStatus(stepId, index);

      if (status === 'complete') return 'bg-spring-500';
      if (status === 'partial') return 'bg-violet-400';
      return 'bg-raspberry-300'; // skipped
    };

    // Scroll to top when step changes (important on mobile where cards stack)
    watch(lifeStageCurrentIndex, () => {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    // Catch-all: when any input/select in the form gets focus, update sidebar position
    // Steps with their own emitWhyField (PersonalInfoStep) will override via @sidebar-update
    let catchAllTimer = null;
    const handleFormFieldFocus = (event) => {
      const target = event.target;
      if (!target || (target.tagName !== 'INPUT' && target.tagName !== 'SELECT' && target.tagName !== 'TEXTAREA')) return;
      if (target.disabled) return;

      const formCol = target.closest('.flex-1');
      if (!formCol) return;

      const colRect = formCol.getBoundingClientRect();
      const inputRect = target.getBoundingClientRect();
      const fieldOffsetY = inputRect.top - colRect.top + (inputRect.height / 2);

      // Extract label text for a contextual message
      const fieldDiv = target.closest('div');
      const label = fieldDiv?.querySelector('label');
      const labelText = label?.textContent?.replace('?', '').trim() || '';

      // Small delay to let step-level emits (which have specific text) fire first
      clearTimeout(catchAllTimer);
      catchAllTimer = setTimeout(() => {
        // Update position even if step already set specific text
        const existingText = sidebarOverride.value?.whyWeAsk;
        sidebarOverride.value = {
          whyWeAsk: existingText || (labelText
            ? `Your ${labelText.toLowerCase()} helps Fynla build a more accurate and personalised financial plan.`
            : 'This information helps Fynla build a more accurate and personalised financial plan for you.'),
          fieldOffsetY,
        };
      }, 15);
    };

    const triggerStepContinue = () => {
      // Delegate to the step component's own handleNext (which saves data then emits 'next')
      const stepComponent = currentStepRef.value;
      if (stepComponent?.handleNext) {
        stepComponent.handleNext();
      } else if (stepComponent?.onNext) {
        stepComponent.onNext();
      } else {
        // Fallback: step has no exposed handleNext, just advance
        handleLifeStageNext();
      }
    };

    const handleLifeStageNext = async (formData) => {
      const currentStepId = lifeStageCurrentStepId.value;

      if (formData && typeof formData === 'object' && currentStepId) {
        savedStepData.value[currentStepId] = { ...formData };
      }

      if (currentStepId === 'family') {
        try {
          await Promise.all([
            store.dispatch('userProfile/fetchFamilyMembers'),
            store.dispatch('userProfile/fetchProfile'),
          ]);
        } catch {
          // Non-blocking
        }
      }

      // Refresh field-level completeness from backend (checks actual DB data).
      // Do NOT blindly stamp as complete — let the backend determine status.
      await store.dispatch('lifeStage/refreshCompleteness');

      const nextIndex = lifeStageCurrentIndex.value + 1;
      if (nextIndex >= lifeStageSteps.value.length) {
        // GA4 onboarding complete
        if (typeof gtag === 'function') {
          gtag('event', 'onboarding_complete', {
            life_stage: currentLifeStage.value,
            steps_completed: lifeStageSteps.value.length,
          });
        }

        // Onboarding complete — refresh net worth cache then go to dashboard
        await store.dispatch('auth/fetchUser', null, { root: true });
        try {
          await netWorthService.refresh();
        } catch {
          // Non-blocking — dashboard will still load
        }
        router.push({ name: 'Dashboard' });
        return;
      }

      sidebarOverride.value = null;
      lifeStageCurrentIndex.value = nextIndex;

      // GA4 onboarding journey tracking
      const nextStepId = lifeStageSteps.value[nextIndex];
      if (typeof gtag === 'function' && nextStepId) {
        gtag('event', 'onboarding_step', {
          step_name: nextStepId,
          step_number: nextIndex + 1,
          total_steps: lifeStageSteps.value.length,
          life_stage: currentLifeStage.value,
        });
      }
    };

    const handleLifeStageBack = () => {
      sidebarOverride.value = null;
      if (lifeStageCurrentIndex.value > 0) {
        lifeStageCurrentIndex.value -= 1;
      }
    };

    const goToStep = (index) => {
      sidebarOverride.value = null;
      lifeStageCurrentIndex.value = index;
    };

    const handleLifeStageSkip = async () => {
      // Do NOT mark as complete — backend field checks will show 'skipped' status.
      // Just refresh completeness so progress bar updates accurately.
      await store.dispatch('lifeStage/refreshCompleteness');

      const nextIndex = lifeStageCurrentIndex.value + 1;
      if (nextIndex >= lifeStageSteps.value.length) {
        router.push({ name: 'Dashboard' });
        return;
      }
      sidebarOverride.value = null;
      lifeStageCurrentIndex.value = nextIndex;
    };

    const handleLifeStageStepSave = async (formData) => {
      // Save the form data via the appropriate API based on current step.
      // SaveAccountModal and LiabilityForm emit data — parent must save.
      // PersonalInformation emits form data — parent must save.
      // Deprecated steps (IncomeStep, SimpleExpenditureStep) save internally.
      const stepId = lifeStageCurrentStepId.value;

      // Cache form data so back navigation can restore it
      if (formData) {
        savedStepData.value[stepId] = { ...formData };
      }

      try {
        if (stepId === 'personal-info' && formData) {
          // PersonalInformation emits raw form data — save via store dispatches
          // (same as the standalone component uses)
          const personalData = {
            first_name: formData.first_name || null,
            surname: formData.surname || null,
            email: formData.email,
            date_of_birth: formData.date_of_birth || null,
            gender: formData.gender || null,
            marital_status: formData.marital_status || null,
            phone: formData.phone || null,
            address_line_1: formData.address_line_1 || null,
            address_line_2: formData.address_line_2 || null,
            city: formData.city || null,
            county: formData.county || null,
            postcode: formData.postcode || null,
            education_level: formData.education_level || null,
            university: formData.university || null,
            student_number: formData.student_number || null,
          };
          const occupationData = {
            occupation: formData.occupation || null,
            employer: formData.employer || null,
            industry: formData.industry || null,
            employment_status: formData.employment_status || null,
            target_retirement_age: formData.target_retirement_age || null,
          };
          await Promise.all([
            store.dispatch('userProfile/updatePersonalInfo', personalData),
            store.dispatch('userProfile/updateIncomeOccupation', occupationData),
          ]);
        } else if (stepId === 'student-loan' && formData) {
          // Fetch liabilities from API to check for existing student loan (avoid duplicates)
          let existingLoan = null;
          try {
            const estateResponse = await estateService.getEstateData();
            const liabilities = estateResponse.data?.liabilities || [];
            existingLoan = liabilities.find(l => l.liability_type === 'student_loan');
          } catch {
            // If fetch fails, try creating (backend may deduplicate)
          }
          if (existingLoan?.id) {
            await estateService.updateLiability(existingLoan.id, formData);
          } else {
            await estateService.createLiability(formData);
          }
        } else if ((stepId === 'savings' || stepId === 'savings-emergency' || stepId === 'first-home-lisa') && formData) {
          await savingsService.createAccount(formData);
        } else if (stepId === 'protection-insurance' && formData) {
          // PolicyFormModal emits with policyType field
          const policyType = formData.policyType || formData.policy_type || 'life';
          const policyCreators = {
            life: (data) => protectionService.createLifePolicy(data),
            criticalIllness: (data) => protectionService.createCriticalIllnessPolicy(data),
            incomeProtection: (data) => protectionService.createIncomeProtectionPolicy(data),
            disability: (data) => protectionService.createDisabilityPolicy(data),
            sicknessIllness: (data) => protectionService.createSicknessIllnessPolicy(data),
          };
          const creator = policyCreators[policyType] || policyCreators.life;
          await creator(formData);
        } else if (stepId === 'state-pension' && formData) {
          await retirementService.updateStatePension(formData);
        } else if (stepId === 'goals' && formData) {
          await goalsService.createGoal(formData);
        } else if (stepId === 'family' && formData) {
          await userProfileService.createFamilyMember(formData);
        }
      } catch (error) {
        logger.error('[Onboarding] Failed to save step data:', error?.message || error);
        // Don't block progress — data can be re-entered from module pages
      }

      // Advance to next step
      handleLifeStageNext();
    };

    // Handle start from the inline stage map (shown via ?stage= query param)
    const handleStageMapStart = async (stageId) => {
      // If changing to a different journey and data has been entered, prompt user
      const existingStage = currentLifeStage.value;
      const hasData = existingStage && existingStage !== stageId && (
        Object.keys(savedStepData.value).length > 0 || lifeStageCurrentIndex.value > 0
      );

      if (hasData) {
        pendingNewStageId.value = stageId;
        showJourneyChangeModal.value = true;
        return;
      }

      await executeJourneyChange(stageId);
    };

    const confirmJourneyChange = async () => {
      showJourneyChangeModal.value = false;
      if (pendingNewStageId.value) {
        await executeJourneyChange(pendingNewStageId.value);
        pendingNewStageId.value = null;
      }
    };

    const executeJourneyChange = async (stageId) => {
      showStageMap.value = false;
      // Reset state for new journey
      savedStepData.value = {};
      sidebarOverride.value = null;
      lifeStageCurrentIndex.value = 0;
      lifeStageStarted.value = true;
      await store.dispatch('lifeStage/setStage', stageId);
      // Fetch fresh progress so steps render correctly for the new stage
      await Promise.all([
        store.dispatch('userProfile/fetchProfile').catch(() => {}),
        store.dispatch('lifeStage/fetchStage').catch(() => {}),
      ]);
    };

    // Journey Map Modal handlers
    const handleStageSelected = async (stageId) => {
      if (typeof gtag === 'function') {
        gtag('event', 'journey_select', {
          event_label: stageId,
          source: document.referrer.includes('/dashboard') ? 'dashboard' : 'homepage',
        });
      }
      // Reset state for new journey
      savedStepData.value = {};
      sidebarOverride.value = null;
      lifeStageCurrentIndex.value = 0;
      lifeStageStarted.value = true;
      await store.dispatch('lifeStage/setStage', stageId);
    };

    const closeJourneyMapModal = () => {
      showJourneyMapModal.value = false;
      selectedStageId.value = null;
    };

    const handleJourneyMapStart = async () => {
      const stageId = selectedStageId.value;
      showJourneyMapModal.value = false;
      selectedStageId.value = null;

      // Set the life stage and enter life stage onboarding mode
      await store.dispatch('lifeStage/setStage', stageId);
      lifeStageCurrentIndex.value = 0;
    };

    const handleJourneyMapPreview = () => {
      const stageConfig = LIFE_STAGES[selectedStageId.value];
      if (stageConfig?.persona) {
        showJourneyMapModal.value = false;
        selectedStageId.value = null;
        // Navigate to preview mode for this persona
        router.push({ name: 'PreviewDashboard', query: { persona: stageConfig.persona } });
      }
    };

    // ================================================================
    // LEGACY MODE (journey, quick, full, module)
    // ================================================================
    const isJourneyMode = computed(() => props.mode === 'journey');
    const isModuleMode = computed(() => props.mode === 'module');
    const isQuickMode = computed(() => {
      if (props.mode === 'full') return false;
      if (props.mode === 'module') return false;
      if (props.mode === 'journey') return false;
      if (props.mode === 'life-stage') return false;
      if (isLifeStageMode.value) return false;
      return props.mode === 'quick' || props.mode === null;
    });

    const quickSteps = [
      { name: 'personal_info', title: 'Personal Information' },
      { name: 'income', title: 'Employment & Income' },
      { name: 'quick_assets', title: 'Your Financial Picture' },
    ];

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

    const displaySteps = computed(() => {
      if (isJourneyMode.value) return journeySteps.value;
      if (isQuickMode.value) return quickSteps;
      if (isModuleMode.value) return props.moduleSteps;
      return store.state.onboarding.steps || [];
    });

    const showProgressBar = computed(() => {
      if (isLifeStageMode.value) return false;
      if (isJourneyMode.value) {
        return !showJourneyCompletion.value && journeySteps.value.length > 0;
      }
      return focusArea.value && displaySteps.value.length > 0;
    });

    const showStepContent = computed(() => {
      if (isLifeStageMode.value) return false;
      if (isJourneyMode.value) {
        return !showJourneyCompletion.value && currentStep.value;
      }
      return focusArea.value && currentStep.value;
    });

    const showSkipToDashboardLink = computed(() => {
      if (isJourneyMode.value) return !showJourneyCompletion.value;
      return !isQuickMode.value && !isCompletionStep.value;
    });

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
        'Personal Information': 'Personal',
        'Your Income': 'Income',
        'Your Monthly Outgoings': 'Spending',
        'Your Savings Accounts': 'Savings',
        'Your Property & Mortgage': 'Property',
        'Your Family & Dependants': 'Family',
        'Your Debts & Loans': 'Debts',
        'Your Existing Protection': 'Protection',
      };
      return labelMap[step.name] || step.title || step.name;
    };

    const isCompletionStep = computed(() => {
      return currentStep.value?.name === 'completion';
    });

    const resolveJourneyComponent = (step) => {
      if (!step) return null;

      const componentName = step.component;
      const fields = step.fields || [];

      if (componentName === 'SimplePersonalInfoStep') return 'SimplePersonalInfoStep';
      if (componentName === 'SimpleIncomeStep') return 'SimpleIncomeStep';
      if (componentName === 'SimpleExpenditureStep') return 'SimpleExpenditureStep';
      if (componentName === 'SimpleSavingsAccountStep') return 'SimpleSavingsAccountStep';
      if (componentName === 'SimplePropertyMortgageStep') return 'SimplePropertyMortgageStep';
      if (componentName === 'FamilyInfoStep') return 'FamilyInfoStep';
      if (componentName === 'LiabilitiesStep') return 'LiabilitiesStep';
      if (componentName === 'ProtectionPoliciesStep') return 'ProtectionPoliciesStep';
      if (componentName === 'JourneyPersonalStep') return 'PersonalInfoStep';
      if (componentName === 'BudgetingStep') return 'BudgetingSteps';

      if (componentName === 'JourneyFinancialStep') {
        if (fields.includes('family_members') || fields.includes('spouse')) return 'FamilyInfoStep';
        if (fields.includes('protection_policies')) return 'ProtectionPoliciesStep';
        if (fields.includes('mortgages') || fields.includes('properties')) return 'AssetsStep';
        if (fields.includes('liabilities')) return 'LiabilitiesStep';
        if (fields.includes('savings_accounts')) return 'QuickAssetsStep';
        if (fields.includes('wills')) return 'WillInfoStep';
        if (fields.includes('trusts')) return 'TrustInfoStep';
        if (fields.includes('pensions') || fields.includes('dc_pensions') || fields.includes('db_pensions') || fields.includes('state_pension')) return 'AssetsStep';
        if (fields.includes('investment_accounts') || fields.includes('investments')) return 'AssetsStep';
        if (fields.includes('business_interests')) return 'AssetsStep';
        if (fields.includes('goals')) return 'GoalSetupStep';
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
        await store.dispatch('onboarding/completeQuickOnboarding');
        if (typeof gtag === 'function') {
          gtag('event', 'onboarding_complete', { method: 'quick' });
        }
        await store.dispatch('auth/fetchUser', null, { root: true });
        router.push({ name: 'Dashboard' });
        return;
      }

      if (isModuleMode.value && nextIndex >= props.moduleSteps.length) {
        router.push({ name: 'Dashboard' });
        return;
      }

      if (isQuickMode.value || isModuleMode.value) {
        store.commit('onboarding/SET_CURRENT_STEP_INDEX', nextIndex);
        store.commit('onboarding/SET_CURRENT_STEP', stepsToUse[nextIndex].name);
      } else {
        await store.dispatch('onboarding/goToNextStep');
      }
    };

    const handleJourneyNext = async () => {
      const isLast = store.getters['journeys/isLastStep'];

      if (isLast) {
        await store.dispatch('journeys/completeJourney', currentJourneyName.value);
        if (typeof gtag === 'function') {
          gtag('event', 'journey_complete', { event_label: currentJourneyName.value });
        }
        showJourneyCompletion.value = true;
      } else {
        store.dispatch('journeys/nextStep');
      }
    };

    const handleJourneyCompletionNext = async () => {
      // JourneyCompletionStep handles its own navigation
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
      const isNewUser = route.query.newUser === '1';
      if (isJourneyMode.value || isLifeStageMode.value) {
        await store.dispatch('auth/fetchUser', null, { root: true });
        router.push({ name: 'Dashboard', query: isNewUser ? { newUser: '1' } : {} });
      } else {
        await store.dispatch('onboarding/skipToDashboard');
        router.push({ path: '/dashboard', query: isNewUser ? { newUser: '1' } : {} });
      }
    };

    onMounted(async () => {
      // GA4: track onboarding start
      if (typeof gtag === 'function') {
        gtag('event', 'onboarding_start', {
          source: document.referrer.includes('/dashboard') ? 'dashboard' : 'homepage',
        });
      }

      // If ?stage= is present, show the journey map for that stage instead of form steps
      const stageFromQuery = route.query?.stage;
      if (stageFromQuery && LIFE_STAGES[stageFromQuery]) {
        showStageMap.value = true;
        return;
      }

      if (isLifeStageMode.value) {
        // Pre-fetch user profile and life stage progress (including field completeness)
        await Promise.all([
          store.dispatch('userProfile/fetchProfile').catch(() => {}),
          store.dispatch('lifeStage/fetchStage').catch(() => {}),
        ]);

        // Returning user with a stage already set — skip welcome, go straight to steps
        if (currentLifeStage.value && lifeStageSteps.value.length > 0) {
          lifeStageStarted.value = true;
        }

        // Find the first uncompleted step to resume from.
        // Use allCompletedSteps (union of explicit + data-readiness) so returning
        // users with data but expired sessions don't restart from step 0.
        const steps = lifeStageSteps.value;
        const completed = store.getters['lifeStage/allCompletedSteps'] || [];
        let resumeIndex = 0;
        for (let i = 0; i < steps.length; i++) {
          if (!completed.includes(steps[i])) {
            resumeIndex = i;
            break;
          }
          if (i === steps.length - 1) {
            resumeIndex = steps.length - 1;
          }
        }

        // Honour ?step= query param from dashboard "Continue Journey" link
        const requestedStep = route.query?.step;
        if (requestedStep) {
          const idx = steps.indexOf(requestedStep);
          if (idx !== -1) resumeIndex = idx;
        }

        lifeStageCurrentIndex.value = resumeIndex;
        return;
      }

      if (isJourneyMode.value) {
        const journey = currentJourneyName.value;
        if (journey) {
          store.commit('onboarding/SET_FOCUS_AREA', journey);
          if (store.state.journeys.currentSteps.length === 0 || store.state.journeys.currentJourney !== journey) {
            await store.dispatch('journeys/fetchSteps', journey);
          }
          const journeyState = store.state.journeys.journeyStates[journey];
          if (!journeyState || journeyState === 'not_started') {
            await store.dispatch('journeys/startJourney', journey);
          }
        }
        return;
      }

      await store.dispatch('onboarding/fetchOnboardingStatus');

      if (isModuleMode.value) {
        store.commit('onboarding/SET_STEPS', props.moduleSteps);
        store.commit('onboarding/SET_CURRENT_STEP_INDEX', 0);
        store.commit('onboarding/SET_CURRENT_STEP', props.moduleSteps[0]?.name);
        if (!focusArea.value) {
          store.commit('onboarding/SET_FOCUS_AREA', 'estate');
        }
      } else {
        store.commit('onboarding/SET_FOCUS_AREA', null);
        store.commit('onboarding/SET_CURRENT_STEP_INDEX', 0);
        store.commit('onboarding/SET_CURRENT_STEP', null);
      }
    });

    watch(() => route.params?.journey, async (newJourney) => {
      if (isJourneyMode.value && newJourney) {
        showJourneyCompletion.value = false;
        store.commit('onboarding/SET_FOCUS_AREA', newJourney);
        await store.dispatch('journeys/fetchSteps', newJourney);
      }
    });

    return {
      // Life stage mode
      isLifeStageMode,
      currentLifeStage,
      lifeStageSteps,
      lifeStageCompletedSteps,
      lifeStageCurrentIndex,
      lifeStageCurrentStepId,
      stepHasOwnNav,
      lifeStageCurrentComponent,
      savedStepData,
      sidebarOverride,
      currentStepRef,
      triggerStepContinue,
      currentStepResources,
      currentDidYouKnow,
      handleFormFieldFocus,
      stageColour,
      stageColourClasses,
      getLifeStageStepStatus,
      getStepCompletenessPercentage,
      isLifeStageCurrentStep,
      getLifeStageStepCircleClass,
      getLifeStageStepLabelClass,
      getLifeStageStepLabel,
      getLifeStageConnectingLineClass,
      handleLifeStageNext,
      handleLifeStageBack,
      goToStep,
      handleLifeStageSkip,
      handleLifeStageStepSave,

      // Journey map modal
      showJourneyMapModal,
      showStageMap,
      handleStageMapStart,
      selectedStageId,
      selectedStageConfig,
      handleStageSelected,
      closeJourneyMapModal,
      handleJourneyMapStart,
      handleJourneyMapPreview,

      // Legacy mode
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
      showJourneyChangeModal,
      currentLifeStageLabel,
      confirmJourneyChange,
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

.journey-node-pulse {
  animation: nodePulse 2s ease-in-out infinite;
}

/* TODO: similar glow animation exists in JourneyMap.vue (nodeGlow).
   That one uses filter:drop-shadow; this uses box-shadow — kept separate intentionally. */
@keyframes nodePulse {
  0%, 100% {
    box-shadow: 0 0 0 0 currentColor;
  }
  50% {
    box-shadow: 0 0 0 6px transparent;
  }
}

.onboarding-page {
  @apply bg-gradient-to-br from-horizon-500 to-raspberry-500;
}
</style>
