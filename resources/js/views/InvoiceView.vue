<template>
  <AppLayout>
    <div class="module-gradient py-4 sm:py-8">
      <!-- Loading -->
      <div v-if="loading" class="flex justify-center items-center py-20">
        <div class="text-center">
          <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin mx-auto"></div>
          <p class="mt-4 text-body-base text-neutral-500">Loading invoice...</p>
        </div>
      </div>

      <!-- Error -->
      <div v-else-if="error" class="max-w-2xl mx-auto">
        <div class="bg-white rounded-lg border border-light-gray p-8 text-center">
          <p class="text-body-base text-raspberry-600 mb-4">{{ error }}</p>
          <router-link to="/profile?section=subscription" class="btn-secondary">
            Back to subscription
          </router-link>
        </div>
      </div>

      <!-- Invoice -->
      <div v-else-if="invoice" class="max-w-3xl mx-auto">
        <!-- Back link -->
        <button @click="$router.push('/profile?section=subscription')" class="text-body-sm text-neutral-500 hover:text-horizon-500 transition-colors mb-6 inline-block">
          &larr; Back to subscription
        </button>

        <div class="bg-white rounded-lg border border-light-gray overflow-hidden">
          <!-- Header -->
          <div class="flex justify-between items-start p-8 pb-0">
            <div>
              <img src="/images/logos/LogoHiResFynlaDark.png" alt="Fynla" class="h-10 w-auto" />
              <p class="text-caption text-neutral-500 mt-1">Your financial companion for life</p>
            </div>
            <div class="text-right">
              <h1 class="text-h2 font-display text-horizon-500">Invoice</h1>
              <p class="text-body-sm text-neutral-500 mt-1">{{ invoice.invoice_number }}</p>
              <span class="inline-block mt-2 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-spring-100 text-spring-800 uppercase">
                Paid
              </span>
            </div>
          </div>

          <!-- Meta -->
          <div class="grid grid-cols-2 gap-8 p-8">
            <div>
              <p class="text-caption font-bold uppercase text-neutral-500 tracking-wide mb-1">Billed To</p>
              <p class="text-body-sm text-horizon-500">{{ invoice.billing_name || 'Customer' }}</p>
              <template v-if="invoice.billing_address">
                <p v-for="(line, i) in invoice.billing_address.split('\n')" :key="i" class="text-body-sm text-neutral-500">
                  {{ line }}
                </p>
              </template>
              <p class="text-body-sm text-neutral-500">{{ invoice.billing_email }}</p>
            </div>
            <div class="text-right">
              <p class="text-caption font-bold uppercase text-neutral-500 tracking-wide mb-1">Invoice Date</p>
              <p class="text-body-sm text-horizon-500">{{ formatDate(invoice.issued_at) }}</p>
              <p class="text-caption font-bold uppercase text-neutral-500 tracking-wide mb-1 mt-3">Billing Period</p>
              <p class="text-body-sm text-horizon-500">{{ formatDateShort(invoice.period_start) }} &mdash; {{ formatDateShort(invoice.period_end) }}</p>
            </div>
          </div>

          <!-- Line Items -->
          <div class="px-8">
            <table class="min-w-full">
              <thead>
                <tr class="border-b-2 border-light-gray bg-savannah-100">
                  <th class="text-left text-caption font-bold uppercase text-neutral-500 tracking-wide py-3 px-3">Description</th>
                  <th class="text-left text-caption font-bold uppercase text-neutral-500 tracking-wide py-3 px-3">Billing Cycle</th>
                  <th class="text-right text-caption font-bold uppercase text-neutral-500 tracking-wide py-3 px-3">Amount</th>
                </tr>
              </thead>
              <tbody>
                <tr class="border-b border-light-gray">
                  <td class="py-3 px-3 text-body-sm text-horizon-500">{{ invoice.plan_name }} Plan</td>
                  <td class="py-3 px-3 text-body-sm text-horizon-500 capitalize">{{ invoice.billing_cycle }}</td>
                  <td class="py-3 px-3 text-body-sm text-horizon-500 text-right">&pound;{{ formatAmount(invoice.subtotal_amount) }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Totals -->
          <div class="px-8 py-6">
            <div class="flex justify-end">
              <div class="w-64">
                <div class="flex justify-between py-1">
                  <span class="text-body-sm text-neutral-500">Subtotal</span>
                  <span class="text-body-sm text-horizon-500">&pound;{{ formatAmount(invoice.subtotal_amount) }}</span>
                </div>
                <div v-if="invoice.discount_amount > 0" class="flex justify-between py-1">
                  <span class="text-body-sm text-neutral-500">
                    Discount<template v-if="invoice.discount_description"> ({{ invoice.discount_description }})</template><template v-if="invoice.discount_code"> &mdash; {{ invoice.discount_code }}</template>
                  </span>
                  <span class="text-body-sm text-spring-600">-&pound;{{ formatAmount(invoice.discount_amount) }}</span>
                </div>
                <div v-if="invoice.tax_amount > 0" class="flex justify-between py-1">
                  <span class="text-body-sm text-neutral-500">Tax</span>
                  <span class="text-body-sm text-horizon-500">&pound;{{ formatAmount(invoice.tax_amount) }}</span>
                </div>
                <div class="flex justify-between py-2 mt-2 border-t-2 border-horizon-500">
                  <span class="text-body-base font-bold text-horizon-500">Total Paid</span>
                  <span class="text-body-base font-bold text-horizon-500">&pound;{{ formatAmount(invoice.total_amount) }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Renewal Notice -->
          <div v-if="invoice.next_renewal_date && invoice.auto_renew" class="mx-8 mb-6 bg-spring-50 border border-spring-200 rounded-lg p-4">
            <p class="text-body-sm text-horizon-500">
              <span class="font-semibold text-spring-600">Auto-renewal:</span>
              Your subscription will automatically renew on <strong>{{ formatDate(invoice.next_renewal_date) }}</strong>
              at <strong>&pound;{{ formatAmount(invoice.renewal_amount) }}/{{ invoice.billing_cycle === 'monthly' ? 'month' : 'year' }}</strong>.
              You can cancel at any time from your profile settings.
            </p>
          </div>

          <!-- Download PDF -->
          <div v-if="invoice.has_pdf" class="px-8 pb-6">
            <a
              :href="`/api/payment/invoices/${invoice.id}/download`"
              class="btn-secondary inline-flex items-center gap-2"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
              </svg>
              Download PDF
            </a>
          </div>

          <!-- Footer -->
          <div class="border-t border-light-gray px-8 py-6 text-center">
            <p class="text-body-sm text-neutral-500">Thank you for choosing Fynla.</p>
            <p class="text-caption text-neutral-500 mt-1">
              Questions? Contact us at <a href="mailto:support@fynla.org" class="text-raspberry-500 hover:text-raspberry-700">support@fynla.org</a>
            </p>
            <p class="text-caption text-neutral-500 mt-2">Payment processed by Revolut &bull; Currency: {{ invoice.currency }}</p>
            <p class="text-caption text-neutral-400 mt-4">Fynla Limited is registered in England &amp; Wales, Company Number: 16903721</p>
            <p class="text-caption text-neutral-400">Registered address: 124 City Road, London, England, EC1V 2NX</p>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppLayout from '@/layouts/AppLayout.vue';
import api from '@/services/api';
import logger from '@/utils/logger';

export default {
  name: 'InvoiceView',

  components: {
    AppLayout,
  },

  setup() {
    const route = useRoute();
    const router = useRouter();
    const invoice = ref(null);
    const loading = ref(true);
    const error = ref(null);

    const fetchInvoice = async () => {
      loading.value = true;
      error.value = null;
      try {
        const response = await api.get(`/payment/invoices/${route.params.id}`);
        invoice.value = response.data.data;
      } catch (err) {
        logger.error('Failed to load invoice', err);
        if (err.response?.status === 404) {
          error.value = 'Invoice not found.';
        } else {
          error.value = 'Failed to load invoice. Please try again.';
        }
      } finally {
        loading.value = false;
      }
    };

    const formatDate = (dateStr) => {
      if (!dateStr) return '\u2014';
      return new Date(dateStr).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
      });
    };

    const formatDateShort = (dateStr) => {
      if (!dateStr) return '\u2014';
      return new Date(dateStr).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
      });
    };

    const formatAmount = (pence) => {
      if (pence == null) return '0.00';
      return (pence / 100).toFixed(2);
    };

    onMounted(fetchInvoice);

    return {
      invoice,
      loading,
      error,
      formatDate,
      formatDateShort,
      formatAmount,
    };
  },
};
</script>
