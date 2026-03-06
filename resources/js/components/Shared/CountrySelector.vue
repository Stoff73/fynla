<template>
  <div class="country-selector">
    <label
      v-if="label"
      :for="inputId"
      class="block text-body-sm font-medium text-neutral-500 mb-1"
    >
      {{ label }}
    </label>

    <div class="relative">
      <!-- Search Input -->
      <input
        :id="inputId"
        v-model="searchQuery"
        type="text"
        :placeholder="placeholder"
        :disabled="disabled"
        :required="required"
        class="input-field pr-10"
        :class="{ 'cursor-not-allowed bg-savannah-100': disabled }"
        @focus="showDropdown = true"
        @blur="handleBlur"
        @input="handleInput"
      />

      <!-- Dropdown Icon -->
      <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
        <svg
          class="w-5 h-5 text-horizon-400"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M19 9l-7 7-7-7"
          />
        </svg>
      </div>

      <!-- Dropdown List -->
      <div
        v-show="showDropdown && filteredCountries.length > 0"
        class="absolute z-10 w-full mt-1 bg-white border border-horizon-300 rounded-md shadow-lg max-h-60 overflow-auto scrollbar-thin"
      >
        <ul class="py-1">
          <li
            v-for="country in filteredCountries"
            :key="country"
            class="px-4 py-2 hover:bg-raspberry-50 cursor-pointer transition-colors"
            :class="{ 'bg-raspberry-100': country === modelValue }"
            @mousedown.prevent="selectCountry(country)"
          >
            {{ country }}
          </li>
        </ul>
      </div>

      <!-- No Results -->
      <div
        v-show="showDropdown && searchQuery && filteredCountries.length === 0"
        class="absolute z-10 w-full mt-1 bg-white border border-horizon-300 rounded-md shadow-lg p-4"
      >
        <p class="text-body-sm text-neutral-500 text-center">
          No countries found matching "{{ searchQuery }}"
        </p>
      </div>
    </div>

    <!-- Selected Country Display (when not focused) -->
    <p v-if="modelValue && !showDropdown" class="mt-1 text-body-sm text-neutral-500">
      Selected: <span class="font-medium">{{ modelValue }}</span>
    </p>
  </div>
</template>

