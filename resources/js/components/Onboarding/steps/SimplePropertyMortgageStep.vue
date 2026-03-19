<template>
  <OnboardingStep
    title="Your Property & Mortgage"
    description="Tell us about your home and any mortgage you have"
    :can-go-back="true"
    :can-skip="true"
    :loading="loading"
    :error="error"
    @next="handleNext"
    @back="handleBack"
    @skip="handleSkip"
  >
    <div class="space-y-6">
      <!-- No Property Option -->
      <div class="flex items-center">
        <input
          id="no_property"
          v-model="noProperty"
          type="checkbox"
          class="h-4 w-4 text-raspberry-500 border-horizon-300 rounded focus:ring-violet-500"
        >
        <label for="no_property" class="ml-2 text-body text-horizon-500">
          I do not own a property
        </label>
      </div>

      <template v-if="!noProperty">
        <!-- Mortgage Section -->
        <div class="bg-white rounded-lg border border-light-gray p-6">
          <h3 class="text-h5 font-display text-horizon-500 mb-4">Mortgage Details</h3>

          <div class="space-y-4">
            <!-- Has Mortgage -->
            <div class="flex items-center">
              <input
                id="has_mortgage"
                v-model="hasMortgage"
                type="checkbox"
                class="h-4 w-4 text-raspberry-500 border-horizon-300 rounded focus:ring-violet-500"
              >
              <label for="has_mortgage" class="ml-2 text-body text-horizon-500">
                I have a mortgage
              </label>
            </div>

            <template v-if="hasMortgage">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Lender -->
                <div>
                  <label for="lender_name" class="label">Lender</label>
                  <input
                    id="lender_name"
                    v-model="mortgageData.lender_name"
                    type="text"
                    class="input-field"
                    placeholder="e.g., Nationwide, Halifax"
                  >
                </div>

                <!-- Mortgage Type -->
                <div>
                  <label for="mortgage_type" class="label">Mortgage Type</label>
                  <select
                    id="mortgage_type"
                    v-model="mortgageData.mortgage_type"
                    class="input-field"
                  >
                    <option value="">Select type</option>
                    <option value="repayment">Repayment</option>
                    <option value="interest_only">Interest Only</option>
                    <option value="mixed">Mixed</option>
                  </select>
                </div>

                <!-- Outstanding Balance -->
                <div>
                  <label for="outstanding_balance" class="label">Outstanding Balance</label>
                  <div class="relative">
                    <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-neutral-500">&pound;</span>
                    <input
                      id="outstanding_balance"
                      v-model.number="mortgageData.outstanding_balance"
                      type="number"
                      min="0"
                      class="input-field pl-8"
                      placeholder="200,000"
                    >
                  </div>
                </div>

                <!-- Monthly Payment -->
                <div>
                  <label for="monthly_payment" class="label">Monthly Payment</label>
                  <div class="relative">
                    <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-neutral-500">&pound;</span>
                    <input
                      id="monthly_payment"
                      v-model.number="mortgageData.monthly_payment"
                      type="number"
                      min="0"
                      class="input-field pl-8"
                      placeholder="1,200"
                    >
                  </div>
                </div>

                <!-- Interest Rate -->
                <div>
                  <label for="interest_rate" class="label">Interest Rate</label>
                  <div class="relative">
                    <input
                      id="interest_rate"
                      v-model.number="mortgageData.interest_rate"
                      type="number"
                      min="0"
                      max="20"
                      step="0.01"
                      class="input-field pr-8"
                      placeholder="4.5"
                    >
                    <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-neutral-500">%</span>
                  </div>
                </div>

                <!-- End Date -->
                <div>
                  <label for="end_date" class="label">Mortgage End Date</label>
                  <input
                    id="end_date"
                    v-model="mortgageData.end_date"
                    type="date"
                    class="input-field"
                    :min="todayDate"
                  >
                </div>
              </div>
            </template>
          </div>
        </div>

        <!-- Property Section (optional, expandable) -->
        <div class="bg-white rounded-lg border border-light-gray p-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-h5 font-display text-horizon-500">Property Details</h3>
            <span class="text-body-sm text-neutral-500">Optional</span>
          </div>

          <p class="text-body-sm text-neutral-500 mb-4">
            For protection purposes, your mortgage details above are most important. You can optionally enter property details for a more complete financial picture.
          </p>

          <div class="flex items-center mb-4">
            <input
              id="add_property_details"
              v-model="showPropertyDetails"
              type="checkbox"
              class="h-4 w-4 text-raspberry-500 border-horizon-300 rounded focus:ring-violet-500"
            >
            <label for="add_property_details" class="ml-2 text-body text-horizon-500">
              Add property details
            </label>
          </div>

          <template v-if="showPropertyDetails">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <!-- Property Type -->
              <div>
                <label for="property_type" class="label">Property Type</label>
                <select
                  id="property_type"
                  v-model="propertyData.property_type"
                  class="input-field"
                >
                  <option value="main_residence">Main Residence</option>
                  <option value="secondary_residence">Secondary Residence</option>
                  <option value="buy_to_let">Buy to Let</option>
                </select>
              </div>

              <!-- Current Value -->
              <div>
                <label for="current_value" class="label">Estimated Current Value</label>
                <div class="relative">
                  <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-neutral-500">&pound;</span>
                  <input
                    id="current_value"
                    v-model.number="propertyData.current_value"
                    type="number"
                    min="0"
                    class="input-field pl-8"
                    placeholder="350,000"
                  >
                </div>
              </div>

              <!-- Address -->
              <div class="md:col-span-2">
                <label for="address_line_1" class="label">Address</label>
                <input
                  id="address_line_1"
                  v-model="propertyData.address_line_1"
                  type="text"
                  class="input-field"
                  placeholder="Street address"
                >
              </div>

              <div>
                <label for="city" class="label">City</label>
                <input
                  id="city"
                  v-model="propertyData.city"
                  type="text"
                  class="input-field"
                  placeholder="City"
                >
              </div>

              <div>
                <label for="postcode" class="label">Postcode</label>
                <input
                  id="postcode"
                  v-model="propertyData.postcode"
                  type="text"
                  class="input-field"
                  placeholder="SW1A 1AA"
                >
              </div>
            </div>
          </template>
        </div>
      </template>

      <UsefulResources :links="STEP_RESOURCES.simpleProperty" />
    </div>
  </OnboardingStep>
