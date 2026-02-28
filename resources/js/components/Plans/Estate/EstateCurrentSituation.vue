<template>
  <div class="mb-6">
    <PlanSectionHeader title="Current Situation" subtitle="Your estate and Inheritance Tax overview" color="purple" />

    <div class="space-y-4">
      <!-- Estate Value -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
        <h3 class="text-sm font-semibold text-gray-900 mb-3">Estate Value</h3>
        <div class="grid grid-cols-3 gap-3">
          <div class="bg-gray-50 rounded-lg p-3">
            <p class="text-xs text-gray-500">Gross Estate</p>
            <p class="text-sm font-bold text-gray-900">{{ formatCurrency(situation.estate_value.gross) }}</p>
          </div>
          <div class="bg-gray-50 rounded-lg p-3">
            <p class="text-xs text-gray-500">Net Estate</p>
            <p class="text-sm font-bold text-gray-900">{{ formatCurrency(situation.estate_value.net) }}</p>
          </div>
          <div class="bg-gray-50 rounded-lg p-3">
            <p class="text-xs text-gray-500">Total Liabilities</p>
            <p class="text-sm font-bold text-gray-900">{{ formatCurrency(situation.estate_value.liabilities) }}</p>
          </div>
        </div>
      </div>

      <!-- Inheritance Tax -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
        <h3 class="text-sm font-semibold text-gray-900 mb-3">Inheritance Tax</h3>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
          <div class="bg-gray-50 rounded-lg p-3">
            <p class="text-xs text-gray-500">Inheritance Tax Liability</p>
            <p class="text-sm font-bold" :class="ihtLiability > 0 ? 'text-red-700' : 'text-green-700'">
              {{ formatCurrency(ihtLiability) }}
            </p>
          </div>
          <div class="bg-gray-50 rounded-lg p-3">
            <p class="text-xs text-gray-500">Nil Rate Band</p>
            <p class="text-sm font-bold text-gray-900">{{ formatCurrency(situation.iht_calculation.nil_rate_band) }}</p>
          </div>
          <div class="bg-gray-50 rounded-lg p-3">
            <p class="text-xs text-gray-500">Residence Nil Rate Band</p>
            <p class="text-sm font-bold text-gray-900">{{ formatCurrency(situation.iht_calculation.residence_nil_rate_band) }}</p>
          </div>
          <div v-if="situation.iht_calculation.spouse_exemption > 0" class="bg-gray-50 rounded-lg p-3">
            <p class="text-xs text-gray-500">Spouse Exemption</p>
            <p class="text-sm font-bold text-gray-900">{{ formatCurrency(situation.iht_calculation.spouse_exemption) }}</p>
          </div>
          <div class="bg-gray-50 rounded-lg p-3">
            <p class="text-xs text-gray-500">Effective Tax Rate</p>
            <p class="text-sm font-bold text-gray-900">{{ situation.iht_calculation.effective_rate }}%</p>
          </div>
        </div>
      </div>

      <!-- Asset Breakdown -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
        <h3 class="text-sm font-semibold text-gray-900 mb-3">Asset Breakdown</h3>
        <div class="grid grid-cols-3 gap-3">
          <div class="bg-gray-50 rounded-lg p-3">
            <p class="text-xs text-gray-500">Liquid Assets</p>
            <p class="text-sm font-bold text-gray-900">{{ formatCurrency(situation.asset_breakdown.liquid) }}</p>
          </div>
          <div class="bg-gray-50 rounded-lg p-3">
            <p class="text-xs text-gray-500">Semi-Liquid Assets</p>
            <p class="text-sm font-bold text-gray-900">{{ formatCurrency(situation.asset_breakdown.semi_liquid) }}</p>
          </div>
          <div class="bg-gray-50 rounded-lg p-3">
            <p class="text-xs text-gray-500">Illiquid Assets</p>
            <p class="text-sm font-bold text-gray-900">{{ formatCurrency(situation.asset_breakdown.illiquid) }}</p>
          </div>
        </div>
      </div>

      <!-- Life Cover -->
      <div v-if="hasLifeCover" class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
        <h3 class="text-sm font-semibold text-gray-900 mb-3">Life Cover</h3>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
          <div class="bg-gray-50 rounded-lg p-3">
            <p class="text-xs text-gray-500">Cover in Trust</p>
            <p class="text-sm font-bold text-green-700">{{ formatCurrency(situation.life_cover.cover_in_trust) }}</p>
          </div>
          <div class="bg-gray-50 rounded-lg p-3">
            <p class="text-xs text-gray-500">Cover Not in Trust</p>
            <p class="text-sm font-bold text-gray-900">{{ formatCurrency(situation.life_cover.cover_not_in_trust) }}</p>
          </div>
          <div class="bg-gray-50 rounded-lg p-3">
            <p class="text-xs text-gray-500">Total Policies</p>
            <p class="text-sm font-bold text-gray-900">{{ situation.life_cover.policy_count }}</p>
          </div>
        </div>
      </div>

      <!-- Charitable Giving -->
      <div v-if="situation.charitable_giving" class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
        <h3 class="text-sm font-semibold text-gray-900 mb-3">Charitable Giving</h3>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
          <div class="bg-gray-50 rounded-lg p-3">
            <p class="text-xs text-gray-500">Current Charitable Rate</p>
            <p class="text-sm font-bold text-gray-900">{{ situation.charitable_giving.current_percentage }}%</p>
          </div>
          <div class="bg-gray-50 rounded-lg p-3">
            <p class="text-xs text-gray-500">Threshold for 36% Rate</p>
            <p class="text-sm font-bold text-gray-900">{{ situation.charitable_giving.threshold }}%</p>
          </div>
          <div v-if="situation.charitable_giving.shortfall > 0" class="bg-gray-50 rounded-lg p-3">
            <p class="text-xs text-gray-500">Shortfall to Qualify</p>
            <p class="text-sm font-bold text-gray-900">{{ formatCurrency(situation.charitable_giving.shortfall) }}</p>
          </div>
          <div v-if="situation.charitable_giving.potential_saving > 0" class="bg-gray-50 rounded-lg p-3">
            <p class="text-xs text-gray-500">Potential Saving</p>
            <p class="text-sm font-bold text-green-700">{{ formatCurrency(situation.charitable_giving.potential_saving) }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import PlanSectionHeader from '@/components/Plans/Shared/PlanSectionHeader.vue';

export default {
  name: 'EstateCurrentSituation',
  components: { PlanSectionHeader },
  mixins: [currencyMixin],
  props: {
    situation: { type: Object, required: true },
  },
  computed: {
    ihtLiability() {
      return this.situation.iht_calculation?.liability ?? 0;
    },
    hasLifeCover() {
      const lc = this.situation.life_cover;
      return lc && (lc.cover_in_trust > 0 || lc.cover_not_in_trust > 0 || lc.policy_count > 0);
    },
  },
};
</script>
