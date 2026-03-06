<template>
  <div class="bg-white rounded-lg border border-light-gray p-4 hover:shadow-md transition-shadow">
    <div class="flex items-start gap-3">
      <!-- Icon -->
      <div
        class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center"
        :class="iconBackgroundClass"
      >
        <!-- Shield Icon (capacity_for_loss) -->
        <svg v-if="factor.icon === 'shield'" class="w-5 h-5" :class="iconColorClass" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
        </svg>
        <!-- Clock Icon (time_horizon) -->
        <svg v-else-if="factor.icon === 'clock'" class="w-5 h-5" :class="iconColorClass" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <!-- Academic Cap Icon (education) -->
        <svg v-else-if="factor.icon === 'academic-cap'" class="w-5 h-5" :class="iconColorClass" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z" />
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222" />
        </svg>
        <!-- Users Icon (dependants) -->
        <svg v-else-if="factor.icon === 'users'" class="w-5 h-5" :class="iconColorClass" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
        </svg>
        <!-- Briefcase Icon (employment) -->
        <svg v-else-if="factor.icon === 'briefcase'" class="w-5 h-5" :class="iconColorClass" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
        </svg>
        <!-- Cash/Banknotes Icon (emergency_cash) -->
        <svg v-else-if="factor.icon === 'cash'" class="w-5 h-5" :class="iconColorClass" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
        </svg>
        <!-- Trending Up Icon (surplus_cash) -->
        <svg v-else-if="factor.icon === 'trending-up'" class="w-5 h-5" :class="iconColorClass" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
        </svg>
        <!-- Default Icon -->
        <svg v-else class="w-5 h-5" :class="iconColorClass" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>

      <!-- Content -->
      <div class="flex-1 min-w-0">
        <div class="flex items-start justify-between gap-2">
          <div>
            <h4 class="text-sm font-medium text-horizon-500">{{ factor.display_name }}</h4>
            <p class="text-lg font-semibold text-horizon-500 mt-0.5">{{ factor.value }}</p>
          </div>
          <!-- Risk Level Badge -->
          <span
            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium whitespace-nowrap"
            :class="levelBadgeClass"
          >
            {{ levelDisplayName }}
          </span>
        </div>
        <p class="text-xs text-neutral-500 mt-1 line-clamp-2">{{ factor.description }}</p>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'FactorBreakdownCard',

  props: {
    factor: {
      type: Object,
      required: true,
    },
  },

  computed: {
    iconBackgroundClass() {
      const classes = {
        low: 'bg-violet-100',
        lower_medium: 'bg-pink-100',
        medium: 'bg-green-100',
        upper_medium: 'bg-teal-100',
        high: 'bg-blue-100',
      };
      return classes[this.factor.level] || 'bg-savannah-100';
    },

    iconColorClass() {
      const classes = {
        low: 'text-violet-600',
        lower_medium: 'text-pink-600',
        medium: 'text-green-600',
        upper_medium: 'text-teal-600',
        high: 'text-blue-600',
      };
      return classes[this.factor.level] || 'text-neutral-500';
    },

    levelBadgeClass() {
      const classes = {
        low: 'bg-violet-100 text-violet-800',
        lower_medium: 'bg-pink-100 text-pink-800',
        medium: 'bg-green-100 text-green-800',
        upper_medium: 'bg-teal-100 text-teal-800',
        high: 'bg-blue-100 text-blue-800',
      };
      return classes[this.factor.level] || 'bg-savannah-100 text-horizon-500';
    },

    levelDisplayName() {
      const names = {
        low: 'Low',
        lower_medium: 'Lower-Med',
        medium: 'Medium',
        upper_medium: 'Upper-Med',
        high: 'High',
      };
      return names[this.factor.level] || this.factor.level;
    },
  },
};
</script>

