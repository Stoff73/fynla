<template>
  <div class="certificate-provider-step">
    <h3 class="text-lg font-bold text-horizon-500 mb-1">Certificate Provider</h3>
    <p class="text-sm text-neutral-500 mb-6">
      A certificate provider is someone who confirms that you understand the Lasting Power of Attorney and that nobody is pressuring you to create it. They must have known you for at least 2 years.
    </p>

    <div class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-horizon-500 mb-1">Full Name</label>
        <input
          type="text"
          :value="modelValue.certificate_provider_name"
          @input="update('certificate_provider_name', $event.target.value)"
          class="w-full border border-light-gray rounded-lg px-3 py-2 text-sm text-horizon-500 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500"
          placeholder="Certificate provider's full name"
        />
        <p v-if="errors.certificate_provider_name" class="text-xs text-raspberry-500 mt-1">{{ errors.certificate_provider_name[0] }}</p>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-horizon-500 mb-1">Relationship to You</label>
          <input
            type="text"
            :value="modelValue.certificate_provider_relationship"
            @input="update('certificate_provider_relationship', $event.target.value)"
            class="w-full border border-light-gray rounded-lg px-3 py-2 text-sm text-horizon-500 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500"
            placeholder="e.g. Family friend, Solicitor, Doctor"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-horizon-500 mb-1">Years Known</label>
          <input
            type="number"
            :value="modelValue.certificate_provider_known_years"
            @input="update('certificate_provider_known_years', $event.target.value ? parseInt($event.target.value) : null)"
            class="w-full border border-light-gray rounded-lg px-3 py-2 text-sm text-horizon-500 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500"
            placeholder="Minimum 2 years"
            min="0"
            max="100"
          />
          <p v-if="modelValue.certificate_provider_known_years !== null && modelValue.certificate_provider_known_years < 2" class="text-xs text-raspberry-500 mt-1">
            The certificate provider must have known you for at least 2 years.
          </p>
          <p v-if="errors.certificate_provider_known_years" class="text-xs text-raspberry-500 mt-1">{{ errors.certificate_provider_known_years[0] }}</p>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-horizon-500 mb-1">Address</label>
        <textarea
          :value="modelValue.certificate_provider_address"
          @input="update('certificate_provider_address', $event.target.value)"
          class="w-full border border-light-gray rounded-lg px-3 py-2 text-sm text-horizon-500 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500"
          rows="2"
          placeholder="Certificate provider's address"
        ></textarea>
      </div>

      <div>
        <label class="block text-sm font-medium text-horizon-500 mb-1">Professional Details (if applicable)</label>
        <textarea
          :value="modelValue.certificate_provider_professional_details"
          @input="update('certificate_provider_professional_details', $event.target.value)"
          class="w-full border border-light-gray rounded-lg px-3 py-2 text-sm text-horizon-500 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500"
          rows="2"
          placeholder="e.g. Solicitor at Smith & Jones, registered with the Solicitors Regulation Authority"
        ></textarea>
        <p class="text-xs text-neutral-500 mt-1">If your certificate provider is a professional (solicitor, doctor, etc.), include their professional details here.</p>
      </div>
    </div>

    <!-- Info box -->
    <div class="bg-savannah-100 rounded-lg p-4 mt-5 text-xs text-neutral-500">
      <p class="font-medium text-horizon-500 mb-1">Who can be a certificate provider?</p>
      <p>
        Someone who has known you well for at least 2 years, or a professional such as a solicitor, doctor, or registered social worker. They cannot be one of your attorneys, a family member of an attorney, or a business partner.
      </p>
    </div>
  </div>
</template>

<script>
export default {
  name: 'CertificateProviderStep',

  props: {
    modelValue: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
  },

  emits: ['update:modelValue'],

  methods: {
    update(field, value) {
      this.$emit('update:modelValue', { ...this.modelValue, [field]: value });
    },
  },
};
</script>
