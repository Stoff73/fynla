<template>
  <div class="contributions-allowances">
    <div class="mb-6">
      <h2 class="text-2xl font-bold text-gray-900">Contributions & Allowances</h2>
      <p class="text-gray-600 mt-1">Track your pension contributions and annual allowance usage</p>
    </div>

    <!-- Annual Allowance Tracker -->
    <div class="mb-8">
      <AnnualAllowanceTracker />
    </div>

    <!-- Current Tax Year Contributions -->
    <div class="bg-white rounded-lg shadow p-6 mb-8">
      <h3 class="text-lg font-semibold text-gray-900 mb-6">Current Tax Year Contributions</h3>

      <!-- Warning if using estimated salary -->
      <div v-if="!profile?.current_income && dcPensions.length > 0" class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 flex items-start">
        <svg class="w-5 h-5 text-blue-600 mr-3 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
        </svg>
        <div>
          <p class="text-sm font-bold text-blue-900">Estimated Contributions</p>
          <p class="text-sm text-blue-800 mt-1">
            Contribution amounts are estimated using a default salary of £50,000. For accurate calculations, please add your actual salary information.
          </p>
        </div>
      </div>

      <div class="space-y-6">
        <!-- DC Pensions Contributions -->
        <div>
          <h4 class="text-md font-medium text-gray-700 mb-4">Defined Contribution Pension Contributions</h4>
          <div v-if="dcPensions.length > 0" class="space-y-3">
            <div
              v-for="pension in dcPensions"
              :key="pension.id"
              class="flex items-center justify-between p-4 bg-gray-50 rounded-lg"
            >
              <div>
                <p class="font-medium text-gray-900">{{ pension.scheme_name }}</p>
                <p class="text-sm text-gray-500">
                  <span v-if="pension.scheme_type === 'workplace'">
                    Employee: {{ parseFloat(pension.employee_contribution_percent || 0) }}% +
                    Employer: {{ parseFloat(pension.employer_contribution_percent || 0) }}%
                  </span>
                  <span v-else-if="pension.scheme_type === 'personal'">
                    Personal Pension: {{ formatCurrency(parseFloat(pension.monthly_contribution_amount || 0)) }}/month
                  </span>
                  <span v-else-if="pension.scheme_type === 'sipp'">
                    Self-Invested Personal Pension: {{ formatCurrency(parseFloat(pension.monthly_contribution_amount || 0)) }}/month
                  </span>
                </p>
              </div>
              <div class="text-right">
                <p class="text-lg font-bold text-gray-900">
                  {{ formatCurrency(calculateAnnualContribution(pension)) }}
                </p>
                <p class="text-sm text-gray-500">per year</p>
              </div>
            </div>
          </div>
          <div v-else class="text-center text-gray-500 py-4">
            No Defined Contribution pensions with contributions
          </div>
        </div>

        <!-- Total Contributions Summary -->
        <div class="border-t-2 border-gray-200 pt-4">
          <div class="flex items-center justify-between">
            <p class="text-lg font-semibold text-gray-900">Total Contributions This Year</p>
            <p class="text-2xl font-bold text-indigo-600">
              {{ formatCurrency(totalContributionsThisYear) }}
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Salary Sacrifice Information -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
      <!-- Salary Sacrifice Benefits -->
      <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center mb-4">
          <svg class="w-6 h-6 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
          </svg>
          <h3 class="text-lg font-semibold text-gray-900">Salary Sacrifice</h3>
        </div>
        <p class="text-sm text-gray-600 mb-4">
          {{ salarySacrificePensionsCount }} of your pensions use salary sacrifice arrangements
        </p>
        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
          <p class="text-sm font-medium text-green-900 mb-2">Benefits:</p>
          <ul class="text-sm text-green-800 space-y-1">
            <li>• Save on National Insurance contributions</li>
            <li>• Employer saves National Insurance too (often passed on)</li>
            <li>• More cost-effective than regular contributions</li>
          </ul>
        </div>
      </div>

      <!-- Key Thresholds -->
      <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center mb-4">
          <svg class="w-6 h-6 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
          </svg>
          <h3 class="text-lg font-semibold text-gray-900">Key Thresholds (2025/26)</h3>
        </div>
        <div class="space-y-3">
          <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
            <span class="text-sm text-gray-600">Standard Annual Allowance</span>
            <span class="font-semibold text-gray-900">£60,000</span>
          </div>
          <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
            <span class="text-sm text-gray-600">Minimum Tapered Allowance</span>
            <span class="font-semibold text-gray-900">£10,000</span>
          </div>
          <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
            <span class="text-sm text-gray-600">Money Purchase Annual Allowance (if triggered)</span>
            <span class="font-semibold text-gray-900">£10,000</span>
          </div>
          <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
            <span class="text-sm text-gray-600">Lifetime Allowance</span>
            <span class="font-semibold text-gray-900">Abolished</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Tapered Annual Allowance Info -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-8">
      <div class="flex items-start">
        <svg class="w-6 h-6 text-blue-600 mr-3 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <div>
          <p class="text-sm font-semibold text-blue-900 mb-2">Tapered Annual Allowance</p>
          <p class="text-sm text-blue-800 mb-3">
            Your annual allowance may be reduced (tapered) if you're a high earner:
          </p>
          <ul class="text-sm text-blue-800 space-y-1">
            <li>• Threshold income: Over £200,000</li>
            <li>• Adjusted income: Over £260,000</li>
            <li>• Taper rate: £1 reduction for every £2 over the threshold</li>
            <li>• Minimum allowance: £10,000 (if adjusted income exceeds £360,000)</li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Contribution Strategy Tips -->
    <div class="bg-white rounded-lg shadow p-6">
      <h3 class="text-lg font-semibold text-gray-900 mb-4">Contribution Strategy Tips</h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="flex items-start">
          <div class="flex-shrink-0 w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center mr-3">
            <span class="text-indigo-600 font-bold">1</span>
          </div>
          <div>
            <p class="font-medium text-gray-900">Maximize Tax Relief</p>
            <p class="text-sm text-gray-600 mt-1">Contribute up to your annual allowance to get full tax relief at your marginal rate</p>
          </div>
        </div>
        <div class="flex items-start">
          <div class="flex-shrink-0 w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center mr-3">
            <span class="text-indigo-600 font-bold">2</span>
          </div>
          <div>
            <p class="font-medium text-gray-900">Use Carry Forward</p>
            <p class="text-sm text-gray-600 mt-1">You can carry forward unused allowance from the previous 3 tax years</p>
          </div>
        </div>
        <div class="flex items-start">
          <div class="flex-shrink-0 w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center mr-3">
            <span class="text-indigo-600 font-bold">3</span>
          </div>
          <div>
            <p class="font-medium text-gray-900">Consider Salary Sacrifice</p>
            <p class="text-sm text-gray-600 mt-1">Save on National Insurance contributions (both you and your employer)</p>
          </div>
        </div>
        <div class="flex items-start">
          <div class="flex-shrink-0 w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center mr-3">
            <span class="text-indigo-600 font-bold">4</span>
          </div>
          <div>
            <p class="font-medium text-gray-900">Review Regularly</p>
            <p class="text-sm text-gray-600 mt-1">Check your contributions annually, especially if your income changes</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { mapState } from 'vuex';
