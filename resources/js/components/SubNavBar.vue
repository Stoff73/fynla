<template>
  <div v-if="config" class="bg-white border-b border-light-gray">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between">
        <!-- Tabs: horizontal scroll on mobile -->
        <div class="flex overflow-x-auto scrollbar-hide -mb-px">
          <router-link
            v-for="tab in config.tabs"
            :key="tabKey(tab)"
            :to="tab.to"
            class="whitespace-nowrap py-3 px-4 border-b-2 text-sm font-medium transition-colors flex-shrink-0"
            :class="isTabActive(tab) ? 'border-raspberry-500 text-raspberry-600' : 'border-transparent text-neutral-500 hover:text-horizon-500 hover:border-horizon-300'"
          >
            {{ tab.label }}
          </router-link>
        </div>

        <!-- CTAs -->
        <div v-if="config.ctas.length" class="flex items-center gap-2 flex-shrink-0 ml-4">
          <button
            v-for="cta in config.ctas"
            :key="cta.action"
            @click="handleCta(cta.action)"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-semibold transition-colors whitespace-nowrap"
            :class="cta.style === 'primary'
              ? 'bg-raspberry-500 text-white hover:bg-raspberry-600'
              : 'bg-white text-horizon-500 border border-light-gray hover:bg-savannah-100'"
          >
            <svg v-if="cta.icon === 'plus'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            <svg v-else-if="cta.icon === 'upload'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
            </svg>
            {{ cta.label }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useStore } from 'vuex';
import { SUB_NAV_CONFIG } from '@/constants/subNavConfig';

export default {
  name: 'SubNavBar',

  setup() {
    const route = useRoute();
    const store = useStore();

    const config = computed(() => {
      const path = route.path;
      for (const entry of SUB_NAV_CONFIG) {
        const matches = Array.isArray(entry.match) ? entry.match : [entry.match];
        if (matches.some(m => path.startsWith(m))) {
          return entry;
        }
      }
      return null;
    });

    const isTabActive = (tab) => {
      const tabPath = typeof tab.to === 'string' ? tab.to : tab.to.path;
      const tabQuery = typeof tab.to === 'object' ? tab.to.query : null;

      // Exact path match required
      if (route.path !== tabPath) return false;

      // If tab has query params, they must match too
      if (tabQuery) {
        return Object.entries(tabQuery).every(([key, val]) => route.query[key] === val);
      }

      // If no query on tab, it's active when path matches and no distinguishing query
      return true;
    };

    const tabKey = (tab) => {
      if (typeof tab.to === 'string') return tab.to;
      return tab.to.path + JSON.stringify(tab.to.query || {});
    };

    const handleCta = (action) => {
      store.dispatch('subNav/triggerCta', action);
    };

    return { config, isTabActive, tabKey, handleCta };
  },
};
</script>
