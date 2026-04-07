<template>
  <AppLayout>
    <div class="module-gradient py-2 sm:py-6">
      <!-- Back link -->
      <button
        class="detail-inline-back mb-4"
        @click="$router.push('/estate')"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
        Back to Estate Planning
      </button>

      <!-- Loading -->
      <div v-if="loading" class="flex justify-center items-center py-12">
        <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
      </div>

      <!-- Content -->
      <div v-else>
        <!-- Spouse Exemption Notice -->
        <SpouseExemptionNotice
          v-if="showSpouseExemptionNotice && secondDeathData"
          :message="secondDeathData.spouse_exemption_message"
          :has-spouse="hasSpouse"
          :data-sharing-enabled="secondDeathData.data_sharing_enabled"
          class="mb-6"
        />

        <!-- IHT Table - Second Death (Married Users) -->
        <div v-if="isMarried && secondDeathData?.second_death_analysis" class="bg-white rounded-lg border border-light-gray p-6 mb-8">
          <div class="flex items-center gap-2 mb-4">
            <h3 class="text-lg font-semibold text-horizon-500">Inheritance Tax Calculation (Joint Death Scenario)</h3>
          </div>
          <IHTCalculationTable
            v-if="secondDeathTableProps"
            v-bind="secondDeathTableProps"
            :charitable-bequest="charitableBequest"
            :effective-i-h-t-rate-label="effectiveIHTRateLabel"
            :has-spouse-linked="hasSpouseLinked"
            :show-minus-5-years="showMinus5Years"
            :show-plus-5-years="showPlus5Years"
            :growth-rate="growthRate"
            :years-to-death-minus-5="yearsToDeathMinus5"
            :years-to-death-plus-5="yearsToDeathPlus5"
            @toggle-minus-5="showMinus5Years = !showMinus5Years"
            @toggle-plus-5="showPlus5Years = !showPlus5Years"
          />
        </div>

        <!-- IHT Table - Standard (Non-Married) -->
        <div v-else-if="ihtData && standardTableProps" class="bg-white rounded-lg border border-light-gray p-6 mb-8">
          <div class="flex items-center gap-2 mb-4">
            <h3 class="text-lg font-semibold text-horizon-500">
              {{ isMarried ? 'Inheritance Tax Calculation (Joint Death Scenario)' : 'Inheritance Tax Calculation Breakdown' }}
            </h3>
          </div>
          <p v-if="!isMarried && projection" class="text-sm text-neutral-500 mb-6">
            Comparison of Inheritance Tax liability if death occurs now vs. at projected life expectancy (Age {{ projection.at_death.estimated_age_at_death }})
          </p>
          <IHTCalculationTable
            v-bind="standardTableProps"
            :charitable-bequest="charitableBequest"
            :effective-i-h-t-rate-label="effectiveIHTRateLabel"
            :has-spouse-linked="false"
            :show-minus-5-years="showMinus5Years"
            :show-plus-5-years="showPlus5Years"
            :growth-rate="growthRate"
            :years-to-death-minus-5="yearsToDeathMinus5"
            :years-to-death-plus-5="yearsToDeathPlus5"
            @toggle-minus-5="showMinus5Years = !showMinus5Years"
            @toggle-plus-5="showPlus5Years = !showPlus5Years"
          />
        </div>

        <!-- Tax Allowances -->
        <div v-if="ihtData" class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
          <div v-if="ihtData.nrb_message" class="bg-eggshell-500 rounded-lg p-4">
            <h3 class="text-sm font-semibold text-violet-800">Tax-Free Allowance</h3>
            <p class="mt-2 text-sm text-violet-800">{{ ihtData.nrb_message }}</p>
          </div>
          <div v-if="ihtData.rnrb_message" class="bg-eggshell-500 rounded-lg p-4">
            <h3 class="text-sm font-semibold" :class="ihtData.rnrb_status === 'full' ? 'text-spring-800' : 'text-horizon-500'">Home Allowance</h3>
            <p class="mt-2 text-sm" :class="ihtData.rnrb_status === 'full' ? 'text-spring-800' : 'text-horizon-500'">{{ ihtData.rnrb_message }}</p>
          </div>
        </div>

        <!-- Asset Breakdown -->
        <IHTAssetBreakdown v-if="ihtData" class="mb-8" />

        <!-- Mitigation Strategies -->
        <IHTMitigationStrategies v-if="ihtData" class="mb-8" />
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { mapState, mapGetters, mapActions } from 'vuex';
import AppLayout from '@/layouts/AppLayout.vue';
import IHTCalculationTable from '@/components/Estate/IHTCalculationTable.vue';
import IHTAssetBreakdown from '@/components/Estate/IHTAssetBreakdown.vue';
import IHTMitigationStrategies from '@/components/Estate/IHTMitigationStrategies.vue';
import SpouseExemptionNotice from '@/components/Estate/SpouseExemptionNotice.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'InheritanceTaxDetail',

  components: {
    AppLayout,
    IHTCalculationTable,
    IHTAssetBreakdown,
    IHTMitigationStrategies,
    SpouseExemptionNotice,
  },

  mixins: [currencyMixin],

  data() {
    return {
      loading: true,
      showMinus5Years: false,
      showPlus5Years: false,
    };
  },

  computed: {
    ...mapState('estate', ['ihtData', 'projection', 'secondDeathPlanning', 'willInfo']),
    ...mapGetters('estate', ['ihtLiability', 'taxableEstate']),

    charitableBequest() {
      return this.$store.state.auth.user?.charitable_bequest || false;
    },

    isMarried() {
      return this.$store.state.auth.user?.marital_status === 'married';
    },

    hasSpouse() {
      return !!this.$store.state.auth.user?.spouse_id;
    },

    hasSpouseLinked() {
      return this.secondDeathData?.data_sharing_enabled === true;
    },

    secondDeathData() {
      return this.secondDeathPlanning;
    },

    showSpouseExemptionNotice() {
      return this.isMarried && this.secondDeathData?.spouse_exemption_message;
    },

    effectiveIHTRateLabel() {
      return this.charitableBequest ? '36%' : '40%';
    },

    growthRate() {
      return this.projection?.growth_rate || 0.03;
    },

    yearsToDeathMinus5() {
      const years = this.projection?.at_death?.years_to_death || 30;
      return Math.max(0, years - 5);
    },

    yearsToDeathPlus5() {
      const years = this.projection?.at_death?.years_to_death || 30;
      return years + 5;
    },

    standardTableProps() {
      if (!this.ihtData || !this.projection) return null;

      return {
        nowData: this.projection.now || {},
        projectedData: this.projection.at_death || {},
        taxableEstate: {
          now: this.projection.now?.taxable_estate || 0,
          projected: this.projection.at_death?.taxable_estate || 0,
        },
        ihtLiability: {
          now: this.projection.now?.iht_liability || 0,
          projected: this.projection.at_death?.iht_liability || 0,
        },
        estimatedAgeAtDeath: this.ihtData.estimated_age_at_death,
      };
    },

    secondDeathTableProps() {
      if (!this.secondDeathData?.second_death_analysis) return null;
      const analysis = this.secondDeathData.second_death_analysis;

      return {
        nowData: analysis.current_iht_calculation || {},
        projectedData: analysis.iht_calculation || {},
        taxableEstate: {
          now: analysis.current_iht_calculation?.taxable_estate || 0,
          projected: analysis.iht_calculation?.taxable_estate || 0,
        },
        ihtLiability: {
          now: analysis.current_iht_calculation?.iht_liability || 0,
          projected: analysis.iht_calculation?.iht_liability || 0,
        },
        estimatedAgeAtDeath: analysis.second_death?.estimated_age_at_death,
      };
    },
  },

  async mounted() {
    try {
      await this.fetchEstateData();
      await this.calculateIHTPlanning();
    } catch {
      // Data may already be loaded from estate dashboard
    } finally {
      this.loading = false;
    }
  },

  methods: {
    ...mapActions('estate', ['fetchEstateData', 'calculateIHTPlanning']),
  },
};
</script>
