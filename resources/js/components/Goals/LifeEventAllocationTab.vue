<template>
  <div class="space-y-6">
    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-raspberry-600"></div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-raspberry-50 border border-raspberry-200 rounded-card p-4">
      <p class="text-sm text-raspberry-700">{{ error }}</p>
      <button @click="loadAllocations" class="mt-2 text-sm text-raspberry-600 underline">Try again</button>
    </div>

    <!-- Content -->
    <template v-else-if="allocations.length > 0">
      <!-- Summary Bar -->
      <div class="bg-savannah-100 rounded-lg p-4">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-center">
          <div>
            <p class="text-xs text-neutral-500">{{ isIncomeEvent ? 'Total to Allocate' : 'Total to Fund' }}</p>
            <p class="text-lg font-bold text-horizon-500">{{ formatCurrency(eventAmount) }}</p>
          </div>
          <div>
            <p class="text-xs text-neutral-500">Allocated</p>
            <p class="text-lg font-bold text-raspberry-600">{{ formatCurrency(totalAllocated) }}</p>
          </div>
          <div>
            <p class="text-xs text-neutral-500">{{ unallocated > 0 ? 'Remaining' : 'Fully Allocated' }}</p>
            <p class="text-lg font-bold" :class="unallocated > 0 ? 'text-violet-600' : 'text-spring-600'">
              {{ formatCurrency(Math.abs(unallocated)) }}
            </p>
          </div>
        </div>
      </div>

      <!-- Allocation Rows -->
      <div class="space-y-3">
        <div
          v-for="allocation in allocations"
          :key="allocation.id"
          class="bg-white rounded-card border border-light-gray shadow-sm p-4 transition-opacity"
          :class="{ 'opacity-50': !allocation.enabled }"
        >
          <div class="flex flex-col sm:flex-row sm:items-center gap-3">
            <!-- Toggle -->
            <button
              v-preview-disabled="'edit'"
              @click="handleToggle(allocation)"
              class="flex-shrink-0 w-10 h-6 rounded-full transition-colors relative"
              :class="allocation.enabled ? 'bg-raspberry-600' : 'bg-horizon-300'"
              :title="allocation.enabled ? 'Disable allocation' : 'Enable allocation'"
            >
              <span
                class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white shadow transition-transform"
                :class="{ 'translate-x-4': allocation.enabled }"
              ></span>
            </button>

            <!-- Step Badge + Account Label -->
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 mb-1 flex-wrap">
                <span
                  class="inline-flex items-center text-xs px-2 py-0.5 rounded-full font-medium"
                  :class="stepBadgeClass(allocation.allocation_step)"
                >
                  {{ stepLabel(allocation.allocation_step) }}
                </span>
                <span class="text-sm font-medium text-horizon-500 truncate">{{ allocation.account_label }}</span>
              </div>
              <p v-if="allocation.rationale" class="text-xs text-neutral-500 line-clamp-2">{{ allocation.rationale }}</p>
            </div>

            <!-- Amount -->
            <div class="flex items-center gap-2 flex-shrink-0">
              <template v-if="editingId === allocation.id">
                <div class="flex items-center gap-2">
                  <input
                    ref="editInput"
                    v-model.number="editAmount"
                    type="number"
                    step="0.01"
                    min="0"
                    class="form-input w-28 text-sm py-1 px-2"
                    @keyup.enter="saveEdit(allocation)"
                    @keyup.escape="cancelEdit"
                  />
                  <button @click="saveEdit(allocation)" class="text-xs text-raspberry-600 font-medium hover:underline">Save</button>
                  <button @click="cancelEdit" class="text-xs text-neutral-500 hover:underline">Cancel</button>
                </div>
              </template>
              <template v-else>
                <span class="text-base font-semibold text-horizon-500">{{ formatCurrency(allocation.amount) }}</span>
                <button
                  v-if="allocation.enabled"
                  v-preview-disabled="'edit'"
                  @click="startEdit(allocation)"
                  class="text-xs text-raspberry-600 hover:underline"
                >
                  Edit
                </button>
              </template>
            </div>
          </div>
        </div>
      </div>

      <!-- Regenerate Button -->
      <div class="flex justify-end">
        <button
          v-preview-disabled="'edit'"
          @click="handleRegenerate"
          :disabled="regenerating"
          class="btn-secondary text-sm"
        >
          <template v-if="regenerating">Regenerating...</template>
          <template v-else>Regenerate Suggestions</template>
        </button>
      </div>
    </template>

    <!-- Empty State -->
    <div v-else class="text-center py-8">
      <p class="text-sm text-neutral-500">No allocation suggestions available for this event.</p>
      <button
        v-preview-disabled="'edit'"
        @click="handleRegenerate"
        class="mt-3 btn-secondary text-sm"
      >
        Generate Suggestions
      </button>
    </div>
  </div>
