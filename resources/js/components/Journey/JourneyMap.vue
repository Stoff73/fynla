<template>
  <div class="journey-map">
    <!-- SVG Map -->
    <div class="relative overflow-x-auto">
      <svg
        :viewBox="svgViewBox"
        class="w-full"
        :style="{ minWidth: svgMinWidth + 'px', minHeight: svgHeight + 'px' }"
        preserveAspectRatio="xMidYMid meet"
      >
        <defs>
          <!-- Stage colour gradient for path -->
          <linearGradient :id="gradientId" x1="0%" y1="0%" x2="100%" y2="0%">
            <stop offset="0%" :stop-color="stageHexColour" />
            <stop offset="100%" stop-color="#20B486" />
          </linearGradient>

          <!-- Glow filter for current node -->
          <filter :id="glowFilterId" x="-50%" y="-50%" width="200%" height="200%">
            <feGaussianBlur stdDeviation="4" result="blur" />
            <feMerge>
              <feMergeNode in="blur" />
              <feMergeNode in="SourceGraphic" />
            </feMerge>
          </filter>
        </defs>

        <!-- Shadow path (depth effect) -->
        <path
          :d="pathD"
          fill="none"
          stroke="#000"
          stroke-width="8"
          stroke-opacity="0.08"
          stroke-linecap="round"
          stroke-dasharray="8,6"
        />

        <!-- Main dashed path -->
        <path
          :d="pathD"
          fill="none"
          :stroke="`url(#${gradientId})`"
          stroke-width="3"
          stroke-linecap="round"
          stroke-dasharray="8,6"
        />

        <!-- Step nodes -->
        <g v-for="(node, index) in nodePositions" :key="'node-' + index">
          <!-- Node circle -->
          <circle
            :cx="node.x"
            :cy="node.y"
            :r="nodeRadius"
            :fill="getNodeFill(index)"
            :stroke="getNodeStroke(index)"
            stroke-width="2"
            :opacity="getNodeOpacity(index)"
            :filter="isCurrentNode(index) ? `url(#${glowFilterId})` : undefined"
            :class="[
              mode === 'preview' ? 'cursor-pointer' : '',
              isCurrentNode(index) ? 'journey-node-glow' : '',
            ]"
            @click="handleNodeClick(index)"
          />

          <!-- Completed tick -->
          <g v-if="isCompletedNode(index)">
            <path
              :d="tickPath(node.x, node.y)"
              fill="none"
              stroke="#fff"
              stroke-width="2.5"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
          </g>

          <!-- Destination flag -->
          <text
            v-if="isDestinationNode(index)"
            :x="node.x"
            :y="node.y + 5"
            text-anchor="middle"
            font-size="16"
            class="select-none"
          >&#127937;</text>

          <!-- Step number (non-completed, non-destination) -->
          <text
            v-if="!isCompletedNode(index) && !isDestinationNode(index)"
            :x="node.x"
            :y="node.y + 5"
            text-anchor="middle"
            fill="#fff"
            font-size="13"
            font-weight="700"
            :opacity="getNodeOpacity(index)"
            class="select-none"
          >{{ index + 1 }}</text>

          <!-- Label: title + description -->
          <g :transform="`translate(${node.labelX}, ${node.labelY})`">
            <text
              :text-anchor="node.labelAnchor"
              font-size="13"
              font-weight="700"
              fill="#1F2A44"
              :opacity="getNodeOpacity(index)"
            >{{ getStepTitle(index) }}</text>
            <text
              :text-anchor="node.labelAnchor"
              font-size="11"
              fill="#717171"
              dy="16"
              :opacity="getNodeOpacity(index)"
            >{{ getStepDescription(index) }}</text>
          </g>
        </g>
      </svg>
    </div>

    <!-- Expanded detail card (preview mode, when a node is selected) -->
    <transition name="expand">
      <div
        v-if="mode === 'preview' && selectedStepIndex !== null && selectedMilestone"
        class="mt-4 rounded-xl p-5 shadow-sm border"
        :class="detailCardClasses"
      >
        <div class="flex items-start gap-3 mb-3">
          <div
            class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 text-white text-sm font-bold"
            :class="detailNodeBgClass"
          >{{ selectedStepIndex + 1 }}</div>
          <div>
            <h4 class="text-sm font-bold text-horizon-500">{{ getStepTitle(selectedStepIndex) }}</h4>
            <p class="text-xs text-neutral-500 mt-0.5">Step {{ selectedStepIndex + 1 }} of {{ steps.length }}</p>
          </div>
        </div>
        <p class="text-sm text-horizon-500 leading-relaxed">
          {{ selectedMilestone.didYouKnow }}
        </p>
        <p v-if="selectedMilestone.quickStat" class="mt-3 text-xs text-neutral-500">
          <span class="font-bold" :class="detailStatClass">{{ selectedMilestone.quickStat.value }}</span>
          — {{ selectedMilestone.quickStat.label }}
        </p>
      </div>
    </transition>

    <!-- Tap hint -->
    <p v-if="mode === 'preview' && selectedStepIndex === null" class="text-center text-xs text-neutral-500 mt-3">
      Tap any step to learn more
    </p>

    <!-- CTAs (preview mode only) -->
    <div v-if="mode === 'preview'" class="mt-6 text-center space-y-3">
      <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
        <button
          type="button"
          class="inline-flex items-center px-8 py-3 border border-transparent text-sm font-medium rounded-button text-white bg-raspberry-500 hover:bg-raspberry-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-violet-500 transition-colors"
          @click="$emit('start')"
        >
          Start My Journey
        </button>
        <button
          type="button"
          class="inline-flex items-center px-8 py-3 border border-light-gray text-sm font-medium rounded-button text-horizon-500 bg-white hover:bg-savannah-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-violet-500 transition-colors"
          @click="$emit('preview')"
        >
          See It in Action
        </button>
      </div>
      <p class="text-xs text-neutral-500">You can skip steps and come back to them later</p>
    </div>
  </div>
