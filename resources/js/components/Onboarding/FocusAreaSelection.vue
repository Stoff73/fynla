<template>
  <div class="max-w-6xl mx-auto">
    <!-- STATE 1: Stage Selection (no stage chosen yet) -->
    <div v-if="!selectedStage" class="onboarding-selection-card rounded-xl sm:rounded-2xl border border-light-gray p-5 sm:p-8 lg:p-10 mb-6">
      <!-- Welcome Header — no logo -->
      <div class="mb-6">
        <h1 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 mb-2">
          Welcome to Fynla
        </h1>
        <p class="text-body text-neutral-500">
          Your personal financial companion — let's get you set up.
        </p>
      </div>

      <!-- Onboarding Intro — light pink box -->
      <div class="bg-light-pink-100 border border-light-gray rounded-lg p-5 mb-8">
        <p class="text-body-sm text-horizon-500 mb-3">
          In just a few minutes, we'll build a personalised picture of your finances. By the end, you'll have:
        </p>
        <ul class="space-y-2 text-body-sm text-neutral-500">
          <li class="flex items-start">
            <svg class="w-4 h-4 text-spring-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
            A clear view of your income, spending, and savings
          </li>
          <li class="flex items-start">
            <svg class="w-4 h-4 text-spring-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
            Personalised insights tailored to your life stage
          </li>
          <li class="flex items-start">
            <svg class="w-4 h-4 text-spring-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
            Actionable goals and a dashboard to track your progress
          </li>
          <li class="flex items-start">
            <svg class="w-4 h-4 text-spring-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
            Links to helpful resources from Gov.uk, MoneyHelper, and more
          </li>
        </ul>
        <p class="text-body-sm text-neutral-500 mt-3">
          You can skip any step and come back to it later — nothing is locked in.
        </p>
      </div>

      <!-- Stage Selection -->
      <h2 class="text-lg font-bold text-horizon-500 mb-4">Where are you in your financial journey?</h2>

      <!-- Life Stage Cards — homepage style -->
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4 mb-8">
        <button
          v-for="stage in stages"
          :key="stage.id"
          type="button"
          class="stage-card group text-left focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-violet-500"
          @click="selectStage(stage.id)"
        >
          <p class="text-base sm:text-lg font-bold text-white mb-1 leading-tight">
            <span class="text-raspberry-400">{{ stage.label.split(' ')[0] }}</span><br>{{ stage.label.split(' ').slice(1).join(' ') }}
          </p>
          <p class="text-xs text-white/70 leading-snug flex-1">{{ stage.tagline }}</p>
          <span class="inline-flex items-center gap-1 text-xs font-semibold text-raspberry-400 mt-2 group-hover:text-white transition-colors">
            Start here
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg>
          </span>
        </button>
      </div>

      <!-- Skip + Back -->
      <div class="flex items-center justify-between mt-2">
        <router-link
          to="/dashboard"
          class="inline-flex items-center px-5 py-2.5 bg-light-pink-100 hover:bg-[#FFE0E6] text-horizon-500 rounded-lg font-bold text-sm transition-colors gap-1.5"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
          Back
        </router-link>
        <div class="text-sm text-neutral-500">
          Alternatively, you can skip and go straight to
          <button
            type="button"
            class="text-raspberry-500 hover:text-raspberry-700 underline font-medium transition-colors"
            @click="skipOnboarding"
          >your dashboard</button>.
        </div>
      </div>
    </div>

    <!-- STATE 2: Journey Map (stage selected, shown inline) -->
    <div v-else class="bg-white rounded-lg border border-light-gray shadow-sm overflow-hidden mb-6">
      <!-- Stage hero — horizon blue gradient, no icon -->
      <div class="px-8 py-6 bg-gradient-to-br from-horizon-500 to-horizon-600 text-white">
        <h2 class="text-xl sm:text-2xl md:text-3xl font-bold mb-1 text-left text-white">{{ selectedStageConfig.label }}</h2>
        <p class="text-sm opacity-90 text-left text-white">{{ selectedStageConfig.tagline }}</p>
      </div>

      <!-- Steps info — centred, larger -->
      <div class="py-5 px-8 text-center border-b border-light-gray">
        <p class="text-lg sm:text-xl font-bold text-horizon-500">{{ stageSteps.length }} steps · Approx {{ stageSteps.length * 2 }} minutes</p>
        <p class="text-sm text-neutral-500 mt-1">You can skip steps and come back to them later</p>
      </div>

      <!-- Journey Map SVG -->
      <div class="px-6 pt-8 pb-4">
        <svg :viewBox="svgViewBoxX + ' 0 ' + svgWidth + ' ' + svgHeight" class="w-full" :style="{ height: svgHeight + 'px' }" preserveAspectRatio="xMidYMid meet">
          <defs>
            <linearGradient id="journeyPathGrad" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" :stop-color="stageHex" />
              <stop offset="70%" :stop-color="stageHex + '80'" />
              <stop offset="100%" stop-color="#20B486" />
            </linearGradient>
            <filter id="journeyGlow">
              <feGaussianBlur stdDeviation="3" result="blur" />
              <feMerge><feMergeNode in="blur" /><feMergeNode in="SourceGraphic" /></feMerge>
            </filter>
          </defs>

          <!-- Shadow path -->
          <path :d="pathD" fill="none" :stroke="stageHex + '12'" stroke-width="8" stroke-linecap="round" />

          <!-- Main dashed path -->
          <path :d="pathD" fill="none" stroke="url(#journeyPathGrad)" stroke-width="3" stroke-dasharray="8,6" stroke-linecap="round" />

          <!-- Destination connector -->
          <path v-if="destinationPathD" :d="destinationPathD" fill="none" stroke="#20B48640" stroke-width="3" stroke-dasharray="8,6" stroke-linecap="round" />

          <!-- Step nodes — full opacity -->
          <g v-for="(node, i) in nodes" :key="'node-' + i">
            <circle
              v-if="!node.isDestination"
              :cx="node.x" :cy="node.y" r="22"
              :fill="stageHex"
              :filter="i === 0 ? 'url(#journeyGlow)' : undefined"
              class="cursor-pointer"
              @click="selectNode(i)"
            />
            <circle
              v-else
              :cx="node.x" :cy="node.y" r="24"
              fill="#20B486"
              filter="url(#journeyGlow)"
            />

            <text
              v-if="!node.isDestination"
              :x="node.x" :y="node.y + 5"
              text-anchor="middle" fill="white" font-weight="800" font-size="14"
            >{{ i + 1 }}</text>

            <text
              v-if="node.isDestination"
              :x="node.x" :y="node.y + 5"
              text-anchor="middle" fill="white" font-size="16"
            >🏁</text>

            <text
              :x="node.labelX" :y="node.labelY"
              :text-anchor="node.labelAnchor"
              :fill="node.isDestination ? '#20B486' : '#1F2A44'"
              font-weight="700" font-size="12"
            >{{ node.title }}</text>
            <text
              :x="node.labelX" :y="node.labelY + 14"
              :text-anchor="node.labelAnchor"
              fill="#717171" font-size="10"
            >{{ node.subtitle }}</text>
          </g>
        </svg>
      </div>

      <!-- Detail card — light pink, always visible -->
      <div class="mx-6 mb-4 p-5 rounded-xl bg-light-pink-100">
        <div class="flex items-center gap-3 mb-2">
          <div class="w-7 h-7 rounded-full bg-raspberry-500 text-white text-xs font-bold flex items-center justify-center">
            {{ (selectedNodeIndex !== null ? selectedNodeIndex : 0) + 1 }}
          </div>
          <div class="text-sm font-bold text-horizon-500">{{ nodes[selectedNodeIndex !== null ? selectedNodeIndex : 0]?.title }}</div>
          <div class="text-xs text-neutral-500 italic ml-auto">Explore each step on the map above</div>
        </div>
        <p class="text-sm text-horizon-500 leading-relaxed mb-3">
          {{ selectedMilestone ? selectedMilestone.didYouKnow : (nodes[0]?.title ? 'Click on any step in the journey map above to learn what it covers and what information you\'ll need.' : '') }}
        </p>

        <!-- Collapsible "What you'll need" -->
        <div class="rounded-lg overflow-hidden">
          <button
            type="button"
            class="w-full flex items-center gap-2 px-3.5 py-2.5 bg-white/60 hover:bg-white/85 rounded-lg transition-colors text-left"
            @click="showNeeds = !showNeeds"
          >
            <span class="text-sm font-semibold text-horizon-500">What you'll need for this step</span>
            <svg
              class="w-4 h-4 text-neutral-500 ml-auto transition-transform duration-200"
              :class="{ 'rotate-180': showNeeds }"
              fill="none" stroke="currentColor" viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>
          <div v-if="showNeeds" class="px-3.5 pt-2.5 pb-1">
            <ul class="space-y-1">
              <li v-for="(need, ni) in currentStepNeeds" :key="ni" class="flex items-center gap-2 text-sm text-neutral-500">
                <span class="w-1.5 h-1.5 rounded-full bg-raspberry-400 flex-shrink-0"></span>
                {{ need }}
              </li>
            </ul>
          </div>
        </div>
      </div>

      <!-- CTAs — light pink Back + Start My Journey with chevron -->
      <div class="px-8 pb-6 text-center">
        <div class="flex gap-3 justify-center pt-5 border-t border-light-gray">
          <button
            class="bg-light-pink-100 hover:bg-[#FFE0E6] text-horizon-500 px-6 py-3 rounded-lg font-bold text-sm transition-colors inline-flex items-center gap-1.5"
            @click="selectedStage = null"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            Back
          </button>
          <button
            class="bg-raspberry-500 hover:bg-raspberry-600 text-white px-8 py-3 rounded-lg font-bold text-sm transition-colors inline-flex items-center gap-1.5"
            @click="startJourney"
          >
            Start My Journey
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { LIFE_STAGES, STAGE_ORDER, PERSONA_TO_STAGE } from '@/constants/lifeStageConfig';