</template>

<script>
// DEPRECATED: Will be replaced by unified form with context="onboarding". See life-stage-journey-design.md §11.7
import { ref, computed, onMounted } from 'vue';
import { useStore } from 'vuex';
import OnboardingStep from '../OnboardingStep.vue';
import UsefulResources from '@/components/Onboarding/UsefulResources.vue';
import { STEP_RESOURCES } from '@/constants/onboardingLinks';
import propertyService from '@/services/propertyService';

export default {
  name: 'SimplePropertyMortgageStep',

  components: {
    OnboardingStep,
    UsefulResources,
  },

  emits: ['next', 'back', 'skip'],

  setup(props, { emit }) {
    const store = useStore();

    const noProperty = ref(false);
    const hasMortgage = ref(true);
    const showPropertyDetails = ref(false);
    const loading = ref(false);
    const error = ref(null);

    const mortgageData = ref({
      lender_name: '',
      mortgage_type: 'repayment',
      outstanding_balance: null,
      monthly_payment: null,
      interest_rate: null,
      end_date: '',
    });

    const propertyData = ref({
      property_type: 'main_residence',
      current_value: null,
      address_line_1: '',
      city: '',
      postcode: '',
    });

    const todayDate = computed(() => {
      return new Date().toISOString().split('T')[0];
    });

    const handleNext = async () => {
      loading.value = true;
      error.value = null;

      try {
        if (noProperty.value) {
          // Save step as completed with no data
          await store.dispatch('onboarding/saveStepData', {
            stepName: 'simple_property_mortgage',
            data: { no_property: true },
          });
        } else {
          // Create property if details provided
          let propertyId = null;

          if (showPropertyDetails.value && propertyData.value.current_value) {
            const propertyPayload = {
              property_type: propertyData.value.property_type,
              current_value: propertyData.value.current_value,
              address_line_1: propertyData.value.address_line_1 || null,
              city: propertyData.value.city || null,
              postcode: propertyData.value.postcode || null,
              ownership_type: 'individual',
              country: 'GB',
            };

            const propertyResponse = await propertyService.createProperty(propertyPayload);
            propertyId = propertyResponse?.data?.id || propertyResponse?.property?.id || propertyResponse?.id;
          } else if (hasMortgage.value) {
            // Create a minimal property record so we can attach the mortgage
            const propertyPayload = {
              property_type: 'main_residence',
              ownership_type: 'individual',
              country: 'GB',
              current_value: 0,
            };

            const propertyResponse = await propertyService.createProperty(propertyPayload);
            propertyId = propertyResponse?.data?.id || propertyResponse?.property?.id || propertyResponse?.id;
          }

          // Create mortgage if provided and we have a property
          if (hasMortgage.value && propertyId && mortgageData.value.outstanding_balance) {
            const mortgagePayload = {
              lender_name: mortgageData.value.lender_name || null,
              mortgage_type: mortgageData.value.mortgage_type || 'repayment',
              outstanding_balance: mortgageData.value.outstanding_balance,
              monthly_payment: mortgageData.value.monthly_payment || null,
              interest_rate: mortgageData.value.interest_rate || null,
              end_date: mortgageData.value.end_date || null,
              country: 'GB',
            };

            await propertyService.createPropertyMortgage(propertyId, mortgagePayload);
          }

          // Track step completion
          await store.dispatch('onboarding/saveStepData', {
            stepName: 'simple_property_mortgage',
            data: {
              has_property: showPropertyDetails.value,
              has_mortgage: hasMortgage.value,
              property_id: propertyId,
            },
          });
        }

        emit('next');
      } catch (err) {
        error.value = err.response?.data?.message || err.message || 'Failed to save. Please try again.';
      } finally {
        loading.value = false;
      }
    };

    const handleBack = () => {
      emit('back');
    };

    const handleSkip = () => {
      emit('skip');
    };

    onMounted(async () => {
      // Load any previously saved step data
      try {
        const stepData = await store.dispatch('onboarding/fetchStepData', 'simple_property_mortgage');
        if (stepData && Object.keys(stepData).length > 0) {
          if (stepData.no_property) {
            noProperty.value = true;
          }
        }
      } catch {
        // No existing data
      }
    });

    return {
      noProperty,
      hasMortgage,
      showPropertyDetails,
      loading,
      error,
      mortgageData,
      propertyData,
      todayDate,
      handleNext,
      handleBack,
      handleSkip,
      STEP_RESOURCES,
    };
  },
};
</script>
