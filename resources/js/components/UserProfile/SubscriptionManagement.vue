<template>
  <div class="space-y-6">
    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center items-center py-12">
      <div class="text-center">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-raspberry-600 mx-auto"></div>
        <p class="mt-4 text-body-base text-neutral-500">Loading subscription details...</p>
      </div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="rounded-md bg-raspberry-100 border border-raspberry-600/20 p-4">
      <div class="flex">
        <div class="flex-shrink-0">
          <svg class="h-5 w-5 text-raspberry-500" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
          </svg>
        </div>
        <div class="ml-3">
          <p class="text-body-sm font-medium text-raspberry-600">{{ error }}</p>
          <button @click="fetchSubscriptionData" class="mt-2 btn-secondary text-xs">Try Again</button>
        </div>
      </div>
    </div>

    <!-- Content -->
    <template v-else>
      <!-- FREE State -->
      <div v-if="presentation.state === 'free'" class="bg-white rounded-lg border border-light-gray p-6">
        <div class="flex justify-between items-start mb-6">
          <div>
            <h3 class="text-h4 font-semibold text-horizon-500">Free</h3>
            <p class="mt-1 text-body-sm text-neutral-500">
              Your Free plan includes Fynla's core financial planning features.
            </p>
          </div>
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-violet-100 text-violet-800">
            Free
          </span>
        </div>

        <button v-if="showUpgradeEntry" @click="showPlanModal = true" class="btn-primary w-full text-center block">
          Compare plans
        </button>
      </div>

      <!-- PENDING Premium checkout State -->
      <div v-else-if="presentation.state === 'pending'" class="bg-white rounded-lg border border-light-gray p-6">
        <div class="flex justify-between items-start mb-6">
          <div>
            <h3 class="text-h4 font-semibold text-horizon-500">Payment pending</h3>
            <p class="mt-1 text-body-sm text-neutral-500">
              Premium will activate only after payment is verified. Free access remains available now.
            </p>
          </div>
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-violet-100 text-violet-800">
            {{ presentation.label }}
          </span>
        </div>

        <button v-if="showUpgradeEntry" @click="showPlanModal = true" class="btn-primary w-full text-center block">
          Continue to Premium
        </button>
      </div>

      <!-- ACTIVE (Subscribed) State -->
      <div v-else-if="presentation.state === 'active'" class="bg-white rounded-lg border border-light-gray p-6">
        <div class="flex justify-between items-start mb-6">
          <div>
            <h3 class="text-h4 font-semibold text-horizon-500">Your Subscription</h3>
            <p class="mt-1 text-body-sm text-neutral-500">
              Your {{ planDisplayName }} plan is active
            </p>
          </div>
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-spring-100 text-spring-800">
            Active
          </span>
        </div>

        <div class="space-y-3 mb-6">
          <div class="flex justify-between">
            <span class="text-body-sm text-neutral-500">Plan:</span>
            <span class="text-body-sm text-horizon-500">{{ planDisplayName }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-body-sm text-neutral-500">Billing Cycle:</span>
            <span class="text-body-sm text-horizon-500 capitalize">{{ subscriptionData.billing_cycle }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-body-sm text-neutral-500">Amount:</span>
            <span class="text-body-sm text-horizon-500">{{ formatCurrencyWithPence(subscriptionData.amount / 100) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-body-sm text-neutral-500">{{ subscriptionData.auto_renew ? 'Next Renewal:' : 'Access Ends:' }}</span>
            <span class="text-body-sm text-horizon-500">{{ formatDate(subscriptionData.current_period_end) }}</span>
          </div>
          <div v-if="subscriptionData.auto_renew" class="flex justify-between">
            <span class="text-body-sm text-neutral-500">Auto-renewal:</span>
            <span class="text-body-sm text-spring-600 font-medium">Active</span>
          </div>
        </div>

        <div v-if="subscriptionData.auto_renew && subscriptionData.next_renewal_date" class="bg-spring-50 border border-spring-200 rounded-lg p-3 mb-4">
          <p class="text-caption text-spring-800">
            Your next payment of {{ formatCurrencyWithPence(subscriptionData.amount / 100) }} will be taken on {{ formatDate(subscriptionData.next_renewal_date) }}.
          </p>
        </div>

        <button
          v-if="subscriptionData.auto_renew"
          @click="showCancelModal = true"
          class="text-body-sm text-raspberry-600 hover:text-raspberry-700 transition-colors"
        >
          Cancel Subscription
        </button>
      </div>

      <!-- CANCELLED State (access until period end) -->
      <div v-else-if="presentation.state === 'cancelled'" class="bg-white rounded-lg border border-light-gray p-6">
        <div class="flex justify-between items-start mb-6">
          <div>
            <h3 class="text-h4 font-semibold text-horizon-500">Subscription Cancelled</h3>
            <p class="mt-1 text-body-sm text-neutral-500">
              Auto-renewal has been cancelled. You retain access until the end of your current billing period.
            </p>
            <p class="mt-1 text-body-sm font-medium text-horizon-500">{{ presentation.label }}</p>
          </div>
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-savannah-100 text-neutral-500">
            Cancelled
          </span>
        </div>

        <!-- Access Countdown -->
        <div v-if="accessCountdown" class="bg-violet-50 border border-violet-200 rounded-lg p-4 mb-6">
          <div class="flex items-center gap-3 mb-3">
            <svg class="w-5 h-5 text-violet-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="text-body-sm font-medium text-violet-800">Access ends in</span>
          </div>
          <div class="flex gap-4">
            <div class="text-center">
              <span class="block text-2xl font-bold text-violet-900">{{ accessCountdown.days }}</span>
              <span class="text-caption text-violet-600">{{ accessCountdown.days === 1 ? 'day' : 'days' }}</span>
            </div>
            <div class="text-center">
              <span class="block text-2xl font-bold text-violet-900">{{ accessCountdown.hours }}</span>
              <span class="text-caption text-violet-600">{{ accessCountdown.hours === 1 ? 'hour' : 'hours' }}</span>
            </div>
            <div class="text-center">
              <span class="block text-2xl font-bold text-violet-900">{{ accessCountdown.minutes }}</span>
              <span class="text-caption text-violet-600">{{ accessCountdown.minutes === 1 ? 'minute' : 'minutes' }}</span>
            </div>
          </div>
        </div>

        <div class="space-y-3 mb-6">
          <div class="flex justify-between">
            <span class="text-body-sm text-neutral-500">Plan:</span>
            <span class="text-body-sm text-horizon-500">{{ planDisplayName }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-body-sm text-neutral-500">Cancelled On:</span>
            <span class="text-body-sm text-horizon-500">{{ formatDate(subscriptionData.cancelled_at) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-body-sm text-neutral-500">Access Until:</span>
            <span class="text-body-sm text-horizon-500">{{ formatDate(subscriptionData.current_period_end) }}</span>
          </div>
        </div>

      </div>

      <!-- PAST DUE (Overdue) State -->
      <div v-else-if="presentation.state === 'past_due'" class="bg-white rounded-lg border border-raspberry-600/20 p-6">
        <div class="flex justify-between items-start mb-6">
          <div>
            <h3 class="text-h4 font-semibold text-horizon-500">Payment Issue</h3>
            <p class="mt-1 text-body-sm text-neutral-500">
              We were unable to process your automatic renewal payment
            </p>
            <p class="mt-1 text-body-sm font-medium text-horizon-500">{{ presentation.label }}</p>
          </div>
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-raspberry-100 text-raspberry-600">
            Payment Failed
          </span>
        </div>

        <div class="bg-raspberry-100 border border-raspberry-600/20 rounded-lg p-4 mb-6">
          <div class="flex gap-3">
            <svg class="w-5 h-5 text-raspberry-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
            </svg>
            <div>
              <p class="text-body-sm font-medium text-raspberry-600">
                We were unable to process your automatic renewal payment. We will retry automatically.
              </p>
              <p class="mt-2 text-body-sm text-raspberry-600">
                If the issue persists, please ensure your payment method is up to date. You retain full access during this period.
              </p>
            </div>
          </div>
        </div>

        <div class="space-y-3 mb-6">
          <div class="flex justify-between">
            <span class="text-body-sm text-neutral-500">Plan:</span>
            <span class="text-body-sm text-horizon-500">{{ planDisplayName }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-body-sm text-neutral-500">Billing Cycle:</span>
            <span class="text-body-sm text-horizon-500 capitalize">{{ subscriptionData.billing_cycle }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-body-sm text-neutral-500">Amount:</span>
            <span class="text-body-sm text-horizon-500">{{ formatCurrencyWithPence(subscriptionData.amount / 100) }}</span>
          </div>
        </div>

        <button v-if="showUpgradeEntry" @click="showPlanModal = true" class="btn-primary w-full text-center block">
          Resolve payment
        </button>
      </div>

      <!-- ENDED / GRACE State -->
      <div v-else-if="['grace', 'expired'].includes(presentation.state)" class="bg-white rounded-lg border border-light-gray p-6">
        <div class="flex justify-between items-start mb-6">
          <div>
            <h3 class="text-h4 font-semibold text-horizon-500">{{ presentation.label }}</h3>
            <p class="mt-1 text-body-sm text-neutral-500">
              Choose Premium to restore full access to your financial planning features.
            </p>
          </div>
          <span
            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-savannah-100 text-neutral-500"
          >
            Ended
          </span>
        </div>

        <!-- Grace period countdown (the full retention controls remain in DataRetentionOverlay) -->
        <div v-if="presentation.state === 'grace' && gracePeriodCountdown" class="bg-violet-50 border border-violet-200 rounded-lg p-4 mb-6">
          <div class="flex items-center gap-3 mb-3">
            <svg class="w-5 h-5 text-violet-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="text-body-sm font-medium text-violet-800">Data retained for</span>
          </div>
          <div class="flex gap-4 mb-3">
            <div class="text-center">
              <span class="block text-2xl font-bold text-violet-900">{{ gracePeriodCountdown.days }}</span>
              <span class="text-caption text-violet-600">{{ gracePeriodCountdown.days === 1 ? 'day' : 'days' }}</span>
            </div>
            <div class="text-center">
              <span class="block text-2xl font-bold text-violet-900">{{ gracePeriodCountdown.hours }}</span>
              <span class="text-caption text-violet-600">{{ gracePeriodCountdown.hours === 1 ? 'hour' : 'hours' }}</span>
            </div>
            <div class="text-center">
              <span class="block text-2xl font-bold text-violet-900">{{ gracePeriodCountdown.minutes }}</span>
              <span class="text-caption text-violet-600">{{ gracePeriodCountdown.minutes === 1 ? 'minute' : 'minutes' }}</span>
            </div>
          </div>
          <p class="text-body-sm text-violet-700">
            Subscribe now to keep your financial plans and data.
          </p>
        </div>

        <button v-if="showUpgradeEntry" @click="showPlanModal = true" class="btn-primary w-full text-center block">
          Subscribe to Premium
        </button>
      </div>
      <!-- Billing History (visible for active, cancelled, past_due, expired states) -->
      <div
        v-if="billingHistory.length > 0"
        class="bg-white rounded-lg border border-light-gray p-6"
      >
        <h3 class="text-h4 font-semibold text-horizon-500 mb-4">Billing History</h3>
        <div class="overflow-x-auto">
          <table class="min-w-full">
            <thead>
              <tr class="border-b border-light-gray">
                <th class="text-left text-caption font-medium text-neutral-500 pb-3">Date</th>
                <th class="text-left text-caption font-medium text-neutral-500 pb-3">Invoice</th>
                <th class="text-left text-caption font-medium text-neutral-500 pb-3">Description</th>
                <th class="text-right text-caption font-medium text-neutral-500 pb-3">Amount</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="payment in billingHistory"
                :key="payment.id"
                class="border-b border-light-gray last:border-0"
              >
                <td class="py-3 text-body-sm text-horizon-500">{{ formatDate(payment.date) }}</td>
                <td class="py-3 text-body-sm">
                  <router-link
                    v-if="payment.has_invoice"
                    :to="`/invoice/${payment.invoice_id}`"
                    class="text-raspberry-500 hover:text-raspberry-700 transition-colors font-mono"
                  >
                    {{ payment.invoice_number }}
                  </router-link>
                  <span v-else class="text-neutral-400">&mdash;</span>
                </td>
                <td class="py-3 text-body-sm text-neutral-500">{{ payment.description }}</td>
                <td class="py-3 text-body-sm text-horizon-500 text-right">{{ formatCurrencyWithPence(payment.amount / 100) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>

    <!-- Plan Selection Modal -->
    <PlanSelectionModal
      v-if="showPlanModal"
      :current-plan="currentPlanForModal"
      @select="handlePlanSelect"
      @close="showPlanModal = false"
    />

    <!-- Cancel Subscription Modal -->
    <div
      v-if="showCancelModal"
      class="fixed inset-0 z-50 overflow-y-auto"
      aria-labelledby="cancel-modal-title"
      role="dialog"
      aria-modal="true"
    >
      <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-savannah-1000/75 transition-opacity" @click="showCancelModal = false"></div>

        <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6 z-10">
          <h3 id="cancel-modal-title" class="text-h4 font-semibold text-horizon-500 mb-2">
            Cancel Subscription
          </h3>
          <p class="text-body-sm text-neutral-500 mb-6">
            Are you sure you want to cancel your {{ planDisplayName }} plan? You will retain access until
            <strong>{{ formatDate(subscriptionData.current_period_end) }}</strong>.
          </p>

          <!-- Cancellation Reason -->
          <div class="mb-6">
            <label for="cancel-reason" class="block text-body-sm font-medium text-neutral-500 mb-1.5">
              Why are you cancelling? <span class="text-horizon-400">(optional)</span>
            </label>
            <select
              id="cancel-reason"
              v-model="cancelReason"
              class="w-full px-3 py-2 border border-horizon-300 rounded-md text-sm
                     focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500"
            >
              <option value="">Select a reason...</option>
              <option value="too_expensive">Too expensive</option>
              <option value="not_using_enough">Not using it enough</option>
              <option value="missing_features">Missing features I need</option>
              <option value="found_alternative">Found an alternative</option>
              <option value="temporary_break">Taking a temporary break</option>
              <option value="technical_issues">Technical issues</option>
              <option value="other">Other</option>
            </select>
          </div>

          <div v-if="cancelReason === 'other'" class="mb-6">
            <label for="cancel-reason-text" class="block text-body-sm font-medium text-neutral-500 mb-1.5">
              Please tell us more
            </label>
            <textarea
              id="cancel-reason-text"
              v-model="cancelReasonText"
              rows="3"
              maxlength="500"
              class="w-full px-3 py-2 border border-horizon-300 rounded-md text-sm
                     focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500"
              placeholder="Tell us what we could improve..."
            ></textarea>
          </div>

          <!-- Cancel Error -->
          <div v-if="cancelError" class="bg-raspberry-100 border border-raspberry-600/20 rounded-lg p-3 mb-4">
            <p class="text-body-sm text-raspberry-600">{{ cancelError }}</p>
          </div>

          <!-- Actions -->
          <div class="flex justify-end gap-3">
            <button
              @click="showCancelModal = false"
              class="btn-secondary"
              :disabled="cancelling"
            >
              Keep Subscription
            </button>
            <button
              @click="confirmCancel"
              class="btn-danger"
              :disabled="cancelling"
            >
              <span v-if="cancelling">Cancelling...</span>
              <span v-else>Cancel Subscription</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useStore } from 'vuex';
import api from '@/services/api';
import { currencyMixin } from '@/mixins/currencyMixin';
import PlanSelectionModal from '@/components/Payment/PlanSelectionModal.vue';
import { getSubscriptionPresentation } from '@/utils/subscriptionPresentation';
import logger from '@/utils/logger';

export default {
  name: 'SubscriptionManagement',

  components: {
    PlanSelectionModal,
  },

  mixins: [currencyMixin],

  setup() {
    const route = useRoute();
    const router = useRouter();
    const store = useStore();
    const loading = ref(true);
    const error = ref(null);
    const subscriptionData = ref(null);
    const showPlanModal = ref(false);
    const showCancelModal = ref(false);
    const cancelling = ref(false);
    const cancelError = ref(null);
    const cancelReason = ref('');
    const cancelReasonText = ref('');
    const now = ref(new Date());
    const billingHistory = ref([]);
    let countdownInterval = null;

    const presentation = computed(() => getSubscriptionPresentation(subscriptionData.value, now.value));

    const showUpgradeEntry = computed(() => presentation.value.showUpgrade
      && !store.getters['preview/isPreviewMode']);

    const openPricingFromQuery = () => {
      if (!route.query.openPricing) return;
      if (showUpgradeEntry.value) showPlanModal.value = true;
      const query = { ...route.query };
      delete query.openPricing;
      router.replace({ path: route.path, query }).catch(() => {});
    };

    const fetchSubscriptionData = async () => {
      loading.value = true;
      error.value = null;
      try {
        const response = await api.get('/payment/subscription-status');
        subscriptionData.value = response.data;
        openPricingFromQuery();
        // Fetch billing history if user has a subscription
        if (response.data.has_subscription) {
          fetchBillingHistory();
        }
      } catch (err) {
        logger.error('Failed to load subscription data', err);
        error.value = 'Failed to load subscription details. Please try again.';
      } finally {
        loading.value = false;
      }
    };

    const fetchBillingHistory = async () => {
      try {
        const response = await api.get('/payment/billing-history');
        billingHistory.value = response.data.data?.payments || [];
      } catch (err) {
        logger.error('Failed to load billing history', err);
      }
    };

    const planDisplayName = computed(() => {
      if (!['active', 'cancelled', 'past_due'].includes(presentation.value.state)) return 'Free';
      return subscriptionData.value?.tier_display_name || 'Premium';
    });

    // Live countdown calculation
    const calculateCountdown = (targetDateStr) => {
      if (!targetDateStr) return null;
      const target = new Date(targetDateStr);
      const diff = target - now.value;
      if (diff <= 0) return { days: 0, hours: 0, minutes: 0 };
      return {
        days: Math.floor(diff / (1000 * 60 * 60 * 24)),
        hours: Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60)),
        minutes: Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60)),
      };
    };

    const accessCountdown = computed(() => {
      return calculateCountdown(subscriptionData.value?.current_period_end);
    });

    const gracePeriodCountdown = computed(() => {
      return calculateCountdown(subscriptionData.value?.grace_period_ends_at);
    });

    const currentPlanForModal = computed(() => {
      return presentation.value.showUpgrade ? 'free' : subscriptionData.value?.tier || 'free';
    });

    const formatDate = (dateStr) => {
      if (!dateStr) return '\u2014';
      const date = new Date(dateStr);
      return date.toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
      });
    };

    const handlePlanSelect = ({ plan, billingCycle }) => {
      showPlanModal.value = false;
      router.push({
        path: '/checkout',
        query: { plan, cycle: billingCycle },
      });
    };

    const confirmCancel = async () => {
      cancelling.value = true;
      cancelError.value = null;

      const reason = cancelReason.value === 'other'
        ? (cancelReasonText.value.trim() || null)
        : (cancelReason.value || null);

      try {
        await api.post('/payment/cancel-subscription', { reason });
        showCancelModal.value = false;
        cancelReason.value = '';
        cancelReasonText.value = '';
        await fetchSubscriptionData();
      } catch (err) {
        logger.error('Failed to cancel subscription', err);
        cancelError.value = err.response?.data?.error || 'Failed to cancel subscription. Please try again.';
      } finally {
        cancelling.value = false;
      }
    };

    // Refresh data once when retained access reaches zero (status may have changed server-side).
    let accessExpiredFetched = false;
    watch(accessCountdown, (val) => {
      if (!accessExpiredFetched && presentation.value.state === 'cancelled' && val && val.days === 0 && val.hours === 0 && val.minutes === 0) {
        accessExpiredFetched = true;
        fetchSubscriptionData();
      }
    });

    // Start live countdown timer
    onMounted(() => {
      fetchSubscriptionData();
      countdownInterval = setInterval(() => {
        now.value = new Date();
      }, 60000); // Update every minute
    });

    onBeforeUnmount(() => {
      if (countdownInterval) {
        clearInterval(countdownInterval);
      }
    });

    return {
      loading,
      error,
      subscriptionData,
      presentation,
      planDisplayName,
      accessCountdown,
      gracePeriodCountdown,
      currentPlanForModal,
      showUpgradeEntry,
      billingHistory,
      showPlanModal,
      handlePlanSelect,
      showCancelModal,
      cancelling,
      cancelError,
      cancelReason,
      cancelReasonText,
      formatDate,
      fetchSubscriptionData,
      confirmCancel,
    };
  },
};
</script>
