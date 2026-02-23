<template>
  <div class="life-event-detail-inline">
    <!-- Back Button -->
    <button @click="$emit('back')" class="back-button mb-4">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
      </svg>
      Back to Life Events
    </button>

    <!-- Event Content -->
    <div v-if="event" class="space-y-6">
      <!-- Header -->
      <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
          <div>
            <div class="flex items-center gap-3 mb-2">
              <span
                class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold"
                :class="impactBadgeClass"
              >
                {{ event.impact_type === 'income' ? '+ Income' : '- Expense' }}
              </span>
              <span
                class="text-xs font-medium"
                :class="certaintyClass"
              >
                {{ certaintyLabel }}
              </span>
            </div>
            <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-gray-900">{{ event.event_name }}</h1>
            <p class="text-base sm:text-lg text-gray-600 mt-1">{{ displayEventType }}</p>
          </div>
          <div class="flex flex-col sm:flex-row gap-2 sm:space-x-2 w-full sm:w-auto">
            <button
              v-preview-disabled="'edit'"
              @click="$emit('edit', event)"
              class="w-full sm:w-auto px-4 py-2 bg-primary-600 text-white rounded-button hover:bg-primary-700 transition-colors"
            >
              Edit
            </button>
            <button
              v-preview-disabled="'delete'"
              @click="$emit('delete', event)"
              class="w-full sm:w-auto px-4 py-2 bg-error-600 text-white rounded-button hover:bg-error-700 transition-colors"
            >
              Delete
            </button>
          </div>
        </div>

        <!-- Key Metrics -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-6">
          <div class="rounded-lg p-4 border" :class="event.impact_type === 'income' ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'">
            <p class="text-sm text-gray-600">Amount</p>
            <p class="text-2xl font-bold" :class="event.impact_type === 'income' ? 'text-green-600' : 'text-red-600'">
              {{ event.impact_type === 'income' ? '+' : '-' }}{{ formatCurrency(event.amount) }}
            </p>
          </div>
          <div class="bg-gray-50 rounded-lg p-4">
            <p class="text-sm text-gray-600">Expected Date</p>
            <p class="text-2xl font-bold text-gray-900">{{ formatDateShort(event.expected_date) }}</p>
            <p v-if="yearsUntil !== null" class="text-xs text-gray-500 mt-1">
              In {{ yearsUntil }} {{ yearsUntil === 1 ? 'year' : 'years' }}
            </p>
          </div>
          <div class="bg-gray-50 rounded-lg p-4">
            <p class="text-sm text-gray-600">Certainty</p>
            <p class="text-2xl font-bold" :class="certaintyClass">{{ certaintyLabel }}</p>
          </div>
          <div class="bg-gray-50 rounded-lg p-4">
            <p class="text-sm text-gray-600">Status</p>
            <p class="text-2xl font-bold text-gray-900 capitalize">{{ event.status || 'Expected' }}</p>
          </div>
        </div>
      </div>

      <!-- Details Card -->
      <div class="bg-white rounded-lg shadow-md">
        <div class="border-b border-gray-200">
          <nav class="flex -mb-px">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              @click="activeTab = tab.id"
              class="px-6 py-3 border-b-2 font-medium text-sm transition-colors whitespace-nowrap"
              :class="
                activeTab === tab.id
                  ? 'border-blue-600 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              "
            >
              {{ tab.label }}
            </button>
          </nav>
        </div>

        <div class="p-6">
          <!-- Details Tab -->
          <div v-show="activeTab === 'details'" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- Event Information -->
              <div>
                <h3 class="text-lg font-semibold text-gray-800 mb-3">Event Information</h3>
                <dl class="space-y-2">
                  <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-gray-600">Event Name:</dt>
                    <dd class="text-sm font-medium text-gray-900">{{ event.event_name }}</dd>
                  </div>
                  <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-gray-600">Event Type:</dt>
                    <dd class="text-sm font-medium text-gray-900">{{ displayEventType }}</dd>
                  </div>
                  <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-gray-600">Impact Type:</dt>
                    <dd class="text-sm font-medium capitalize" :class="event.impact_type === 'income' ? 'text-green-600' : 'text-red-600'">
                      {{ event.impact_type }}
                    </dd>
                  </div>
                  <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-gray-600">Amount:</dt>
                    <dd class="text-sm font-medium" :class="event.impact_type === 'income' ? 'text-green-600' : 'text-red-600'">
                      {{ event.impact_type === 'income' ? '+' : '-' }}{{ formatCurrency(event.amount) }}
                    </dd>
                  </div>
                  <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-gray-600">Expected Date:</dt>
                    <dd class="text-sm font-medium text-gray-900">{{ formatDate(event.expected_date) }}</dd>
                  </div>
                  <div v-if="event.age_at_event" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-gray-600">Age at Event:</dt>
                    <dd class="text-sm font-medium text-gray-900">{{ event.age_at_event }}</dd>
                  </div>
                </dl>
              </div>

              <!-- Planning Details -->
              <div>
                <h3 class="text-lg font-semibold text-gray-800 mb-3">Planning Details</h3>
                <dl class="space-y-2">
                  <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-gray-600">Certainty:</dt>
                    <dd class="text-sm font-medium capitalize" :class="certaintyClass">{{ certaintyLabel }}</dd>
                  </div>
                  <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-gray-600">Status:</dt>
                    <dd class="text-sm font-medium text-gray-900 capitalize">{{ event.status || 'Expected' }}</dd>
                  </div>
                  <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-gray-600">Show in Projection:</dt>
                    <dd class="text-sm font-medium text-gray-900">{{ event.show_in_projection ? 'Yes' : 'No' }}</dd>
                  </div>
                  <div v-if="yearsUntil !== null" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-gray-600">Time Until Event:</dt>
                    <dd class="text-sm font-medium text-gray-900">{{ yearsUntil }} {{ yearsUntil === 1 ? 'year' : 'years' }}</dd>
                  </div>
                  <div v-if="event.created_at" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-gray-600">Created:</dt>
                    <dd class="text-sm font-medium text-gray-900">{{ formatDate(event.created_at) }}</dd>
                  </div>
                </dl>
              </div>
            </div>

            <!-- Description -->
            <div v-if="event.description">
              <h3 class="text-lg font-semibold text-gray-800 mb-3">Description</h3>
              <p class="text-sm text-gray-700 bg-gray-50 rounded-lg p-4">{{ event.description }}</p>
            </div>

            <!-- Notes -->
            <div v-if="event.notes">
              <h3 class="text-lg font-semibold text-gray-800 mb-3">Notes</h3>
              <p class="text-sm text-gray-700 bg-gray-50 rounded-lg p-4">{{ event.notes }}</p>
            </div>
          </div>

          <!-- Impact Tab -->
          <div v-show="activeTab === 'impact'" class="space-y-6">
            <div class="text-center py-4">
              <div class="inline-flex items-center justify-center w-16 h-16 rounded-full mb-4"
                :class="event.impact_type === 'income' ? 'bg-green-100' : 'bg-red-100'"
              >
                <svg v-if="event.impact_type === 'income'" class="w-8 h-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12" />
                </svg>
                <svg v-else class="w-8 h-8 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6" />
                </svg>
              </div>
              <h3 class="text-lg font-semibold text-gray-900">
                {{ event.impact_type === 'income' ? 'Positive' : 'Negative' }} Financial Impact
              </h3>
              <p class="text-3xl font-bold mt-2"
                :class="event.impact_type === 'income' ? 'text-green-600' : 'text-red-600'"
              >
                {{ event.impact_type === 'income' ? '+' : '-' }}{{ formatCurrency(event.amount) }}
              </p>
              <p class="text-sm text-gray-500 mt-2">
                Expected {{ formatDateLong(event.expected_date) }}
              </p>
            </div>

            <!-- Impact Context -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
              <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-blue-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                </svg>
                <div>
                  <p class="text-sm text-blue-800">
                    <template v-if="event.impact_type === 'income'">
                      This event is expected to add {{ formatCurrency(event.amount) }} to your financial position.
                      It is factored into your financial projections
                      {{ event.show_in_projection ? 'and is visible on the projection chart' : 'but is not currently shown on the projection chart' }}.
                    </template>
                    <template v-else>
                      This event represents an expected outflow of {{ formatCurrency(event.amount) }}.
                      It is factored into your financial projections
                      {{ event.show_in_projection ? 'and is visible on the projection chart' : 'but is not currently shown on the projection chart' }}.
                    </template>
                  </p>
                  <p class="text-sm text-blue-700 mt-2">
                    Certainty level: <strong class="capitalize">{{ certaintyLabel }}</strong>
                    <template v-if="event.certainty === 'confirmed'"> - this event is confirmed and highly likely to occur.</template>
                    <template v-else-if="event.certainty === 'likely'"> - this event is expected to occur.</template>
                    <template v-else-if="event.certainty === 'possible'"> - this event may or may not occur.</template>
                    <template v-else-if="event.certainty === 'speculative'"> - this event is speculative and uncertain.</template>
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import { previewModeMixin } from '@/mixins/previewModeMixin';
import { LIFE_EVENT_ICONS } from '@/constants/eventIcons';

