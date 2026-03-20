<template>
  <div class="notification-persons-step">
    <h3 class="text-lg font-bold text-horizon-500 mb-1">People to Notify</h3>
    <p class="text-sm text-neutral-500 mb-6">
      You can choose up to 5 people to be notified when your Lasting Power of Attorney is registered with the Office of the Public Guardian. These people can raise objections if they believe there is a problem. This step is optional.
    </p>

    <!-- Existing persons -->
    <div v-for="(person, index) in modelValue" :key="index" class="bg-eggshell-500 rounded-lg p-4 mb-3">
      <div class="flex items-start justify-between mb-3">
        <h4 class="text-sm font-bold text-horizon-500">Person {{ index + 1 }}</h4>
        <button
          class="text-xs text-raspberry-500 hover:text-raspberry-600"
          @click="removePerson(index)"
        >
          Remove
        </button>
      </div>

      <div class="space-y-3">
        <div>
          <label class="block text-xs font-medium text-horizon-500 mb-1">Full Name</label>
          <input
            type="text"
            :value="person.full_name"
            @input="updatePerson(index, 'full_name', $event.target.value)"
            class="w-full border border-light-gray rounded-lg px-3 py-2 text-sm text-horizon-500 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500"
            placeholder="Full name"
          />
        </div>

        <AddressFieldGroup
          :model-value="{
            address_line_1: person.address_line_1,
            address_line_2: person.address_line_2,
            address_city: person.address_city,
            address_county: person.address_county,
            address_postcode: person.address_postcode,
          }"
          @update:model-value="updatePersonAddress(index, $event)"
        />
      </div>
    </div>

    <!-- Add button -->
    <button
      v-if="modelValue.length < 5"
      class="w-full border-2 border-dashed border-light-gray rounded-lg py-3 text-sm font-medium text-neutral-500 hover:border-violet-500 hover:text-violet-500 transition-colors"
      @click="addPerson"
    >
      + Add {{ modelValue.length === 0 ? 'a person' : 'another person' }} to notify ({{ modelValue.length }}/5)
    </button>
    <p v-else class="text-xs text-neutral-500 text-center mt-2">Maximum of 5 people to notify has been reached.</p>
  </div>
</template>

<script>
import AddressFieldGroup from '../AddressFieldGroup.vue';

export default {
  name: 'NotificationPersonsStep',

  components: { AddressFieldGroup },

  props: {
    modelValue: { type: Array, default: () => [] },
    errors: { type: Object, default: () => ({}) },
  },

  emits: ['update:modelValue'],

  methods: {
    addPerson() {
      if (this.modelValue.length >= 5) return;
      this.$emit('update:modelValue', [...this.modelValue, {
        full_name: '',
        address_line_1: '',
        address_line_2: '',
        address_city: '',
        address_county: '',
        address_postcode: '',
      }]);
    },

    removePerson(index) {
      const updated = [...this.modelValue];
      updated.splice(index, 1);
      this.$emit('update:modelValue', updated);
    },

    updatePerson(index, field, value) {
      const updated = [...this.modelValue];
      updated[index] = { ...updated[index], [field]: value };
      this.$emit('update:modelValue', updated);
    },

    updatePersonAddress(index, address) {
      const updated = [...this.modelValue];
      updated[index] = {
        ...updated[index],
        address_line_1: address.address_line_1,
        address_line_2: address.address_line_2,
        address_city: address.address_city,
        address_county: address.address_county,
        address_postcode: address.address_postcode,
      };
      this.$emit('update:modelValue', updated);
    },
  },
};
</script>
