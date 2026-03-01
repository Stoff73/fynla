<template>
  <div class="risk-assessment">
    <PlanSectionHeader
      title="Risk Assessment"
      subtitle="Identified risk areas and mitigation strategies"
      color="blue"
    />

    <!-- Overall Risk Level -->
    <div class="bg-white border border-gray-200 rounded-lg p-6 mb-6">
      <div class="flex items-center">
        <div :class="getRiskLevelBg(riskData.risk_level)" class="w-12 h-12 rounded-lg flex items-center justify-center mr-4">
          <svg :class="getRiskLevelColour(riskData.risk_level)" class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path v-if="isHighRisk(riskData.risk_level)" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
          </svg>
        </div>
        <div>
          <p class="text-sm text-gray-600 font-medium mb-1">Overall Risk Level</p>
          <p class="text-2xl font-bold" :class="getRiskLevelColour(riskData.risk_level)">
            {{ riskData.risk_level }}
          </p>
        </div>
      </div>
    </div>

    <!-- Risk Areas -->
    <div v-if="riskData.risk_areas && riskData.risk_areas.length > 0" class="space-y-4">
      <h4 class="text-md font-semibold text-gray-900">Identified Risk Areas ({{ riskData.total_risk_areas }})</h4>

      <div
        v-for="(risk, index) in riskData.risk_areas"
        :key="index"
        class="bg-white border rounded-lg p-5 hover:shadow-md transition-shadow"
        :class="getRiskBorderClass(risk.severity)"
      >
        <div class="flex items-start justify-between">
          <div class="flex-1">
            <div class="flex items-center space-x-3 mb-2">
              <span :class="getSeverityBadgeClass(risk.severity)" class="px-3 py-1 text-xs font-bold rounded-full uppercase">
                {{ risk.severity }}
              </span>
              <span class="text-lg font-semibold text-gray-900">{{ risk.area }}</span>
            </div>
            <p class="text-sm text-gray-700">{{ risk.description }}</p>
          </div>
          <svg
            :class="getSeverityIconClass(risk.severity)"
            class="h-8 w-8 ml-4 flex-shrink-0"
            fill="currentColor"
            viewBox="0 0 20 20"
          >
            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
          </svg>
        </div>
      </div>
    </div>

    <!-- No Risks -->
    <div v-else class="bg-green-50 border border-green-200 rounded-lg p-8 text-center">
      <svg class="mx-auto h-16 w-16 text-green-500 mb-4" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
      </svg>
      <h3 class="text-lg font-semibold text-green-900 mb-2">No Significant Risks Identified</h3>
      <p class="text-sm text-green-700">Your financial plan appears well-balanced with minimal risk exposure.</p>
    </div>

    <!-- Risk Mitigation Tips -->
    <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-6">
      <h4 class="text-md font-semibold text-blue-900 mb-3 flex items-center">
        <svg class="h-5 w-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
        </svg>
        Risk Mitigation Strategies
      </h4>
      <ul class="space-y-2 text-sm text-blue-800">
        <li class="flex items-start">
          <svg class="h-5 w-5 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
          </svg>
          Regularly review and adjust your financial plan (at least annually)
        </li>
        <li class="flex items-start">
          <svg class="h-5 w-5 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
          </svg>
          Diversify your investment portfolio across asset classes
        </li>
        <li class="flex items-start">
          <svg class="h-5 w-5 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
          </svg>
          Maintain adequate emergency fund (3-6 months expenses)
        </li>
        <li class="flex items-start">
          <svg class="h-5 w-5 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
          </svg>
          Ensure protection coverage matches your needs and circumstances
        </li>
        <li class="flex items-start">
          <svg class="h-5 w-5 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
          </svg>
          Consider seeking professional financial advice for complex situations
        </li>
      </ul>
    </div>
  </div>
</template>

<script>
import PlanSectionHeader from '@/components/Plans/Shared/PlanSectionHeader.vue';

export default {
  name: 'RiskAssessment',
  components: { PlanSectionHeader },

  props: {
    riskData: {
      type: Object,
      required: true,
    },
  },

  methods: {
    isHighRisk(level) {
      const levelLower = level?.toLowerCase();
      return levelLower?.includes('high') || levelLower?.includes('critical');
    },

    getRiskLevelColour(level) {
      const levelLower = level?.toLowerCase();
      if (levelLower?.includes('high') || levelLower?.includes('critical')) {
        return 'text-red-600';
      }
      if (levelLower?.includes('moderate')) {
        return 'text-blue-600';
      }
      if (levelLower?.includes('low')) {
        return 'text-blue-600';
      }
      return 'text-green-600';
    },

    getRiskLevelBg(level) {
      const levelLower = level?.toLowerCase();
      if (levelLower?.includes('high') || levelLower?.includes('critical')) {
        return 'bg-red-100';
      }
      if (levelLower?.includes('moderate')) {
        return 'bg-blue-100';
      }
      if (levelLower?.includes('low')) {
        return 'bg-blue-100';
      }
      return 'bg-green-100';
    },

    getSeverityBadgeClass(severity) {
      const severityLower = severity?.toLowerCase();
      if (severityLower === 'high' || severityLower === 'critical') {
        return 'bg-red-100 text-red-800';
      }
      if (severityLower === 'medium') {
        return 'bg-blue-100 text-blue-800';
      }
      return 'bg-blue-100 text-blue-800';
    },

    getSeverityIconClass(severity) {
      const severityLower = severity?.toLowerCase();
      if (severityLower === 'high' || severityLower === 'critical') {
        return 'text-red-500';
      }
      if (severityLower === 'medium') {
        return 'text-blue-500';
      }
      return 'text-blue-500';
    },

    getRiskBorderClass(severity) {
      const severityLower = severity?.toLowerCase();
      if (severityLower === 'high' || severityLower === 'critical') {
        return 'border-red-200';
      }
      if (severityLower === 'medium') {
        return 'border-blue-200';
      }
      return 'border-blue-200';
    },
  },
};
</script>
