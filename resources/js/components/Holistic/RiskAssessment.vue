<template>
  <div class="risk-assessment">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Risk Assessment</h3>

    <!-- Overall Risk Score -->
    <div class="bg-white border border-gray-200 rounded-lg p-6 mb-6">
      <div class="flex items-center justify-between">
        <div class="flex-1">
          <p class="text-sm text-gray-600 font-medium mb-2">Overall Risk Level</p>
          <p class="text-3xl font-bold" :class="getRiskLevelColour(riskData.risk_level)">
            {{ riskData.risk_level }}
          </p>
          <p class="text-sm text-gray-500 mt-1">Risk Score: {{ riskData.overall_risk_score }}/100</p>
        </div>
        <div class="ml-6">
          <apexchart
            type="radialBar"
            width="180"
            :options="gaugeOptions"
            :series="[riskData.overall_risk_score]"
          ></apexchart>
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
            <p class="text-sm text-gray-700 mb-3">{{ risk.description }}</p>
            <div class="flex items-center">
              <div class="flex-1 bg-gray-200 rounded-full h-2">
                <div
                  :class="getScoreBarClass(risk.score)"
                  class="h-2 rounded-full transition-all"
                  :style="{ width: `${100 - risk.score}%` }"
                ></div>
              </div>
              <span class="ml-3 text-sm font-medium text-gray-600">{{ risk.score }}/100</span>
            </div>
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
import { SUCCESS_COLORS, WARNING_COLORS, ERROR_COLORS } from '@/constants/designSystem';

export default {
  name: 'RiskAssessment',

  props: {
    riskData: {
      type: Object,
      required: true,
    },
  },

  computed: {
    gaugeOptions() {
      return {
        chart: {
          type: 'radialBar',
        },
        plotOptions: {
          radialBar: {
            startAngle: -90,
            endAngle: 90,
            hollow: {
              size: '65%',
            },
            dataLabels: {
              name: {
                show: false,
              },
              value: {
                fontSize: '24px',
                fontWeight: 'bold',
                offsetY: 0,
                formatter: (val) => `${val.toFixed(0)}`,
              },
            },
          },
        },
        fill: {
          type: 'gradient',
          gradient: {
            shade: 'dark',
            type: 'horizontal',
            shadeIntensity: 0.5,
            gradientToColours: this.getGaugeColour(this.riskData.overall_risk_score),
            stops: [0, 100],
          },
        },
        colours: this.getGaugeColour(this.riskData.overall_risk_score),
      };
    },
  },

  methods: {
    getRiskLevelColour(level) {
      const levelLower = level?.toLowerCase();
      if (levelLower?.includes('high') || levelLower?.includes('critical')) {
        return 'text-red-600';
      }
      if (levelLower?.includes('moderate')) {
        return 'text-orange-600';
      }
      if (levelLower?.includes('low')) {
        return 'text-yellow-600';
      }
      return 'text-green-600';
    },

    getGaugeColour(score) {
      if (score >= 70) return [ERROR_COLORS[600]]; // red
      if (score >= 50) return [WARNING_COLORS[500]]; // orange
      if (score >= 30) return [WARNING_COLORS[500]]; // yellow
      return [SUCCESS_COLORS[500]]; // green
    },

    getSeverityBadgeClass(severity) {
      const severityLower = severity?.toLowerCase();
      if (severityLower === 'high' || severityLower === 'critical') {
        return 'bg-red-100 text-red-800';
      }
      if (severityLower === 'medium') {
        return 'bg-orange-100 text-orange-800';
      }
      return 'bg-yellow-100 text-yellow-800';
    },

    getSeverityIconClass(severity) {
      const severityLower = severity?.toLowerCase();
      if (severityLower === 'high' || severityLower === 'critical') {
        return 'text-red-500';
      }
      if (severityLower === 'medium') {
        return 'text-orange-500';
      }
      return 'text-yellow-500';
    },

    getRiskBorderClass(severity) {
      const severityLower = severity?.toLowerCase();
      if (severityLower === 'high' || severityLower === 'critical') {
        return 'border-red-200';
      }
      if (severityLower === 'medium') {
        return 'border-orange-200';
      }
      return 'border-yellow-200';
    },

    getScoreBarClass(score) {
      if (score < 30) return 'bg-red-500';
      if (score < 50) return 'bg-orange-500';
      if (score < 70) return 'bg-yellow-500';
      return 'bg-green-500';
    },
  },
};
</script>