import AnnualAllowanceTracker from '../../components/Retirement/AnnualAllowanceTracker.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'ContributionsAllowances',
  mixins: [currencyMixin],

  components: {
    AnnualAllowanceTracker,
  },

  computed: {
    ...mapState('retirement', ['dcPensions', 'profile']),

    totalContributionsThisYear() {
      return this.dcPensions.reduce((total, pension) => {
        return total + this.calculateAnnualContribution(pension);
      }, 0);
    },

    salarySacrificePensionsCount() {
      return this.dcPensions.filter(p => p.salary_sacrifice).length;
    },
  },

  methods: {
    calculateAnnualContribution(pension) {
      // For personal/SIPP pensions, use the monthly contribution amount
      if (pension.scheme_type === 'personal' || pension.scheme_type === 'sipp') {
        const monthlyAmount = parseFloat(pension.monthly_contribution_amount || 0);
        return monthlyAmount * 12; // Convert monthly to annual
      }

      // For workplace pensions, calculate based on percentage of salary
      const employeePercent = parseFloat(pension.employee_contribution_percent || 0);
      const employerPercent = parseFloat(pension.employer_contribution_percent || 0);
      const totalPercent = employeePercent + employerPercent;

      // Use annual_salary if available, otherwise use profile income, otherwise estimate
      const salary = parseFloat(pension.annual_salary || this.profile?.current_income || 50000);
      return (salary * totalPercent) / 100;
    },

    // formatCurrency provided by currencyMixin
  },
};
</script>

<style scoped>
/* Animations */
@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.contributions-allowances > div {
  animation: fadeIn 0.5s ease-out;
}
</style>
