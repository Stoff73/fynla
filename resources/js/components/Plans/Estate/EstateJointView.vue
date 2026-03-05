<template>
  <div class="mb-6">
    <PlanSectionHeader
      title="Joint Estate Overview"
      subtitle="Side-by-side estate positions for both partners"
      color="violet"
    />

    <div class="bg-white rounded-lg shadow-sm border border-light-gray p-5">
      <!-- Side-by-side columns -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Primary partner -->
        <div>
          <h4 class="text-sm font-semibold text-horizon-500 mb-3">{{ jointView.primary.name }}</h4>
          <div class="space-y-2">
            <div class="flex justify-between">
              <span class="text-xs text-neutral-500">Gross Estate</span>
              <span class="text-sm font-medium text-horizon-500">{{ formatCurrency(jointView.primary.gross_estate) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-xs text-neutral-500">Liabilities</span>
              <span class="text-sm font-medium text-horizon-500">{{ formatCurrency(jointView.primary.liabilities) }}</span>
            </div>
            <div class="flex justify-between border-t border-savannah-100 pt-2">
              <span class="text-xs font-medium text-horizon-500">Net Estate</span>
              <span class="text-sm font-bold text-horizon-500">{{ formatCurrency(jointView.primary.net_estate) }}</span>
            </div>
            <div v-if="jointView.primary.cover_in_trust > 0" class="flex justify-between">
              <span class="text-xs text-neutral-500">Life Cover in Trust</span>
              <span class="text-sm font-medium text-spring-700">{{ formatCurrency(jointView.primary.cover_in_trust) }}</span>
            </div>
          </div>
        </div>

        <!-- Spouse -->
        <div>
          <h4 class="text-sm font-semibold text-horizon-500 mb-3">{{ jointView.spouse.name }}</h4>
          <div class="space-y-2">
            <div class="flex justify-between">
              <span class="text-xs text-neutral-500">Gross Estate</span>
              <span class="text-sm font-medium text-horizon-500">{{ formatCurrency(jointView.spouse.gross_estate) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-xs text-neutral-500">Liabilities</span>
              <span class="text-sm font-medium text-horizon-500">{{ formatCurrency(jointView.spouse.liabilities) }}</span>
            </div>
            <div class="flex justify-between border-t border-savannah-100 pt-2">
              <span class="text-xs font-medium text-horizon-500">Net Estate</span>
              <span class="text-sm font-bold text-horizon-500">{{ formatCurrency(jointView.spouse.net_estate) }}</span>
            </div>
            <div v-if="jointView.spouse.cover_in_trust > 0" class="flex justify-between">
              <span class="text-xs text-neutral-500">Life Cover in Trust</span>
              <span class="text-sm font-medium text-spring-700">{{ formatCurrency(jointView.spouse.cover_in_trust) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Combined totals -->
      <div class="mt-4 pt-4 border-t border-light-gray">
        <h4 class="text-sm font-semibold text-horizon-500 mb-3">Combined Position</h4>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
          <div class="bg-eggshell-500 rounded-lg p-3">
            <p class="text-xs text-neutral-500">Combined Gross Estate</p>
            <p class="text-sm font-bold text-horizon-500">{{ formatCurrency(jointView.combined.gross_estate) }}</p>
          </div>
          <div class="bg-eggshell-500 rounded-lg p-3">
            <p class="text-xs text-neutral-500">Combined Net Estate</p>
            <p class="text-sm font-bold text-horizon-500">{{ formatCurrency(jointView.combined.net_estate) }}</p>
          </div>
          <div class="bg-eggshell-500 rounded-lg p-3">
            <p class="text-xs text-neutral-500">Nil Rate Band</p>
            <p class="text-sm font-bold text-horizon-500">{{ formatCurrency(jointView.combined.nil_rate_band) }}</p>
          </div>
          <div class="bg-eggshell-500 rounded-lg p-3">
            <p class="text-xs text-neutral-500">Residence Nil Rate Band</p>
            <p class="text-sm font-bold text-horizon-500">{{ formatCurrency(jointView.combined.residence_nil_rate_band) }}</p>
          </div>
        </div>
      </div>

      <!-- Spouse exemption note -->
      <div v-if="jointView.spouse_exemption_note" class="mt-4 p-3 bg-violet-50 rounded-lg">
        <p class="text-xs text-violet-700">{{ jointView.spouse_exemption_note }}</p>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import PlanSectionHeader from '@/components/Plans/Shared/PlanSectionHeader.vue';

export default {
  name: 'EstateJointView',
  components: { PlanSectionHeader },
  mixins: [currencyMixin],
  props: {
    jointView: { type: Object, required: true },
  },
};
</script>
