<template>
  <div class="event-icons-overlay absolute top-0 left-0 right-0 pointer-events-none" style="height: 50px;">
    <div class="relative h-full" ref="container">
      <transition-group name="fade">
        <div
          v-for="event in positionedEvents"
          :key="`${event.type}-${event.id}`"
          class="absolute transform -translate-x-1/2 pointer-events-auto cursor-pointer"
          :style="{ left: `${event.position}%` }"
          @click="$emit('icon-click', event)"
          @mouseenter="onHover(event, $event)"
          @mouseleave="$emit('icon-hover', null)"
        >
          <div
            class="w-7 h-7 rounded-full flex items-center justify-center shadow-md transition-transform hover:scale-110"
            :style="{
              backgroundColor: event.color,
              opacity: getOpacity(event),
            }"
            :title="`${event.name}: ${formatAmount(event.amount)}`"
          >
            <!-- Use text abbreviation for icon -->
            <span class="text-white text-xs font-bold">
              {{ getIconText(event) }}
            </span>
          </div>
          <!-- Connector line -->
          <div
            class="absolute left-1/2 transform -translate-x-1/2"
            :style="{
              width: '1px',
              height: '20px',
              backgroundColor: event.color,
              opacity: 0.5,
              top: '100%',
            }"
          ></div>
        </div>
      </transition-group>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import { CERTAINTY_STYLES } from '@/constants/eventIcons';

export default {
  name: 'EventIconsOverlay',
  mixins: [currencyMixin],

  props: {
    events: {
      type: Array,
      required: true,
    },
    yearlyData: {
      type: Array,
      required: true,
    },
    chartType: {
      type: String,
      default: 'area',
    },
  },

  emits: ['icon-click', 'icon-hover'],

  computed: {
    ageRange() {
      if (!this.yearlyData?.length) return { min: 0, max: 100 };
      const ages = this.yearlyData.map(d => d.age);
      return {
        min: Math.min(...ages),
        max: Math.max(...ages),
      };
    },

    positionedEvents() {
      const { min, max } = this.ageRange;
      const range = max - min || 1;

      // Calculate chart padding (approximate ApexCharts padding)
      const leftPadding = 10; // percentage
      const rightPadding = 5; // percentage
      const chartWidth = 100 - leftPadding - rightPadding;

      return this.events.map(event => {
        // Position based on age relative to chart range
        const normalizedPosition = (event.age - min) / range;
        const position = leftPadding + (normalizedPosition * chartWidth);

        return {
          ...event,
          position: Math.max(leftPadding, Math.min(100 - rightPadding, position)),
        };
      });
    },
  },

  methods: {
    getIconText(event) {
      // Return text abbreviations for event types
      const iconTextMap = {
        // Goals
        emergency_fund: 'EF',
        property_purchase: 'H',
        home_deposit: 'H',
        holiday: 'H',
        car_purchase: 'C',
        wedding: 'W',
        education: 'E',
        retirement: 'R',
        wealth_accumulation: 'W',
        debt_repayment: 'D',
        custom: 'G',
        // Life Events - Income
        inheritance: 'I',
        gift_received: 'G',
        bonus: 'B',
        redundancy_payment: 'R',
        property_sale: 'PS',
        business_sale: 'BS',
        pension_lump_sum: 'P',
        lottery_windfall: 'L',
        custom_income: '+',
        // Life Events - Expense
        large_purchase: 'LP',
        home_improvement: 'HI',
        education_fees: 'E',
        gift_given: 'GG',
        medical_expense: 'M',
        custom_expense: '-',
      };

      const category = event.category || event.type || '';
      return iconTextMap[category] || (event.impact === 'income' ? '+' : '-');
    },

    getOpacity(event) {
      if (event.certainty) {
        return CERTAINTY_STYLES[event.certainty]?.opacity || 1;
      }
      return 1;
    },

    formatAmount(amount) {
      return this.formatCurrency(amount);
    },

    onHover(event, domEvent) {
      const rect = domEvent.target.getBoundingClientRect();
      this.$emit('icon-hover', event, {
        x: rect.left + rect.width / 2,
        y: rect.top,
      });
    },
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
