<template>
  <div class="bg-white rounded-lg shadow-sm border border-light-gray p-6">
    <h2 class="text-h3 font-bold text-horizon-500 mb-2">Residuary Estate</h2>
    <p class="text-sm text-neutral-500 mb-6">
      The residuary estate is everything remaining after debts, taxes, and any specific gifts have been paid. Specify how you would like it distributed.
    </p>

    <!-- Mirror Will Auto-fill Notice -->
    <div v-if="formData.will_type === 'mirror'" class="bg-savannah-100 rounded-lg p-4 mb-6">
      <p class="text-sm text-neutral-500">
        <strong>Mirror will:</strong> Your spouse will be set as the primary beneficiary. If they predecease you, the estate passes to the beneficiaries listed below.
      </p>
    </div>

    <!-- Beneficiaries -->
    <div class="space-y-3 mb-4">
      <div
        v-for="(beneficiary, index) in beneficiaries"
        :key="index"
        class="border border-light-gray rounded-lg p-4"
      >
        <div class="flex justify-between items-start mb-3">
          <h3 class="text-sm font-semibold text-horizon-500">Beneficiary {{ index + 1 }}</h3>
          <button
            v-if="beneficiaries.length > 1"
            @click="beneficiaries.splice(index, 1)"
            class="text-xs text-raspberry-500 hover:text-raspberry-700"
          >
            Remove
          </button>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div>
            <label class="block text-xs font-medium text-neutral-500 mb-1">Name</label>
            <input
              v-model="beneficiary.beneficiary_name"
              type="text"
              class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:ring-violet-500 focus:border-violet-500 text-sm"
              placeholder="e.g. Emily Carter"
            />
          </div>
          <div>
            <label class="block text-xs font-medium text-neutral-500 mb-1">Share (%)</label>
            <input
              v-model.number="beneficiary.percentage"
              type="number"
              min="0"
              max="100"
              class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:ring-violet-500 focus:border-violet-500 text-sm"
            />
          </div>
          <div>
            <label class="block text-xs font-medium text-neutral-500 mb-1">If they predecease you</label>
            <input
              v-model="beneficiary.substitution_beneficiary"
              type="text"
              class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:ring-violet-500 focus:border-violet-500 text-sm"
              placeholder="e.g. Their children equally"
            />
          </div>
        </div>
      </div>
    </div>

    <button
      @click="addBeneficiary"
      class="text-sm text-raspberry-500 hover:text-raspberry-700 font-medium"
    >
      + Add Beneficiary
    </button>

    <!-- Percentage Tracker -->
    <div class="mt-4 p-3 rounded-lg" :class="totalClass">
      <div class="flex justify-between items-center">
        <span class="text-sm font-medium">Total allocated:</span>
        <span class="text-sm font-bold">{{ totalPercentage }}%</span>
      </div>
      <div class="w-full bg-white bg-opacity-50 rounded-full h-2 mt-2">
        <div
          class="h-2 rounded-full transition-all"
          :class="totalPercentage === 100 ? 'bg-spring-500' : totalPercentage > 100 ? 'bg-raspberry-500' : 'bg-violet-500'"
          :style="{ width: Math.min(totalPercentage, 100) + '%' }"
        ></div>
      </div>
      <p v-if="totalPercentage !== 100" class="text-xs mt-1">
        {{ totalPercentage < 100 ? `${100 - totalPercentage}% remaining to allocate` : 'Total exceeds 100% — please adjust' }}
      </p>
    </div>

    <!-- Suggested Children -->
    <div v-if="suggestedChildren.length > 0 && beneficiaries.length < 2" class="mt-4 bg-savannah-100 rounded-lg p-3">
      <p class="text-xs text-neutral-500 mb-2">
        <strong>Suggestion:</strong> Add your children as beneficiaries?
      </p>
      <div class="flex flex-wrap gap-2">
        <button
          v-for="child in suggestedChildren"
          :key="child.full_name"
          @click="addChildBeneficiary(child)"
          class="text-xs px-3 py-1 bg-white border border-light-gray rounded-full hover:border-raspberry-300 transition-colors"
        >
          + {{ child.full_name }}
        </button>
      </div>
    </div>

    <!-- Navigation -->
    <div class="flex justify-between pt-6 border-t border-light-gray mt-6">
      <button @click="$emit('back')" class="px-4 py-2 text-neutral-500 hover:text-horizon-500 transition-colors">
        Back
      </button>
      <button
        @click="handleNext"
        :disabled="totalPercentage !== 100 || beneficiaries.length === 0"
        class="px-6 py-2.5 bg-raspberry-500 text-white rounded-lg font-medium hover:bg-raspberry-600 transition-colors disabled:opacity-50"
      >
        Continue
      </button>
    </div>
  </div>
</template>

<script>
export default {
  name: 'WillBuilderResiduaryStep',

  props: {
    formData: { type: Object, required: true },
    prePopulated: { type: Object, default: null },
  },

  emits: ['next', 'back'],

  data() {
    let existing = (this.formData.residuary_estate || []).map(b => ({ ...b }));

    // For mirror wills with no existing data, pre-fill with spouse
    if (existing.length === 0 && this.formData.will_type === 'mirror' && this.prePopulated?.spouse) {
      existing = [{ beneficiary_name: this.prePopulated.spouse.full_name, percentage: 100, substitution_beneficiary: '' }];
    }

    if (existing.length === 0) {
      existing = [{ beneficiary_name: '', percentage: 100, substitution_beneficiary: '' }];
    }

    return { beneficiaries: existing };
  },

  computed: {
    totalPercentage() {
      return this.beneficiaries.reduce((sum, b) => sum + (Number(b.percentage) || 0), 0);
    },

    totalClass() {
      if (this.totalPercentage === 100) return 'bg-spring-50 border border-spring-200';
      if (this.totalPercentage > 100) return 'bg-raspberry-50 border border-raspberry-200';
      return 'bg-violet-50 border border-violet-200';
    },

    suggestedChildren() {
      return (this.prePopulated?.children || []).filter(c => {
        return !this.beneficiaries.some(b =>
          b.beneficiary_name.toLowerCase() === c.full_name.toLowerCase()
        );
      });
    },
  },

  methods: {
    addBeneficiary() {
      this.beneficiaries.push({ beneficiary_name: '', percentage: 0, substitution_beneficiary: '' });
    },

    addChildBeneficiary(child) {
      this.beneficiaries.push({ beneficiary_name: child.full_name, percentage: 0, substitution_beneficiary: '' });
    },

    handleNext() {
      const cleaned = this.beneficiaries.filter(b => b.beneficiary_name.trim());
      this.$emit('next', { residuary_estate: cleaned });
    },
  },
};
</script>
