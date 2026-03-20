<template>
  <div class="bg-white rounded-lg shadow-sm border border-light-gray p-6">
    <h2 class="text-h3 font-bold text-horizon-500 mb-2">Specific Gifts</h2>
    <p class="text-sm text-neutral-500 mb-6">
      Leave specific cash amounts or named items to particular people. These are distributed before the residuary estate. This step is optional.
    </p>

    <!-- Gifts List -->
    <div v-if="gifts.length > 0" class="space-y-3 mb-4">
      <div
        v-for="(gift, index) in gifts"
        :key="index"
        class="border border-light-gray rounded-lg p-4"
      >
        <div class="flex justify-between items-start mb-3">
          <h3 class="text-sm font-semibold text-horizon-500">Gift {{ index + 1 }}</h3>
          <button @click="gifts.splice(index, 1)" class="text-xs text-raspberry-500 hover:text-raspberry-700">Remove</button>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-neutral-500 mb-1">Beneficiary Name</label>
            <input
              v-model="gift.beneficiary_name"
              type="text"
              class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:ring-violet-500 focus:border-violet-500 text-sm"
              placeholder="e.g. John Smith"
            />
          </div>
          <div>
            <label class="block text-xs font-medium text-neutral-500 mb-1">Type</label>
            <select
              v-model="gift.type"
              class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:ring-violet-500 focus:border-violet-500 text-sm"
            >
              <option value="cash">Cash Amount</option>
              <option value="item">Specific Item</option>
            </select>
          </div>
          <div v-if="gift.type === 'cash'">
            <label class="block text-xs font-medium text-neutral-500 mb-1">Amount (£)</label>
            <input
              v-model.number="gift.amount"
              type="number"
              min="0"
              class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:ring-violet-500 focus:border-violet-500 text-sm"
              placeholder="e.g. 5000"
            />
          </div>
          <div v-else>
            <label class="block text-xs font-medium text-neutral-500 mb-1">Item Description</label>
            <input
              v-model="gift.description"
              type="text"
              class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:ring-violet-500 focus:border-violet-500 text-sm"
              placeholder="e.g. My gold watch, My pearl necklace"
            />
          </div>
          <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-neutral-500 mb-1">Conditions (optional)</label>
            <input
              v-model="gift.conditions"
              type="text"
              class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:ring-violet-500 focus:border-violet-500 text-sm"
              placeholder="e.g. On their 21st birthday"
            />
          </div>
        </div>
      </div>
    </div>

    <button
      @click="addGift"
      class="text-sm text-raspberry-500 hover:text-raspberry-700 font-medium"
    >
      + Add a Gift
    </button>

    <!-- Navigation -->
    <div class="flex justify-between pt-6 border-t border-light-gray mt-6">
      <button @click="$emit('back')" class="px-4 py-2 text-neutral-500 hover:text-horizon-500 transition-colors">
        Back
      </button>
      <div class="flex gap-3">
        <button
          v-if="gifts.length === 0"
          @click="handleSkip"
          class="px-4 py-2 text-neutral-500 hover:text-horizon-500 transition-colors text-sm"
        >
          Skip — No Specific Gifts
        </button>
        <button
          @click="handleNext"
          class="px-6 py-2.5 bg-raspberry-500 text-white rounded-lg font-medium hover:bg-raspberry-600 transition-colors"
        >
          Continue
        </button>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'WillBuilderGiftsStep',

  props: {
    formData: { type: Object, required: true },
  },

  emits: ['next', 'back'],

  data() {
    return {
      gifts: (this.formData.specific_gifts || []).map(g => ({ ...g })),
    };
  },

  methods: {
    addGift() {
      this.gifts.push({ beneficiary_name: '', type: 'cash', amount: null, description: '', conditions: '' });
    },

    handleSkip() {
      this.$emit('next', { specific_gifts: [] });
    },

    handleNext() {
      const cleaned = this.gifts.filter(g => g.beneficiary_name.trim());
      this.$emit('next', { specific_gifts: cleaned });
    },
  },
};
</script>
