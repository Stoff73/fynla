<template>
  <div class="events-tab">
    <!-- Header (hidden when detail view is active) -->
    <template v-if="!selectedEvent">
      <div class="mb-6">
        <p class="text-sm text-neutral-500">
          Future occurrences that will impact your financial position
        </p>
      </div>

      <!-- Summary Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-spring-50 border border-spring-200 rounded-lg p-4">
          <p class="text-sm text-spring-600 font-medium">Expected Income</p>
          <p class="text-2xl font-bold text-spring-900 mt-1">{{ formatCurrency(totalIncome) }}</p>
          <p class="text-xs text-spring-600 mt-1">{{ incomeCount }} event{{ incomeCount !== 1 ? 's' : '' }}</p>
        </div>
        <div class="bg-raspberry-50 border border-raspberry-200 rounded-lg p-4">
          <p class="text-sm text-raspberry-600 font-medium">Expected Expenses</p>
          <p class="text-2xl font-bold text-raspberry-900 mt-1">{{ formatCurrency(totalExpense) }}</p>
          <p class="text-xs text-raspberry-600 mt-1">{{ expenseCount }} event{{ expenseCount !== 1 ? 's' : '' }}</p>
        </div>
        <div class="bg-violet-50 border border-violet-200 rounded-lg p-4">
          <p class="text-sm text-violet-600 font-medium">Net Impact</p>
          <p
            class="text-2xl font-bold mt-1"
            :class="netImpact >= 0 ? 'text-spring-900' : 'text-raspberry-900'"
          >
            {{ netImpact >= 0 ? '+' : '' }}{{ formatCurrency(netImpact) }}
          </p>
          <p class="text-xs text-violet-600 mt-1">Total life events planned</p>
        </div>
      </div>

      <!-- Filter/Sort -->
      <div class="flex flex-wrap items-center gap-4 mb-4">
        <select
          v-model="filterType"
          class="px-3 py-2 text-sm border border-horizon-300 rounded-md"
        >
          <option value="all">All Events</option>
          <option value="income">Income Only</option>
          <option value="expense">Expenses Only</option>
        </select>

        <select
          v-model="sortBy"
          class="px-3 py-2 text-sm border border-horizon-300 rounded-md"
        >
          <option value="date">Sort by Date</option>
          <option value="amount">Sort by Amount</option>
          <option value="certainty">Sort by Certainty</option>
        </select>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="flex justify-center items-center py-12">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-raspberry-600"></div>
      </div>
    </template>

    <!-- Life Event Detail View (inline, replaces list) -->
    <LifeEventDetailInline
      v-if="selectedEvent"
      :event="selectedEvent"
      @back="closeEventDetail"
      @edit="handleEditFromDetail"
      @delete="handleDeleteFromDetail"
    />

    <!-- Events List (hidden when detail view is active) -->
    <template v-if="!selectedEvent && !loading">
      <div v-if="filteredEvents.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <LifeEventCard
          v-for="event in filteredEvents"
          :key="event.id"
          :event="event"
          @click="viewEvent"
          @edit="openEditModal"
          @delete="confirmDelete"
        />
      </div>

      <!-- Empty State -->
      <div v-else class="text-center py-12 bg-savannah-100 rounded-lg border border-light-gray">
      <svg class="mx-auto h-12 w-12 text-horizon-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
      </svg>
      <h3 class="mt-2 text-sm font-medium text-horizon-500">No life events</h3>
      <p class="mt-1 text-sm text-neutral-500">
        Add expected future events like inheritances or major purchases.
      </p>
      <button
        @click="openCreateModal"
        class="mt-4 inline-flex items-center px-4 py-2 text-sm font-medium text-raspberry-600 bg-raspberry-50 rounded-button hover:bg-raspberry-100 transition-colors"
      >
        <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Add Your First Life Event
      </button>
      </div>
    </template>

    <!-- Life Event Form Modal -->
    <LifeEventForm
      :is-open="showFormModal"
      :event="editingEvent"
      @close="closeFormModal"
      @save="handleSaveEvent"
    />

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteModal" class="fixed inset-0 z-50 overflow-y-auto">
      <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-savannah-1000 bg-opacity-75 transition-opacity" @click="closeDeleteModal"></div>
        <div class="relative z-10 inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
          <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
            <div class="sm:flex sm:items-start">
              <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-raspberry-100 sm:mx-0 sm:h-10 sm:w-10">
                <svg class="h-6 w-6 text-raspberry-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
              </div>
              <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                <h3 class="text-lg leading-6 font-medium text-horizon-500">Delete Life Event</h3>
                <div class="mt-2">
                  <p class="text-sm text-neutral-500">
                    Are you sure you want to delete "{{ deletingEvent?.event_name }}"? This action cannot be undone.
                  </p>
                </div>
              </div>
            </div>
          </div>
          <div class="bg-savannah-100 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
            <button
              type="button"
              @click="handleDeleteEvent"
              :disabled="deleteLoading"
              class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-raspberry-600 text-base font-medium text-white hover:bg-raspberry-700 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
            >
              {{ deleteLoading ? 'Deleting...' : 'Delete' }}
            </button>
            <button
              type="button"
              @click="closeDeleteModal"
              class="mt-3 w-full inline-flex justify-center rounded-md border border-horizon-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-neutral-500 hover:bg-savannah-100 sm:mt-0 sm:w-auto sm:text-sm"
            >
              Cancel
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { mapState, mapActions } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import LifeEventCard from './LifeEventCard.vue';
import LifeEventForm from './LifeEventForm.vue';
import LifeEventDetailInline from './LifeEventDetailInline.vue';