export default {
  name: 'FocusAreaSelection',

  emits: ['stage-selected', 'focus-selected', 'selected'],

  data() {
    return {
      logoImage: '/images/logos/LogoHiResFynlaDark.png',
      selectedStage: null,
      selectedNodeIndex: null,
      showNeeds: false,
    };
  },

  mounted() {
    // Auto-select stage if user came from a stage page (e.g. /onboarding?stage=early_career)
    const stageFromQuery = this.$route.query?.stage;
    if (stageFromQuery && LIFE_STAGES[stageFromQuery]) {
      this.selectedStage = stageFromQuery;
    }
  },

  computed: {
    stages() {
      return STAGE_ORDER.map(id => LIFE_STAGES[id]);
    },

    selectedStageConfig() {
      return this.selectedStage ? LIFE_STAGES[this.selectedStage] : null;
    },

    stageSteps() {
      return this.selectedStageConfig?.onboarding?.steps || [];
    },

    stageHex() {
      const map = {
        violet: '#5854E6', spring: '#20B486', raspberry: '#E83E6D',
        'light-blue': '#6C83BC', horizon: '#1F2A44',
      };
      return map[this.selectedStageConfig?.colour] || '#5854E6';
    },

    stageHexLight() {
      const map = {
        violet: '#7c79ff', spring: '#2dd49e', raspberry: '#ff5a8a',
        'light-blue': '#8fa3d0', horizon: '#3a4a6a',
      };
      return map[this.selectedStageConfig?.colour] || '#7c79ff';
    },

    currentStepNeeds() {
      const needsMap = {
        'Your profile': ['Your date of birth', 'Employment status and occupation', 'Whether you have a partner or dependents'],
        'Income': ['Your annual salary or self-employment income', 'Any additional income sources (rental, dividends)'],
        'Spending': ['Monthly household bills estimate', 'Regular commitments (subscriptions, memberships)'],
        'Protection': ['Details of any life insurance policies', 'Critical illness or income protection cover'],
        'Savings': ['Current savings account balances', 'ISA balances and types'],
        'Property': ['Property value and mortgage balance', 'Mortgage interest rate and term remaining'],
        'Investments': ['Investment account values', 'Fund names or platform details'],
        'Retirement': ['Pension scheme details and current values', 'State pension forecast (from Gov.uk)'],
        'Estate': ['Whether you have a will', 'Any gifts or trust arrangements'],
      };
      const idx = this.selectedNodeIndex !== null ? this.selectedNodeIndex : 0;
      const title = this.nodes[idx]?.title;
      return needsMap[title] || ['Information related to this topic'];
    },

    // Exact coordinates from approved v6 mockup for "Starting Out" (6 steps).
    // viewBox 0 0 900 540. Every position, label, and anchor matches the approved HTML.
    nodes() {
      const steps = this.stageSteps;
      if (!steps.length) return [];

      const stepTitles = {
        'personal-info': { title: 'About You', subtitle: 'Age, situation &\ncircumstances' },
        'student-loan': { title: 'Student Loan', subtitle: 'Plan type, balance\n& repayment' },
        'income': { title: 'Your Income', subtitle: 'Part-time, placement\n& support' },
        'income-career': { title: 'Income & Career', subtitle: 'Salary & growth' },
        'income-tax': { title: 'Income & Tax', subtitle: 'Tax position' },
        'expenditure': { title: 'Your Spending', subtitle: 'Track where your\nmoney goes' },
        'savings': { title: 'Your Savings', subtitle: 'ISA, LISA & safety net' },
        'savings-emergency': { title: 'Emergency Fund', subtitle: 'Safety net' },
        'first-home-lisa': { title: 'First Home', subtitle: 'LISA & deposit' },
        'investments': { title: 'Investments', subtitle: 'Portfolio' },
        'investments-isa': { title: 'Investments', subtitle: 'ISA & portfolio' },
        'goals': { title: 'Your Goals', subtitle: 'Targets that give\nmoney purpose' },
        'family': { title: 'Family', subtitle: 'Dependants' },
        'property-mortgage': { title: 'Property', subtitle: 'Home & mortgage' },
        'property-portfolio': { title: 'Property', subtitle: 'Portfolio' },
        'protection-insurance': { title: 'Protection', subtitle: 'Your cover' },
        'pensions': { title: 'Pensions', subtitle: 'Pension pots' },
        'pension-auto-enrolment': { title: 'Pension', subtitle: 'Auto-enrolment' },
        'pension-review': { title: 'Pension Review', subtitle: 'Consolidate' },
        'pension-drawdown': { title: 'Drawdown', subtitle: 'Income strategy' },
        'state-pension': { title: 'State Pension', subtitle: 'Forecast' },
        'will-estate': { title: 'Will & Estate', subtitle: 'Estate planning' },
        'estate-iht': { title: 'Estate', subtitle: 'Inheritance Tax' },
        'estate-legacy': { title: 'Estate & Legacy', subtitle: 'Your legacy' },
      };

      // Base 6 nodes from approved v6 mockup (shared by all step counts)
      const baseNodes = [
        // Node 1: top, label BELOW (28px gap)
        { x: 100, y: 90, labelX: 100, labelY: 140, labelAnchor: 'middle' },
        // Node 2: bottom, label ABOVE (28px gap)
        { x: 340, y: 260, labelX: 340, labelY: 182, labelAnchor: 'middle' },
        // Node 3: top, label BELOW
        { x: 580, y: 90, labelX: 580, labelY: 140, labelAnchor: 'middle' },
        // Node 4: right drop, label LEFT (28px gap)
        { x: 790, y: 280, labelX: 740, labelY: 275, labelAnchor: 'end' },
        // Node 5: lower right, label BELOW (28px gap)
        { x: 770, y: 450, labelX: 770, labelY: 500, labelAnchor: 'middle' },
        // Node 6: return left, label ABOVE (28px gap)
        { x: 530, y: 370, labelX: 530, labelY: 292, labelAnchor: 'middle' },
      ];

      // Extended node for 7 steps: continues curve DOWN-LEFT from node 6 (530,370)
      // Stays BELOW the horizontal meander — no path crossing
      // Label ABOVE node (28px gap: node top 440-22=418, title at 390)
      // Above keeps it clear of destination label below
      const node7Down = { x: 280, y: 440, labelX: 280, labelY: 362, labelAnchor: 'middle' };

      // Extended node for 8 steps: from node 7 (280,440), curves UP-LEFT to (100,350)
      // More horizontal separation from node 1 (x=100 vs x=100 but y=350 vs y=90 — clear)
      // Label BELOW node (28px gap: node bottom 350+22=372, title at 400)
      const node8Up = { x: 100, y: 350, labelX: 100, labelY: 400, labelAnchor: 'middle' };

      // Destinations — well below all step labels
      const destinations = {
        6: { x: 350, y: 430, labelX: 390, labelY: 426, labelAnchor: 'start' },
        7: { x: 100, y: 510, labelX: 140, labelY: 506, labelAnchor: 'start' },
        8: { x: 100, y: 470, labelX: 140, labelY: 466, labelAnchor: 'start' },
      };

      // Select positions based on step count
      const stepCount = steps.length;
      let positions;
      if (stepCount <= 6) {
        positions = baseNodes.slice(0, stepCount);
      } else if (stepCount === 7) {
        positions = [...baseNodes, node7Down];
      } else {
        positions = [...baseNodes, node7Down, node8Up];
      }

      const dest = destinations[Math.min(stepCount, 8)] || destinations[6];

      const result = [];
      for (let i = 0; i < positions.length; i++) {
        const pos = positions[i];
        const meta = stepTitles[steps[i]] || { title: steps[i], subtitle: '' };
        result.push({
          ...pos,
          title: meta.title,
          subtitle: meta.subtitle.split('\n')[0],
          isDestination: false,
        });
      }

      // Destination
      result.push({
        ...dest,
        title: 'Your Dashboard',
        subtitle: 'Personalised to your stage',
        isDestination: true,
      });

      return result;
    },

    svgWidth() { return 900; },
    svgHeight() {
      const count = this.stageSteps.length;
      if (count <= 6) return 540;
      if (count === 7) return 560;
      return 540;
    },
    svgViewBoxX() { return this.stageSteps.length >= 8 ? -20 : 0; },

    // Paths per step count — all smooth cubic beziers, no sharp turns
    pathD() {
      // Base v6 path (6 nodes)
      const base = 'M 100,90 C 210,90 230,260 340,260 C 450,260 470,90 580,90 C 690,90 750,210 790,280 C 830,340 820,420 770,450 C 720,480 620,390 530,370';
      const stepCount = this.stageSteps.length;

      if (stepCount <= 6) return base;
      // 7 steps: extend from node 6 (530,370) curving DOWN-LEFT to node 7 (280,440)
      if (stepCount === 7) return base + ' C 430,390 360,430 280,440';
      // 8 steps: extend further from node 7 (280,440) with gentle curve UP-LEFT to node 8 (100,350)
      // C1 (190,448) continues the left-down momentum from node 7
      // C2 (130,380) gradually lifts toward node 8 — smooth, no sharp turn
      return base + ' C 430,390 360,430 280,440 C 190,448 130,380 100,350';
    },

    destinationPathD() {
      const stepCount = this.stageSteps.length;
      // 6 steps: from node 6 (530,370) to dest (350,430)
      if (stepCount <= 6) return 'M 530,370 C 460,355 400,390 350,430';
      // 7 steps: from node 7 (280,440) to dest (100,500)
      if (stepCount === 7) return 'M 280,440 C 200,460 140,480 100,500';
      // 8 steps: from node 8 (100,350) to dest (100,470)
      return 'M 100,350 C 90,390 95,440 100,470';
    },

    selectedMilestone() {
      if (this.selectedNodeIndex === null || !this.selectedStageConfig) return null;
      const stepId = this.stageSteps[this.selectedNodeIndex];
      return this.selectedStageConfig.onboarding?.learningMilestones?.[stepId] || null;
    },

    detailCardClass() {
      const map = {
        violet: 'bg-violet-50 border-violet-200',
        spring: 'bg-spring-50 border-spring-200',
        raspberry: 'bg-raspberry-50 border-raspberry-200',
        'light-blue': 'bg-light-blue-100 border-horizon-200',
        horizon: 'bg-horizon-50 border-horizon-200',
      };
      return map[this.selectedStageConfig?.colour] || 'bg-violet-50 border-violet-200';
    },

    detailNodeClass() {
      const map = {
        violet: 'bg-violet-500',
        spring: 'bg-spring-500',
        raspberry: 'bg-raspberry-500',
        'light-blue': 'bg-light-blue-500',
        horizon: 'bg-horizon-500',
      };
      return map[this.selectedStageConfig?.colour] || 'bg-violet-500';
    },
  },

  methods: {
    selectStage(stageId) {
      this.selectedStage = stageId;
      this.selectedNodeIndex = null;
      // Persist stage selection immediately so it's saved even if user skips or times out
      this.$store.dispatch('lifeStage/setStage', stageId).catch(() => {});
    },

    selectNode(index) {
      this.selectedNodeIndex = this.selectedNodeIndex === index ? null : index;
    },

    startJourney() {
      this.$emit('stage-selected', this.selectedStage);
    },

    seeInAction() {
      // Find persona for this stage and enter preview
      const personaMap = {};
      Object.entries(PERSONA_TO_STAGE).forEach(([persona, stage]) => {
        if (!personaMap[stage]) personaMap[stage] = persona;
      });
      const persona = personaMap[this.selectedStage];
      if (persona) {
        this.$store.dispatch('preview/enterPreviewMode', persona);
      }
    },

    async skipOnboarding() {
      // Save selected stage before navigating away so dashboard shows the right journey
      if (this.selectedStage) {
        await this.$store.dispatch('lifeStage/setStage', this.selectedStage).catch(() => {});
      }
      this.$router.push({ name: 'Dashboard' });
    },

    stageCardBorderClass(stage) {
      const map = {
        violet: 'hover:border-violet-400',
        spring: 'hover:border-spring-400',
        raspberry: 'hover:border-raspberry-400',
        'light-blue': 'hover:border-light-blue-500',
        horizon: 'hover:border-horizon-400',
      };
      return map[stage.colour] || 'hover:border-violet-400';
    },

    stageIconBgClass(stage) {
      const map = {
        violet: 'bg-gradient-to-br from-violet-400 to-violet-600',
        spring: 'bg-gradient-to-br from-spring-400 to-spring-600',
        raspberry: 'bg-gradient-to-br from-raspberry-400 to-raspberry-600',
        'light-blue': 'bg-gradient-to-br from-light-blue-500 to-horizon-400',
        horizon: 'bg-gradient-to-br from-horizon-400 to-horizon-600',
      };
      return map[stage.colour] || 'bg-gradient-to-br from-violet-400 to-violet-600';
    },

    stageTextColourClass(stage) {
      const map = {
        violet: 'text-violet-500',
        spring: 'text-spring-500',
        raspberry: 'text-raspberry-500',
        'light-blue': 'text-light-blue-500',
        horizon: 'text-horizon-500',
      };
      return map[stage.colour] || 'text-violet-500';
    },

    stageIconComponent() {
      // Simple circle — the icon components were causing Vue warnings
      return 'span';
    },
  },
};
</script>

<style scoped>
.onboarding-selection-card {
  background: linear-gradient(180deg, #FFFFFF 0%, #F3F3F3 100%);
  box-shadow: 0 4px 24px rgba(0, 0, 0, 0.12), 0 8px 40px rgba(0, 0, 0, 0.08);
}

.stage-card {
  @apply bg-gradient-to-br from-horizon-600 to-horizon-700 rounded-card border border-white/10 p-4 sm:p-5 flex flex-col items-start justify-center cursor-pointer transition-all duration-200;
}
@media (min-width: 1024px) {
  .stage-card {
    aspect-ratio: 1;
  }
}
.stage-card:hover {
  @apply border-white/30 -translate-y-0.5 shadow-lg;
}
</style>
