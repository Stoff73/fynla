<template>
  <div>
    <!-- Page Header -->
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-black text-horizon-500">Reviews Due</h1>
      <div v-if="overdueCount > 0" class="text-sm font-semibold px-3 py-1 rounded-full bg-raspberry-50 text-raspberry-700">
        {{ overdueCount }} overdue
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-16">
      <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
    </div>

    <!-- Reviews List -->
    <div v-else-if="reviewsDue.length > 0" class="space-y-4">
      <div
        v-for="review in reviewsDue"
        :key="review.client_id"
        class="bg-white border border-light-gray rounded-xl p-6 shadow-card hover:shadow-md transition-shadow"
      >
        <div class="flex items-start justify-between">
          <!-- Left: Client info -->
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-3 mb-3">
              <h3 class="text-base font-bold text-horizon-500">{{ review.display_name }}</h3>
              <span
                class="text-xs font-bold px-2.5 py-0.5 rounded-full"
                :class="review.is_overdue
                  ? 'bg-raspberry-50 text-raspberry-700'
                  : 'bg-violet-50 text-violet-700'"
              >
                {{ review.is_overdue ? `${review.days_overdue} days overdue` : `Due in ${review.days_until_due} days` }}
              </span>
            </div>

            <div class="grid grid-cols-3 gap-4 text-sm mb-3">
              <div>
                <span class="text-neutral-500">Last Review</span>
                <div class="font-semibold text-horizon-500 mt-0.5">
                  {{ review.last_review_date ? formatDateShort(review.last_review_date) : 'No reviews yet' }}
                </div>
              </div>
              <div>
                <span class="text-neutral-500">Frequency</span>
                <div class="font-semibold text-horizon-500 mt-0.5">
                  {{ formatFrequency(review.review_frequency_months) }}
                </div>
              </div>
              <div>
                <span class="text-neutral-500">Next Due</span>
                <div
                  class="font-semibold mt-0.5"
                  :class="review.is_overdue ? 'text-raspberry-500' : 'text-horizon-500'"
                >
                  {{ review.next_review_due ? formatDateShort(review.next_review_due) : '--' }}
                </div>
              </div>
            </div>

            <div v-if="review.last_activity_summary || review.focus" class="text-sm text-neutral-500 leading-relaxed">
              <span v-if="review.focus" class="font-semibold text-horizon-500">{{ review.focus }}</span>
              <span v-if="review.focus && review.last_activity_summary"> — </span>
              <span v-if="review.last_activity_summary">{{ review.last_activity_summary }}</span>
            </div>

            <div v-if="review.action" class="mt-2">
              <span class="text-sm text-raspberry-500 font-semibold">Action: {{ review.action }}</span>
            </div>
          </div>

          <!-- Right: Action button -->
          <div class="ml-4 flex-shrink-0">
            <button
              class="px-4 py-2 text-sm font-semibold rounded-lg border border-light-gray bg-white text-horizon-500 shadow-sm hover:bg-savannah-100 hover:shadow-md transition-all"
              @click="viewClient(review.client_id)"
            >
              View Client
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="bg-white border border-light-gray rounded-xl p-12 shadow-card text-center">
      <div class="w-14 h-14 rounded-full bg-spring-50 flex items-center justify-center mx-auto mb-4">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#20B486" stroke-width="2">
          <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
          <polyline points="22 4 12 14.01 9 11.01" />
        </svg>
      </div>
      <div class="text-base font-bold text-horizon-500 mb-1">All reviews are up to date</div>
      <div class="text-sm text-neutral-500">No client reviews are currently due or overdue.</div>
    </div>
  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import { formatDateLong } from '@/utils/dateFormatter';

export default {
  name: 'AdvisorReviewsDue',

  computed: {
    ...mapGetters('advisor', ['overdueReviews', 'loading']),

    reviewsDue() {
      return this.$store.state.advisor.reviewsDue || [];
    },

    overdueCount() {
      return this.overdueReviews.length;
    },
  },

  mounted() {
    this.$store.dispatch('advisor/fetchReviewsDue');
  },

  methods: {
    formatDateShort(dateStr) {
      if (!dateStr) return '';
      return formatDateLong(dateStr, true);
    },

    formatFrequency(frequency) {
      if (!frequency) return 'Annual';
      if (typeof frequency === 'number') {
        const monthLabels = { 1: 'Monthly', 3: 'Quarterly', 6: 'Semi-Annual', 12: 'Annual', 24: 'Biennial' };
        return monthLabels[frequency] || `Every ${frequency} months`;
      }
      const labels = {
        annually: 'Annual',
        annual: 'Annual',
        semi_annually: 'Semi-Annual',
        quarterly: 'Quarterly',
        monthly: 'Monthly',
      };
      return labels[frequency] || frequency.charAt(0).toUpperCase() + frequency.slice(1).replace(/_/g, ' ');
    },

    viewClient(clientId) {
      this.$router.push(`/advisor/clients/${clientId}`);
    },
  },
};
</script>
