<template>
  <AppLayout>
    <div class="bg-eggshell-500 py-4 sm:py-8">
      <ModuleStatusBar />
      <div class="bg-white rounded-lg shadow-sm mb-6 module-gradient">
        <!-- Tab Content -->
        <div class="p-6">
          <!-- Loading State -->
          <div v-if="loading" class="flex justify-center items-center py-12">
            <div class="text-center">
              <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-raspberry-500 mx-auto"></div>
              <p class="mt-4 text-body-base text-neutral-500">Loading...</p>
            </div>
          </div>

          <!-- Tab Content Components -->
          <div v-else>
            <LetterToSpouse v-if="activeTab === 'letter'" />
            <IncomeOccupation v-if="activeTab === 'income'" />
            <ExpenditureOverview v-if="activeTab === 'expenditure'" />
            <RiskProfileSummary v-if="activeTab === 'risk'" />
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { ref, computed, onMounted, watch } from 'vue';
import { useStore } from 'vuex';
import { useRouter, useRoute } from 'vue-router';
import AppLayout from '@/layouts/AppLayout.vue';
import LetterToSpouse from '@/components/UserProfile/LetterToSpouse.vue';
import RiskProfileSummary from '@/components/Risk/RiskProfileSummary.vue';
import IncomeOccupation from '@/components/UserProfile/IncomeOccupation.vue';
import ExpenditureOverview from '@/components/UserProfile/ExpenditureOverview.vue';
import ModuleStatusBar from '@/components/Shared/ModuleStatusBar.vue';

import logger from '@/utils/logger';
export default {
  name: 'ValuableInfo',

  components: {
    AppLayout,
    LetterToSpouse,
    RiskProfileSummary,
    IncomeOccupation,
    ExpenditureOverview,
    ModuleStatusBar,
  },

  setup() {
    const store = useStore();
    const router = useRouter();
    const route = useRoute();
    const activeTab = ref('letter');
    const loading = ref(false);

    const user = computed(() => store.getters['userProfile/user']);

    // Update URL when tab changes (for proper back navigation)
    watch(activeTab, (newTab) => {
      const currentSection = route.query.section;
      if (currentSection !== newTab) {
        router.replace({ query: { section: newTab } });
      }
    });

    // Update active tab when route query changes (e.g., sidebar navigation)
    watch(() => route.query.section, (section) => {
      const validTabIds = ['letter', 'income', 'expenditure', 'risk'];
      if (section && validTabIds.includes(section) && activeTab.value !== section) {
        activeTab.value = section;
      }
    });

    // Marital statuses that show "Expression of Wishes" instead of "Letter to Spouse"
    const expressionOfWishesStatuses = ['single', 'widowed', 'divorced'];

    // Tabs with dynamic label for Letter/Expression based on marital status
    const tabs = computed(() => {
      const maritalStatus = user.value?.marital_status;
      const isExpressionOfWishes = expressionOfWishesStatuses.includes(maritalStatus);

      // Get spouse's first name for personalized tab label
      let letterLabel = 'Letter to Spouse';
      if (isExpressionOfWishes) {
        letterLabel = 'Expression of Wishes';
      } else if (user.value?.spouse?.name) {
        const spouseFirstName = user.value.spouse.name.split(' ')[0];
        letterLabel = `Letter to ${spouseFirstName}`;
      }

      return [
        { id: 'letter', label: letterLabel },
        { id: 'income', label: 'Income' },
        { id: 'expenditure', label: 'Expenditure' },
        { id: 'risk', label: 'Risk Profile' },
      ];
    });

    const loadProfile = async () => {
      loading.value = true;
      try {
        await store.dispatch('userProfile/fetchProfile');
      } catch (err) {
        logger.error('Failed to load profile:', err);
      } finally {
        loading.value = false;
      }
    };

    // Watch for AI form fill targeting expenditure
    const pendingFill = computed(() => store.state.aiFormFill?.pendingFill);
    watch(pendingFill, (fill) => {
      if (fill && fill.entityType === 'expenditure') {
        activeTab.value = 'expenditure';
      }
    }, { immediate: true });

    onMounted(() => {
      loadProfile();

      // Check for section query parameter and set active tab
      const section = route.query.section;
      if (section) {
        const validTabIds = ['letter', 'income', 'expenditure', 'risk'];
        if (validTabIds.includes(section)) {
          activeTab.value = section;
        }
      }
    });

    return {
      activeTab,
      tabs,
      loading,
      user,
    };
  },
};
</script>

