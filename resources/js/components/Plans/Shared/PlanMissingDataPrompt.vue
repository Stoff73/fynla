<template>
  <div v-if="warning" class="bg-violet-50 border border-violet-200 rounded-lg p-4 mb-6">
    <div class="flex">
      <div class="flex-shrink-0">
        <svg class="h-5 w-5 text-violet-600" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
        </svg>
      </div>
      <div class="ml-3 flex-1">
        <h3 class="text-sm font-medium text-violet-800">Incomplete Data</h3>
        <p class="text-sm text-violet-700 mt-1">{{ warning.message }}</p>

        <div v-if="warning.missing_items && warning.missing_items.length" class="mt-3 space-y-2">
          <div
            v-for="item in warning.missing_items"
            :key="item.field"
            class="flex items-center justify-between bg-violet-100/50 rounded px-3 py-2"
          >
            <div>
              <p class="text-sm font-medium text-violet-900">{{ item.label }}</p>
              <p class="text-xs text-violet-700">{{ item.description }}</p>
            </div>
            <router-link
              v-if="item.link"
              :to="item.link"
              class="text-xs font-medium text-violet-600 hover:text-violet-800 whitespace-nowrap ml-3"
            >
              Add &rarr;
            </router-link>
          </div>
        </div>

        <div v-if="warning.completeness_percentage !== undefined" class="mt-3">
          <div class="flex items-center justify-between text-xs text-violet-700 mb-1">
            <span>Data completeness</span>
            <span>{{ warning.completeness_percentage }}%</span>
          </div>
          <div class="w-full bg-violet-200 rounded-full h-1.5">
            <div
              class="bg-violet-600 h-1.5 rounded-full transition-all duration-300"
              :style="{ width: `${warning.completeness_percentage}%` }"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'PlanMissingDataPrompt',

  props: {
    warning: {
      type: Object,
      default: null,
    },
  },
};
</script>
