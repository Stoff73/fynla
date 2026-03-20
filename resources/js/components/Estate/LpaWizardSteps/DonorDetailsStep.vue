<template>
  <div class="donor-details-step">
    <h3 class="text-lg font-bold text-horizon-500 mb-1">Donor Details</h3>
    <p class="text-sm text-neutral-500 mb-6">
      The donor is the person creating the Lasting Power of Attorney. These details have been pre-filled from your profile where available.
    </p>

    <div class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-horizon-500 mb-1">Full Legal Name</label>
        <input
          type="text"
          :value="modelValue.donor_full_name"
          @input="update('donor_full_name', $event.target.value)"
          class="w-full border border-light-gray rounded-lg px-3 py-2 text-sm text-horizon-500 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500"
          placeholder="Full name as it appears on official documents"
        />
        <p v-if="errors.donor_full_name" class="text-xs text-raspberry-500 mt-1">{{ errors.donor_full_name[0] }}</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-horizon-500 mb-1">Date of Birth</label>
        <input
          type="date"
          :value="modelValue.donor_date_of_birth"
          @input="update('donor_date_of_birth', $event.target.value)"
          class="w-full border border-light-gray rounded-lg px-3 py-2 text-sm text-horizon-500 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500"
        />
        <p v-if="errors.donor_date_of_birth" class="text-xs text-raspberry-500 mt-1">{{ errors.donor_date_of_birth[0] }}</p>
        <p class="text-xs text-neutral-500 mt-1">You must be 18 or older to create a Lasting Power of Attorney.</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-horizon-500 mb-2">Address</label>
        <AddressFieldGroup
          :model-value="{
            address_line_1: modelValue.donor_address_line_1,
            address_line_2: modelValue.donor_address_line_2,
            address_city: modelValue.donor_address_city,
            address_county: modelValue.donor_address_county,
            address_postcode: modelValue.donor_address_postcode,
          }"
          @update:model-value="updateAddress"
        />
      </div>
    </div>
  </div>
</template>

<script>
import AddressFieldGroup from '../AddressFieldGroup.vue';

export default {
  name: 'DonorDetailsStep',

  components: { AddressFieldGroup },

  props: {
    modelValue: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
  },

  emits: ['update:modelValue'],

  methods: {
    update(field, value) {
      this.$emit('update:modelValue', { ...this.modelValue, [field]: value });
    },
    updateAddress(address) {
      this.$emit('update:modelValue', {
        ...this.modelValue,
        donor_address_line_1: address.address_line_1,
        donor_address_line_2: address.address_line_2,
        donor_address_city: address.address_city,
        donor_address_county: address.address_county,
        donor_address_postcode: address.address_postcode,
      });
    },
  },
};
</script>
