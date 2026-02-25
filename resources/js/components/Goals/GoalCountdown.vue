<template>
  <div class="goal-countdown" :class="containerClass">
    <!-- Compact Mode -->
    <template v-if="compact">
      <span class="font-medium" :class="textClass">{{ displayText }}</span>
    </template>

    <!-- Full Mode -->
    <template v-else>
      <div v-if="isOverdue" class="text-center">
        <div class="text-3xl font-bold text-red-600 mb-1">Overdue</div>
        <div class="text-sm text-red-500">{{ Math.abs(daysRemaining) }} days past target</div>
      </div>

      <div v-else-if="daysRemaining === 0" class="text-center">
        <div class="text-3xl font-bold text-blue-600 mb-1">Today!</div>
        <div class="text-sm text-blue-500">Target date is today</div>
      </div>

      <div v-else-if="showDetailed" class="flex justify-center gap-4">
        <!-- Years -->
        <div v-if="years > 0" class="text-center">
          <div class="text-2xl font-bold" :class="numberClass">{{ years }}</div>
          <div class="text-xs text-gray-500 uppercase tracking-wider">{{ years === 1 ? 'Year' : 'Years' }}</div>
        </div>

        <!-- Months -->
        <div v-if="years > 0 || months > 0" class="text-center">
          <div class="text-2xl font-bold" :class="numberClass">{{ months }}</div>
          <div class="text-xs text-gray-500 uppercase tracking-wider">{{ months === 1 ? 'Month' : 'Months' }}</div>
        </div>

        <!-- Days -->
        <div v-if="years === 0" class="text-center">
          <div class="text-2xl font-bold" :class="numberClass">{{ days }}</div>
          <div class="text-xs text-gray-500 uppercase tracking-wider">{{ days === 1 ? 'Day' : 'Days' }}</div>
        </div>
      </div>

      <div v-else class="text-center">
        <div class="text-3xl font-bold" :class="numberClass">{{ displayText }}</div>
        <div class="text-sm text-gray-500">remaining</div>
      </div>

      <!-- Urgency Indicator -->
      <div v-if="showUrgency && urgencyLevel !== 'normal'" class="mt-2 text-center">
        <span
          class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
          :class="urgencyBadgeClass"
        >
          {{ urgencyText }}
        </span>
      </div>
    </template>
  </div>
</template>

<script>
export default {
  name: 'GoalCountdown',

  props: {
    daysRemaining: {
      type: Number,
      default: 0,
    },
    compact: {
      type: Boolean,
      default: false,
    },
    showDetailed: {
      type: Boolean,
      default: true,
    },
    showUrgency: {
      type: Boolean,
      default: true,
    },
    variant: {
      type: String,
      default: 'auto',
      validator: (value) => ['auto', 'neutral'].includes(value),
    },
  },

  computed: {
    isOverdue() {
      return this.daysRemaining < 0;
    },

    years() {
      if (this.daysRemaining <= 0) return 0;
      return Math.floor(this.daysRemaining / 365);
    },

    months() {
      if (this.daysRemaining <= 0) return 0;
      return Math.floor((this.daysRemaining % 365) / 30);
    },

    days() {
      if (this.daysRemaining <= 0) return 0;
      if (this.years > 0) {
        return Math.floor((this.daysRemaining % 365) % 30);
      }
      if (this.months > 0) {
        return Math.floor(this.daysRemaining % 30);
      }
      return this.daysRemaining;
    },

    displayText() {
      if (this.isOverdue) return 'Overdue';
      if (this.daysRemaining === 0) return 'Today';
      if (this.daysRemaining === 1) return '1 day';
      if (this.daysRemaining < 7) return `${this.daysRemaining} days`;
      if (this.daysRemaining < 30) return `${this.daysRemaining} days`;
      if (this.daysRemaining < 365) {
        const months = Math.floor(this.daysRemaining / 30);
        const days = this.daysRemaining % 30;
        if (days === 0) return `${months}m`;
        return `${months}m ${days}d`;
      }
      const years = Math.floor(this.daysRemaining / 365);
      const months = Math.floor((this.daysRemaining % 365) / 30);
      if (months === 0) return `${years}y`;
      return `${years}y ${months}m`;
    },

    urgencyLevel() {
      if (this.isOverdue) return 'overdue';
      if (this.daysRemaining <= 7) return 'critical';
      if (this.daysRemaining <= 30) return 'urgent';
      if (this.daysRemaining <= 90) return 'approaching';
      return 'normal';
    },

    urgencyText() {
      const texts = {
        overdue: 'Overdue',
        critical: 'Due very soon',
        urgent: 'Due soon',
        approaching: 'Approaching',
        normal: '',
      };
      return texts[this.urgencyLevel];
    },

    containerClass() {
      if (this.compact) return '';
      return 'p-4 rounded-lg ' + this.backgroundClass;
    },

    backgroundClass() {
      if (this.variant === 'neutral') return 'bg-gray-50';
      if (this.isOverdue) return 'bg-red-50';
      if (this.urgencyLevel === 'critical') return 'bg-red-50';
      if (this.urgencyLevel === 'urgent') return 'bg-blue-50';
      if (this.urgencyLevel === 'approaching') return 'bg-blue-50';
      return 'bg-gray-50';
    },

    textClass() {
      if (this.variant === 'neutral') return 'text-gray-700';
      if (this.isOverdue) return 'text-red-600';
      if (this.urgencyLevel === 'critical') return 'text-red-600';
      if (this.urgencyLevel === 'urgent') return 'text-blue-600';
      if (this.urgencyLevel === 'approaching') return 'text-blue-600';
      return 'text-gray-700';
    },

    numberClass() {
      if (this.variant === 'neutral') return 'text-gray-900';
      if (this.isOverdue) return 'text-red-600';
      if (this.urgencyLevel === 'critical') return 'text-red-600';
      if (this.urgencyLevel === 'urgent') return 'text-blue-600';
      if (this.urgencyLevel === 'approaching') return 'text-blue-600';
      return 'text-gray-900';
    },

    urgencyBadgeClass() {
      const classes = {
        overdue: 'bg-red-100 text-red-700',
        critical: 'bg-red-100 text-red-700',
        urgent: 'bg-blue-100 text-blue-700',
        approaching: 'bg-blue-100 text-blue-700',
        normal: 'bg-gray-100 text-gray-700',
      };
      return classes[this.urgencyLevel];
    },
  },
};
</script>
