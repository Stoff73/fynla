<template>
  <div class="bg-white rounded-lg shadow-sm border border-light-gray p-6">
    <h2 class="text-h3 font-bold text-horizon-500 mb-2">Appoint Your Executors</h2>
    <p class="text-sm text-neutral-500 mb-4">
      An executor is the person responsible for carrying out the instructions in your will. They will manage your estate, pay any debts and taxes, and distribute your assets to your beneficiaries.
    </p>

    <div class="bg-savannah-100 rounded-lg p-4 mb-6">
      <p class="text-xs text-neutral-500">
        <strong>Tip:</strong> Choose someone you trust who is younger than you. We recommend appointing a backup executor in case your primary executor is unable to act. Executors should not be the spouse of a beneficiary.
      </p>
    </div>

    <!-- Executors List -->
    <div class="space-y-4">
      <div
        v-for="(executor, index) in executors"
        :key="index"
        class="border border-light-gray rounded-lg p-4"
      >
        <div class="flex justify-between items-center mb-3">
          <h3 class="text-sm font-semibold text-horizon-500">
            {{ index === 0 ? 'Primary Executor' : 'Backup Executor' }}
          </h3>
          <button
            v-if="index > 0"
            @click="removeExecutor(index)"
            class="text-xs text-raspberry-500 hover:text-raspberry-700"
          >
            Remove
          </button>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-neutral-500 mb-1">Full Name</label>
            <input
              v-model="executor.name"
              type="text"
              class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:ring-violet-500 focus:border-violet-500 text-sm"
              placeholder="e.g. John Smith"
            />
          </div>
          <div>
            <label class="block text-xs font-medium text-neutral-500 mb-1">Relationship</label>
            <input
              v-model="executor.relationship"
              type="text"
              class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:ring-violet-500 focus:border-violet-500 text-sm"
              placeholder="e.g. Brother, Friend, Solicitor"
            />
          </div>
          <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-neutral-500 mb-1">Address</label>
            <input
              v-model="executor.address"
              type="text"
              class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:ring-violet-500 focus:border-violet-500 text-sm"
              placeholder="Full postal address"
            />
          </div>
          <div>
            <label class="block text-xs font-medium text-neutral-500 mb-1">Phone Number</label>
            <input
              v-model="executor.phone"
              type="tel"
              class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:ring-violet-500 focus:border-violet-500 text-sm"
              placeholder="e.g. 07700 900000"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Add Backup Executor -->
    <button
      v-if="executors.length < 2"
      @click="addExecutor"
      class="mt-4 text-sm text-raspberry-500 hover:text-raspberry-700 font-medium"
    >
      + Add Backup Executor
    </button>

    <!-- Navigation -->
    <div class="flex justify-between pt-6 border-t border-light-gray mt-6">
      <button
        @click="$emit('back')"
        class="px-4 py-2 text-neutral-500 hover:text-horizon-500 transition-colors"
      >
        Back
      </button>
      <button
        @click="handleNext"
        :disabled="!canProceed"
        class="px-6 py-2.5 bg-raspberry-500 text-white rounded-lg font-medium hover:bg-raspberry-600 transition-colors disabled:opacity-50"
      >
        Continue
      </button>
    </div>
  </div>
</template>

<script>
export default {
  name: 'WillBuilderExecutorsStep',

  props: {
    formData: { type: Object, required: true },
    prePopulated: { type: Object, default: null },
  },

  emits: ['next', 'back'],

  data() {
    const existing = this.formData.executors?.length
      ? this.formData.executors.map(e => ({ ...e }))
      : [{ name: '', address: '', relationship: '', phone: '' }];

    return { executors: existing };
  },

  computed: {
    canProceed() {
      return this.executors.length > 0 && this.executors[0].name.trim().length > 0;
    },
  },

  methods: {
    addExecutor() {
      if (this.executors.length < 2) {
        this.executors.push({ name: '', address: '', relationship: '', phone: '' });
      }
    },

    removeExecutor(index) {
      this.executors.splice(index, 1);
    },

    handleNext() {
      if (!this.canProceed) return;
      const cleaned = this.executors.filter(e => e.name.trim());
      this.$emit('next', { executors: cleaned });
    },
  },
};
</script>
