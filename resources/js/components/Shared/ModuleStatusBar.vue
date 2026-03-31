<template>
  <div
    class="module-status-bar rounded-lg mb-4 overflow-hidden transition-all duration-200 bg-light-pink-100 relative z-[1]"
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

    <!-- Expanded three-column layout -->
    <div
      class="checklist-body transition-all duration-200 ease-out"
      :class="expanded ? 'max-h-[500px] opacity-100' : 'max-h-0 opacity-0'"
      style="overflow: hidden;"
    >
      <div class="px-4 pb-4 pt-1 border-t border-light-pink-200">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">

          <!-- LEFT: Completed -->
          <div>
            <div class="col-header text-spring-500">
              <svg class="inline w-3.5 h-3.5 -mt-0.5 mr-1" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
              </svg>
              Completed ({{ filledCount }})
            </div>
            <div
              v-for="item in filledItems"
              :key="item.key"
              class="flex items-center gap-2 py-1"
            >
              <svg class="w-4 h-4 flex-shrink-0 text-spring-500" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
              </svg>
              <span class="text-sm text-spring-500">{{ item.label }}</span>
            </div>
            <div v-if="filledItems.length === 0" class="text-sm text-neutral-400 py-1">
              No items completed yet
            </div>
          </div>

          <!-- MIDDLE: Outstanding -->
          <div>
            <div class="col-header text-violet-600">
              <svg class="inline w-3.5 h-3.5 -mt-0.5 mr-1" viewBox="0 0 24 24" fill="currentColor">
                <circle cx="12" cy="12" r="10"/>
                <path d="M12 8v4M12 16h.01" stroke="white" stroke-width="2" stroke-linecap="round"/>
              </svg>
              Outstanding ({{ missingItems.length }})
            </div>
            <div
              v-for="item in missingItems"
              :key="item.key"
              class="flex items-center gap-2 py-1 rounded-md px-1 -mx-1 transition-colors"
              :class="hoveredItem?.key === item.key ? 'bg-violet-500/10' : ''"
              @mouseenter="hoveredItem = item"
              @mouseleave="hoveredItem = null"
            >
              <svg class="w-4 h-4 flex-shrink-0 text-violet-500" viewBox="0 0 24 24" fill="currentColor">
                <circle cx="12" cy="12" r="10"/>
                <path d="M12 8v4M12 16h.01" stroke="white" stroke-width="2" stroke-linecap="round"/>
              </svg>
              <router-link
                v-if="item.link"
                :to="item.link"
                class="text-sm text-violet-600 hover:text-violet-700 hover:underline"
              >{{ item.label }}</router-link>
              <span v-else class="text-sm text-violet-600">{{ item.label }}</span>
            </div>
            <div v-if="missingItems.length === 0" class="text-sm text-neutral-400 py-1">
              All items complete
            </div>
          </div>

          <!-- RIGHT: Why we need this -->
          <div>
            <div class="col-header text-neutral-400">
              <svg class="inline w-3.5 h-3.5 -mt-0.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              Why we need this
            </div>
            <div class="bg-white rounded-lg p-3 min-h-[80px] flex flex-col justify-center">
              <template v-if="hoveredItem">
                <span class="text-xs font-semibold text-horizon-500 mb-1">{{ hoveredItem.label }}</span>
                <span class="text-xs text-neutral-500 leading-relaxed">{{ hoveredItem.why }}</span>
              </template>
              <span v-else class="text-xs text-neutral-300">Hover over an outstanding item to see why it's needed</span>
            </div>
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
    const hoveredItem = ref(null);

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
    const missingItems = computed(() => store.getters['infoGuide/missingItems'] || []);
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
      hoveredItem,
      toggle,
      loading,
      allRequirements,
      filledItems,
      missingItems,
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

<style scoped>
.col-header {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 8px;
  padding-bottom: 6px;
  border-bottom: 1px solid #FECDD3;
}
</style>
