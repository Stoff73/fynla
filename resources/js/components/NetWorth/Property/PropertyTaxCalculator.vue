<template>
  <div class="space-y-6">
    <div class="bg-savannah-100 rounded-md p-4">
      <p class="text-sm text-violet-800">
        <strong>Disclaimer:</strong> These calculators are for informational purposes only and should not be considered financial or tax advice.
        Please consult with a qualified tax advisor for your specific situation.
      </p>
    </div>

    <!-- Tax Calculator Tabs -->
    <div class="border-b border-light-gray">
      <nav class="flex -mb-px">
        <button
          v-for="tab in calculatorTabs"
          :key="tab.id"
          @click="activeCalculator = tab.id"
          class="px-6 py-3 border-b-2 font-medium text-sm transition-colors"
          :class="
            activeCalculator === tab.id
              ? 'border-violet-600 text-violet-600'
              : 'border-transparent text-neutral-500 hover:text-neutral-500 hover:border-horizon-300'
          "
        >
          {{ tab.label }}
        </button>
      </nav>
    </div>

    <!-- SDLT Calculator -->
    <div v-show="activeCalculator === 'sdlt'" class="space-y-4">
      <h3 class="text-lg font-semibold text-horizon-500">Stamp Duty Land Tax Calculator</h3>
      <p class="text-sm text-neutral-500">Calculate Stamp Duty Land Tax based on {{ currentTaxYear }} rates</p>

      <form @submit.prevent="calculateSDLT" class="space-y-4">
        <div>
          <label for="sdlt_purchase_price" class="block text-sm font-medium text-horizon-500 mb-1">
            Purchase Price (£)
          </label>
          <input
            id="sdlt_purchase_price"
            v-model.number="sdltForm.purchase_price"
            type="number"
            step="0.01"
            min="0"
            required
            class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-raspberry-500"
          />
        </div>

        <div>
          <label for="sdlt_property_type" class="block text-sm font-medium text-horizon-500 mb-1">
            Property Type
          </label>
          <select
            id="sdlt_property_type"
            v-model="sdltForm.property_type"
            required
            class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-raspberry-500"
          >
            <option value="main_residence">Main Residence</option>
            <option value="secondary_residence">Secondary Residence / Additional Property</option>
            <option value="buy_to_let">Buy to Let</option>
          </select>
        </div>

        <div>
          <label class="flex items-center">
            <input
              type="checkbox"
              v-model="sdltForm.is_first_home"
              class="mr-2"
              :disabled="sdltForm.property_type !== 'main_residence'"
            />
            <span class="text-sm text-neutral-500">First-time buyer</span>
          </label>
          <p class="text-xs text-neutral-500 mt-1">
            First-time buyer relief applies to properties up to £625,000 (relief up to £425,000)
          </p>
        </div>

        <button
          type="submit"
          :disabled="calculatingSDLT"
          class="w-full px-4 py-2 bg-raspberry-500 text-white rounded-button hover:bg-raspberry-600 transition-colors disabled:opacity-50"
        >
          {{ calculatingSDLT ? 'Calculating...' : 'Calculate Stamp Duty' }}
        </button>
      </form>

      <!-- SDLT Results -->
      <div v-if="sdltResult" class="mt-6 space-y-4">
        <div class="bg-savannah-100 rounded-lg p-6">
          <h4 class="text-xl font-semibold text-spring-800 mb-2">Stamp Duty Calculation Result</h4>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <p class="text-sm text-spring-700">Total Stamp Duty</p>
              <p class="text-3xl font-bold text-spring-900">{{ formatCurrency(sdltResult.total_sdlt) }}</p>
            </div>
            <div>
              <p class="text-sm text-spring-700">Effective Rate</p>
              <p class="text-3xl font-bold text-spring-900">{{ sdltResult.effective_rate }}%</p>
            </div>
          </div>
        </div>

        <div class="bg-white border border-light-gray rounded-lg overflow-hidden">
          <table class="w-full">
            <thead class="bg-savannah-100">
              <tr>
                <th class="px-4 py-3 text-left text-sm font-medium text-horizon-500">Band</th>
                <th class="px-4 py-3 text-right text-sm font-medium text-horizon-500">Rate</th>
                <th class="px-4 py-3 text-right text-sm font-medium text-horizon-500">Tax</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-200">
              <tr v-for="(band, index) in sdltResult.bands" :key="index">
                <td class="px-4 py-3 text-sm text-horizon-500">{{ band.description }}</td>
                <td class="px-4 py-3 text-sm text-right text-neutral-500">{{ band.rate }}%</td>
                <td class="px-4 py-3 text-sm text-right font-medium text-horizon-500">{{ formatCurrency(band.tax) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- CGT Calculator -->
    <div v-show="activeCalculator === 'cgt'" class="space-y-4">
      <h3 class="text-lg font-semibold text-horizon-500">Capital Gains Tax Calculator</h3>

      <div v-if="property.property_type === 'main_residence'" class="bg-savannah-100 rounded-md p-4">
        <p class="text-sm text-violet-800">
          <strong>Note:</strong> As a main residence, this property may qualify for Private Residence Relief,
          which could reduce or eliminate Capital Gains Tax liability.
        </p>
      </div>

      <form @submit.prevent="calculateCGT" class="space-y-4">
        <div>
          <label for="cgt_disposal_price" class="block text-sm font-medium text-horizon-500 mb-1">
            Disposal Price (£)
          </label>
          <input
            id="cgt_disposal_price"
            v-model.number="cgtForm.disposal_price"
            type="number"
            step="0.01"
            min="0"
            required
            class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-raspberry-500"
          />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label for="cgt_disposal_costs" class="block text-sm font-medium text-horizon-500 mb-1">
              Disposal Costs (£)
            </label>
            <input
              id="cgt_disposal_costs"
              v-model.number="cgtForm.disposal_costs"
              type="number"
              step="0.01"
              min="0"
              placeholder="Legal fees, estate agent fees"
              class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-raspberry-500"
            />
          </div>

          <div>
            <label for="cgt_improvement_costs" class="block text-sm font-medium text-horizon-500 mb-1">
              Improvement Costs (£)
            </label>
            <input
              id="cgt_improvement_costs"
              v-model.number="cgtForm.improvement_costs"
              type="number"
              step="0.01"
              min="0"
              placeholder="Extensions, renovations"
              class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-raspberry-500"
            />
          </div>
        </div>

        <button
          type="submit"
          :disabled="calculatingCGT"
          class="w-full px-4 py-2 bg-raspberry-500 text-white rounded-button hover:bg-raspberry-600 transition-colors disabled:opacity-50"
        >
          {{ calculatingCGT ? 'Calculating...' : 'Calculate Capital Gains Tax' }}
        </button>
      </form>

      <!-- CGT Results -->
      <div v-if="cgtResult" class="mt-6 space-y-4">
        <div class="bg-savannah-100 rounded-lg p-6">
          <h4 class="text-xl font-semibold text-spring-800 mb-4">Capital Gains Tax Calculation Result</h4>
          <dl class="space-y-2">
            <div class="flex justify-between">
              <dt class="text-sm text-spring-700">Disposal Price:</dt>
              <dd class="text-sm font-medium text-spring-900">{{ formatCurrency(cgtForm.disposal_price) }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-sm text-spring-700">Less: Purchase Price:</dt>
              <dd class="text-sm font-medium text-spring-900">-{{ formatCurrency(property.purchase_price) }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-sm text-spring-700">Less: Costs:</dt>
              <dd class="text-sm font-medium text-spring-900">
                -{{ formatCurrency((cgtForm.disposal_costs || 0) + (cgtForm.improvement_costs || 0)) }}
              </dd>
            </div>
            <div class="flex justify-between border-t border-spring-300 pt-2">
              <dt class="text-sm font-semibold text-spring-700">Gain:</dt>
              <dd class="text-sm font-bold text-spring-900">{{ formatCurrency(cgtResult.gain) }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-sm text-spring-700">Less: Annual Exempt Amount:</dt>
              <dd class="text-sm font-medium text-spring-900">-£{{ cgtAnnualAllowance.toLocaleString() }}</dd>
            </div>
            <div class="flex justify-between border-t border-spring-300 pt-2">
              <dt class="text-sm font-semibold text-spring-700">Taxable Gain:</dt>
              <dd class="text-sm font-bold text-spring-900">{{ formatCurrency(cgtResult.taxable_gain) }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-sm text-spring-700">Capital Gains Tax Rate:</dt>
              <dd class="text-sm font-medium text-spring-900">{{ cgtResult.cgt_rate }}%</dd>
            </div>
            <div class="flex justify-between border-t border-spring-300 pt-2 mt-2">
              <dt class="text-lg font-bold text-spring-700">Capital Gains Tax Liability:</dt>
              <dd class="text-2xl font-bold text-spring-900">{{ formatCurrency(cgtResult.cgt_liability) }}</dd>
            </div>
          </dl>
        </div>
      </div>
    </div>

    <!-- Rental Income Tax Calculator -->
    <div v-show="activeCalculator === 'rental'" class="space-y-4">
      <h3 class="text-lg font-semibold text-horizon-500">Rental Income Tax Calculator</h3>

      <div v-if="property.property_type !== 'buy_to_let'" class="bg-savannah-100 rounded-md p-4">
        <p class="text-sm text-violet-800">
          This calculator is primarily for Buy to Let properties. Results may not be accurate for other property types.
        </p>
      </div>

      <button
        @click="calculateRentalTax"
        :disabled="calculatingRental"
        class="w-full px-4 py-2 bg-raspberry-500 text-white rounded-button hover:bg-raspberry-600 transition-colors disabled:opacity-50"
      >
        {{ calculatingRental ? 'Calculating...' : 'Calculate Rental Income Tax' }}
      </button>

      <!-- Rental Tax Results -->
      <div v-if="rentalResult" class="mt-6 space-y-4">
        <div class="bg-savannah-100 rounded-lg p-6">
          <h4 class="text-xl font-semibold text-spring-800 mb-4">Rental Income Tax Result</h4>
          <dl class="space-y-2">
            <div class="flex justify-between">
              <dt class="text-sm text-spring-700">Gross Rental Income:</dt>
              <dd class="text-sm font-medium text-spring-900">{{ formatCurrency(rentalResult.gross_income) }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-sm text-spring-700">Less: Allowable Expenses:</dt>
              <dd class="text-sm font-medium text-spring-900">-{{ formatCurrency(rentalResult.allowable_expenses) }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-sm text-spring-700">Mortgage Interest:</dt>
              <dd class="text-sm font-medium text-spring-900">{{ formatCurrency(rentalResult.mortgage_interest || 0) }}</dd>
            </div>
            <div class="flex justify-between border-t border-spring-300 pt-2">
              <dt class="text-sm font-semibold text-spring-700">Taxable Profit:</dt>
              <dd class="text-sm font-bold text-spring-900">{{ formatCurrency(rentalResult.taxable_profit) }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-sm text-spring-700">Tax at Marginal Rate:</dt>
              <dd class="text-sm font-medium text-spring-900">{{ formatCurrency(rentalResult.tax_before_relief) }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-sm text-spring-700">Less: Mortgage Interest Tax Relief (20%):</dt>
              <dd class="text-sm font-medium text-spring-900">-{{ formatCurrency(rentalResult.mortgage_interest_relief || 0) }}</dd>
            </div>
            <div class="flex justify-between border-t border-spring-300 pt-2 mt-2">
              <dt class="text-lg font-bold text-spring-700">Tax Liability:</dt>
              <dd class="text-2xl font-bold text-spring-900">{{ formatCurrency(rentalResult.tax_liability) }}</dd>
            </div>
          </dl>
        </div>

        <div class="bg-savannah-100 rounded-md p-4">
          <p class="text-sm text-violet-800">
            <strong>Note:</strong> Mortgage interest is no longer fully deductible. You receive a 20% tax credit instead.
            Higher rate taxpayers may face increased tax liability on rental income.
          </p>
        </div>
      </div>
    </div>

    <!-- Error Message -->
    <div v-if="error" class="p-3 bg-savannah-100 rounded-md">
      <p class="text-sm text-raspberry-600">{{ error }}</p>
    </div>
  </div>
</template>

<script>
import { mapActions } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import { getCurrentTaxYear } from '@/utils/dateFormatter';
import { CGT_ANNUAL_ALLOWANCE } from '@/constants/taxConfig';

export default {
  name: 'PropertyTaxCalculator',
  mixins: [currencyMixin],

  props: {
    property: {
      type: Object,
      required: true,
    },
  },

  data() {
    return {
      cgtAnnualAllowance: CGT_ANNUAL_ALLOWANCE,
      activeCalculator: 'sdlt',
      calculatorTabs: [
        { id: 'sdlt', label: 'Stamp Duty' },
        { id: 'cgt', label: 'Capital Gains Tax' },
        { id: 'rental', label: 'Rental Income Tax' },
      ],
      sdltForm: {
        purchase_price: null,
        property_type: 'main_residence',
        is_first_home: false,
      },
      cgtForm: {
        disposal_price: null,
        disposal_costs: null,
        improvement_costs: null,
      },
      sdltResult: null,
      cgtResult: null,
      rentalResult: null,
      calculatingSDLT: false,
      calculatingCGT: false,
      calculatingRental: false,
      error: null,
    };
  },

  mounted() {
    // Pre-fill forms with property data
    this.sdltForm.purchase_price = this.property.purchase_price;
    this.sdltForm.property_type = this.property.property_type;
  },

  computed: {
    currentTaxYear() {
      return getCurrentTaxYear();
    },
  },

  methods: {
    ...mapActions('netWorth', ['calculateSDLT', 'calculateCGT', 'calculateRentalIncomeTax']),

    async calculateSDLT() {
      this.calculatingSDLT = true;
      this.error = null;
      this.sdltResult = null;

      try {
        this.sdltResult = await this.calculateSDLT({
          purchase_price: this.sdltForm.purchase_price,
          property_type: this.sdltForm.property_type,
          is_first_home: this.sdltForm.is_first_home,
        });
      } catch (error) {
        this.error = 'Failed to calculate Stamp Duty Land Tax. Please try again.';
      } finally {
        this.calculatingSDLT = false;
      }
    },

    async calculateCGT() {
      this.calculatingCGT = true;
      this.error = null;
      this.cgtResult = null;

      try {
        this.cgtResult = await this.calculateCGT({
          propertyId: this.property.id,
          data: {
            disposal_price: this.cgtForm.disposal_price,
            disposal_costs: this.cgtForm.disposal_costs || 0,
            improvement_costs: this.cgtForm.improvement_costs || 0,
          },
        });
      } catch (error) {
        this.error = 'Failed to calculate Capital Gains Tax. Please try again.';
      } finally {
        this.calculatingCGT = false;
      }
    },

    async calculateRentalTax() {
      this.calculatingRental = true;
      this.error = null;
      this.rentalResult = null;

      try {
        this.rentalResult = await this.calculateRentalIncomeTax(this.property.id);
      } catch (error) {
        this.error = 'Failed to calculate rental income tax. Please try again.';
      } finally {
        this.calculatingRental = false;
      }
    },

  },
};
</script>