export default {
  name: 'LifeEventDetailInline',
  mixins: [currencyMixin, previewModeMixin],

  props: {
    event: {
      type: Object,
      required: true,
    },
  },

  emits: ['back', 'edit', 'delete'],

  data() {
    return {
      activeTab: 'details',
    };
  },

  computed: {
    tabs() {
      return [
        { id: 'details', label: 'Details' },
        { id: 'impact', label: 'Impact' },
      ];
    },

    displayEventType() {
      const config = LIFE_EVENT_ICONS[this.event.event_type];
      return config?.label || this.formatEventType(this.event.event_type);
    },

    impactBadgeClass() {
      return this.event.impact_type === 'income'
        ? 'bg-green-100 text-green-800'
        : 'bg-red-100 text-red-800';
    },

    certaintyLabel() {
      const labels = {
        confirmed: 'Confirmed',
        likely: 'Likely',
        possible: 'Possible',
        speculative: 'Speculative',
      };
      return labels[this.event.certainty] || 'Likely';
    },

    certaintyClass() {
      const classes = {
        confirmed: 'text-green-600',
        likely: 'text-blue-600',
        possible: 'text-blue-500',
        speculative: 'text-gray-500',
      };
      return classes[this.event.certainty] || 'text-gray-500';
    },

    yearsUntil() {
      return this.event.years_until_event ?? null;
    },
  },

  methods: {
    formatEventType(type) {
      if (!type) return '';
      return type
        .split('_')
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
    },

    formatDate(date) {
      if (!date) return '';
      return new Date(date).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
      });
    },

    formatDateShort(date) {
      if (!date) return '-';
      return new Date(date).toLocaleDateString('en-GB', {
        month: 'short',
        year: 'numeric',
      });
    },

    formatDateLong(date) {
      if (!date) return '';
      return new Date(date).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
      });
    },
  },
};
</script>

<style scoped>
.life-event-detail-inline {
  animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

.back-button {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  background: white;
  @apply border border-gray-200;
  border-radius: 8px;
  @apply text-gray-700;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.back-button:hover {
  @apply bg-gray-100;
  @apply border-gray-300;
}
</style>
