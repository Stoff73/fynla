<template>
  <div
    class="module-status-bar rounded-lg mb-4 overflow-hidden transition-all duration-200"
    style="background: #FAD6E0; position: relative; z-index: 1;"
  >
    <!-- Minimised bar (always visible) -->
    <button
      class="w-full flex items-center justify-between px-4 py-2.5 hover:bg-light-pink-200/50 transition-colors"
      :aria-expanded="expanded"
      @click="toggle"
    >
      <div class="flex items-center gap-3">
        <!-- Progress ring -->
        <svg
          :width="28"
          :height="28"
          viewBox="0 0 36 36"
          role="img"
          :aria-label="completionPercentage + '% complete'"
        >
          <circle
            cx="18" cy="18" r="15.5"
            fill="none"
            stroke="#FECDD3"
            stroke-width="3"
          />
          <circle
            cx="18" cy="18" r="15.5"
            fill="none"
            :stroke="ringColour"
            stroke-width="3"
            stroke-linecap="round"
            :stroke-dasharray="circumference"
            :stroke-dashoffset="dashOffset"
            transform="rotate(-90 18 18)"
            class="transition-all duration-500"
          />
          <text
            x="18" y="18"
            text-anchor="middle"
            dominant-baseline="central"
            :fill="ringColour"
            font-size="8"
            font-weight="700"
          >{{ completionPercentage }}%</text>
        </svg>
        <span class="text-sm font-medium text-horizon-500">
          {{ filledCount }} of {{ totalCount }} items complete
        </span>
      </div>
      <div class="flex items-center gap-1.5">
        <span class="text-xs text-neutral-400 hidden sm:inline">What powers this view</span>
        <svg
          class="w-3.5 h-3.5 text-neutral-400 transition-transform duration-200"
          :class="{ 'rotate-180': expanded }"
          fill="none" stroke="currentColor" viewBox="0 0 24 24"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
      </div>
    </button>

    <!-- Expanded checklist -->
    <div
      class="checklist-body transition-all duration-200 ease-out"
      :class="expanded ? 'max-h-[500px] opacity-100' : 'max-h-0 opacity-0'"
      style="overflow: hidden;"
    >
      <div class="px-4 pb-4 pt-1 border-t border-light-pink-200">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1.5 text-sm">
          <div
            v-for="item in allRequirements"
            :key="item.key"
            class="flex items-center gap-2 py-1"
          >
            <!-- Filled -->
            <template v-if="item.status === 'filled'">
              <svg class="w-4 h-4 flex-shrink-0 text-spring-500" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
              </svg>
              <span class="text-horizon-500">{{ item.label }}</span>
            </template>
            <!-- Missing -->
            <template v-else>
              <svg class="w-4 h-4 flex-shrink-0 text-violet-500" viewBox="0 0 24 24" fill="currentColor">
                <circle cx="12" cy="12" r="10"/>
                <path d="M12 8v4M12 16h.01" stroke="white" stroke-width="2" stroke-linecap="round"/>
              </svg>
              <router-link
                v-if="item.link"
                :to="item.link"
                class="text-violet-600 hover:text-violet-700 hover:underline"
              >{{ item.label }}</router-link>
              <span v-else class="text-violet-600">{{ item.label }}</span>
            </template>
          </div>
        </div>
      </div>
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading && !allRequirements.length" class="px-4 py-3">
      <div class="animate-pulse flex items-center gap-3">
        <div class="w-7 h-7 rounded-full bg-savannah-200"></div>
        <div class="h-4 bg-savannah-200 rounded w-40"></div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted, watch } from 'vue';
import { useStore } from 'vuex';
import { useRoute } from 'vue-router';
import { resolveModule } from '@/utils/moduleMap';
import storage from '@/utils/storage';

const STORAGE_KEY = 'moduleStatusBarCollapsed';

export default {
  name: 'ModuleStatusBar',

  setup() {
    const store = useStore();
    const route = useRoute();

    const expanded = ref(storage.get(STORAGE_KEY) === 'true');

    const toggle = () => {
      expanded.value = !expanded.value;
      storage.set(STORAGE_KEY, expanded.value);
    };

    // Resolve module from route and fetch requirements
    const currentModule = computed(() => resolveModule(route.path));

    const fetchData = () => {
      store.dispatch('infoGuide/fetchRequirements', currentModule.value);
    };

    onMounted(fetchData);
    watch(currentModule, fetchData);

    // Info guide getters
    const loading = computed(() => store.state.infoGuide?.loading ?? false);
    const allRequirements = computed(() => store.getters['infoGuide/allRequirements'] || []);
    const filledItems = computed(() => store.getters['infoGuide/filledItems'] || []);
    const completionPercentage = computed(() => store.getters['infoGuide/completionPercentage'] || 0);

    const filledCount = computed(() => filledItems.value.length);
    const totalCount = computed(() => allRequirements.value.length);

    // Progress ring calculations
    const circumference = 2 * Math.PI * 15.5;
    const dashOffset = computed(() => {
      const pct = completionPercentage.value / 100;
      return circumference - (pct * circumference);
    });

    // Always pink (raspberry) progress ring
    const ringColour = computed(() => '#E8326E');

    return {
      expanded,
      toggle,
      loading,
      allRequirements,
      filledCount,
      totalCount,
      completionPercentage,
      circumference,
      dashOffset,
      ringColour,
    };
  },
};
</script>
