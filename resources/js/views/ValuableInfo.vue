<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-4 sm:py-8">
      <!-- Tab Navigation -->
      <div class="bg-white rounded-lg shadow-sm mb-6">
        <div class="border-b border-light-gray">
          <nav class="-mb-px flex overflow-x-auto scrollbar-hide px-3" aria-label="Tabs">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              @click="activeTab = tab.id"
              :class="[
                activeTab === tab.id
                  ? 'border-raspberry-500 text-raspberry-700'
                  : 'border-transparent text-neutral-500 hover:text-horizon-500 hover:border-horizon-300',
                'whitespace-nowrap py-3 px-2 sm:px-3 border-b-2 font-medium text-xs sm:text-sm transition-colors flex-shrink-0',
              ]"
            >
              {{ tab.label }}
            </button>
          </nav>
        </div>

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

export default {
  name: 'ValuableInfo',

  components: {
    AppLayout,
    LetterToSpouse,
    RiskProfileSummary,
    IncomeOccupation,
    ExpenditureOverview,
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
        console.error('Failed to load profile:', err);
      } finally {
        loading.value = false;
      }
    };

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