import logger from '@/utils/logger';
export default {
  name: 'EventsTab',
  mixins: [currencyMixin],

  components: {
    LifeEventCard,
    LifeEventForm,
    LifeEventDetailInline,
  },

  data() {
    return {
      filterType: 'all',
      sortBy: 'date',
      selectedEvent: null,
      showFormModal: false,
      editingEvent: null,
      showDeleteModal: false,
      deletingEvent: null,
      deleteLoading: false,
    };
  },

  computed: {
    ...mapState('goals', ['lifeEvents', 'lifeEventsLoading']),
    ...mapState('aiFormFill', ['pendingFill']),
    subNavAction() { return this.$store.getters['subNav/pendingAction']; },
    subNavCounter() { return this.$store.getters['subNav/actionCounter']; },

    loading() {
      return this.lifeEventsLoading;
    },

    filteredEvents() {
      let events = [...(this.lifeEvents || [])];

      // Filter
      if (this.filterType === 'income') {
        events = events.filter(e => e.impact_type === 'income');
      } else if (this.filterType === 'expense') {
        events = events.filter(e => e.impact_type === 'expense');
      }

      // Sort
      events.sort((a, b) => {
        if (this.sortBy === 'date') {
          return new Date(a.expected_date) - new Date(b.expected_date);
        }
        if (this.sortBy === 'amount') {
          return parseFloat(b.amount) - parseFloat(a.amount);
        }
        if (this.sortBy === 'certainty') {
          const order = { confirmed: 1, likely: 2, possible: 3, speculative: 4 };
          return (order[a.certainty] || 5) - (order[b.certainty] || 5);
        }
        return 0;
      });

      return events;
    },

    incomeEvents() {
      return (this.lifeEvents || []).filter(e => e.impact_type === 'income');
    },

    expenseEvents() {
      return (this.lifeEvents || []).filter(e => e.impact_type === 'expense');
    },

    totalIncome() {
      return this.incomeEvents.reduce((sum, e) => sum + parseFloat(e.amount || 0), 0);
    },

    totalExpense() {
      return this.expenseEvents.reduce((sum, e) => sum + parseFloat(e.amount || 0), 0);
    },

    netImpact() {
      return this.totalIncome - this.totalExpense;
    },

    incomeCount() {
      return this.incomeEvents.length;
    },

    expenseCount() {
      return this.expenseEvents.length;
    },
  },

  watch: {
    subNavCounter() {
      if (this.subNavAction === 'addLifeEvent') {
        this.openCreateModal();
        this.$store.dispatch('subNav/consumeCta');
      }
    },

    pendingFill(fill) {
      if (fill && fill.entityType === 'life_event') {
        if (fill.mode === 'edit' && fill.entityId) {
          const event = this.lifeEvents?.find(e => e.id === fill.entityId);
          if (event) this.openEditModal(event);
        } else {
          this.openCreateModal();
        }
      }
    },
  },

  mounted() {
    this.fetchLifeEvents();
  },

  methods: {
    ...mapActions('goals', [
      'fetchLifeEvents',
      'createLifeEvent',
      'updateLifeEvent',
      'deleteLifeEvent',
    ]),

    viewEvent(event) {
      this.selectedEvent = event;
    },

    closeEventDetail() {
      this.selectedEvent = null;
    },

    handleEditFromDetail(event) {
      this.openEditModal(event);
    },

    handleDeleteFromDetail(event) {
      this.confirmDelete(event);
    },

    openCreateModal() {
      this.editingEvent = null;
      this.showFormModal = true;
    },

    openEditModal(event) {
      this.editingEvent = event;
      this.showFormModal = true;
    },

    closeFormModal() {
      this.showFormModal = false;
      this.editingEvent = null;
    },

    async handleSaveEvent(formData) {
      try {
        if (this.editingEvent) {
          await this.updateLifeEvent({
            eventId: this.editingEvent.id,
            eventData: formData,
          });
        } else {
          await this.createLifeEvent(formData);
        }
        if (this.$store.state.aiFormFill.pendingFill) {
          this.$store.dispatch('aiFormFill/completeFill');
        }
        this.closeFormModal();
        // Close detail view after edit so user sees updated list
        if (this.selectedEvent) {
          this.selectedEvent = null;
        }
      } catch (error) {
        logger.error('Failed to save life event:', error);
      }
    },

    confirmDelete(event) {
      this.deletingEvent = event;
      this.showDeleteModal = true;
    },

    closeDeleteModal() {
      this.showDeleteModal = false;
      this.deletingEvent = null;
    },

    async handleDeleteEvent() {
      if (!this.deletingEvent) return;

      this.deleteLoading = true;
      try {
        await this.deleteLifeEvent(this.deletingEvent.id);
        this.closeDeleteModal();
        // Close detail view if deleting from it
        if (this.selectedEvent && this.selectedEvent.id === this.deletingEvent.id) {
          this.selectedEvent = null;
        }
      } catch (error) {
        logger.error('Failed to delete life event:', error);
      } finally {
        this.deleteLoading = false;
      }
    },
  },
};
</script>
