<template>
  <div v-if="info" class="bg-white rounded-lg shadow-sm border border-light-gray p-6 mb-6">
    <div class="mb-6">
      <h3 class="text-h4 font-semibold text-horizon-500">Personal Information</h3>
      <p class="mt-1 text-body-sm text-neutral-500">
        A summary of your personal and financial details used in this plan
      </p>
    </div>

    <!-- Row 1: Personal Details & Family -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-12 gap-y-8">
      <!-- Personal Details -->
      <div>
        <h3 class="text-body-base font-semibold text-horizon-500 mb-4">Personal Details</h3>
        <div class="space-y-3">
          <div class="flex justify-between">
            <span class="text-body-sm text-neutral-500">Full Name:</span>
            <span class="text-body-sm text-horizon-500 text-right">{{ info.full_name || '—' }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-body-sm text-neutral-500">Date of Birth:</span>
            <span class="text-body-sm text-horizon-500 text-right">{{ formatDateOfBirth(info.date_of_birth) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-body-sm text-neutral-500">Age:</span>
            <span class="text-body-sm text-horizon-500 text-right">{{ info.age != null ? info.age : '—' }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-body-sm text-neutral-500">Marital Status:</span>
            <span class="text-body-sm text-horizon-500 text-right capitalize">{{ info.marital_status || '—' }}</span>
          </div>
        </div>
      </div>

      <!-- Family -->
      <div>
        <h3 class="text-body-base font-semibold text-horizon-500 mb-4">Family</h3>
        <div class="space-y-3">
          <div class="flex justify-between">
            <span class="text-body-sm text-neutral-500">Spouse:</span>
            <span class="text-body-sm text-horizon-500 text-right">{{ info.spouse_name || '—' }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-body-sm text-neutral-500">Children:</span>
            <span class="text-body-sm text-horizon-500 text-right">{{ childrenDisplay }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Row 2: Financial Overview & Risk Profile -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-12 gap-y-8 mt-8">
      <!-- Financial Overview -->
      <div>
        <h3 class="text-body-base font-semibold text-horizon-500 mb-4">Financial Overview</h3>
        <div class="space-y-3">
          <div class="flex justify-between">
            <span class="text-body-sm text-neutral-500">Gross Income:</span>
            <span class="text-body-sm text-horizon-500 text-right">{{ formatCurrency(info.gross_income) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-body-sm text-neutral-500">Net Income:</span>
            <span class="text-body-sm text-horizon-500 text-right">{{ formatCurrency(info.net_income) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-body-sm text-neutral-500">Annual Expenditure:</span>
            <span class="text-body-sm text-horizon-500 text-right">{{ formatCurrency(info.annual_expenditure) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-body-sm text-neutral-500">Disposable Income (annual):</span>
            <span class="text-body-sm text-horizon-500 text-right">{{ formatCurrency(info.disposable_income) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-body-sm text-neutral-500">Disposable Income (monthly):</span>
            <span class="text-body-sm text-horizon-500 text-right">{{ formatCurrency(info.monthly_disposable) }}</span>
          </div>
        </div>
      </div>

      <!-- Risk Profile -->
      <div>
        <h3 class="text-body-base font-semibold text-horizon-500 mb-4">Risk Profile</h3>
        <div class="space-y-3">
          <div class="flex justify-between">
            <span class="text-body-sm text-neutral-500">Risk Level:</span>
            <span class="text-body-sm text-horizon-500 text-right capitalize">{{ info.risk_level || '—' }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'InvestmentPersonalInformation',
  mixins: [currencyMixin],
  props: {
    info: { type: Object, default: null },
  },
  computed: {
    childrenDisplay() {
      if (!this.info?.children || this.info.children.length === 0) {
        return 'None';
      }
      return this.info.children.join(', ');
    },
  },
  methods: {
    formatDateOfBirth(date) {
      if (!date) return '—';
      try {
        const dateObj = new Date(date + 'T00:00:00');
        if (isNaN(dateObj.getTime())) return '—';
        return dateObj.toLocaleDateString('en-GB', {
          day: 'numeric',
          month: 'long',
          year: 'numeric',
        });
      } catch {
        return '—';
      }
    },
  },
};
</script>
