<template>
  <AppLayout>
    <div class="module-gradient py-8">
      <div class="mb-8">
        <h1 class="text-h2 font-display text-horizon-500">Settings</h1>
        <p class="mt-2 text-body-base text-neutral-500">
          Manage your account settings and preferences
        </p>
      </div>

      <SettingsTabBar />

      <!-- Account Actions Card -->
      <div class="card">
        <div class="border-b border-light-gray pb-4 mb-6">
          <h2 class="text-h4 font-semibold text-horizon-500">General</h2>
          <p class="mt-1 text-body-sm text-neutral-500">
            General account settings and preferences
          </p>
        </div>

        <div class="space-y-4">
          <!-- Account Status -->
          <div class="flex items-center justify-between py-4 border-b border-light-gray">
            <div>
              <h3 class="text-body-base font-medium text-horizon-500">Account Status</h3>
              <p class="text-body-sm text-neutral-500">
                <span v-if="subscriptionLoading">Loading...</span>
                <span v-else>{{ planDisplayName }}</span>
              </p>
            </div>
            <button
              v-if="!subscriptionLoading && activePlanSlug !== 'premium'"
              @click="showPlanModal = true"
              class="btn-primary"
            >
              Choose a Plan
            </button>
          </div>

          <div class="flex items-center justify-between py-4 border-b border-light-gray">
            <div>
              <h3 class="text-body-base font-medium text-horizon-500">Email Notifications</h3>
              <p class="text-body-sm text-neutral-500">Manage your email notification preferences</p>
            </div>
            <router-link to="/settings/notifications" class="btn-secondary">
              Manage
            </router-link>
          </div>

        </div>
      </div>
    </div>

    <!-- Plan Selection Modal -->
    <PlanSelectionModal
      v-if="showPlanModal"
      :current-plan="activePlanSlug"
      :show-all-plans="true"
      @select="handlePlanSelect"
      @close="showPlanModal = false"
    />
  </AppLayout>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsTabBar from '@/components/Settings/SettingsTabBar.vue';
import PlanSelectionModal from '@/components/Payment/PlanSelectionModal.vue';
import api from '@/services/api';

const PLAN_NAMES = {
  free: 'Free',
  premium: 'Premium',
};

export default {
  name: 'Settings',

  components: {
    AppLayout,
    SettingsTabBar,
    PlanSelectionModal,
  },

  setup() {
    const router = useRouter();
    const showPlanModal = ref(false);
    const subscriptionData = ref(null);
    const subscriptionLoading = ref(true);

    const activePlanSlug = computed(() => {
      if (!subscriptionData.value) return null;
      return subscriptionData.value.tier || 'free';
    });

    const planDisplayName = computed(() => {
      const tier = subscriptionData.value?.tier || 'free';
      return PLAN_NAMES[tier] || tier;
    });

    const fetchSubscription = async () => {
      subscriptionLoading.value = true;
      try {
        const response = await api.get('/payment/subscription-status');
        subscriptionData.value = response.data;
      } catch {
        // Silently fail
      } finally {
        subscriptionLoading.value = false;
      }
    };

    const handlePlanSelect = ({ plan, billingCycle, isUpgrade }) => {
      showPlanModal.value = false;
      const upgradeParam = isUpgrade ? '&upgrade=true' : '';
      router.push(`/payment/checkout?plan=${plan}&billing=${billingCycle}${upgradeParam}`);
    };

    onMounted(() => {
      fetchSubscription();
    });

    return {
      showPlanModal,
      subscriptionLoading,
      activePlanSlug,
      planDisplayName,
      handlePlanSelect,
    };
  },
};
</script>
