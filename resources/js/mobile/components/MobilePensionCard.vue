<template>
  <div class="px-4 py-3">
    <div class="flex items-start justify-between">
      <div class="flex-1 min-w-0">
        <h4 class="text-sm font-bold text-horizon-500 truncate">{{ pension.scheme_name || pension.name || 'Pension' }}</h4>
        <p class="text-xs text-neutral-500 mt-0.5">{{ typeLabel }}</p>
      </div>
      <div class="text-right ml-3">
        <p class="text-sm font-bold text-horizon-500">{{ primaryValue }}</p>
        <p class="text-xs text-neutral-500 mt-0.5">{{ primaryLabel }}</p>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-x-4 gap-y-1.5 mt-3 pt-3 border-t border-light-gray">
      <div v-for="detail in details" :key="detail.label">
        <p class="text-xs text-neutral-400">{{ detail.label }}</p>
        <p class="text-xs font-medium text-horizon-500">{{ detail.value }}</p>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'MobilePensionCard',

  mixins: [currencyMixin],

  props: {
    pension: { type: Object, required: true },
    pensionType: {
      type: String,
      required: true,
      validator: (v) => ['dc', 'db', 'state'].includes(v),
    },
  },

  computed: {
    typeLabel() {
      if (this.pensionType === 'dc') {
        const sub = this.pension.pension_type || this.pension.type || '';
        if (sub.toLowerCase().includes('sipp')) return 'Self-invested personal pension';
        if (sub.toLowerCase().includes('workplace')) return 'Workplace pension';
        return 'Defined contribution';
      }
      if (this.pensionType === 'db') return 'Defined benefit';
      return 'State pension';
    },

    primaryValue() {
      if (this.pensionType === 'dc') return this.formatCurrency(this.pension.current_fund_value || this.pension.fund_value || this.pension.current_value || 0);
      if (this.pensionType === 'db') return this.formatCurrency(this.pension.accrued_annual_pension || this.pension.annual_income || this.pension.annual_pension || 0);
      return this.formatCurrency(this.pension.state_pension_forecast_annual || (this.pension.weekly_amount || 0) * 52);
    },

    primaryLabel() {
      if (this.pensionType === 'dc') return 'Fund value';
      if (this.pensionType === 'db') return 'Annual income';
      return 'Annual forecast';
    },

    details() {
      if (this.pensionType === 'dc') {
        const items = [];
        if (this.pension.provider) items.push({ label: 'Provider', value: this.pension.provider });
        if (this.pension.employee_contribution_pct != null) {
          items.push({ label: 'Employee', value: `${this.pension.employee_contribution_pct}%` });
        }
        if (this.pension.employer_contribution_pct != null) {
          items.push({ label: 'Employer', value: `${this.pension.employer_contribution_pct}%` });
        }
        if (this.pension.projected_value) {
          items.push({ label: 'Projected', value: this.formatCurrency(this.pension.projected_value) });
        }
        return items;
      }

      if (this.pensionType === 'db') {
        const items = [];
        if (this.pension.employer) items.push({ label: 'Employer', value: this.pension.employer });
        if (this.pension.accrual_rate) items.push({ label: 'Accrual rate', value: this.pension.accrual_rate });
        if (this.pension.normal_retirement_age) items.push({ label: 'Retirement age', value: String(this.pension.normal_retirement_age) });
        if (this.pension.spouse_benefit_pct != null) items.push({ label: 'Spouse benefit', value: `${this.pension.spouse_benefit_pct}%` });
        return items;
      }

      // State pension
      const items = [];
      const annualForecast = this.pension.state_pension_forecast_annual || (this.pension.weekly_amount || 0) * 52;
      if (annualForecast) items.push({ label: 'Weekly', value: this.formatCurrency(annualForecast / 52) });
      const niYears = this.pension.ni_years_completed ?? this.pension.qualifying_years;
      if (niYears != null) items.push({ label: 'Qualifying years', value: `${niYears} of ${this.pension.ni_years_required || 35}` });
      if (this.pension.state_pension_age) items.push({ label: 'Pension age', value: String(this.pension.state_pension_age) });
      if (this.pension.already_receiving) items.push({ label: 'Status', value: 'Receiving' });
      return items;
    },
  },
};
</script>
