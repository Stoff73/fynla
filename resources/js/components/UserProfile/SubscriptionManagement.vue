<template>
  <div class="space-y-6">
    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center items-center py-12">
      <div class="text-center">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600 mx-auto"></div>
        <p class="mt-4 text-body-base text-gray-600">Loading subscription details...</p>
      </div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="rounded-md bg-error-100 border border-error-600/20 p-4">
      <div class="flex">
        <div class="flex-shrink-0">
          <svg class="h-5 w-5 text-error-500" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
          </svg>
        </div>
        <div class="ml-3">
          <p class="text-body-sm font-medium text-error-600">{{ error }}</p>
          <button @click="fetchSubscriptionData" class="mt-2 btn-secondary text-xs">Try Again</button>
        </div>
      </div>
    </div>

    <!-- Content -->
    <template v-else>
      <!-- FREE TRIAL State -->
      <div v-if="subscriptionState === 'trialing'" class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="flex justify-between items-start mb-6">
          <div>
            <h3 class="text-h4 font-semibold text-gray-900">Free Trial</h3>
            <p class="mt-1 text-body-sm text-gray-600">
              You have full access to all {{ planDisplayName }} features during your trial
            </p>
          </div>
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
            Trial
          </span>
        </div>

        <!-- Live Countdown -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
          <div class="flex items-center gap-3 mb-3">
            <svg class="w-5 h-5 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="text-body-sm font-medium text-blue-800">Trial ends in</span>
          </div>
          <div class="flex gap-4">
            <div class="text-center">
              <span class="block text-2xl font-bold text-blue-900">{{ trialCountdown.days }}</span>
              <span class="text-caption text-blue-600">{{ trialCountdown.days === 1 ? 'day' : 'days' }}</span>
            </div>
            <div class="text-center">
              <span class="block text-2xl font-bold text-blue-900">{{ trialCountdown.hours }}</span>
              <span class="text-caption text-blue-600">{{ trialCountdown.hours === 1 ? 'hour' : 'hours' }}</span>
            </div>
            <div class="text-center">
              <span class="block text-2xl font-bold text-blue-900">{{ trialCountdown.minutes }}</span>
              <span class="text-caption text-blue-600">{{ trialCountdown.minutes === 1 ? 'minute' : 'minutes' }}</span>
            </div>
          </div>

          <!-- Progress bar -->
          <div class="mt-4">
            <div class="bg-blue-200 rounded-full h-1.5">
              <div
                class="bg-blue-500 h-1.5 rounded-full transition-all duration-500"
                :style="{ width: subscriptionData.progress + '%' }"
              ></div>
            </div>
          </div>
        </div>

        <!-- Plan Details -->
        <div class="space-y-3 mb-6">
          <div class="flex justify-between">
            <span class="text-body-sm text-gray-600">Plan:</span>
            <span class="text-body-sm text-gray-900">{{ planDisplayName }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-body-sm text-gray-600">Billing Cycle:</span>
            <span class="text-body-sm text-gray-900 capitalize">{{ subscriptionData.billing_cycle }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-body-sm text-gray-600">Price After Trial:</span>
            <span class="text-body-sm text-gray-900">{{ formatCurrency(subscriptionData.amount / 100) }}/{{ subscriptionData.billing_cycle === 'yearly' ? 'year' : 'month' }}</span>
          </div>
        </div>

        <button @click="showPlanModal = true" class="btn-primary w-full text-center block">
          Subscribe Now
        </button>
      </div>

      <!-- ACTIVE (Subscribed) State -->
      <div v-else-if="subscriptionState === 'active'" class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="flex justify-between items-start mb-6">
          <div>
            <h3 class="text-h4 font-semibold text-gray-900">Your Subscription</h3>
            <p class="mt-1 text-body-sm text-gray-600">
              Your {{ planDisplayName }} plan is active
            </p>
          </div>
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">
            Active
          </span>
        </div>

        <div class="space-y-3 mb-6">
          <div class="flex justify-between">
            <span class="text-body-sm text-gray-600">Plan:</span>
            <span class="text-body-sm text-gray-900">{{ planDisplayName }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-body-sm text-gray-600">Billing Cycle:</span>
            <span class="text-body-sm text-gray-900 capitalize">{{ subscriptionData.billing_cycle }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-body-sm text-gray-600">Amount:</span>
            <span class="text-body-sm text-gray-900">{{ formatCurrency(subscriptionData.amount / 100) }}/{{ subscriptionData.billing_cycle === 'yearly' ? 'year' : 'month' }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-body-sm text-gray-600">Next Renewal:</span>
            <span class="text-body-sm text-gray-900">{{ formatDate(subscriptionData.current_period_end) }}</span>
          </div>
        </div>

        <!-- Renewal Countdown -->
        <div v-if="renewalCountdown" class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6">
          <div class="flex items-center gap-3 mb-3">
            <svg class="w-5 h-5 text-gray-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <span class="text-body-sm font-medium text-gray-700">Renews in</span>
          </div>
          <div class="flex gap-4">
            <div class="text-center">
              <span class="block text-2xl font-bold text-gray-900">{{ renewalCountdown.days }}</span>
              <span class="text-caption text-gray-500">{{ renewalCountdown.days === 1 ? 'day' : 'days' }}</span>
            </div>
            <div class="text-center">
              <span class="block text-2xl font-bold text-gray-900">{{ renewalCountdown.hours }}</span>
              <span class="text-caption text-gray-500">{{ renewalCountdown.hours === 1 ? 'hour' : 'hours' }}</span>
            </div>
            <div class="text-center">
              <span class="block text-2xl font-bold text-gray-900">{{ renewalCountdown.minutes }}</span>
              <span class="text-caption text-gray-500">{{ renewalCountdown.minutes === 1 ? 'minute' : 'minutes' }}</span>
            </div>
          </div>
        </div>

        <button
          @click="showCancelModal = true"
          class="text-body-sm text-error-600 hover:text-error-700 transition-colors"
        >
          Cancel Subscription
        </button>
      </div>

      <!-- CANCELLED State (access until period end) -->
      <div v-else-if="subscriptionState === 'cancelled'" class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="flex justify-between items-start mb-6">
          <div>
            <h3 class="text-h4 font-semibold text-gray-900">Subscription Cancelled</h3>
            <p class="mt-1 text-body-sm text-gray-600">
              You still have access until the end of your current billing period
            </p>
          </div>
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">
            Cancelled
          </span>
        </div>

        <!-- Access Countdown -->
        <div v-if="accessCountdown" class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
          <div class="flex items-center gap-3 mb-3">
            <svg class="w-5 h-5 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="text-body-sm font-medium text-blue-800">Access ends in</span>
          </div>
          <div class="flex gap-4">
            <div class="text-center">
              <span class="block text-2xl font-bold text-blue-900">{{ accessCountdown.days }}</span>
              <span class="text-caption text-blue-600">{{ accessCountdown.days === 1 ? 'day' : 'days' }}</span>
            </div>
            <div class="text-center">
              <span class="block text-2xl font-bold text-blue-900">{{ accessCountdown.hours }}</span>
              <span class="text-caption text-blue-600">{{ accessCountdown.hours === 1 ? 'hour' : 'hours' }}</span>
            </div>
            <div class="text-center">
              <span class="block text-2xl font-bold text-blue-900">{{ accessCountdown.minutes }}</span>
              <span class="text-caption text-blue-600">{{ accessCountdown.minutes === 1 ? 'minute' : 'minutes' }}</span>
            </div>
          </div>
        </div>

        <div class="space-y-3 mb-6">
          <div class="flex justify-between">
            <span class="text-body-sm text-gray-600">Plan:</span>
            <span class="text-body-sm text-gray-900">{{ planDisplayName }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-body-sm text-gray-600">Cancelled On:</span>
            <span class="text-body-sm text-gray-900">{{ formatDate(subscriptionData.cancelled_at) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-body-sm text-gray-600">Access Until:</span>
            <span class="text-body-sm text-gray-900">{{ formatDate(subscriptionData.current_period_end) }}</span>
          </div>
        </div>

        <button @click="showPlanModal = true" class="btn-primary w-full text-center block">
          Renew
        </button>
      </div>

      <!-- PAST DUE (Overdue) State -->
      <div v-else-if="subscriptionState === 'past_due'" class="bg-white rounded-lg border border-error-600/20 p-6">
        <div class="flex justify-between items-start mb-6">
          <div>
            <h3 class="text-h4 font-semibold text-gray-900">Payment Issue</h3>
            <p class="mt-1 text-body-sm text-gray-600">
              Your most recent payment was unsuccessful
            </p>
          </div>
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-error-100 text-error-600">
            Payment Failed
          </span>
        </div>

        <div class="bg-error-100 border border-error-600/20 rounded-lg p-4 mb-6">
          <div class="flex gap-3">
            <svg class="w-5 h-5 text-error-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
            </svg>
            <div>
              <p class="text-body-sm font-medium text-error-600">
                Your payment could not be processed. We will automatically retry within the next 7 days.
              </p>
              <p class="mt-2 text-body-sm text-error-600">
                If the issue persists, please ensure your payment method is up to date. You retain full access during this period.
              </p>
            </div>
          </div>
        </div>

        <div class="space-y-3 mb-6">
          <div class="flex justify-between">
            <span class="text-body-sm text-gray-600">Plan:</span>
            <span class="text-body-sm text-gray-900">{{ planDisplayName }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-body-sm text-gray-600">Billing Cycle:</span>
            <span class="text-body-sm text-gray-900 capitalize">{{ subscriptionData.billing_cycle }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-body-sm text-gray-600">Amount:</span>
            <span class="text-body-sm text-gray-900">{{ formatCurrency(subscriptionData.amount / 100) }}/{{ subscriptionData.billing_cycle === 'yearly' ? 'year' : 'month' }}</span>
          </div>
        </div>

        <button @click="showPlanModal = true" class="btn-primary w-full text-center block">
          Update Payment Method
        </button>
      </div>

      <!-- EXPIRED / NO SUBSCRIPTION State -->
      <div v-else-if="subscriptionState === 'expired' || subscriptionState === 'none'" class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="flex justify-between items-start mb-6">
          <div>
            <h3 class="text-h4 font-semibold text-gray-900">
              {{ subscriptionState === 'expired' ? 'Subscription Expired' : 'No Subscription' }}
            </h3>
            <p class="mt-1 text-body-sm text-gray-600">
              {{ subscriptionState === 'expired'
                ? 'Your subscription has expired. Subscribe to regain full access to all features.'
                : 'Subscribe to access all Fynla financial planning features.'
              }}
            </p>
          </div>
          <span
            v-if="subscriptionState === 'expired'"
            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-700"
          >
            Expired
          </span>
        </div>

        <!-- Grace period countdown (simplified — full overlay in Task 8) -->
        <div v-if="subscriptionState === 'expired' && isInGracePeriod && gracePeriodCountdown" class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
          <div class="flex items-center gap-3 mb-3">
            <svg class="w-5 h-5 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="text-body-sm font-medium text-blue-800">Data retained for</span>
          </div>
          <div class="flex gap-4 mb-3">
            <div class="text-center">
              <span class="block text-2xl font-bold text-blue-900">{{ gracePeriodCountdown.days }}</span>
              <span class="text-caption text-blue-600">{{ gracePeriodCountdown.days === 1 ? 'day' : 'days' }}</span>
            </div>
            <div class="text-center">
              <span class="block text-2xl font-bold text-blue-900">{{ gracePeriodCountdown.hours }}</span>
              <span class="text-caption text-blue-600">{{ gracePeriodCountdown.hours === 1 ? 'hour' : 'hours' }}</span>
            </div>
            <div class="text-center">
              <span class="block text-2xl font-bold text-blue-900">{{ gracePeriodCountdown.minutes }}</span>
              <span class="text-caption text-blue-600">{{ gracePeriodCountdown.minutes === 1 ? 'minute' : 'minutes' }}</span>
            </div>
          </div>
          <p class="text-body-sm text-blue-700">
            Subscribe now to keep your financial plans and data.
          </p>
        </div>

        <button @click="showPlanModal = true" class="btn-primary w-full text-center block">
          Subscribe Now
        </button>
      </div>
      <!-- Billing History (visible for active, cancelled, past_due, expired states) -->
      <div
        v-if="billingHistory.length > 0"
        class="bg-white rounded-lg border border-gray-200 p-6"
      >
        <h3 class="text-h4 font-semibold text-gray-900 mb-4">Billing History</h3>
        <div class="overflow-x-auto">
          <table class="min-w-full">
            <thead>
              <tr class="border-b border-gray-200">
                <th class="text-left text-caption font-medium text-gray-500 pb-3">Date</th>
                <th class="text-left text-caption font-medium text-gray-500 pb-3">Description</th>
                <th class="text-left text-caption font-medium text-gray-500 pb-3">Reference</th>
                <th class="text-right text-caption font-medium text-gray-500 pb-3">Amount</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="payment in billingHistory"
                :key="payment.id"
                class="border-b border-gray-100 last:border-0"
              >
                <td class="py-3 text-body-sm text-gray-900">{{ formatDate(payment.date) }}</td>
                <td class="py-3 text-body-sm text-gray-600">{{ payment.description }}</td>
                <td class="py-3 text-body-sm text-gray-500 font-mono">{{ payment.reference }}</td>
                <td class="py-3 text-body-sm text-gray-900 text-right">{{ formatCurrency(payment.amount / 100) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>

    <!-- Plan Selection Modal -->
    <PlanSelectionModal
      v-if="showPlanModal"
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
        <div class="fixed inset-0 bg-gray-500/75 transition-opacity" @click="showCancelModal = false"></div>

        <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6 z-10">
          <h3 id="cancel-modal-title" class="text-h4 font-semibold text-gray-900 mb-2">
            Cancel Subscription
          </h3>
          <p class="text-body-sm text-gray-600 mb-6">
            Are you sure you want to cancel your {{ planDisplayName }} plan? You will retain access until
            <strong>{{ formatDate(subscriptionData.current_period_end) }}</strong>.
          </p>

          <!-- Cancellation Reason -->
          <div class="mb-6">
            <label for="cancel-reason" class="block text-body-sm font-medium text-gray-700 mb-1.5">
              Why are you cancelling? <span class="text-gray-400">(optional)</span>
            </label>
            <select
              id="cancel-reason"
              v-model="cancelReason"
              class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm
                     focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
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
            <label for="cancel-reason-text" class="block text-body-sm font-medium text-gray-700 mb-1.5">
              Please tell us more
            </label>
            <textarea
              id="cancel-reason-text"
              v-model="cancelReasonText"
              rows="3"
              maxlength="500"
              class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm
                     focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              placeholder="Tell us what we could improve..."
            ></textarea>
          </div>

          <!-- Cancel Error -->
          <div v-if="cancelError" class="bg-error-100 border border-error-600/20 rounded-lg p-3 mb-4">
            <p class="text-body-sm text-error-600">{{ cancelError }}</p>
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
import { useRouter } from 'vue-router';
import api from '@/services/api';
import { currencyMixin } from '@/mixins/currencyMixin';
import PlanSelectionModal from '@/components/Payment/PlanSelectionModal.vue';
import logger from '@/utils/logger';

export default {
  name: 'SubscriptionManagement',

  components: {
    PlanSelectionModal,
  },

  mixins: [currencyMixin],

  setup() {
    const router = useRouter();
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

    const fetchSubscriptionData = async () => {
      loading.value = true;
      error.value = null;
      try {
        const response = await api.get('/payment/trial-status');
        subscriptionData.value = response.data;
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
        billingHistory.value = response.data.payments || [];
      } catch (err) {
        logger.error('Failed to load billing history', err);
      }
    };

    const subscriptionState = computed(() => {
      if (!subscriptionData.value || !subscriptionData.value.has_subscription) return 'none';
      return subscriptionData.value.status || 'none';
    });

    const planDisplayName = computed(() => {
      if (!subscriptionData.value?.plan) return '';
      return subscriptionData.value.plan.charAt(0).toUpperCase() + subscriptionData.value.plan.slice(1);
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

    const trialCountdown = computed(() => {
      return calculateCountdown(subscriptionData.value?.trial_ends_at) || { days: 0, hours: 0, minutes: 0 };
    });

    const renewalCountdown = computed(() => {
      return calculateCountdown(subscriptionData.value?.current_period_end);
    });

    const accessCountdown = computed(() => {
      return calculateCountdown(subscriptionData.value?.current_period_end);
    });

    const gracePeriodCountdown = computed(() => {
      return calculateCountdown(subscriptionData.value?.grace_period_ends_at);
    });

    const isInGracePeriod = computed(() => {
      return subscriptionData.value?.is_in_grace_period || false;
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
      router.push(`/checkout?plan=${plan}&cycle=${billingCycle}`);
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

    // Refresh data when a countdown reaches zero (status may have changed server-side)
    watch(trialCountdown, (val) => {
      if (subscriptionState.value === 'trialing' && val.days === 0 && val.hours === 0 && val.minutes === 0) {
        fetchSubscriptionData();
      }
    });

    watch(accessCountdown, (val) => {
      if (subscriptionState.value === 'cancelled' && val && val.days === 0 && val.hours === 0 && val.minutes === 0) {
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
      subscriptionState,
      planDisplayName,
      trialCountdown,
      renewalCountdown,
      accessCountdown,
      gracePeriodCountdown,
      isInGracePeriod,
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
