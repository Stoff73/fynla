<template>
  <div v-if="visible" class="data-retention-overlay">
    <!-- Semi-transparent backdrop that fades the content behind it -->
    <div class="fixed inset-0 bg-horizon-600/60 z-40"></div>

    <!-- Non-dismissable modal -->
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-lg shadow-2xl max-w-lg w-full p-8 relative">
        <!-- Countdown header -->
        <div class="text-center mb-6">
          <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-100 mb-4">
            <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <h2 class="text-xl font-bold text-horizon-500">Your Subscription Has Expired</h2>
          <p class="mt-2 text-body-sm text-neutral-500">
            Your data will be permanently deleted in:
          </p>
        </div>

        <!-- Countdown display -->
        <div class="bg-eggshell-500 border border-light-gray rounded-lg p-4 mb-6">
          <div class="flex justify-center gap-6">
            <div class="text-center">
              <span class="block text-3xl font-bold text-horizon-500">{{ countdown.days }}</span>
              <span class="text-caption text-neutral-500">{{ countdown.days === 1 ? 'day' : 'days' }}</span>
            </div>
            <div class="text-center">
              <span class="block text-3xl font-bold text-horizon-500">{{ countdown.hours }}</span>
              <span class="text-caption text-neutral-500">{{ countdown.hours === 1 ? 'hour' : 'hours' }}</span>
            </div>
            <div class="text-center">
              <span class="block text-3xl font-bold text-horizon-500">{{ countdown.minutes }}</span>
              <span class="text-caption text-neutral-500">{{ countdown.minutes === 1 ? 'minute' : 'minutes' }}</span>
            </div>
          </div>
        </div>

        <!-- Info text -->
        <p class="text-body-sm text-neutral-500 text-center mb-6">
          Subscribe now to regain full access to your financial plans. Or choose to delete all your data and start fresh.
        </p>

        <!-- Action buttons -->
        <div class="space-y-3">
          <router-link
            to="/checkout"
            class="btn-primary w-full text-center block py-3 text-base font-semibold"
          >
            Subscribe Now
          </router-link>

          <button
            v-if="!showDeleteConfirmation"
            @click="showDeleteConfirmation = true"
            class="w-full py-3 px-4 text-sm font-medium text-neutral-500 hover:text-neutral-500 border border-horizon-300 rounded-lg hover:border-horizon-400 transition-colors"
          >
            Delete All Data &amp; Start Again
          </button>

          <!-- Delete confirmation -->
          <div v-if="showDeleteConfirmation" class="border border-raspberry-600/20 rounded-lg p-4 bg-raspberry-100/50">
            <p class="text-body-sm font-medium text-raspberry-600 mb-3">
              This action is permanent and cannot be undone. All your financial plans, policies, pensions, investments, savings, goals, and documents will be deleted.
            </p>
            <label for="delete-password" class="block text-body-sm text-neutral-500 mb-1.5">
              Enter your password:
            </label>
            <input
              id="delete-password"
              v-model="currentPassword"
              type="password"
              autocomplete="current-password"
              class="w-full px-3 py-2 border border-horizon-300 rounded-md text-sm
                     focus:outline-none focus:ring-2 focus:ring-raspberry-500 focus:border-raspberry-500 mb-3"
              placeholder="Your account password"
            />
            <label for="delete-confirm" class="block text-body-sm text-neutral-500 mb-1.5">
              Type <strong>DELETE</strong> to confirm:
            </label>
            <input
              id="delete-confirm"
              v-model="deleteConfirmText"
              type="text"
              autocomplete="off"
              class="w-full px-3 py-2 border border-horizon-300 rounded-md text-sm
                     focus:outline-none focus:ring-2 focus:ring-raspberry-500 focus:border-raspberry-500 mb-3"
              placeholder="Type DELETE here"
            />

            <!-- Delete error -->
            <div v-if="deleteError" class="bg-raspberry-100 border border-raspberry-600/20 rounded-lg p-2 mb-3">
              <p class="text-body-sm text-raspberry-600">{{ deleteError }}</p>
            </div>

            <div class="flex gap-3">
              <button
                @click="showDeleteConfirmation = false; deleteConfirmText = ''; currentPassword = ''; deleteError = null"
                class="btn-secondary flex-1"
                :disabled="deleting"
              >
                Cancel
              </button>
              <button
                @click="confirmDeleteAll"
                class="flex-1 py-2 px-4 text-sm font-medium text-white rounded-lg transition-colors"
                :class="deleteConfirmText === 'DELETE' && currentPassword.length > 0
                  ? 'bg-raspberry-600 hover:bg-raspberry-700'
                  : 'bg-horizon-300 cursor-not-allowed'"
                :disabled="deleteConfirmText !== 'DELETE' || !currentPassword || deleting"
              >
                <span v-if="deleting">Deleting...</span>
                <span v-else>Permanently Delete</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import api from '@/services/api';
import logger from '@/utils/logger';

export default {
  name: 'DataRetentionOverlay',

  setup() {
    const subscriptionData = ref(null);
    const now = ref(new Date());
    const showDeleteConfirmation = ref(false);
    const deleteConfirmText = ref('');
    const currentPassword = ref('');
    const deleting = ref(false);
    const deleteError = ref(null);
    let countdownInterval = null;

    const fetchSubscriptionStatus = async () => {
      try {
        const response = await api.get('/payment/trial-status');
        subscriptionData.value = response.data;
      } catch (err) {
        logger.error('DataRetentionOverlay: failed to fetch status', err);
      }
    };

    const visible = computed(() => {
      if (!subscriptionData.value) return false;
      return subscriptionData.value.status === 'expired'
        && subscriptionData.value.is_in_grace_period === true
        && subscriptionData.value.payment_enabled === true;
    });

    const countdown = computed(() => {
      const target = subscriptionData.value?.grace_period_ends_at;
      if (!target) return { days: 0, hours: 0, minutes: 0 };
      const diff = new Date(target) - now.value;
      if (diff <= 0) return { days: 0, hours: 0, minutes: 0 };
      return {
        days: Math.floor(diff / (1000 * 60 * 60 * 24)),
        hours: Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60)),
        minutes: Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60)),
      };
    });

    const confirmDeleteAll = async () => {
      if (deleteConfirmText.value !== 'DELETE' || !currentPassword.value) return;

      deleting.value = true;
      deleteError.value = null;

      try {
        await api.post('/payment/delete-all-data', {
          confirmation_text: 'DELETE',
          current_password: currentPassword.value,
        });

        // Redirect to landing page after successful deletion
        window.location.href = '/';
      } catch (err) {
        logger.error('Failed to delete all data', err);
        deleteError.value = err.response?.data?.error || 'Failed to delete data. Please try again.';
        deleting.value = false;
      }
    };

    onMounted(() => {
      fetchSubscriptionStatus();
      countdownInterval = setInterval(() => {
        now.value = new Date();
      }, 60000);
    });

    onBeforeUnmount(() => {
      if (countdownInterval) {
        clearInterval(countdownInterval);
      }
    });

    return {
      visible,
      countdown,
      showDeleteConfirmation,
      deleteConfirmText,
      currentPassword,
      deleting,
      deleteError,
      confirmDeleteAll,
    };
  },
};
</script>
