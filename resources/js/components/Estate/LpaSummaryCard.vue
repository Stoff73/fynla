<template>
  <div class="bg-white rounded-lg border border-light-gray p-5 hover:shadow-md transition-shadow">
    <!-- Header -->
    <div class="flex items-start justify-between mb-3">
      <div class="flex items-center space-x-3">
        <div :class="['w-10 h-10 rounded-lg flex items-center justify-center', iconBgClass]">
          <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path v-if="lpa.lpa_type === 'property_financial'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
          </svg>
        </div>
        <div>
          <h3 class="text-sm font-bold text-horizon-500">{{ typeLabel }}</h3>
          <p class="text-xs text-neutral-500">{{ sourceLabel }}</p>
        </div>
      </div>
      <span
        :class="['inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium', statusClass]"
      >
        {{ statusLabel }}
      </span>
    </div>

    <!-- Attorney Summary -->
    <div v-if="primaryAttorneys.length > 0" class="mb-3">
      <p class="text-xs font-medium text-neutral-500 uppercase tracking-wider mb-1">
        {{ primaryAttorneys.length === 1 ? 'Attorney' : 'Attorneys' }}
      </p>
      <p class="text-sm text-horizon-500">
        {{ primaryAttorneys.map(a => a.full_name).join(', ') }}
      </p>
      <p v-if="lpa.attorney_decision_type && primaryAttorneys.length > 1" class="text-xs text-neutral-500 mt-0.5">
        {{ decisionTypeLabel }}
      </p>
    </div>

    <!-- Replacement Attorneys -->
    <div v-if="replacementAttorneys.length > 0" class="mb-3">
      <p class="text-xs font-medium text-neutral-500 uppercase tracking-wider mb-1">Replacement</p>
      <p class="text-sm text-horizon-500">
        {{ replacementAttorneys.map(a => a.full_name).join(', ') }}
      </p>
    </div>

    <!-- Registration Info -->
    <div v-if="lpa.is_registered_with_opg" class="flex items-center text-xs text-spring-700 mb-3">
      <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
      </svg>
      Registered with the Office of the Public Guardian
      <span v-if="lpa.opg_reference" class="ml-1">({{ lpa.opg_reference }})</span>
    </div>

    <!-- Actions -->
    <div class="flex items-center space-x-2 pt-3 border-t border-light-gray">
      <button
        class="text-xs font-medium text-violet-600 hover:text-violet-700"
        @click="$emit('view', lpa)"
      >
        View Details
      </button>
      <span class="text-light-gray">|</span>
      <button
        v-preview-disabled
        class="text-xs font-medium text-horizon-400 hover:text-horizon-500"
        @click="$emit('edit', lpa)"
      >
        Edit
      </button>
      <span class="text-light-gray">|</span>
      <button
        v-preview-disabled
        class="text-xs font-medium text-raspberry-500 hover:text-raspberry-600"
        @click="$emit('delete', lpa)"
      >
        Delete
      </button>
    </div>
  </div>
</template>

<script>
export default {
  name: 'LpaSummaryCard',

  props: {
    lpa: {
      type: Object,
      required: true,
    },
  },

  emits: ['view', 'edit', 'delete'],

  computed: {
    typeLabel() {
      return this.lpa.lpa_type === 'property_financial'
        ? 'Property & Financial Affairs'
        : 'Health & Welfare';
    },
    sourceLabel() {
      return this.lpa.source === 'uploaded'
        ? 'Uploaded document'
        : 'Created in Fynla';
    },
    statusLabel() {
      const labels = {
        draft: 'Draft',
        completed: 'Completed',
        registered: 'Registered',
        uploaded: 'Uploaded',
      };
      return labels[this.lpa.status] || this.lpa.status;
    },
    statusClass() {
      const classes = {
        draft: 'bg-neutral-100 text-neutral-600',
        completed: 'bg-violet-100 text-violet-800',
        registered: 'bg-spring-100 text-spring-800',
        uploaded: 'bg-light-blue-100 text-light-blue-800',
      };
      return classes[this.lpa.status] || 'bg-neutral-100 text-neutral-600';
    },
    iconBgClass() {
      return this.lpa.lpa_type === 'property_financial'
        ? 'bg-horizon-500'
        : 'bg-violet-500';
    },
    primaryAttorneys() {
      return (this.lpa.attorneys || []).filter(a => a.attorney_type === 'primary');
    },
    replacementAttorneys() {
      return (this.lpa.attorneys || []).filter(a => a.attorney_type === 'replacement');
    },
    decisionTypeLabel() {
      const labels = {
        jointly: 'Acting jointly (all must agree)',
        jointly_and_severally: 'Acting jointly and severally',
        jointly_for_some: 'Jointly for some, severally for others',
      };
      return labels[this.lpa.attorney_decision_type] || '';
    },
  },
};
</script>