<script>
export default {
  name: 'CountrySelector',

  props: {
    modelValue: {
      type: String,
      default: '',
    },
    label: {
      type: String,
      default: '',
    },
    placeholder: {
      type: String,
      default: 'Search for a country...',
    },
    required: {
      type: Boolean,
      default: false,
    },
    disabled: {
      type: Boolean,
      default: false,
    },
    defaultCountry: {
      type: String,
      default: 'England',
    },
  },

  emits: ['update:modelValue'],

  data() {
    return {
      uniqueId: `country-selector-${Math.random().toString(36).substr(2, 9)}`,
      searchQuery: this.modelValue || '',
      showDropdown: false,
      blurTimeout: null,
      countries: [
        // Top 5 priority countries
        'England',
        'Scotland',
        'Wales',
        'Northern Ireland',
        'South Africa',
        // Rest alphabetically
        'Afghanistan',
        'Albania',
        'Algeria',
        'Andorra',
        'Angola',
        'Antigua and Barbuda',
        'Argentina',
        'Armenia',
        'Australia',
        'Austria',
        'Azerbaijan',
        'Bahamas',
        'Bahrain',
        'Bangladesh',
        'Barbados',
        'Belarus',
        'Belgium',
        'Belize',
        'Benin',
        'Bhutan',
        'Bolivia',
        'Bosnia and Herzegovina',
        'Botswana',
        'Brazil',
        'Brunei',
        'Bulgaria',
        'Burkina Faso',
        'Burundi',
        'Cambodia',
        'Cameroon',
        'Canada',
        'Cape Verde',
        'Central African Republic',
        'Chad',
        'Chile',
        'China',
        'Colombia',
        'Comoros',
        'Congo',
        'Costa Rica',
        'Croatia',
        'Cuba',
        'Cyprus',
        'Czech Republic',
        'Denmark',
        'Djibouti',
        'Dominica',
        'Dominican Republic',
        'East Timor',
        'Ecuador',
        'Egypt',
        'El Salvador',
        'Equatorial Guinea',
        'Eritrea',
        'Estonia',
        'Eswatini',
        'Ethiopia',
        'Fiji',
        'Finland',
        'France',
        'Gabon',
        'Gambia',
        'Georgia',
        'Germany',
        'Ghana',
        'Greece',
        'Grenada',
        'Guatemala',
        'Guinea',
        'Guinea-Bissau',
        'Guyana',
        'Haiti',
        'Honduras',
        'Hungary',
        'Iceland',
        'India',
        'Indonesia',
        'Iran',
        'Iraq',
        'Ireland',
        'Israel',
        'Italy',
        'Ivory Coast',
        'Jamaica',
        'Japan',
        'Jordan',
        'Kazakhstan',
        'Kenya',
        'Kiribati',
        'Kuwait',
        'Kyrgyzstan',
        'Laos',
        'Latvia',
        'Lebanon',
        'Lesotho',
        'Liberia',
        'Libya',
        'Liechtenstein',
        'Lithuania',
        'Luxembourg',
        'Madagascar',
        'Malawi',
        'Malaysia',
        'Maldives',
        'Mali',
        'Malta',
        'Marshall Islands',
        'Mauritania',
        'Mauritius',
        'Mexico',
        'Micronesia',
        'Moldova',
        'Monaco',
        'Mongolia',
        'Montenegro',
        'Morocco',
        'Mozambique',
        'Myanmar',
        'Namibia',
        'Nauru',
        'Nepal',
        'Netherlands',
        'New Zealand',
        'Nicaragua',
        'Niger',
        'Nigeria',
        'North Korea',
        'North Macedonia',
        'Norway',
        'Oman',
        'Pakistan',
        'Palau',
        'Palestine',
        'Panama',
        'Papua New Guinea',
        'Paraguay',
        'Peru',
        'Philippines',
        'Poland',
        'Portugal',
        'Qatar',
        'Romania',
        'Russia',
        'Rwanda',
        'Saint Kitts and Nevis',
        'Saint Lucia',
        'Saint Vincent and the Grenadines',
        'Samoa',
        'San Marino',
        'Sao Tome and Principe',
        'Saudi Arabia',
        'Senegal',
        'Serbia',
        'Seychelles',
        'Sierra Leone',
        'Singapore',
        'Slovakia',
        'Slovenia',
        'Solomon Islands',
        'Somalia',
        'South Korea',
        'South Sudan',
        'Spain',
        'Sri Lanka',
        'Sudan',
        'Suriname',
        'Sweden',
        'Switzerland',
        'Syria',
        'Taiwan',
        'Tajikistan',
        'Tanzania',
        'Thailand',
        'Togo',
        'Tonga',
        'Trinidad and Tobago',
        'Tunisia',
        'Turkey',
        'Turkmenistan',
        'Tuvalu',
        'Uganda',
        'Ukraine',
        'United Arab Emirates',
        'United States',
        'Uruguay',
        'Uzbekistan',
        'Vanuatu',
        'Vatican City',
        'Venezuela',
        'Vietnam',
        'Yemen',
        'Zambia',
        'Zimbabwe',
      ],
    };
  },

  computed: {
    inputId() {
      return this.uniqueId;
    },

    filteredCountries() {
      if (!this.searchQuery || this.searchQuery === this.modelValue) {
        return this.countries;
      }

      const query = this.searchQuery.toLowerCase();
      return this.countries.filter(country =>
        country.toLowerCase().includes(query)
      );
    },
  },

  watch: {
    modelValue(newValue) {
      if (newValue && newValue !== this.searchQuery) {
        this.searchQuery = newValue;
      }
    },
  },

  beforeUnmount() {
    if (this.blurTimeout) clearTimeout(this.blurTimeout);
  },

  mounted() {
    // If no value is set and we have a default, use it
    if (!this.modelValue && this.defaultCountry) {
      this.selectCountry(this.defaultCountry);
    }
  },

  methods: {
    handleInput() {
      // Clear selection if user is typing
      if (this.searchQuery !== this.modelValue) {
        this.$emit('update:modelValue', '');
      }
    },

    selectCountry(country) {
      this.searchQuery = country;
      this.$emit('update:modelValue', country);
      this.showDropdown = false;
    },

    handleBlur() {
      // Small delay to allow click events on dropdown items
      if (this.blurTimeout) clearTimeout(this.blurTimeout);
      this.blurTimeout = setTimeout(() => {
        this.showDropdown = false;

        // If search query doesn't match a country, revert to current value
        if (!this.countries.includes(this.searchQuery)) {
          this.searchQuery = this.modelValue || '';
        }
      }, 200);
    },
  },
};
</script>

<style scoped>
/* Uses global .scrollbar-thin class — applied via template */
</style>
