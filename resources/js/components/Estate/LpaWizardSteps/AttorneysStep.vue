<template>
  <div class="attorneys-step">
    <h3 class="text-lg font-bold text-horizon-500 mb-1">{{ title }}</h3>
    <p class="text-sm text-neutral-500 mb-6">{{ description }}</p>

    <!-- Existing attorneys -->
    <div v-for="(attorney, index) in filteredAttorneys" :key="index" class="bg-eggshell-500 rounded-lg p-4 mb-3">
      <div class="flex items-start justify-between mb-3">
        <h4 class="text-sm font-bold text-horizon-500">{{ title.replace('Attorneys', 'Attorney') }} {{ index + 1 }}</h4>
        <button
          class="text-xs text-raspberry-500 hover:text-raspberry-600"
          @click="removeAttorney(index)"
        >
          Remove
        </button>
      </div>

      <div class="space-y-3">
        <div>
          <label class="block text-xs font-medium text-horizon-500 mb-1">Full Name</label>
          <input
            type="text"
            :value="attorney.full_name"
            @input="updateAttorney(index, 'full_name', $event.target.value)"
            class="w-full border border-light-gray rounded-lg px-3 py-2 text-sm text-horizon-500 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500"
            placeholder="Full legal name"
          />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-horizon-500 mb-1">Date of Birth</label>
            <input
              type="date"
              :value="attorney.date_of_birth"
              @input="updateAttorney(index, 'date_of_birth', $event.target.value)"
              class="w-full border border-light-gray rounded-lg px-3 py-2 text-sm text-horizon-500 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500"
            />
          </div>
          <div>
            <label class="block text-xs font-medium text-horizon-500 mb-1">Relationship to Donor</label>
            <input
              type="text"
              :value="attorney.relationship_to_donor"
              @input="updateAttorney(index, 'relationship_to_donor', $event.target.value)"
              class="w-full border border-light-gray rounded-lg px-3 py-2 text-sm text-horizon-500 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500"
              placeholder="e.g. Spouse, Son, Daughter, Friend"
            />
          </div>
        </div>

        <AddressFieldGroup
          :model-value="{
            address_line_1: attorney.address_line_1,
            address_line_2: attorney.address_line_2,
            address_city: attorney.address_city,
            address_county: attorney.address_county,
            address_postcode: attorney.address_postcode,
          }"
          @update:model-value="updateAttorneyAddress(index, $event)"
        />
      </div>
    </div>

    <!-- Add button -->
    <button
      class="w-full border-2 border-dashed border-light-gray rounded-lg py-3 text-sm font-medium text-neutral-500 hover:border-violet-500 hover:text-violet-500 transition-colors"
      @click="addAttorney"
    >
      + Add {{ filteredAttorneys.length === 0 ? 'an' : 'another' }} {{ attorneyType === 'primary' ? 'attorney' : 'replacement attorney' }}
    </button>
  </div>
</template>

<script>
import AddressFieldGroup from '../AddressFieldGroup.vue';

export default {
  name: 'AttorneysStep',

  components: { AddressFieldGroup },

  props: {
    modelValue: { type: Array, default: () => [] },
    errors: { type: Object, default: () => ({}) },
    attorneyType: { type: String, default: 'primary' },
    title: { type: String, default: 'Primary Attorneys' },
    description: { type: String, default: '' },
  },

  emits: ['update:modelValue'],

  computed: {
    filteredAttorneys() {
      return this.modelValue.filter(a => a.attorney_type === this.attorneyType);
    },
  },

  methods: {
    addAttorney() {
      const updated = [...this.modelValue, {
        attorney_type: this.attorneyType,
        full_name: '',
        date_of_birth: '',
        address_line_1: '',
        address_line_2: '',
        address_city: '',
        address_county: '',
        address_postcode: '',
        relationship_to_donor: '',
      }];
      this.$emit('update:modelValue', updated);
    },

    removeAttorney(filteredIndex) {
      const attorneys = [...this.modelValue];
      let count = 0;
      for (let i = 0; i < attorneys.length; i++) {
        if (attorneys[i].attorney_type === this.attorneyType) {
          if (count === filteredIndex) {
            attorneys.splice(i, 1);
            break;
          }
          count++;
        }
      }
      this.$emit('update:modelValue', attorneys);
    },

    updateAttorney(filteredIndex, field, value) {
      const attorneys = [...this.modelValue];
      let count = 0;
      for (let i = 0; i < attorneys.length; i++) {
        if (attorneys[i].attorney_type === this.attorneyType) {
          if (count === filteredIndex) {
            attorneys[i] = { ...attorneys[i], [field]: value };
            break;
          }
          count++;
        }
      }
      this.$emit('update:modelValue', attorneys);
    },

    updateAttorneyAddress(filteredIndex, address) {
      const attorneys = [...this.modelValue];
      let count = 0;
      for (let i = 0; i < attorneys.length; i++) {
        if (attorneys[i].attorney_type === this.attorneyType) {
          if (count === filteredIndex) {
            attorneys[i] = {
              ...attorneys[i],
              address_line_1: address.address_line_1,
              address_line_2: address.address_line_2,
              address_city: address.address_city,
              address_county: address.address_county,
              address_postcode: address.address_postcode,
            };
            break;
          }
          count++;
        }
      }
      this.$emit('update:modelValue', attorneys);
    },
  },
};
</script>