</template>

<script>
import { LIFE_STAGES } from '@/constants/lifeStageConfig';

export default {
  name: 'JourneyMap',

  props: {
    stage: {
      type: String,
      required: true,
    },
    completedSteps: {
      type: Array,
      default: () => [],
    },
    mode: {
      type: String,
      default: 'preview',
      validator: (v) => ['preview', 'progress'].includes(v),
    },
  },

  emits: ['step-clicked', 'start', 'preview'],

  data() {
    return {
      selectedStepIndex: null,
      nodeRadius: 18,
    };
  },

  computed: {
    stageConfig() {
      return LIFE_STAGES[this.stage] || {};
    },

    steps() {
      return this.stageConfig?.onboarding?.steps || [];
    },

    learningMilestones() {
      return this.stageConfig?.onboarding?.learningMilestones || {};
    },

    stageColour() {
      return this.stageConfig?.colour || 'violet';
    },

    stageHexColour() {
      const map = {
        violet: '#5854E6',
        spring: '#20B486',
        raspberry: '#E83E6D',
        'light-blue': '#6C83BC',
        horizon: '#1F2A44',
      };
      return map[this.stageColour] || '#5854E6';
    },

    gradientId() {
      return `journey-gradient-${this.stage}`;
    },

    glowFilterId() {
      return `glow-${this.stage}`;
    },

    stepCount() {
      return this.steps.length;
    },

    // SVG dimensions
    svgWidth() {
      return Math.max(700, this.stepCount * 130);
    },

    svgHeight() {
      return 280;
    },

    svgMinWidth() {
      return this.svgWidth;
    },

    svgViewBox() {
      return `0 0 ${this.svgWidth} ${this.svgHeight}`;
    },

    // Calculate node positions in a meandering pattern
    nodePositions() {
      const count = this.stepCount;
      if (count === 0) return [];

      const positions = [];
      const padding = 60;
      const availableWidth = this.svgWidth - padding * 2;
      const stepWidth = availableWidth / Math.max(count - 1, 1);
      const topY = 80;
      const bottomY = 180;

      for (let i = 0; i < count; i++) {
        const x = padding + i * stepWidth;
        // Alternate between top and bottom positions
        const isTop = i % 2 === 0;
        const y = isTop ? topY : bottomY;

        // Label positioning: below for top nodes, above for bottom nodes
        const labelY = isTop ? y + this.nodeRadius + 28 : y - this.nodeRadius - 32;
        const labelX = x;
        const labelAnchor = 'middle';

        positions.push({ x, y, labelX, labelY, labelAnchor, isTop });
      }

      return positions;
    },

    // SVG path through all nodes (meandering cubic bezier)
    pathD() {
      const nodes = this.nodePositions;
      if (nodes.length < 2) return '';

      let d = `M ${nodes[0].x} ${nodes[0].y}`;

      for (let i = 1; i < nodes.length; i++) {
        const prev = nodes[i - 1];
        const curr = nodes[i];
        const midX = (prev.x + curr.x) / 2;

        // Cubic bezier: control points at midpoint horizontally, at source/target Y vertically
        d += ` C ${midX} ${prev.y}, ${midX} ${curr.y}, ${curr.x} ${curr.y}`;
      }

      return d;
    },

    currentStepIndex() {
      for (let i = 0; i < this.steps.length; i++) {
        if (!this.completedSteps.includes(this.steps[i])) {
          return i;
        }
      }
      return this.steps.length; // All complete
    },

    selectedMilestone() {
      if (this.selectedStepIndex === null) return null;
      const stepId = this.steps[this.selectedStepIndex];
      return this.learningMilestones[stepId] || null;
    },

    detailCardClasses() {
      const map = {
        violet: 'bg-violet-50 border-violet-200',
        spring: 'bg-spring-50 border-spring-200',
        raspberry: 'bg-raspberry-50 border-raspberry-200',
        'light-blue': 'bg-light-blue-100 border-horizon-200',
        horizon: 'bg-horizon-50 border-horizon-200',
      };
      return map[this.stageColour] || 'bg-violet-50 border-violet-200';
    },

    detailNodeBgClass() {
      const map = {
        violet: 'bg-violet-500',
        spring: 'bg-spring-500',
        raspberry: 'bg-raspberry-500',
        'light-blue': 'bg-light-blue-500',
        horizon: 'bg-horizon-500',
      };
      return map[this.stageColour] || 'bg-violet-500';
    },

    detailStatClass() {
      const map = {
        violet: 'text-violet-600',
        spring: 'text-spring-600',
        raspberry: 'text-raspberry-600',
        'light-blue': 'text-light-blue-500',
        horizon: 'text-horizon-500',
      };
      return map[this.stageColour] || 'text-violet-600';
    },
  },

  watch: {
    stage() {
      this.selectedStepIndex = null;
    },
  },

  methods: {
    getStepTitle(index) {
      const titles = {
        'personal-info': 'About You',
        'student-loan': 'Student Loan',
        'income': 'Income',
        'income-career': 'Income & Career',
        'income-tax': 'Income & Tax',
        'expenditure': 'Spending',
        'savings': 'Savings',
        'savings-emergency': 'Savings & Emergency Fund',
        'first-home-lisa': 'First Home & Lifetime ISA',
        'pension-auto-enrolment': 'Pension & Auto-enrolment',
        'investments': 'Investments',
        'investments-isa': 'Investments & ISA',
        'assets': 'Assets',
        'liabilities': 'Liabilities',
        'goals': 'Goals',
        'family': 'Family',
        'property-mortgage': 'Property & Mortgage',
        'property-portfolio': 'Property Portfolio',
        'protection-insurance': 'Protection & Insurance',
        'pensions': 'Pensions',
        'pension-review': 'Pension Review',
        'pension-drawdown': 'Pension & Drawdown',
        'will-estate': 'Will & Estate',
        'estate-iht': 'Estate & Inheritance Tax',
        'estate-legacy': 'Estate & Legacy',
        'state-pension': 'State Pension',
      };
      return titles[this.steps[index]] || this.steps[index];
    },

    getStepDescription(index) {
      const descriptions = {
        'personal-info': 'Your details',
        'student-loan': 'Loan & repayment',
        'income': 'What comes in',
        'income-career': 'Salary & career',
        'income-tax': 'Tax position',
        'expenditure': 'What goes out',
        'savings': 'Your accounts',
        'savings-emergency': 'Emergency fund',
        'first-home-lisa': 'House deposit',
        'pension-auto-enrolment': 'Workplace pension',
        'investments': 'Your portfolio',
        'investments-isa': 'ISA & investments',
        'assets': 'What you own',
        'liabilities': 'What you owe',
        'goals': 'Your targets',
        'family': 'Dependants',
        'property-mortgage': 'Home & mortgage',
        'property-portfolio': 'All properties',
        'protection-insurance': 'Your cover',
        'pensions': 'Pension pots',
        'pension-review': 'All pensions',
        'pension-drawdown': 'Withdrawal plan',
        'will-estate': 'Estate planning',
        'estate-iht': 'Tax planning',
        'estate-legacy': 'Legacy planning',
        'state-pension': 'State entitlement',
      };
      return descriptions[this.steps[index]] || '';
    },

    isCompletedNode(index) {
      return this.completedSteps.includes(this.steps[index]);
    },

    isCurrentNode(index) {
      return index === this.currentStepIndex;
    },

    isDestinationNode(index) {
      return index === this.steps.length - 1;
    },

    getNodeFill(index) {
      if (this.isCompletedNode(index)) return '#20B486'; // spring-500
      if (this.isDestinationNode(index) && this.currentStepIndex >= this.steps.length) return '#20B486';
      if (this.isDestinationNode(index)) return '#20B486';
      return this.stageHexColour;
    },

    getNodeStroke(index) {
      if (this.isCompletedNode(index)) return '#059669'; // spring-600
      if (this.isDestinationNode(index)) return '#059669';
      return this.stageHexColour;
    },

    getNodeOpacity(index) {
      if (this.isCompletedNode(index)) return 1;
      if (this.isCurrentNode(index)) return 1;

      // Decreasing opacity for upcoming nodes
      const distance = index - this.currentStepIndex;
      if (distance <= 0) return 1;
      return Math.max(0.5, 1 - distance * 0.1);
    },

    tickPath(cx, cy) {
      // Tick mark centred in node
      const size = 7;
      const x1 = cx - size * 0.6;
      const y1 = cy;
      const x2 = cx - size * 0.1;
      const y2 = cy + size * 0.5;
      const x3 = cx + size * 0.7;
      const y3 = cy - size * 0.5;
      return `M ${x1} ${y1} L ${x2} ${y2} L ${x3} ${y3}`;
    },

    handleNodeClick(index) {
      if (this.mode !== 'preview') return;

      if (this.selectedStepIndex === index) {
        this.selectedStepIndex = null;
      } else {
        this.selectedStepIndex = index;
        this.$emit('step-clicked', {
          index,
          stepId: this.steps[index],
          title: this.getStepTitle(index),
          milestone: this.learningMilestones[this.steps[index]] || null,
        });
      }
    },
  },
};
</script>

<style scoped>
.journey-node-glow {
  animation: nodeGlow 2s ease-in-out infinite;
}

/* TODO: similar glow animation exists in OnboardingWizard.vue (nodePulse).
   That one uses box-shadow; this uses filter:drop-shadow — kept separate intentionally. */
@keyframes nodeGlow {
  0%, 100% {
    filter: drop-shadow(0 0 4px currentColor);
    opacity: 1;
  }
  50% {
    filter: drop-shadow(0 0 10px currentColor);
    opacity: 0.9;
  }
}

.expand-enter-active,
.expand-leave-active {
  transition: all 0.25s ease;
  overflow: hidden;
}

.expand-enter-from,
.expand-leave-to {
  opacity: 0;
  max-height: 0;
}

.expand-enter-to,
.expand-leave-from {
  opacity: 1;
  max-height: 500px;
}
</style>
