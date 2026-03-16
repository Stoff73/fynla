<template>
  <div class="review-step">
    <h3 class="text-lg font-bold text-horizon-500 mb-1">Review Your Lasting Power of Attorney</h3>
    <p class="text-sm text-neutral-500 mb-6">
      Please review all the details below before completing. You can go back to any step to make changes.
    </p>

    <!-- Donor Summary -->
    <div class="border-b border-light-gray pb-4 mb-4">
      <h4 class="text-xs font-bold text-neutral-500 uppercase tracking-wider mb-2">Donor</h4>
      <p class="text-sm font-medium text-horizon-500">{{ formData.donor_full_name || 'Not specified' }}</p>
      <p class="text-xs text-neutral-500">{{ formData.donor_date_of_birth || 'Date of birth not specified' }}</p>
      <p v-if="donorAddress" class="text-xs text-neutral-500">{{ donorAddress }}</p>
    </div>

    <!-- Attorneys Summary -->
    <div class="border-b border-light-gray pb-4 mb-4">
      <h4 class="text-xs font-bold text-neutral-500 uppercase tracking-wider mb-2">
        Primary Attorneys ({{ primaryAttorneys.length }})
      </h4>
      <div v-if="primaryAttorneys.length === 0" class="text-sm text-raspberry-500">
        No primary attorneys appointed — at least one is required.
      </div>
      <div v-else class="space-y-1">
        <p v-for="a in primaryAttorneys" :key="a.full_name" class="text-sm text-horizon-500">
          {{ a.full_name }}
          <span v-if="a.relationship_to_donor" class="text-xs text-neutral-500">({{ a.relationship_to_donor }})</span>
        </p>
      </div>

      <div v-if="replacementAttorneys.length > 0" class="mt-3">
        <h4 class="text-xs font-bold text-neutral-500 uppercase tracking-wider mb-1">
          Replacement Attorneys ({{ replacementAttorneys.length }})
        </h4>
        <p v-for="a in replacementAttorneys" :key="a.full_name" class="text-sm text-horizon-500">
          {{ a.full_name }}
          <span v-if="a.relationship_to_donor" class="text-xs text-neutral-500">({{ a.relationship_to_donor }})</span>
        </p>
      </div>

      <div v-if="primaryAttorneys.length > 1 && formData.attorney_decision_type" class="mt-2">
        <p class="text-xs text-neutral-500">Decision type: <span class="font-medium text-horizon-500">{{ decisionTypeLabel }}</span></p>
      </div>
    </div>

    <!-- When Can Act (Property only) -->
    <div v-if="lpaType === 'property_financial'" class="border-b border-light-gray pb-4 mb-4">
      <h4 class="text-xs font-bold text-neutral-500 uppercase tracking-wider mb-2">When Attorneys Can Act</h4>
      <p class="text-sm text-horizon-500">
        {{ formData.when_attorneys_can_act === 'while_has_capacity'
          ? 'While the donor has capacity and when they lose it'
          : formData.when_attorneys_can_act === 'only_when_lost_capacity'
            ? 'Only when the donor has lost mental capacity'
            : 'Not specified' }}
      </p>
    </div>

    <!-- Preferences -->
    <div class="border-b border-light-gray pb-4 mb-4">
      <h4 class="text-xs font-bold text-neutral-500 uppercase tracking-wider mb-2">Preferences & Instructions</h4>
      <div class="space-y-2">
        <div>
          <p class="text-xs text-neutral-500">Preferences</p>
          <p class="text-sm text-horizon-500">{{ formData.preferences || 'None specified' }}</p>
        </div>
        <div>
          <p class="text-xs text-neutral-500">Instructions</p>
          <p class="text-sm text-horizon-500">{{ formData.instructions || 'None specified' }}</p>
        </div>
        <div v-if="lpaType === 'health_welfare'">
          <p class="text-xs text-neutral-500">Life-sustaining treatment</p>
          <p class="text-sm text-horizon-500">
            {{ formData.life_sustaining_treatment === 'can_consent'
              ? 'Attorneys can give or refuse consent'
              : formData.life_sustaining_treatment === 'cannot_consent'
                ? 'Attorneys cannot give or refuse consent'
                : 'Not specified' }}
          </p>
        </div>
      </div>
    </div>

    <!-- Certificate Provider -->
    <div class="border-b border-light-gray pb-4 mb-4">
      <h4 class="text-xs font-bold text-neutral-500 uppercase tracking-wider mb-2">Certificate Provider</h4>
      <p class="text-sm text-horizon-500">{{ formData.certificate_provider_name || 'Not specified' }}</p>
      <p v-if="formData.certificate_provider_relationship" class="text-xs text-neutral-500">{{ formData.certificate_provider_relationship }}</p>
      <p v-if="formData.certificate_provider_known_years" class="text-xs text-neutral-500">Known for {{ formData.certificate_provider_known_years }} years</p>
    </div>

    <!-- Notification Persons -->
    <div class="pb-4 mb-4">
      <h4 class="text-xs font-bold text-neutral-500 uppercase tracking-wider mb-2">
        People to Notify ({{ notificationPersons.length }})
      </h4>
      <div v-if="notificationPersons.length === 0" class="text-sm text-neutral-500 italic">
        No people to notify listed.
      </div>
      <p v-for="p in notificationPersons" :key="p.full_name" class="text-sm text-horizon-500">
        {{ p.full_name }}
      </p>
    </div>

    <!-- Next Steps -->
    <div class="bg-savannah-100 rounded-lg p-4 text-xs text-neutral-500">
      <p class="font-medium text-horizon-500 mb-1">Next Steps After Completing</p>
      <ol class="list-decimal list-inside space-y-1">
        <li>Print the completed form</li>
        <li>Sign the form with wet ink (donor, attorneys, certificate provider, witnesses)</li>
        <li>Register with the Office of the Public Guardian (currently £82 per Lasting Power of Attorney)</li>
        <li>Wait for registration confirmation (up to 8 weeks)</li>
        <li>Return here to mark as registered and add the reference number</li>
      </ol>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ReviewStep',

  props: {
    formData: { type: Object, required: true },
    lpaType: { type: String, required: true },
  },

  computed: {
    primaryAttorneys() {
      return (this.formData.attorneys || []).filter(a => a.attorney_type === 'primary');
    },
    replacementAttorneys() {
      return (this.formData.attorneys || []).filter(a => a.attorney_type === 'replacement');
    },
    notificationPersons() {
      return this.formData.notification_persons || [];
    },
    donorAddress() {
      const parts = [
        this.formData.donor_address_line_1,
        this.formData.donor_address_city,
        this.formData.donor_address_postcode,
      ].filter(Boolean);
      return parts.length > 0 ? parts.join(', ') : null;
    },
    decisionTypeLabel() {
      const labels = {
        jointly: 'Jointly (all must agree)',
        jointly_and_severally: 'Jointly and severally',
        jointly_for_some: 'Jointly for some, severally for others',
      };
      return labels[this.formData.attorney_decision_type] || '';
    },
  },
};
</script>
