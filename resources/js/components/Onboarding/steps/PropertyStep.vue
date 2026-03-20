<template>
  <OnboardingStep
    title="Your Property"
    description="Add your home and any other properties you own"
    :can-go-back="true"
    :can-skip="true"
    :loading="loading"
    :error="error"
    :next-button-text="properties.length > 0 && !showForm ? 'Continue' : undefined"
    @next="handleNext"
    @back="handleBack"
    @skip="handleSkip"
  >
    <div class="space-y-6">
      <!-- Saved Properties List -->
      <div v-if="properties.length > 0 && !showForm" class="space-y-3">
        <h4 class="text-body font-medium text-horizon-500">Your Properties</h4>
        <div
          v-for="property in properties"
          :key="property.id"
          class="bg-eggshell-500 rounded-lg p-4"
        >
          <div class="flex items-start justify-between">
            <div>
              <div class="flex items-center gap-2 mb-1">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-raspberry-100 text-raspberry-700">
                  {{ formatPropertyType(property.property_type) }}
                </span>
                <span v-if="property.ownership_type === 'joint'" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-violet-100 text-violet-700">
                  Joint
                </span>
              </div>
              <p class="text-body font-medium text-horizon-500">
                {{ property.address_line_1 || 'Property' }}
              </p>
              <p v-if="property.city" class="text-body-sm text-neutral-500">
                {{ [property.city, property.postcode].filter(Boolean).join(', ') }}
              </p>
            </div>
            <div class="text-right">
              <p class="text-body font-semibold text-horizon-500">
                {{ formatCurrency(property.current_value) }}
              </p>
              <p v-if="property.mortgages && property.mortgages.length > 0" class="text-body-sm text-neutral-500">
                Mortgage: {{ formatCurrency(property.mortgages[0].outstanding_balance) }}
              </p>
            </div>
          </div>
        </div>

        <button
          type="button"
          class="flex items-center gap-2 text-body-sm font-medium text-raspberry-500 hover:text-raspberry-600 transition-colors"
          @click="showForm = true"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Add another property
        </button>
      </div>

      <!-- Property Form -->
      <div v-if="showForm || properties.length === 0">
        <PropertyForm
          context="onboarding"
          :user-address="userAddress"
          @save="handlePropertySaved"
          @close="handleFormClose"
        />
      </div>
    </div>
  </OnboardingStep>
</template>

<script>
import { ref, onMounted } from 'vue';
import { useStore } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import OnboardingStep from '../OnboardingStep.vue';
import PropertyForm from '@/components/NetWorth/Property/PropertyForm.vue';
import propertyService from '@/services/propertyService';

export default {
  name: 'PropertyStep',

  components: {
    OnboardingStep,
    PropertyForm,
  },

  mixins: [currencyMixin],

  emits: ['next', 'back', 'skip'],

  setup(props, { emit }) {
    const store = useStore();
    const properties = ref([]);
    const showForm = ref(true);
    const loading = ref(false);
    const error = ref(null);
    const userAddress = ref(null);

    const propertyTypeLabels = {
      main_residence: 'Main Residence',
      secondary_residence: 'Secondary Residence',
      buy_to_let: 'Buy to Let',
    };

    const formatPropertyType = (type) => {
      return propertyTypeLabels[type] || type;
    };

    const loadProperties = async () => {
      try {
        const data = await propertyService.getProperties();
        if (data?.data?.properties) {
          properties.value = data.data.properties;
        } else if (Array.isArray(data?.data)) {
          properties.value = data.data;
        }
        if (properties.value.length > 0) {
          showForm.value = false;
        }
      } catch {
        // No existing properties
      }
    };

    const handlePropertySaved = async (data) => {
      loading.value = true;
      error.value = null;

      try {
        const result = await propertyService.createProperty(data.property || data);

        if (data.mortgage && result?.data?.id) {
          await propertyService.createPropertyMortgage(result.data.id, data.mortgage);
        }

        await loadProperties();
        showForm.value = false;

        await store.dispatch('onboarding/saveStepData', {
          stepName: 'property',
          data: { properties_added: properties.value.length },
        });
      } catch (err) {
        error.value = err.message || 'Failed to save property. Please try again.';
      } finally {
        loading.value = false;
      }
    };

    const handleFormClose = () => {
      if (properties.value.length > 0) {
        showForm.value = false;
      }
    };

    const handleNext = () => {
      if (!showForm.value && properties.value.length > 0) {
        emit('next');
      }
    };

    const handleBack = () => {
      emit('back');
    };

    const handleSkip = () => {
      emit('skip');
    };

    onMounted(async () => {
      // Load user address from profile for pre-filling main residence
      await store.dispatch('userProfile/fetchProfile').catch(() => {});
      const personalInfo = store.getters['userProfile/personalInfo'];
      if (personalInfo?.address) {
        userAddress.value = {
          address_line_1: personalInfo.address.line_1 || personalInfo.address.address_line_1 || '',
          address_line_2: personalInfo.address.line_2 || personalInfo.address.address_line_2 || '',
          city: personalInfo.address.city || '',
          county: personalInfo.address.county || '',
          postcode: personalInfo.address.postcode || '',
        };
      }

      await loadProperties();

      // If no properties yet, auto-set main residence type on the form
      if (properties.value.length === 0 && userAddress.value) {
        // PropertyForm will auto-fill address when property_type is set to main_residence
        // via its watch on property_type + userAddress prop
      }
    });

    return {
      properties,
      showForm,
      loading,
      error,
      userAddress,
      formatPropertyType,
      handlePropertySaved,
      handleFormClose,
      handleNext,
      handleBack,
      handleSkip,
    };
  },
};
</script>
