<template>
  <div class="property-list">
    <ModuleStatusBar />
    <!-- Property Detail View (when a property is selected) -->
    <PropertyDetailInline
      v-if="selectedProperty"
      :property-id="selectedProperty.id"
      @back="clearSelection"
      @deleted="handlePropertyDeleted"
    />

    <!-- Property List View (default) -->
    <template v-else>
      <div v-if="loading" class="loading-state">
        <p>Loading properties...</p>
      </div>

      <div v-else-if="error" class="error-state">
        <p>{{ error }}</p>
      </div>

      <div v-else-if="filteredProperties.length === 0" class="empty-state">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="empty-icon">
          <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
        </svg>
        <p>No properties found</p>
        <p class="empty-subtitle">Add your first property to track your property portfolio</p>
        <button v-preview-disabled="'add'" @click="addProperty" class="add-first-button">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
          </svg>
          Add Your First Property
        </button>
      </div>

      <div v-else class="properties-grid">
        <PropertyCard
          v-for="property in filteredProperties"
          :key="property.id"
          :property="property"
          @select-property="selectProperty"
        />
      </div>
    </template>

    <!-- Property Form Modal -->
    <Teleport to="body">
      <PropertyForm
        v-if="showPropertyForm"
        :property="selectedProperty"
        :user-address="userAddress"
        @save="handleSaveProperty"
        @close="closePropertyForm"
      />
    </Teleport>

    <!-- Success/Error Messages -->
    <div v-if="successMessage" class="notification success animate-slide-in-right">
      {{ successMessage }}
    </div>
    <div v-if="errorMessage" class="notification error animate-slide-in-right">
      {{ errorMessage }}
    </div>
  </div>
</template>

<script>
import { mapActions, mapState, mapGetters } from 'vuex';
import PropertyCard from './PropertyCard.vue';
import PropertyForm from './Property/PropertyForm.vue';
import PropertyDetailInline from '@/components/NetWorth/Property/PropertyDetailInline.vue';
import ModuleStatusBar from '@/components/Shared/ModuleStatusBar.vue';
import api from '@/services/api';

