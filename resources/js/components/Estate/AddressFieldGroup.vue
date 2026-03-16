<template>
  <div class="address-field-group space-y-3">
    <div>
      <label class="block text-sm font-medium text-horizon-500 mb-1">Address Line 1</label>
      <input
        type="text"
        :value="modelValue.address_line_1"
        @input="update('address_line_1', $event.target.value)"
        class="w-full border border-light-gray rounded-lg px-3 py-2 text-sm text-horizon-500 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500"
        :placeholder="placeholder || 'Street address'"
      />
    </div>
    <div>
      <label class="block text-sm font-medium text-horizon-500 mb-1">Address Line 2</label>
      <input
        type="text"
        :value="modelValue.address_line_2"
        @input="update('address_line_2', $event.target.value)"
        class="w-full border border-light-gray rounded-lg px-3 py-2 text-sm text-horizon-500 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500"
        placeholder="Flat, suite, etc. (optional)"
      />
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
      <div>
        <label class="block text-sm font-medium text-horizon-500 mb-1">City</label>
        <input
          type="text"
          :value="modelValue.address_city"
          @input="update('address_city', $event.target.value)"
          class="w-full border border-light-gray rounded-lg px-3 py-2 text-sm text-horizon-500 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500"
          placeholder="City"
        />
      </div>
      <div>
        <label class="block text-sm font-medium text-horizon-500 mb-1">County</label>
        <input
          type="text"
          :value="modelValue.address_county"
          @input="update('address_county', $event.target.value)"
          class="w-full border border-light-gray rounded-lg px-3 py-2 text-sm text-horizon-500 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500"
          placeholder="County"
        />
      </div>
      <div>
        <label class="block text-sm font-medium text-horizon-500 mb-1">Postcode</label>
        <input
          type="text"
          :value="modelValue.address_postcode"
          @input="update('address_postcode', $event.target.value)"
          class="w-full border border-light-gray rounded-lg px-3 py-2 text-sm text-horizon-500 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500"
          placeholder="Postcode"
          maxlength="10"
        />
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'AddressFieldGroup',

  props: {
    modelValue: {
      type: Object,
      default: () => ({
        address_line_1: '',
        address_line_2: '',
        address_city: '',
        address_county: '',
        address_postcode: '',
      }),
    },
    placeholder: {
      type: String,
      default: '',
    },
  },

  emits: ['update:modelValue'],

  methods: {
    update(field, value) {
      this.$emit('update:modelValue', {
        ...this.modelValue,
        [field]: value,
      });
    },
  },
};
</script>
