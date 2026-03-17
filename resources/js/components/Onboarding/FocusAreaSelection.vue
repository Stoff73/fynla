<template>
  <div class="max-w-5xl mx-auto">
    <!-- STATE 1: Stage Selection (no stage chosen yet) -->
    <div v-if="!selectedStage" class="bg-white rounded-lg border border-light-gray shadow-sm p-6 mb-6">
      <!-- Welcome Header -->
      <div class="flex items-center justify-between mb-8">
        <div>
          <h1 class="text-h2 font-display text-horizon-500 mb-2">
            Where are you in your financial journey?
          </h1>
          <p class="text-body text-neutral-500">
            Choose the stage that best describes where you are right now.
          </p>
        </div>
        <img :src="logoImage" alt="Fynla" class="h-24 w-auto hidden sm:block">
      </div>

      <!-- Life Stage Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-8">
        <button
          v-for="stage in stages"
          :key="stage.id"
          type="button"
          class="card group text-left transition-all duration-200 hover:shadow-md hover:-translate-y-1 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-violet-500 cursor-pointer"
          :class="stageCardBorderClass(stage)"
          @click="selectStage(stage.id)"
        >
          <div class="flex justify-center mb-4">
            <div
              class="w-14 h-14 rounded-full flex items-center justify-center"
              :class="stageIconBgClass(stage)"
            >
              <component :is="stageIconComponent(stage)" class="w-7 h-7 text-white" />
            </div>
          </div>
          <h3 class="text-base font-bold text-horizon-500 text-center mb-1">{{ stage.label }}</h3>
          <p class="text-sm font-semibold text-center mb-2" :class="stageTextColourClass(stage)">
            Ages {{ stage.ageRange }}
          </p>
          <p class="text-xs text-neutral-500 text-center leading-relaxed">{{ stage.tagline }}</p>
        </button>
      </div>

      <!-- Divider -->
      <div class="relative mb-6">
        <div class="absolute inset-0 flex items-center">
          <div class="w-full border-t border-light-gray"></div>
        </div>
        <div class="relative flex justify-center">
          <span class="bg-white px-4 text-sm text-neutral-500">or focus on a specific area</span>
        </div>
      </div>

      <!-- Focus Area Pills -->
      <div class="flex flex-wrap justify-center gap-3 mb-6">
        <button
          v-for="area in focusAreas"
          :key="area.id"
          type="button"
          class="inline-flex items-center px-5 py-2.5 rounded-full border border-light-gray text-sm font-medium text-horizon-500 bg-white hover:bg-savannah-100 hover:border-savannah-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-violet-500 transition-all duration-200"
          @click="handleFocusClick(area.id)"
        >
          {{ area.label }}
        </button>
      </div>

      <!-- Skip -->
      <div class="text-center">
        <button
          type="button"
          class="inline-flex items-center text-sm font-medium text-neutral-500 hover:text-raspberry-500 transition-colors"
          @click="skipOnboarding"
        >
          Skip to Dashboard
          <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
          </svg>
        </button>
      </div>
    </div>

    <!-- STATE 2: Journey Map (stage selected, shown inline) -->
    <div v-else class="bg-white rounded-lg border border-light-gray shadow-sm overflow-hidden mb-6">
      <!-- Stage hero header -->
      <div
        class="px-8 py-6 text-white text-center"
        :style="{ background: `linear-gradient(135deg, ${stageHex}, ${stageHexLight})` }"
      >
        <div class="text-3xl mb-1">{{ selectedStageConfig.icon === 'graduation-cap' ? '🎓' : selectedStageConfig.icon === 'briefcase' ? '🏠' : selectedStageConfig.icon === 'shield' ? '👨‍👩‍👧‍👦' : selectedStageConfig.icon === 'chart-line' ? '📈' : '🌅' }}</div>
        <h2 class="text-xl font-black mb-1">{{ selectedStageConfig.label }}</h2>
        <p class="text-sm opacity-90">{{ selectedStageConfig.tagline }}</p>
        <p class="text-xs opacity-70 mt-1">{{ stageSteps.length }} steps · About {{ stageSteps.length * 2 }} minutes</p>
      </div>

      <!-- Journey Map SVG — matching approved v6 mockup -->
      <div class="px-6 pt-8 pb-4">
        <svg viewBox="0 0 900 540" class="w-full" style="height: 540px;" preserveAspectRatio="xMidYMid meet">
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

          <!-- Shadow path (v6: stage colour at 12% alpha) -->
          <path :d="pathD" fill="none" :stroke="stageHex + '12'" stroke-width="8" stroke-linecap="round" />

          <!-- Main dashed path -->
          <path :d="pathD" fill="none" stroke="url(#journeyPathGrad)" stroke-width="3" stroke-dasharray="8,6" stroke-linecap="round" />

          <!-- Destination connector -->
          <path v-if="destinationPathD" :d="destinationPathD" fill="none" stroke="#20B48640" stroke-width="3" stroke-dasharray="8,6" stroke-linecap="round" />

          <!-- Step nodes -->
          <g v-for="(node, i) in nodes" :key="'node-' + i">
            <!-- Node circle -->
            <circle
              v-if="!node.isDestination"
              :cx="node.x" :cy="node.y" r="22"
              :fill="stageHex"
              :opacity="1 - (i * 0.1)"
              :filter="i === 0 ? 'url(#journeyGlow)' : undefined"
              class="cursor-pointer"
              @click="selectNode(i)"
            />
            <!-- Destination circle -->
            <circle
              v-else
              :cx="node.x" :cy="node.y" r="24"
              fill="#20B486"
              filter="url(#journeyGlow)"
            />

            <!-- Step number -->
            <text
              v-if="!node.isDestination"
              :x="node.x" :y="node.y + 5"
              text-anchor="middle" fill="white" font-weight="800" font-size="14"
            >{{ i + 1 }}</text>

            <!-- Destination flag -->
            <text
              v-if="node.isDestination"
              :x="node.x" :y="node.y + 5"
              text-anchor="middle" fill="white" font-size="16"
            >🏁</text>

            <!-- Label -->
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

      <!-- Detail card when a node is tapped -->
      <div v-if="selectedNodeIndex !== null && selectedMilestone" class="mx-6 mb-4 p-5 rounded-xl border-2" :class="detailCardClass">
        <div class="flex items-center gap-3 mb-2">
          <div class="w-7 h-7 rounded-full text-white text-xs font-bold flex items-center justify-center" :class="detailNodeClass">
            {{ selectedNodeIndex + 1 }}
          </div>
          <div class="text-sm font-bold text-horizon-500">{{ nodes[selectedNodeIndex]?.title }}</div>
          <div class="text-xs text-neutral-500 ml-auto">Tap any step to learn more</div>
        </div>
        <p class="text-sm text-neutral-500 leading-relaxed">{{ selectedMilestone.didYouKnow }}</p>
      </div>

      <!-- CTAs -->
      <div class="px-8 pb-6 text-center">
        <div class="flex gap-3 justify-center pt-5 border-t border-light-gray">
          <button
            class="bg-raspberry-500 hover:bg-raspberry-600 text-white px-8 py-3 rounded-lg font-bold text-sm transition-colors"
            @click="startJourney"
          >
            Start My Journey
          </button>
          <button
            class="bg-white hover:bg-savannah-100 text-horizon-500 px-8 py-3 rounded-lg font-bold text-sm border border-light-gray transition-colors"
            @click="seeInAction"
          >
            See It in Action
          </button>
        </div>
        <p class="text-xs text-neutral-500 mt-3">You can skip steps and come back to them later</p>
        <button class="text-xs text-neutral-500 hover:text-horizon-500 mt-2" @click="selectedStage = null">
          ← Choose a different stage
        </button>
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
      focusAreas: [
        { id: 'budgeting', label: 'Cash & Budget' },
        { id: 'protection', label: 'Protection' },
        { id: 'investment', label: 'Investment' },
        { id: 'retirement', label: 'Retirement' },
      ],
    };
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

      // Exact v6 approved positions for 6 steps
      const v6Positions = [
        // Node 1: top, label BELOW (28px gap: node bottom 112, title at 140)
        { x: 100, y: 90, labelX: 100, labelY: 140, labelAnchor: 'middle' },
        // Node 2: bottom, label ABOVE (28px gap: node top 238, last line at 210)
        { x: 340, y: 260, labelX: 340, labelY: 182, labelAnchor: 'middle' },
        // Node 3: top, label BELOW
        { x: 580, y: 90, labelX: 580, labelY: 140, labelAnchor: 'middle' },
        // Node 4: right, label LEFT (28px gap: node left 768, label right ~740)
        { x: 790, y: 280, labelX: 740, labelY: 275, labelAnchor: 'end' },
        // Node 5: lower right, label BELOW (28px gap: node bottom 472, title at 500)
        { x: 770, y: 450, labelX: 770, labelY: 500, labelAnchor: 'middle' },
        // Node 6: return, label ABOVE (28px gap: node top 348, last line at 320)
        { x: 530, y: 370, labelX: 530, labelY: 292, labelAnchor: 'middle' },
      ];

      // Destination: exact v6
      const v6Destination = { x: 350, y: 430, labelX: 390, labelY: 426, labelAnchor: 'start' };

      const result = [];
      const count = Math.min(steps.length, v6Positions.length);

      for (let i = 0; i < count; i++) {
        const pos = v6Positions[i];
        const meta = stepTitles[steps[i]] || { title: steps[i], subtitle: '' };
        result.push({
          ...pos,
          title: meta.title,
          subtitle: meta.subtitle.split('\n')[0], // first line only for SVG
          isDestination: false,
        });
      }

      // Destination
      result.push({
        ...v6Destination,
        title: 'Your Dashboard',
        subtitle: 'Personalised to your stage',
        isDestination: true,
      });

      return result;
    },

    // Exact v6 mockup dimensions
    svgWidth() { return 900; },
    svgHeight() { return 540; },
    svgViewBoxX() { return 0; },

    // Exact path from approved v6 mockup
    pathD() {
      return 'M 100,90 C 210,90 230,260 340,260 C 450,260 470,90 580,90 C 690,90 750,210 790,280 C 830,340 820,420 770,450 C 720,480 620,390 530,370';
    },

    destinationPathD() {
      return 'M 530,370 C 460,355 400,390 350,430';
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

    handleFocusClick(areaId) {
      this.$emit('focus-selected', areaId);
      this.$emit('selected', areaId);
    },

    skipOnboarding() {
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