</template>

<script>
import { mapGetters, mapActions } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import { previewModeMixin } from '@/mixins/previewModeMixin';

import logger from '@/utils/logger';
export default {
  name: 'LifeEventAllocationTab',
  mixins: [currencyMixin, previewModeMixin],

  props: {
    event: {
      type: Object,
      required: true,
    },
    active: {
      type: Boolean,
      required: true,
    },
  },

  data() {
    return {
      loaded: false,
      loading: false,
      error: null,
      editingId: null,
      editAmount: 0,
      regenerating: false,
    };
  },

  computed: {
    ...mapGetters('goals', ['allocationsForEvent', 'enabledAllocationsTotal']),

    allocations() {
      return this.allocationsForEvent(this.event.id);
    },

    totalAllocated() {
      return this.enabledAllocationsTotal(this.event.id);
    },

    eventAmount() {
      return parseFloat(this.event.amount || 0);
    },

    unallocated() {
      return this.eventAmount - this.totalAllocated;
    },

    isIncomeEvent() {
      return this.event.impact_type === 'income';
    },
  },

  watch: {
    active(isActive) {
      if (isActive && !this.loaded) {
        this.loadAllocations();
      }
    },
  },

  methods: {
    ...mapActions('goals', ['fetchAllocations', 'updateAllocation', 'regenerateAllocations']),

    async loadAllocations() {
      this.loading = true;
      this.error = null;
      try {
        await this.fetchAllocations(this.event.id);
        this.loaded = true;
      } catch (err) {
        this.error = 'Failed to load allocation suggestions. Please try again.';
      } finally {
        this.loading = false;
      }
    },

    async handleToggle(allocation) {
      try {
        await this.updateAllocation({
          eventId: this.event.id,
          allocationId: allocation.id,
          amount: parseFloat(allocation.amount),
          enabled: !allocation.enabled,
        });
      } catch (err) {
        logger.error('Failed to toggle allocation:', err);
      }
    },

    startEdit(allocation) {
      this.editingId = allocation.id;
      this.editAmount = parseFloat(allocation.amount);
      this.$nextTick(() => {
        if (this.$refs.editInput) {
          const input = Array.isArray(this.$refs.editInput) ? this.$refs.editInput[0] : this.$refs.editInput;
          input?.focus();
          input?.select();
        }
      });
    },

    async saveEdit(allocation) {
      const newAmount = parseFloat(this.editAmount) || 0;

      if (newAmount === parseFloat(allocation.amount)) {
        this.editingId = null;
        return;
      }

      try {
        await this.updateAllocation({
          eventId: this.event.id,
          allocationId: allocation.id,
          amount: newAmount,
          enabled: allocation.enabled,
        });
        this.editingId = null;
      } catch (err) {
        logger.error('Failed to update allocation amount:', err);
      }
    },

    cancelEdit() {
      this.editingId = null;
    },

    async handleRegenerate() {
      this.regenerating = true;
      try {
        await this.regenerateAllocations(this.event.id);
      } catch (err) {
        logger.error('Failed to regenerate allocations:', err);
      } finally {
        this.regenerating = false;
      }
    },

    stepLabel(step) {
      const labels = {
        goals: 'Goal',
        isa: 'ISA',
        pension: 'Pension',
        bond: 'Bond',
        cash: 'Cash',
      };
      return labels[step] || step;
    },

    stepBadgeClass(step) {
      const classes = {
        goals: 'bg-violet-100 text-violet-800',
        isa: 'bg-spring-100 text-spring-800',
        pension: 'bg-indigo-100 text-indigo-800',
        bond: 'bg-purple-100 text-purple-800',
        cash: 'bg-savannah-100 text-horizon-500',
      };
      return classes[step] || 'bg-savannah-100 text-horizon-500';
    },
  },
};
</script>
