<template>
  <AppLayout>
    <div class="max-w-4xl mx-auto py-8 px-4">
      <!-- Back Button -->
      <button
        @click="goBack"
        class="inline-flex items-center text-sm text-neutral-500 hover:text-neutral-500 transition-colors mb-6"
      >
        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
        Back
      </button>

      <!-- Missing Plan/Cycle -->
      <div v-if="!plan || !billingCycle" class="bg-white rounded-xl border border-light-gray p-8 text-center">
        <p class="text-body-base text-neutral-500 mb-4">No plan selected. Please choose a plan first.</p>
        <router-link to="/dashboard" class="btn-primary">Go to Dashboard</router-link>
      </div>

      <!-- Checkout Content -->
      <div v-else>
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
          <!-- Order Summary (left) -->
          <div class="lg:col-span-2">
            <div class="bg-white rounded-xl border border-light-gray p-6 sticky top-24">
              <h2 class="text-h4 font-semibold text-horizon-500 mb-4">
                {{ isUpgrade ? 'Upgrade Summary' : 'Order Summary' }}
              </h2>

              <div class="space-y-3">
                <div class="flex justify-between">
                  <span class="text-body-sm text-neutral-500">Plan</span>
                  <span class="text-body-sm font-medium text-horizon-500">{{ planDisplayName }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-body-sm text-neutral-500">Billing</span>
                  <span class="text-body-sm font-medium text-horizon-500 capitalize">{{ billingCycle }}</span>
                </div>
                <div v-if="isUpgrade && monthsRemaining" class="flex justify-between">
                  <span class="text-body-sm text-neutral-500">Prorated</span>
                  <span class="text-body-sm font-medium text-horizon-500">{{ monthsRemaining }} {{ monthsRemaining === 1 ? 'month' : 'months' }} remaining</span>
                </div>
                <!-- Discount applied -->
                <div v-if="discountApplied" class="flex justify-between">
                  <span class="text-body-sm text-neutral-500">Subtotal</span>
                  <span class="text-body-sm text-neutral-500 line-through">{{ originalPrice }}</span>
                </div>
                <div v-if="discountApplied" class="flex justify-between">
                  <span class="text-body-sm text-spring-600">{{ discountDescription }}</span>
                  <span class="text-body-sm font-medium text-spring-600">-{{ formatCurrencyWithPence(discountAmountPounds) }}</span>
                </div>

                <div class="border-t border-light-gray pt-3">
                  <div class="flex justify-between">
                    <span class="text-body-base font-semibold text-horizon-500">
                      {{ isUpgrade ? 'Upgrade Cost' : 'Total' }}
                    </span>
                    <span class="text-body-base font-semibold text-horizon-500">
                      {{ isUpgrade && upgradeAmount ? formatCurrencyWithPence(upgradeAmount / 100) : displayPrice }}
                    </span>
                  </div>
                  <p v-if="isUpgrade" class="text-caption text-neutral-500 mt-1">
                    Prorated difference until your next renewal
                  </p>
                </div>
              </div>

              <!-- Discount Code Input -->
              <div v-if="!isUpgrade" class="mt-4 pt-4 border-t border-light-gray">
                <div v-if="!showDiscountInput" class="text-center">
                  <button
                    @click="showDiscountInput = true"
                    class="text-body-sm text-violet-500 hover:text-violet-700 transition-colors"
                  >
                    Have a discount code?
                  </button>
                </div>
                <div v-else>
                  <label class="text-body-sm font-medium text-horizon-500 mb-1 block">Discount code</label>
                  <div class="flex gap-2">
                    <input
                      v-model="discountCodeInput"
                      type="text"
                      placeholder="Enter code"
                      class="flex-1 px-3 py-2 border border-light-gray rounded-lg text-body-sm uppercase focus:outline-none focus:ring-2 focus:ring-violet-500/20 focus:border-violet-500"
                      @keyup.enter="applyDiscountCode"
                      :disabled="discountLoading"
                    />
                    <button
                      @click="applyDiscountCode"
                      :disabled="!discountCodeInput.trim() || discountLoading"
                      class="px-4 py-2 bg-raspberry-500 text-white text-body-sm font-medium rounded-lg hover:bg-raspberry-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                      {{ discountLoading ? 'Checking...' : 'Apply' }}
                    </button>
                  </div>
                  <p v-if="discountError" class="text-caption text-raspberry-600 mt-1">{{ discountError }}</p>
                  <p v-if="discountSuccess" class="text-caption text-spring-600 mt-1">{{ discountSuccess }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Checkout Widget (right) -->
          <div class="lg:col-span-3">
            <!-- Initialisation Error -->
            <div v-if="error" class="bg-raspberry-100 border border-raspberry-600/20 rounded-lg p-4 mb-4">
              <p class="text-body-sm text-raspberry-600">{{ error }}</p>
              <button @click="initCheckout" class="mt-2 text-sm text-raspberry-700 underline hover:no-underline">
                Try again
              </button>
            </div>

            <!-- Payment Error -->
            <div v-if="paymentError" class="bg-raspberry-100 border border-raspberry-600/20 rounded-lg p-4 mb-4">
              <p class="text-body-sm text-raspberry-600">{{ paymentError }}</p>
            </div>

            <!-- Widget Container -->
            <div
              v-show="!paymentComplete && !error"
              class="bg-white rounded-xl border border-light-gray p-6"
            >
              <h2 class="text-h4 font-semibold text-horizon-500 mb-4">
                {{ isUpgrade ? 'Upgrade Payment' : 'Payment Method' }}
              </h2>
              <div ref="checkoutContainer" class="min-h-[300px] revolut-checkout-container"></div>
              <p class="text-caption text-neutral-500 mt-3 text-center">
                Your subscription will automatically renew each {{ billingCycle === 'monthly' ? 'month' : 'year' }}.
                You can cancel at any time from your profile.
              </p>
            </div>

            <!-- Processing Overlay -->
            <div v-if="processing" class="bg-white rounded-xl border border-light-gray p-8 text-center">
              <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-raspberry-600 mx-auto mb-4"></div>
              <p class="text-body-base text-neutral-500">Confirming your payment...</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Success Modal -->
      <div
        v-if="paymentComplete"
        class="fixed inset-0 z-50 overflow-y-auto"
        role="dialog"
        aria-modal="true"
      >
        <div class="flex items-center justify-center min-h-screen px-4">
          <div class="fixed inset-0 bg-savannah-1000/75 transition-opacity"></div>

          <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-8 z-10 text-center">
            <div class="mx-auto w-16 h-16 bg-spring-100 rounded-full flex items-center justify-center mb-4">
              <svg class="w-8 h-8 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
              </svg>
            </div>
            <h2 class="text-h3 font-semibold text-horizon-500 mb-2">
              {{ isUpgrade ? 'Upgrade Successful' : 'Payment Successful' }}
            </h2>
            <p class="text-body-sm text-neutral-500 mb-6">
              {{ isUpgrade
                ? `You have been upgraded to the ${planDisplayName} plan.`
                : `Your ${planDisplayName} plan is now active.`
              }}
            </p>
            <button
              @click="goToDashboard"
              class="btn-primary w-full"
            >
              Go to Dashboard
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import api from '@/services/api';
import { currencyMixin } from '@/mixins/currencyMixin';
import logger from '@/utils/logger';
import { fireConversion as fireAwinConversion } from '@/utils/awinTracking';

/**
 * Load the Revolut Merchant SDK from CDN.
 * The npm package (@revolut/checkout v1.1.24) does NOT expose embeddedCheckout
 * as a static method — it only exists on instances created with an order token.
 * Loading the CDN script directly gives us the documented static API:
 *   RevolutCheckout.embeddedCheckout({ publicToken, mode, ... })
 */
function loadRevolutSDK(sandbox) {
  const scriptId = 'revolut-checkout';

  // Return cached global if already loaded
  if (window.RevolutCheckout?.embeddedCheckout) {
    return Promise.resolve(window.RevolutCheckout);
  }

  // Return existing loading promise if script tag already injected
  const existing = document.getElementById(scriptId);
  if (existing && existing._loadPromise) {
    return existing._loadPromise;
  }

  const src = sandbox
    ? 'https://sandbox-merchant.revolut.com/embed.js'
    : 'https://merchant.revolut.com/embed.js';

  const script = document.createElement('script');
  script.id = scriptId;
  script.src = src;
  script.async = true;

  const promise = new Promise((resolve, reject) => {
    script.onload = () => {
      if (window.RevolutCheckout) {
        resolve(window.RevolutCheckout);
      } else {
        reject(new Error('RevolutCheckout not available after script load'));
      }
    };
    script.onerror = () => reject(new Error('Failed to load Revolut SDK'));
  });

  script._loadPromise = promise;
  document.head.appendChild(script);

  return promise;
}

// Module-level variable: stores the validated discount code so the Revolut
// createOrder callback can always access it, regardless of execution context.
// Updated by applyDiscountCode(), read by createOrder callback.
let _validatedDiscountCode = '';

export default {
  name: 'CheckoutPage',

  components: {
    AppLayout,
  },

  mixins: [currencyMixin],

  data() {
    return {
      error: null,
      paymentError: null,
      processing: false,
      paymentComplete: false,
      destroyWidget: null,
      revolutOrderId: null,
      planData: null,
      upgradeAmount: null,
      monthsRemaining: null,
      // Discount code
      showDiscountInput: false,
      discountCodeInput: '',
      discountLoading: false,
      discountError: null,
      discountSuccess: null,
      discountApplied: false,
      discountAmountPence: 0,
      discountDescription: '',
      finalAmountPence: null,
      originalAmountPence: null,
    };
  },

  computed: {
    plan() {
      return this.$route.query.plan;
    },

    billingCycle() {
      return this.$route.query.cycle;
    },

    planDisplayName() {
      if (!this.plan) return '';
      return this.plan.charAt(0).toUpperCase() + this.plan.slice(1);
    },

    isUpgrade() {
      return this.$route.query.upgrade === 'true';
    },

    userEmail() {
      return this.$store.state.user?.email || '';
    },

    planPrice() {
      if (!this.planData) return '...';
      const launchPence = this.billingCycle === 'monthly'
        ? this.planData.launch_monthly_price
        : this.planData.launch_yearly_price;
      const fullPence = this.billingCycle === 'monthly'
        ? this.planData.monthly_price
        : this.planData.yearly_price;
      return this.formatCurrencyWithPence((launchPence || fullPence) / 100);
    },

    displayPrice() {
      if (this.discountApplied && this.finalAmountPence !== null) {
        return this.formatCurrencyWithPence(this.finalAmountPence / 100);
      }
      return this.planPrice;
    },

    originalPrice() {
      if (this.originalAmountPence !== null) {
        return this.formatCurrencyWithPence(this.originalAmountPence / 100);
      }
      return this.planPrice;
    },

    discountAmountPounds() {
      return this.discountAmountPence / 100;
    },

    prefilledDiscountCode() {
      return this.$route.query.discount || '';
    },
  },

  mounted() {
    if (this.plan && this.billingCycle) {
      this.fetchPlanData();
      // If a prefilled discount code exists, apply it FIRST — then initCheckout
      // will be called after the discount is validated (via reinitializeCheckout).
      // This prevents creating a Revolut order at full price then immediately
      // creating another at the discounted price.
      if (this.prefilledDiscountCode) {
        this.discountCodeInput = this.prefilledDiscountCode;
        this.showDiscountInput = true;
        this.applyDiscountCode();
      } else {
        this.initCheckout();
      }
    }
  },

  beforeUnmount() {
    if (this.destroyWidget) {
      this.destroyWidget();
    }
  },

  methods: {
    async fetchPlanData() {
      try {
        const response = await api.get('/payment/plans');
        const plans = response.data.plans || [];
        this.planData = plans.find(p => p.slug === this.plan) || null;
      } catch {
        // Non-critical — price just shows "..."
      }
    },

    async initCheckout() {
      this.error = null;
      this.paymentError = null;

      // Wait for DOM to render the container
      await this.$nextTick();

      if (!this.$refs.checkoutContainer) {
        this.error = 'Failed to initialise checkout: container not found.';
        return;
      }

      try {
        const isSandbox = import.meta.env.VITE_REVOLUT_SANDBOX === 'true';
        const RevolutCheckout = await loadRevolutSDK(isSandbox);

        const { destroy } = await RevolutCheckout.embeddedCheckout({
          publicToken: import.meta.env.VITE_REVOLUT_PUBLIC_KEY,
          mode: isSandbox ? 'sandbox' : 'prod',
          locale: 'auto',
          target: this.$refs.checkoutContainer,
          createOrder: async () => {
            // Called by widget when user clicks Pay
            const endpoint = this.isUpgrade ? '/payment/upgrade' : '/payment/create-order';
            // Read discount code from module-level variable (not Vue reactive data)
            // because the Revolut SDK may invoke this callback in a context where
            // Vue's `this` is not accessible.
            const discountCode = _validatedDiscountCode;
            const payload = {
              plan: this.plan,
              billing_cycle: this.billingCycle,
            };
            if (discountCode) {
              payload.discount_code = discountCode;
            }
            const response = await api.post(endpoint, payload);
            // Store the internal UUID for confirmPayment call
            // CRITICAL: onSuccess's orderId is the TOKEN, not the UUID
            this.revolutOrderId = response.data.order_id;
            // Store upgrade details for display
            if (this.isUpgrade && response.data.upgrade_amount) {
              this.upgradeAmount = response.data.upgrade_amount;
              this.monthsRemaining = response.data.months_remaining;
            }
            // Return token to widget as { publicId }
            return { publicId: response.data.token };
          },
          onSuccess: () => {
            // orderId in callback is the ORDER TOKEN (not UUID) per Revolut docs
            // We use this.revolutOrderId (the UUID) for the confirm call
            this.handlePaymentSuccess();
          },
          onError: ({ error }) => {
            this.paymentError = error.message || 'Payment failed. Please try again.';
          },
          onCancel: () => {
            // User cancelled — stay on page, no action needed
          },
          email: this.userEmail,
        });
        this.destroyWidget = destroy;
      } catch (err) {
        if (err.name === 'RevolutCheckout') {
          this.error = 'Failed to initialise checkout: ' + err.message;
        } else {
          this.error = 'Failed to initialise payment system. Please try again.';
        }
        logger.error('Checkout init failed', err);
      }
    },

    async handlePaymentSuccess() {
      this.processing = true;
      // Revolut fires onSuccess while order may still be 'processing'.
      // Retry confirm up to 5 times with delay to allow Revolut state to settle.
      const maxRetries = 5;
      const delayMs = 2000;

      for (let attempt = 1; attempt <= maxRetries; attempt++) {
        try {
          const confirmResponse = await api.post('/payment/confirm', { order_id: this.revolutOrderId });
          this.paymentComplete = true;
          this.processing = false;

          // Analytics tracking — skip for preview/admin/test users
          const trackingUser = this.$store.state.auth?.user;
          const skipTracking = trackingUser?.is_preview_user || trackingUser?.is_admin;

          // GA4 ecommerce purchase tracking
          if (!skipTracking && typeof gtag === 'function' && this.planData) {
            const pricePence = this.billingCycle === 'monthly'
              ? (this.planData.launch_monthly_price || this.planData.monthly_price)
              : (this.planData.launch_yearly_price || this.planData.yearly_price);
            const priceGBP = (pricePence || 0) / 100;

            gtag('event', 'purchase', {
              transaction_id: this.revolutOrderId,
              value: priceGBP,
              currency: 'GBP',
              items: [{
                item_id: this.plan,
                item_name: `Fynla ${this.planDisplayName} (${this.billingCycle})`,
                price: priceGBP,
                quantity: 1,
                item_category: this.isUpgrade ? 'upgrade' : 'new_subscription',
              }],
            });
          }

          // Meta Pixel: Subscribe
          if (!skipTracking && typeof fbq === 'function' && this.planData) {
            const monthlyPence = this.planData.launch_monthly_price || this.planData.monthly_price;
            const yearlyPence = this.planData.launch_yearly_price || this.planData.yearly_price;
            const isMonthly = this.billingCycle === 'monthly';
            const priceGBP = ((isMonthly ? monthlyPence : yearlyPence) || 0) / 100;
            const ltvGBP = ((isMonthly ? monthlyPence * 12 : yearlyPence) || 0) / 100;
            fbq('track', 'Subscribe', {
              currency: 'GBP',
              value: priceGBP,
              predicted_ltv: ltvGBP,
            });
          }

          // Awin affiliate conversion — browser-side pixel. Backend returns
          // the full payload (order_ref, amount, currency, voucher, customer
          // acquisition flag) only when AWIN_ENABLED=true and the user is
          // not an admin, so this is a no-op otherwise.
          const awinPayload = confirmResponse?.data?.awin;
          if (awinPayload) {
            fireAwinConversion(awinPayload);
          }

          return;
        } catch (err) {
          const state = err.response?.data?.state;
          // If Revolut state hasn't settled yet, wait and retry
          if (attempt < maxRetries && (state === 'pending' || state === 'processing' || err.response?.status === 400)) {
            await new Promise(resolve => setTimeout(resolve, delayMs));
            continue;
          }
          // Final attempt failed — still show success (webhook backup will handle)
          this.paymentComplete = true;
          this.processing = false;
          return;
        }
      }
    },

    async applyDiscountCode() {
      const code = this.discountCodeInput.trim();
      if (!code) return;

      this.discountLoading = true;
      this.discountError = null;
      this.discountSuccess = null;

      try {
        const response = await api.post('/payment/validate-discount', {
          code,
          plan: this.plan,
          billing_cycle: this.billingCycle,
        });

        if (response.data.success) {
          const data = response.data.data;
          this.discountApplied = true;
          this.discountAmountPence = data.discount_amount;
          this.finalAmountPence = data.final_amount;
          this.originalAmountPence = data.original_amount;
          this.discountDescription = data.discount_description;
          this.discountSuccess = response.data.message;
          // Store in module-level variable so Revolut callback can always access it
          _validatedDiscountCode = code;
          // CRITICAL: Reinitialize the Revolut widget so that createOrder fires
          // again with the discount code. The SDK calls createOrder at init time,
          // so the previous order was created at full price.
          await this.reinitializeCheckout();
        } else {
          this.discountApplied = false;
          this.discountError = response.data.message;
          _validatedDiscountCode = '';
        }
      } catch (err) {
        this.discountApplied = false;
        this.discountError = err.response?.data?.message || 'Failed to validate discount code.';
      } finally {
        this.discountLoading = false;
      }
    },

    async reinitializeCheckout() {
      if (this.destroyWidget) {
        this.destroyWidget();
        this.destroyWidget = null;
      }
      await this.initCheckout();
    },

    goToDashboard() {
      this.$router.push({ path: '/dashboard', query: { payment: 'success' } });
    },

    goBack() {
      this.$router.back();
    },
  },
};
</script>

<style scoped>
/* Hide Revolut's duplicate "Payment method" heading inside the iframe.
   clip-path on the iframe clips the top 40px (the heading) while
   margin-top pulls it up so there is no gap. Using clip-path instead of
   overflow:hidden on the container avoids breaking the Revolut SDK's
   postMessage-based iframe auto-resize. min-height is a safety net for
   when the SDK sets a tiny initial height (known sandbox issue). */
.revolut-checkout-container :deep(iframe[src*="embedded-checkout"]) {
  margin-top: -40px;
  clip-path: inset(40px 0 0 0);
  min-height: 500px !important;
}
</style>