import logger from '@/utils/logger';
export default {
  name: 'PropertyList',

  components: {
    PropertyCard,
    PropertyForm,
    PropertyDetailInline,
    ModuleStatusBar,
  },

  data() {
    return {
      properties: [],
      loading: false,
      error: null,
      showPropertyForm: false,
      selectedProperty: null,
      editingProperty: null,
      successMessage: null,
      errorMessage: null,
      successTimeout: null,
      errorTimeout: null,
    };
  },

  computed: {
    ...mapState('netWorth', ['isDetailView']),
    ...mapGetters('auth', ['currentUser']),
    ...mapGetters('subNav', ['pendingAction', 'actionCounter']),

    isPreviewMode() {
      return this.$store.getters['preview/isPreviewMode'];
    },

    filteredProperties() {
      // Sort by value (high to low) by default
      return [...this.properties].sort((a, b) => b.current_value - a.current_value);
    },

    userAddress() {
      const user = this.currentUser;
      if (!user) return null;
      // Only return address if at least one field is populated
      if (!user.address_line_1 && !user.city && !user.postcode) return null;
      return {
        address_line_1: user.address_line_1 || '',
        address_line_2: user.address_line_2 || '',
        city: user.city || '',
        county: user.county || '',
        postcode: user.postcode || '',
      };
    },
  },

  watch: {
    actionCounter() {
      if (this.pendingAction === 'addProperty') {
        this.addProperty();
        this.$store.dispatch('subNav/consumeCta');
      }
    },
    // Clear selection when sidebar link is clicked (sets isDetailView to false)
    isDetailView(newVal) {
      if (!newVal && this.selectedProperty) {
        this.selectedProperty = null;
        this.fetchProperties();
      }
    },

    // AI Form Fill: open form when pendingFill targets property or mortgage
    '$store.state.aiFormFill.pendingFill'(fill) {
      if (fill && (fill.entityType === 'property' || fill.entityType === 'mortgage')) {
        if (fill.mode === 'edit' && fill.entityId) {
          // Find existing property and open edit modal
          const record = this.properties.find(p => p.id === fill.entityId);
          if (record) {
            this.editingProperty = record;
            this.showPropertyForm = true;
          }
        } else {
          // Open create modal
          this.editingProperty = null;
          this.showPropertyForm = true;
        }
      }
    },
  },

  beforeUnmount() {
    if (this.successTimeout) clearTimeout(this.successTimeout);
    if (this.errorTimeout) clearTimeout(this.errorTimeout);
  },

  methods: {
    ...mapActions('netWorth', ['setDetailView']),

    // Property selection for detail view
    selectProperty(property) {
      this.selectedProperty = property;
      this.setDetailView(true);
    },

    clearSelection() {
      this.selectedProperty = null;
      this.setDetailView(false);
      // Refresh properties list after returning
      this.fetchProperties();
    },

    handlePropertyDeleted() {
      this.selectedProperty = null;
      this.setDetailView(false);
      this.fetchProperties();
      this.successMessage = 'Property deleted successfully';
      if (this.successTimeout) clearTimeout(this.successTimeout);
      this.successTimeout = setTimeout(() => {
        this.successMessage = null;
      }, 5000);
    },

    addProperty() {
      this.editingProperty = null;
      this.showPropertyForm = true;
    },

    closePropertyForm() {
      this.showPropertyForm = false;
      this.editingProperty = null;
    },

    async handleSaveProperty(data) {
      if (this.isPreviewMode) {
        return;
      }
      this.clearMessages();

      try {
        let propertyResponse;

        if (data.property.id) {
          // Update existing property
          propertyResponse = await api.put(`/properties/${data.property.id}`, data.property);
          const updatedProperty = propertyResponse.data.data?.property || propertyResponse.data;
          const index = this.properties.findIndex(p => p.id === data.property.id);
          if (index !== -1) {
            this.properties.splice(index, 1, updatedProperty);
          }
          this.successMessage = 'Property updated successfully';
        } else {
          // Create new property
          // Note: PropertyController automatically creates mortgage(s) if outstanding_mortgage is provided
          // For joint ownership, it creates reciprocal mortgage records automatically

          // Include ALL mortgage data if provided
          if (data.mortgage && data.mortgage.outstanding_balance) {
            data.property.outstanding_mortgage = data.mortgage.outstanding_balance;
            data.property.mortgage_lender_name = data.mortgage.lender_name;
            data.property.mortgage_type = data.mortgage.mortgage_type;
            data.property.mortgage_monthly_payment = data.mortgage.monthly_payment;
            data.property.mortgage_interest_rate = data.mortgage.interest_rate;
            data.property.mortgage_rate_type = data.mortgage.rate_type;
            data.property.mortgage_start_date = data.mortgage.start_date;
            data.property.mortgage_maturity_date = data.mortgage.maturity_date;
            data.property.mortgage_ownership_type = data.mortgage.ownership_type;
            data.property.mortgage_original_loan_amount = data.mortgage.original_loan_amount;
            // Include joint ownership fields for mortgage
            data.property.mortgage_joint_owner_id = data.mortgage.joint_owner_id;
            data.property.mortgage_ownership_percentage = data.mortgage.ownership_percentage;
          }

          propertyResponse = await api.post('/properties', data.property);
          const newProperty = propertyResponse.data.data?.property || propertyResponse.data;
          this.properties.push(newProperty);

          // Check if mortgage was auto-created
          const hasMortgage = data.property.outstanding_mortgage > 0;
          this.successMessage = hasMortgage
            ? 'Property and mortgage added successfully'
            : 'Property added successfully';
        }

        // Complete AI fill if this was an AI-driven save
        if (this.$store.state.aiFormFill.pendingFill) {
          this.$store.dispatch('aiFormFill/completeFill');
        }
        this.closePropertyForm();

        // Auto-hide success message after 5 seconds
        if (this.successTimeout) clearTimeout(this.successTimeout);
        this.successTimeout = setTimeout(() => {
          this.successMessage = null;
        }, 5000);
      } catch (error) {
        logger.error('Error saving property:', error);
        this.errorMessage = error.response?.data?.message || 'Failed to save property. Please try again.';

        // Auto-hide error message after 5 seconds
        if (this.errorTimeout) clearTimeout(this.errorTimeout);
        this.errorTimeout = setTimeout(() => {
          this.errorMessage = null;
        }, 5000);
      }
    },

    clearMessages() {
      this.successMessage = null;
      this.errorMessage = null;
    },

    async fetchProperties() {
      // Preview users are real DB users - use normal API to fetch their data
      this.loading = true;
      this.error = null;

      try {
        const response = await api.get('/properties');
        this.properties = response.data.data?.properties || response.data.properties || [];
      } catch (error) {
        logger.error('Error fetching properties:', error);
        this.error = error.response?.data?.message || 'Failed to load properties';
      } finally {
        this.loading = false;
      }
    },

  },

  async mounted() {
    // Check for pendingFill that was set before this component mounted
    const fill = this.$store.state.aiFormFill?.pendingFill;
    if (fill && (fill.entityType === 'property' || fill.entityType === 'mortgage') && fill.mode !== 'edit') {
      this.editingProperty = null;
      this.showPropertyForm = true;
    }

    this.setDetailView(false);
    // Fetch family members to ensure spouse data is available (works for both regular and preview users)
    await this.$store.dispatch('userProfile/fetchFamilyMembers');
    await this.fetchProperties();
  },
};
</script>

