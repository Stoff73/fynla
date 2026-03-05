<template>
  <div class="detail-inline">
    <!-- Back Button -->
    <button @click="$emit('back')" class="detail-inline-back mb-4">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
      </svg>
      Back to Life Events
    </button>

    <!-- Event Content -->
    <div v-if="event" class="space-y-6">
      <!-- Header -->
      <div class="bg-white rounded-card border border-light-gray shadow-sm p-6">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
          <div>
            <div class="flex items-center gap-3 mb-2 flex-wrap">
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
            <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-horizon-500">{{ event.event_name }}</h1>
            <p class="text-base sm:text-lg text-neutral-500 mt-1">{{ displayEventType }}</p>
          </div>
          <div class="flex flex-col sm:flex-row gap-2 sm:space-x-2 w-full sm:w-auto shrink-0">
            <button
              v-preview-disabled="'edit'"
              @click="$emit('edit', event)"
              class="btn-primary w-full sm:w-auto"
            >
              Edit
            </button>
            <button
              v-preview-disabled="'delete'"
              @click="$emit('delete', event)"
              class="btn-danger w-full sm:w-auto"
            >
              Delete
            </button>
          </div>
        </div>

        <!-- Key Metrics -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-6">
          <div class="rounded-lg p-4 border" :class="event.impact_type === 'income' ? 'bg-spring-50 border-spring-200' : 'bg-raspberry-50 border-raspberry-200'">
            <p class="text-sm text-neutral-500">Amount</p>
            <p class="text-2xl font-bold" :class="event.impact_type === 'income' ? 'text-spring-600' : 'text-raspberry-600'">
              {{ event.impact_type === 'income' ? '+' : '-' }}{{ formatCurrency(event.amount) }}
            </p>
          </div>
          <div class="bg-savannah-100 rounded-lg p-4">
            <p class="text-sm text-neutral-500">Expected Date</p>
            <p class="text-2xl font-bold text-horizon-500">{{ formatDateDisplay(event.expected_date) }}</p>
            <p v-if="yearsUntil !== null" class="text-xs text-neutral-500 mt-1">
              In {{ yearsUntil }} {{ yearsUntil === 1 ? 'year' : 'years' }}
            </p>
          </div>
          <div class="bg-savannah-100 rounded-lg p-4">
            <p class="text-sm text-neutral-500">Certainty</p>
            <p class="text-2xl font-bold" :class="certaintyClass">{{ certaintyLabel }}</p>
          </div>
          <div class="bg-savannah-100 rounded-lg p-4">
            <p class="text-sm text-neutral-500">Status</p>
            <p class="text-2xl font-bold text-horizon-500 capitalize">{{ event.status || 'Expected' }}</p>
          </div>
        </div>
      </div>

      <!-- Details Card -->
      <div class="bg-white rounded-card border border-light-gray shadow-sm">
        <div class="border-b border-light-gray">
          <nav class="flex -mb-px overflow-x-auto">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              @click="activeTab = tab.id"
              class="px-6 py-3 border-b-2 font-medium text-sm transition-colors whitespace-nowrap flex-shrink-0"
              :class="
                activeTab === tab.id
                  ? 'border-raspberry-600 text-raspberry-600'
                  : 'border-transparent text-neutral-500 hover:text-neutral-500 hover:border-horizon-300'
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
                <h3 class="text-lg font-semibold text-horizon-500 mb-3">Event Information</h3>
                <dl class="space-y-2">
                  <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Event Name:</dt>
                    <dd class="text-sm font-medium text-horizon-500 sm:text-right">{{ event.event_name }}</dd>
                  </div>
                  <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Event Type:</dt>
                    <dd class="text-sm font-medium text-horizon-500 sm:text-right">{{ displayEventType }}</dd>
                  </div>
                  <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Impact Type:</dt>
                    <dd class="text-sm font-medium capitalize sm:text-right" :class="event.impact_type === 'income' ? 'text-spring-600' : 'text-raspberry-600'">
                      {{ event.impact_type }}
                    </dd>
                  </div>
                  <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Amount:</dt>
                    <dd class="text-sm font-medium sm:text-right" :class="event.impact_type === 'income' ? 'text-spring-600' : 'text-raspberry-600'">
                      {{ event.impact_type === 'income' ? '+' : '-' }}{{ formatCurrency(event.amount) }}
                    </dd>
                  </div>
                  <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Expected Date:</dt>
                    <dd class="text-sm font-medium text-horizon-500 sm:text-right">{{ formatDateDisplay(event.expected_date) }}</dd>
                  </div>
                  <div v-if="event.age_at_event" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Age at Event:</dt>
                    <dd class="text-sm font-medium text-horizon-500 sm:text-right">{{ event.age_at_event }}</dd>
                  </div>
                </dl>
              </div>

              <!-- Planning Details -->
              <div>
                <h3 class="text-lg font-semibold text-horizon-500 mb-3">Planning Details</h3>
                <dl class="space-y-2">
                  <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Certainty:</dt>
                    <dd class="text-sm font-medium capitalize sm:text-right" :class="certaintyClass">{{ certaintyLabel }}</dd>
                  </div>
                  <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Status:</dt>
                    <dd class="text-sm font-medium text-horizon-500 capitalize sm:text-right">{{ event.status || 'Expected' }}</dd>
                  </div>
                  <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Show in Projection:</dt>
                    <dd class="text-sm font-medium text-horizon-500 sm:text-right">{{ event.show_in_projection ? 'Yes' : 'No' }}</dd>
                  </div>
                  <div v-if="yearsUntil !== null" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Time Until Event:</dt>
                    <dd class="text-sm font-medium text-horizon-500 sm:text-right">{{ yearsUntil }} {{ yearsUntil === 1 ? 'year' : 'years' }}</dd>
                  </div>
                  <div v-if="event.created_at" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Created:</dt>
                    <dd class="text-sm font-medium text-horizon-500 sm:text-right">{{ formatDateDisplay(event.created_at) }}</dd>
                  </div>
                </dl>
              </div>
            </div>

            <!-- Description -->
            <div v-if="event.description">
              <h3 class="text-lg font-semibold text-horizon-500 mb-3">Description</h3>
              <p class="text-sm text-neutral-500 bg-savannah-100 rounded-lg p-4">{{ event.description }}</p>
            </div>

            <!-- Notes -->
            <div v-if="event.notes">
              <h3 class="text-lg font-semibold text-horizon-500 mb-3">Notes</h3>
              <p class="text-sm text-neutral-500 bg-savannah-100 rounded-lg p-4">{{ event.notes }}</p>
            </div>
          </div>

          <!-- Impact Tab -->
          <div v-show="activeTab === 'impact'" class="space-y-6">
            <div class="text-center py-4">
              <div class="inline-flex items-center justify-center w-16 h-16 rounded-full mb-4"
                :class="event.impact_type === 'income' ? 'bg-spring-100' : 'bg-raspberry-100'"
              >
                <svg v-if="event.impact_type === 'income'" class="w-8 h-8 text-spring-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12" />
                </svg>
                <svg v-else class="w-8 h-8 text-raspberry-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6" />
                </svg>
              </div>
              <h3 class="text-lg font-semibold text-horizon-500">
                {{ event.impact_type === 'income' ? 'Positive' : 'Negative' }} Financial Impact
              </h3>
              <p class="text-3xl font-bold mt-2"
                :class="event.impact_type === 'income' ? 'text-spring-600' : 'text-raspberry-600'"
              >
                {{ event.impact_type === 'income' ? '+' : '-' }}{{ formatCurrency(event.amount) }}
              </p>
              <p class="text-sm text-neutral-500 mt-2">
                Expected {{ formatDateDisplay(event.expected_date) }}
              </p>
            </div>

            <!-- Impact Context -->
            <div class="bg-violet-50 border border-violet-200 rounded-lg p-4">
              <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-violet-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                </svg>
                <div>
                  <p class="text-sm text-violet-800">
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
                  <p class="text-sm text-violet-700 mt-2">
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

          <!-- Tax Optimised Allocation Tab -->
          <div v-show="activeTab === 'allocation'">
            <LifeEventAllocationTab
              :event="event"
              :active="activeTab === 'allocation'"
            />
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
import { formatDateLong } from '@/utils/dateFormatter';
import LifeEventAllocationTab from '@/components/Goals/LifeEventAllocationTab.vue';

export default {
  name: 'LifeEventDetailInline',
  mixins: [currencyMixin, previewModeMixin],

  components: {
    LifeEventAllocationTab,
  },

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
        { id: 'allocation', label: 'Tax Optimised Allocation' },
      ];
    },

    displayEventType() {
      const config = LIFE_EVENT_ICONS[this.event.event_type];
      return config?.label || this.formatEventType(this.event.event_type);
    },

    impactBadgeClass() {
      return this.event.impact_type === 'income'
        ? 'bg-spring-100 text-spring-800'
        : 'bg-raspberry-100 text-raspberry-800';
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
        confirmed: 'text-spring-600',
        likely: 'text-violet-600',
        possible: 'text-violet-500',
        speculative: 'text-neutral-500',
      };
      return classes[this.event.certainty] || 'text-neutral-500';
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

    formatDateDisplay(date) {
      return formatDateLong(date) || '\u2014';
    },
  },
};
</script>
