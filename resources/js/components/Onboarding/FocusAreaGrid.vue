<template>
  <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 mb-6">
    <button
      v-for="area in focusAreas"
      :key="area.id"
      type="button"
      v-preview-disabled
      class="relative flex flex-col items-center p-4 rounded-lg border-2 transition-all duration-200 cursor-pointer select-none"
      :class="[
        isSelected(area.id)
          ? 'bg-savannah-100 border-raspberry-500 shadow-sm scale-[1.02]'
          : 'bg-white border-light-gray hover:border-horizon-300 hover:shadow-sm'
      ]"
      role="checkbox"
      :aria-checked="isSelected(area.id)"
      :aria-label="area.label"
      @click="toggleSelection(area.id)"
    >
      <!-- Checkmark badge -->
      <transition name="fade">
        <div
          v-if="isSelected(area.id)"
          class="absolute top-2 right-2 w-5 h-5 bg-raspberry-500 rounded-full flex items-center justify-center"
        >
          <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
          </svg>
        </div>
      </transition>

      <!-- Icon -->
      <div
        class="w-10 h-10 rounded-full flex items-center justify-center mb-2 transition-colors duration-200"
        :class="isSelected(area.id) ? area.selectedBg : 'bg-eggshell-500'"
      >
        <!-- Budgeting -->
        <svg v-if="area.id === 'budgeting'" class="w-5 h-5" :class="isSelected(area.id) ? 'text-white' : area.iconColor" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
        </svg>
        <!-- Protection -->
        <svg v-else-if="area.id === 'protection'" class="w-5 h-5" :class="isSelected(area.id) ? 'text-white' : area.iconColor" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
        </svg>
        <!-- Investment -->
        <svg v-else-if="area.id === 'investment'" class="w-5 h-5" :class="isSelected(area.id) ? 'text-white' : area.iconColor" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
        </svg>
        <!-- Retirement -->
        <svg v-else-if="area.id === 'retirement'" class="w-5 h-5" :class="isSelected(area.id) ? 'text-white' : area.iconColor" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <!-- Estate -->
        <svg v-else-if="area.id === 'estate'" class="w-5 h-5" :class="isSelected(area.id) ? 'text-white' : area.iconColor" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        <!-- Family -->
        <svg v-else-if="area.id === 'family'" class="w-5 h-5" :class="isSelected(area.id) ? 'text-white' : area.iconColor" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
        </svg>
        <!-- Business -->
        <svg v-else-if="area.id === 'business'" class="w-5 h-5" :class="isSelected(area.id) ? 'text-white' : area.iconColor" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
        </svg>
        <!-- Goal Tracking -->
        <svg v-else-if="area.id === 'goals'" class="w-5 h-5" :class="isSelected(area.id) ? 'text-white' : area.iconColor" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
        </svg>
      </div>

      <!-- Label -->
      <span
        class="text-body-sm font-medium text-center"
        :class="isSelected(area.id) ? 'text-horizon-500' : 'text-neutral-500'"
      >
        {{ area.label }}
      </span>
    </button>
  </div>
</template>

<script>
export default {
  name: 'FocusAreaGrid',

  props: {
    selections: {
      type: Array,
      default: () => [],
    },
  },

  emits: ['update:selections'],

  data() {
    return {
      focusAreas: [
        {
          id: 'budgeting',
          label: 'Budgeting',
          iconColor: 'text-spring-500',
          selectedBg: 'bg-spring-500',
        },
        {
          id: 'protection',
          label: 'Protection',
          iconColor: 'text-horizon-500',
          selectedBg: 'bg-horizon-500',
        },
        {
          id: 'investment',
          label: 'Investment',
          iconColor: 'text-raspberry-500',
          selectedBg: 'bg-raspberry-500',
        },
        {
          id: 'retirement',
          label: 'Retirement',
          iconColor: 'text-violet-500',
          selectedBg: 'bg-violet-500',
        },
        {
          id: 'estate',
          label: 'Estate Planning',
          iconColor: 'text-horizon-600',
          selectedBg: 'bg-horizon-600',
        },
        {
          id: 'family',
          label: 'Family',
          iconColor: 'text-raspberry-400',
          selectedBg: 'bg-raspberry-400',
        },
        {
          id: 'business',
          label: 'Business',
          iconColor: 'text-spring-600',
          selectedBg: 'bg-spring-600',
        },
        {
          id: 'goals',
          label: 'Goal Tracking',
          iconColor: 'text-violet-600',
          selectedBg: 'bg-violet-600',
        },
      ],
    };
  },

  methods: {
    isSelected(id) {
      return this.selections.includes(id);
    },

    toggleSelection(id) {
      const newSelections = this.isSelected(id)
        ? this.selections.filter(s => s !== id)
        : [...this.selections, id];
      this.$emit('update:selections', newSelections);
    },
  },
};
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