<style scoped>
.property-list {
  padding: 24px;
  @apply bg-eggshell-500;
}

.list-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
  flex-wrap: wrap;
  gap: 16px;
}

.list-title {
  font-size: 24px;
  font-weight: 700;
  @apply text-horizon-500;
  margin: 0;
}

.add-property-button {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  @apply bg-raspberry-500;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}

.add-property-button:hover {
  @apply bg-raspberry-500;
}

.button-icon {
  width: 20px;
  height: 20px;
}

.properties-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 20px;
}

.loading-state,
.error-state,
.empty-state {
  text-align: center;
  padding: 60px 20px;
}

.loading-state p,
.error-state p {
  @apply text-neutral-500;
  font-size: 16px;
  margin: 0;
}

.error-state p {
  @apply text-raspberry-500;
}

.empty-state {
  @apply bg-light-blue-100 border border-light-gray;
  border-radius: 12px;
  padding: 80px 40px;
  text-align: center;
}

.add-first-button {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  margin-top: 24px;
  padding: 12px 24px;
  @apply bg-horizon-500;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}

.add-first-button:hover {
  @apply bg-horizon-600;
}

.empty-icon {
  width: 64px;
  height: 64px;
  @apply text-horizon-400;
  margin: 0 auto 16px;
}

.empty-state p {
  @apply text-neutral-500;
  font-size: 18px;
  font-weight: 600;
  margin: 0 0 8px 0;
}

.empty-subtitle {
  @apply text-horizon-400;
  font-size: 14px;
  font-weight: 400;
}

.notification {
  position: fixed;
  top: 20px;
  right: 20px;
  padding: 16px 20px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  z-index: 100;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.notification.success {
  @apply bg-spring-500;
  color: white;
}

.notification.error {
  @apply bg-raspberry-500;
  color: white;
}

@media (max-width: 768px) {
  .property-list {
    padding: 16px;
  }

  .list-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .add-property-button {
    width: 100%;
  }

  .properties-grid {
    grid-template-columns: 1fr;
  }
}
</style>
