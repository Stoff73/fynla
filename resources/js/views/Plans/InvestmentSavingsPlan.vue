<template>
  <AppLayout>
    <PrintHeader title="Investment & Savings Plan" />
    <div class="investment-savings-plan py-6">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="no-print mb-6 flex items-center justify-between">
          <div>
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Investment & Savings Plan</h1>
            <p class="text-gray-600">
              Comprehensive analysis of your investment portfolio and savings strategy
            </p>
          </div>
          <div class="flex gap-3">
            <button
              @click="downloadPDF"
              class="px-6 py-3 bg-primary-600 text-white rounded-button hover:bg-primary-700 flex items-center gap-2 shadow-md"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              Download PDF
            </button>
            <router-link
              to="/plans"
              class="px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-button hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
            >
              Back to Plans
            </router-link>
          </div>
        </div>

        <!-- Comprehensive Investment & Savings Plan -->
        <div id="investment-savings-plan-document">
          <!-- PDF Logo Header -->
          <div class="pdf-header flex items-center justify-between p-6 mb-6 bg-white rounded-lg shadow-md border-b-2 border-gray-200">
            <img :src="logoUrl" alt="Fynla" class="h-12 w-auto" />
            <div class="text-right">
              <div class="text-lg font-semibold text-gray-700">Investment & Savings Plan</div>
              <div class="text-sm text-gray-500">{{ currentDate }}</div>
            </div>
          </div>

          <InvestmentSavingsPlanView />
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import PrintHeader from '@/components/Common/PrintHeader.vue';
import InvestmentSavingsPlanView from '@/components/Plans/InvestmentSavingsPlanView.vue';
import html2pdf from 'html2pdf.js';
import logoImage from '@/assets/logoTransparent.png';

export default {
  name: 'InvestmentSavingsPlan',

  components: {
    AppLayout,
    PrintHeader,
    InvestmentSavingsPlanView,
  },

  data() {
    return {
      logoUrl: logoImage,
    };
  },

  computed: {
    currentDate() {
      return new Date().toLocaleDateString('en-GB', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
      });
    },
  },

  methods: {
    downloadPDF() {
      const element = document.getElementById('investment-savings-plan-document');
      if (!element) return;

      // Show the PDF header before generating
      const pdfHeader = element.querySelector('.pdf-header');
      if (pdfHeader) pdfHeader.style.display = 'flex';

      const opt = {
        margin: [10, 10, 10, 10],
        filename: `Investment_Savings_Plan_${new Date().toISOString().split('T')[0]}.pdf`,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true, letterRendering: true },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
        pagebreak: { mode: ['css'], before: '.pdf-page-break' },
      };

      html2pdf().set(opt).from(element).save().then(() => {
        // Hide the PDF header after generating
        if (pdfHeader) pdfHeader.style.display = 'none';
      });
    },
  },
};
</script>

<style scoped>
/* Print styles moved to global app.css */
</style>
