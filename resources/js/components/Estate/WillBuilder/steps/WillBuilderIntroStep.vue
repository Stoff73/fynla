<template>
  <div class="bg-white rounded-lg shadow-sm border border-light-gray p-6">
    <h2 class="text-h3 font-bold text-horizon-500 mb-2">Build Your Will</h2>
    <p class="text-sm text-neutral-500 mb-6">
      This guided tool will help you create a legally-structured will for England and Wales.
    </p>

    <!-- Legal Disclaimer -->
    <div class="bg-violet-50 border border-violet-200 rounded-lg p-5 mb-6">
      <h3 class="text-sm font-semibold text-violet-800 mb-2">Important Legal Notice</h3>
      <ul class="text-sm text-violet-700 space-y-2">
        <li>This tool does <strong>not</strong> provide legal advice. It generates a structured will document based on the information you provide.</li>
        <li>For complex estates (business interests, blended families, foreign property, or trusts), we strongly recommend having your will reviewed by a qualified solicitor.</li>
        <li>Your will is only legally valid once it has been properly signed and witnessed. This tool cannot verify witnessing.</li>
        <li>This tool is designed for <strong>England and Wales</strong> law only. Scotland and Northern Ireland have different requirements.</li>
      </ul>
      <label class="flex items-center mt-4 cursor-pointer">
        <input
          type="checkbox"
          v-model="disclaimerAccepted"
          class="form-checkbox text-raspberry-500 rounded"
        />
        <span class="ml-2 text-sm font-medium text-violet-800">
          I understand this tool does not provide legal advice
        </span>
      </label>
    </div>

    <!-- Domicile Confirmation -->
    <div class="mb-6">
      <label class="block text-sm font-medium text-horizon-500 mb-3">
        Where is your permanent home (domicile)?
      </label>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <button
          v-for="option in domicileOptions"
          :key="option.value"
          type="button"
          @click="domicile = option.value"
          class="p-3 rounded-lg border-2 text-left transition-all"
          :class="domicile === option.value
            ? 'border-raspberry-500 bg-raspberry-50'
            : 'border-light-gray hover:border-savannah-300'"
        >
          <span class="text-sm font-medium text-horizon-500">{{ option.label }}</span>
        </button>
      </div>
      <div v-if="domicile && domicile !== 'england_wales'" class="mt-3 bg-violet-50 border border-violet-200 rounded-lg p-3">
        <p class="text-sm text-violet-800">
          This will builder is designed for England and Wales law only. Different rules apply in {{ domicileLabel }}. We recommend consulting a solicitor in your jurisdiction.
        </p>
      </div>
    </div>

    <!-- Will Type -->
    <div class="mb-6">
      <label class="block text-sm font-medium text-horizon-500 mb-3">
        Type of will
      </label>

      <!--
        A married user is not offered a choice here (CLAUDE.md Rule 16 —
        CSJ direction W-0019). Rendering a lone button would still frame the
        step as a chooser, so the choice is replaced by a statement. All the
        wording comes from the server (WillTypePolicy) so the form, the API
        and Fyn cannot drift apart.
      -->
      <template v-if="isRefused">
        <p class="text-sm text-neutral-500 mb-3">{{ refusalLead }}</p>
        <div v-if="refusalDetail.length" class="bg-violet-50 border border-violet-200 rounded-lg p-4">
          <h3 class="text-sm font-semibold text-violet-800 mb-2">{{ willTypePolicy.refusal_heading }}</h3>
          <div class="text-sm text-violet-700 space-y-2">
            <p v-for="(paragraph, i) in refusalDetail" :key="i">{{ paragraph }}</p>
          </div>
        </div>
      </template>

      <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <button
          v-if="allowsSimple"
          type="button"
          @click="willType = 'simple'"
          class="p-4 rounded-lg border-2 text-left transition-all"
          :class="willType === 'simple'
            ? 'border-raspberry-500 bg-raspberry-50'
            : 'border-light-gray hover:border-savannah-300'"
        >
          <span class="block text-sm font-semibold text-horizon-500">Simple Will</span>
          <span class="block text-xs text-neutral-500 mt-1">A single will for you, distributing your estate as you wish.</span>
        </button>
        <button
          v-if="allowsMirror"
          type="button"
          @click="willType = 'mirror'"
          class="p-4 rounded-lg border-2 text-left transition-all"
          :class="willType === 'mirror'
            ? 'border-raspberry-500 bg-raspberry-50'
            : 'border-light-gray hover:border-savannah-300'"
        >
          <span class="block text-sm font-semibold text-horizon-500">Mirror Will</span>
          <span class="block text-xs text-neutral-500 mt-1">Two matching wills — one for you and one for your spouse. Each leaves to the other first, then to your chosen beneficiaries.</span>
        </button>
      </div>
    </div>

    <!-- Navigation -->
    <div class="flex justify-end pt-4 border-t border-light-gray">
      <button
        @click="handleNext"
        :disabled="!canProceed"
        class="px-6 py-2.5 bg-raspberry-500 text-white rounded-lg font-medium hover:bg-raspberry-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
      >
        Continue
      </button>
    </div>
  </div>
</template>

<script>
export default {
  name: 'WillBuilderIntroStep',

  props: {
    formData: { type: Object, required: true },
    prePopulated: { type: Object, default: null },
  },

  emits: ['next', 'update'],

  data() {
    const policy = this.prePopulated?.will_type_policy || null;
    const allowed = policy?.allowed_will_types || ['simple'];

    return {
      disclaimerAccepted: false,
      // Never default to 'simple' for a user who may not have one — before
      // W-0019 a married user who ignored this block proceeded with a
      // one-sided will having made no choice at all.
      willType: allowed.includes(this.formData.will_type)
        ? this.formData.will_type
        : (allowed[0] || null),
      domicile: this.formData.domicile_confirmed || null,
    };
  },

  computed: {
    willTypePolicy() {
      return this.prePopulated?.will_type_policy || {
        married: false,
        allowed_will_types: ['simple'],
        mirror_available: false,
        can_build: true,
        refusal_heading: '',
        refusal: null,
      };
    },

    isRefused() {
      return Array.isArray(this.willTypePolicy.refusal) && this.willTypePolicy.refusal.length > 0;
    },

    refusalLead() {
      return this.isRefused ? this.willTypePolicy.refusal[0] : '';
    },

    refusalDetail() {
      return this.isRefused ? this.willTypePolicy.refusal.slice(1) : [];
    },

    allowsSimple() {
      return this.willTypePolicy.allowed_will_types.includes('simple');
    },

    allowsMirror() {
      return this.willTypePolicy.allowed_will_types.includes('mirror');
    },

    canProceed() {
      return this.disclaimerAccepted
        && this.domicile
        && this.willType
        && this.willTypePolicy.can_build;
    },

    domicileOptions() {
      return [
        { value: 'england_wales', label: 'England or Wales' },
        { value: 'scotland', label: 'Scotland' },
        { value: 'northern_ireland', label: 'Northern Ireland' },
        { value: 'other', label: 'Outside the United Kingdom' },
      ];
    },

    domicileLabel() {
      const map = {
        scotland: 'Scotland',
        northern_ireland: 'Northern Ireland',
        other: 'your jurisdiction',
      };
      return map[this.domicile] || '';
    },
  },

  methods: {
    handleNext() {
      if (!this.canProceed) return;
      this.$emit('next', {
        will_type: this.willType,
        domicile_confirmed: this.domicile,
      });
    },
  },
};
</script>
