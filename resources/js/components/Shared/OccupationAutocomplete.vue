<template>
  <div class="relative">
    <input
      :id="id"
      ref="inputRef"
      v-model="searchQuery"
      type="text"
      :class="inputClass"
      :placeholder="placeholder"
      autocomplete="off"
      @input="handleInput"
      @focus="handleFocus"
      @blur="handleBlur"
      @keydown.down.prevent="navigateDown"
      @keydown.up.prevent="navigateUp"
      @keydown.enter.prevent="selectHighlighted"
      @keydown.escape="closeDropdown"
    />

    <!-- Dropdown -->
    <div
      v-if="showDropdown && suggestions.length > 0"
      class="absolute z-50 w-full mt-1 bg-white border border-light-gray rounded-lg shadow-lg max-h-60 overflow-y-auto"
    >
      <button
        v-for="(suggestion, index) in suggestions"
        :key="suggestion.id"
        type="button"
        class="w-full px-4 py-2 text-left text-body-sm hover:bg-savannah-100 focus:bg-savannah-100 focus:outline-none"
        :class="{ 'bg-savannah-100': index === highlightedIndex }"
        @mousedown.prevent="selectSuggestion(suggestion)"
        @mouseover="highlightedIndex = index"
      >
        <span class="font-medium text-horizon-500">{{ suggestion.title }}</span>
        <span v-if="suggestion.soc_code" class="ml-2 text-neutral-500 text-body-xs">
          ({{ suggestion.soc_code }})
        </span>
      </button>
    </div>

    <!-- Loading indicator -->
    <div
      v-if="loading"
      class="absolute right-3 top-1/2 transform -translate-y-1/2"
    >
      <svg class="animate-spin h-4 w-4 text-horizon-400" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
      </svg>
    </div>

    <!-- Hint text -->
    <p v-if="showHint && !showDropdown" class="mt-1 text-body-xs text-neutral-500">
      Type at least 3 characters to search
    </p>
  </div>
</template>

<script>
import { ref, watch, computed, onBeforeUnmount } from 'vue';
import occupationService from '@/services/occupationService';

export default {
  name: 'OccupationAutocomplete',

  props: {
    modelValue: {
      type: String,
      default: '',
    },
    id: {
      type: String,
      default: 'occupation',
    },
    placeholder: {
      type: String,
      default: 'e.g., Software Engineer',
    },
    inputClass: {
      type: String,
      default: 'input-field',
    },
    showHint: {
      type: Boolean,
      default: false,
    },
  },

  emits: ['update:modelValue', 'select'],

  setup(props, { emit }) {
    const inputRef = ref(null);
    const searchQuery = ref(props.modelValue || '');
    const suggestions = ref([]);
    const loading = ref(false);
    const showDropdown = ref(false);
    const highlightedIndex = ref(-1);
    let debounceTimer = null;
    let blurTimeout = null;

    // Sync with v-model
    watch(() => props.modelValue, (newVal) => {
      if (newVal !== searchQuery.value) {
        searchQuery.value = newVal || '';
      }
    });

    const handleInput = () => {
      emit('update:modelValue', searchQuery.value);
      highlightedIndex.value = -1;

      // Debounce the search
      if (debounceTimer) {
        clearTimeout(debounceTimer);
      }

      if (searchQuery.value.length < 3) {
        suggestions.value = [];
        showDropdown.value = false;
        return;
      }

      debounceTimer = setTimeout(async () => {
        loading.value = true;
        try {
          suggestions.value = await occupationService.search(searchQuery.value);
          showDropdown.value = suggestions.value.length > 0;
        } catch (error) {
          suggestions.value = [];
        } finally {
          loading.value = false;
        }
      }, 300);
    };

    const handleFocus = () => {
      if (suggestions.value.length > 0) {
        showDropdown.value = true;
      }
    };

    const handleBlur = () => {
      // Delay to allow click on suggestion
      if (blurTimeout) clearTimeout(blurTimeout);
      blurTimeout = setTimeout(() => {
        showDropdown.value = false;
      }, 200);
    };

    const selectSuggestion = (suggestion) => {
      searchQuery.value = suggestion.title;
      emit('update:modelValue', suggestion.title);
      emit('select', suggestion);
      showDropdown.value = false;
      suggestions.value = [];
    };

    const navigateDown = () => {
      if (suggestions.value.length > 0) {
        highlightedIndex.value = Math.min(
          highlightedIndex.value + 1,
          suggestions.value.length - 1
        );
      }
    };

    const navigateUp = () => {
      if (suggestions.value.length > 0) {
        highlightedIndex.value = Math.max(highlightedIndex.value - 1, 0);
      }
    };

    const selectHighlighted = () => {
      if (highlightedIndex.value >= 0 && suggestions.value[highlightedIndex.value]) {
        selectSuggestion(suggestions.value[highlightedIndex.value]);
      }
    };

    onBeforeUnmount(() => {
      if (debounceTimer) clearTimeout(debounceTimer);
      if (blurTimeout) clearTimeout(blurTimeout);
    });

    const closeDropdown = () => {
      showDropdown.value = false;
    };

    return {
      inputRef,
      searchQuery,
      suggestions,
      loading,
      showDropdown,
      highlightedIndex,
      handleInput,
      handleFocus,
      handleBlur,
      selectSuggestion,
      navigateDown,
      navigateUp,
      selectHighlighted,
      closeDropdown,
    };
  },
};
</script>
