<template>
  <div v-if="categoryConfig" class="bg-white border-b border-light-gray">
    <div class="px-4 sm:px-6 lg:px-8">
      <!-- Tabs row -->
      <div class="flex overflow-x-auto scrollbar-hide -mb-px">
        <router-link
          v-for="tab in categoryConfig.tabs"
          :key="tabKey(tab)"
          :to="tab.to"
          class="whitespace-nowrap py-3 px-4 border-b-2 text-sm font-medium transition-colors flex-shrink-0"
          :class="isTabActive(tab) ? 'border-raspberry-500 text-raspberry-600' : 'border-transparent text-neutral-500 hover:text-horizon-500 hover:border-horizon-300'"
        >
          {{ tab.label }}
        </router-link>
      </div>

      <!-- CTAs row: below tabs, left-aligned -->
      <div v-if="activeCtas.length" class="flex items-center gap-2 py-2 mt-[5px]">
        <button
          v-for="cta in activeCtas"
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
</template>

<script>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useStore } from 'vuex';
import { findCategoryConfig, findActiveTab, getActiveCtas } from '@/constants/subNavConfig';

export default {
  name: 'SubNavBar',

  setup() {
    const route = useRoute();
    const store = useStore();

    const categoryConfig = computed(() => {
      return findCategoryConfig(route.path, route.query);
    });

    const activeTabComputed = computed(() => {
      return findActiveTab(categoryConfig.value, route.path, route.query);
    });

    const activeCtas = computed(() => {
      return getActiveCtas(categoryConfig.value, activeTabComputed.value);
    });

    const isTabActive = (tab) => {
      if (!activeTabComputed.value) return false;
      return tabKey(tab) === tabKey(activeTabComputed.value);
    };

    const tabKey = (tab) => {
      if (typeof tab.to === 'string') return tab.to;
      return tab.to.path + JSON.stringify(tab.to.query || {});
    };

    const handleCta = (action) => {
      store.dispatch('subNav/triggerCta', action);
    };

    return { categoryConfig, activeCtas, isTabActive, tabKey, handleCta };
  },
};
</script>
