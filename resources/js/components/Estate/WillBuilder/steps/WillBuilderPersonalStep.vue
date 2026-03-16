<template>
  <div class="bg-white rounded-lg shadow-sm border border-light-gray p-6">
    <h2 class="text-h3 font-bold text-horizon-500 mb-2">Personal Details</h2>
    <p class="text-sm text-neutral-500 mb-6">
      Confirm your details as they should appear on your will. These have been pre-populated from your profile.
    </p>

    <div class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-horizon-500 mb-1">Full Legal Name</label>
        <input
          v-model="fullName"
          type="text"
          class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:ring-violet-500 focus:border-violet-500"
          placeholder="e.g. James Andrew Carter"
        />
        <p class="text-xs text-neutral-500 mt-1">Your name exactly as it appears on official documents</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-horizon-500 mb-1">Full Address</label>
        <textarea
          v-model="address"
          rows="2"
          class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:ring-violet-500 focus:border-violet-500"
          placeholder="e.g. 42 Maple Drive, Guildford, Surrey, GU1 3AA"
        ></textarea>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-horizon-500 mb-1">Date of Birth</label>
          <input
            v-model="dob"
            type="date"
            class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:ring-violet-500 focus:border-violet-500"
          />
          <p v-if="ageWarning" class="text-xs text-raspberry-600 mt-1">{{ ageWarning }}</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-horizon-500 mb-1">Occupation</label>
          <input
            v-model="occupation"
            type="text"
            class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:ring-violet-500 focus:border-violet-500"
            placeholder="e.g. Software Engineer"
          />
        </div>
      </div>
    </div>

    <!-- Spouse Details (Mirror Will) -->
    <div v-if="formData.will_type === 'mirror' && prePopulated?.spouse" class="mt-6 pt-6 border-t border-light-gray">
      <h3 class="text-base font-semibold text-horizon-500 mb-4">Spouse's Details</h3>
      <div class="bg-savannah-100 rounded-lg p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
          <div>
            <span class="text-neutral-500">Name:</span>
            <span class="ml-2 text-horizon-500 font-medium">{{ prePopulated.spouse.full_name }}</span>
          </div>
          <div v-if="prePopulated.spouse.date_of_birth">
            <span class="text-neutral-500">Date of Birth:</span>
            <span class="ml-2 text-horizon-500 font-medium">{{ formatDate(prePopulated.spouse.date_of_birth) }}</span>
          </div>
        </div>
        <p class="text-xs text-neutral-500 mt-2">Your spouse's mirror will be generated automatically from the same details.</p>
      </div>
    </div>

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
  name: 'WillBuilderPersonalStep',

  props: {
    formData: { type: Object, required: true },
    prePopulated: { type: Object, default: null },
  },

  emits: ['next', 'back', 'update'],

  data() {
    return {
      fullName: this.formData.testator_full_name || '',
      address: this.formData.testator_address || '',
      dob: this.formData.testator_date_of_birth || '',
      occupation: this.formData.testator_occupation || '',
    };
  },

  computed: {
    ageWarning() {
      if (!this.dob) return null;
      const birthDate = new Date(this.dob);
      const today = new Date();
      let age = today.getFullYear() - birthDate.getFullYear();
      const monthDiff = today.getMonth() - birthDate.getMonth();
      if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
        age--;
      }
      if (age < 18) {
        return 'You must be 18 or older to create a valid will in England and Wales.';
      }
      return null;
    },

    canProceed() {
      return this.fullName.trim().length > 0 && !this.ageWarning;
    },
  },

  methods: {
    formatDate(dateStr) {
      if (!dateStr) return '';
      const d = new Date(dateStr);
      return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
    },

    handleNext() {
      if (!this.canProceed) return;
      this.$emit('next', {
        testator_full_name: this.fullName.trim(),
        testator_address: this.address.trim(),
        testator_date_of_birth: this.dob,
        testator_occupation: this.occupation.trim(),
      });
    },
  },
};
</script>
