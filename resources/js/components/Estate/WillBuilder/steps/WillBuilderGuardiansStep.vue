<template>
  <div class="bg-white rounded-lg shadow-sm border border-light-gray p-6">
    <h2 class="text-h3 font-bold text-horizon-500 mb-2">Appoint Guardians</h2>
    <p class="text-sm text-neutral-500 mb-4">
      As you have children under 18, you can appoint a guardian to care for them if both parents pass away.
    </p>

    <!-- Children List -->
    <div class="bg-savannah-100 rounded-lg p-4 mb-6">
      <h3 class="text-xs font-semibold text-neutral-500 uppercase tracking-wider mb-2">Your Minor Children</h3>
      <div class="space-y-1">
        <div v-for="child in minorChildren" :key="child.full_name" class="text-sm text-horizon-500">
          {{ child.full_name }}
          <span v-if="child.date_of_birth" class="text-neutral-500">(born {{ formatDate(child.date_of_birth) }})</span>
        </div>
      </div>
    </div>

    <div class="bg-violet-50 border border-violet-200 rounded-lg p-3 mb-6">
      <p class="text-xs text-violet-700">
        <strong>Legal note:</strong> A guardian appointment only takes effect if both parents are deceased. If the other parent is alive, they will automatically have parental responsibility.
      </p>
    </div>

    <!-- Guardians -->
    <div class="space-y-4">
      <div
        v-for="(guardian, index) in guardians"
        :key="index"
        class="border border-light-gray rounded-lg p-4"
      >
        <div class="flex justify-between items-center mb-3">
          <h3 class="text-sm font-semibold text-horizon-500">
            {{ index === 0 ? 'Primary Guardian' : 'Backup Guardian' }}
          </h3>
          <button
            v-if="index > 0"
            @click="guardians.splice(index, 1)"
            class="text-xs text-raspberry-500 hover:text-raspberry-700"
          >
            Remove
          </button>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-neutral-500 mb-1">Full Name</label>
            <input
              v-model="guardian.name"
              type="text"
              class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:ring-violet-500 focus:border-violet-500 text-sm"
              placeholder="e.g. Sarah Johnson"
            />
          </div>
          <div>
            <label class="block text-xs font-medium text-neutral-500 mb-1">Relationship</label>
            <input
              v-model="guardian.relationship"
              type="text"
              class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:ring-violet-500 focus:border-violet-500 text-sm"
              placeholder="e.g. Sister, Aunt"
            />
          </div>
          <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-neutral-500 mb-1">Address</label>
            <input
              v-model="guardian.address"
              type="text"
              class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:ring-violet-500 focus:border-violet-500 text-sm"
              placeholder="Full postal address"
            />
          </div>
        </div>
      </div>
    </div>

    <button
      v-if="guardians.length < 2"
      @click="guardians.push({ name: '', address: '', relationship: '' })"
      class="mt-4 text-sm text-raspberry-500 hover:text-raspberry-700 font-medium"
    >
      + Add Backup Guardian
    </button>

    <!-- Navigation -->
    <div class="flex justify-between pt-6 border-t border-light-gray mt-6">
      <button @click="$emit('back')" class="px-4 py-2 text-neutral-500 hover:text-horizon-500 transition-colors">
        Back
      </button>
      <div class="flex gap-3">
        <button
          @click="handleSkip"
          class="px-4 py-2 text-neutral-500 hover:text-horizon-500 transition-colors text-sm"
        >
          Skip This Step
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
  name: 'WillBuilderGuardiansStep',

  props: {
    formData: { type: Object, required: true },
    prePopulated: { type: Object, default: null },
  },

  emits: ['next', 'back'],

  data() {
    const existing = this.formData.guardians?.length
      ? this.formData.guardians.map(g => ({ ...g }))
      : [{ name: '', address: '', relationship: '' }];

    return { guardians: existing };
  },

  computed: {
    minorChildren() {
      return (this.prePopulated?.children || []).filter(c => c.is_minor);
    },
  },

  methods: {
    formatDate(dateStr) {
      if (!dateStr) return '';
      return new Date(dateStr).toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
    },

    handleSkip() {
      this.$emit('next', { guardians: [] });
    },

    handleNext() {
      const cleaned = this.guardians.filter(g => g.name.trim());
      this.$emit('next', { guardians: cleaned });
    },
  },
};
</script>
