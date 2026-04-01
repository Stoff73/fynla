<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">
          Security &
          <span class="text-raspberry-300">privacy</span>
        </h1>
        <p class="text-lg text-white/70">
          We take the security of your financial data seriously. Learn about the measures we have in place to protect your information.
        </p>
      </div>
    </div>

    <!-- Intro -->
    <section class="py-12 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <p class="text-sm text-neutral-500 leading-relaxed">
          Your financial data is among the most sensitive information you have. At Fynla, protecting it isn't just a technical requirement — it's a core value.
          We don't sell your data, we don't share it with third parties, and we don't earn commission from financial product providers.
          Every decision we make about how your data is stored, accessed, and protected is guided by one principle: your information belongs to you.
        </p>
      </div>
    </section>

    <!-- Security sections -->
    <section class="pb-12 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        <!-- Authentication & account security -->
        <div class="bg-light-pink-100 rounded-xl p-6 sm:p-8">
          <h2 class="text-xl sm:text-2xl md:text-3xl font-bold text-horizon-500 mb-6">Authentication & account security</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div class="bg-white rounded-xl p-5">
              <h4 class="text-base font-bold text-horizon-500 mb-1 flex items-center gap-2"><svg class="w-5 h-5 text-horizon-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>Multi-factor authentication</h4>
              <p class="text-sm text-neutral-500">App-based TOTP authentication with backup recovery codes for secure account access.</p>
            </div>
            <div class="bg-white rounded-xl p-5">
              <h4 class="text-base font-bold text-horizon-500 mb-1 flex items-center gap-2"><svg class="w-5 h-5 text-horizon-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>Session management</h4>
              <p class="text-sm text-neutral-500">View and revoke active sessions, with automatic logout after periods of inactivity.</p>
            </div>
            <div class="bg-white rounded-xl p-5">
              <h4 class="text-base font-bold text-horizon-500 mb-1 flex items-center gap-2"><svg class="w-5 h-5 text-horizon-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>Brute-force protection</h4>
              <p class="text-sm text-neutral-500">Progressive lockout after repeated failed login attempts protects against unauthorised access.</p>
            </div>
            <div class="bg-white rounded-xl p-5">
              <h4 class="text-base font-bold text-horizon-500 mb-1 flex items-center gap-2"><svg class="w-5 h-5 text-horizon-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>Password security</h4>
              <p class="text-sm text-neutral-500">Password breach checking and prevention of password reuse keeps your account secure.</p>
            </div>
          </div>
        </div>

        <!-- Data protection & encryption -->
        <div class="bg-light-blue-100 rounded-xl p-6 sm:p-8">
          <h2 class="text-xl sm:text-2xl md:text-3xl font-bold text-horizon-500 mb-6">Data protection & encryption</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div class="bg-white rounded-xl p-5">
              <h4 class="text-base font-bold text-horizon-500 mb-1 flex items-center gap-2"><svg class="w-5 h-5 text-horizon-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>Encryption at rest</h4>
              <p class="text-sm text-neutral-500">AES-256 encryption for sensitive financial fields including balances and account details.</p>
            </div>
            <div class="bg-white rounded-xl p-5">
              <h4 class="text-base font-bold text-horizon-500 mb-1 flex items-center gap-2"><svg class="w-5 h-5 text-horizon-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>Key management</h4>
              <p class="text-sm text-neutral-500">Centralised key management with regular key rotation policies to maintain security.</p>
            </div>
            <div class="bg-white rounded-xl p-5">
              <h4 class="text-base font-bold text-horizon-500 mb-1 flex items-center gap-2"><svg class="w-5 h-5 text-horizon-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>Encrypted backups</h4>
              <p class="text-sm text-neutral-500">All backups are encrypted with secure retention and deletion policies.</p>
            </div>
            <div class="bg-white rounded-xl p-5">
              <h4 class="text-base font-bold text-horizon-500 mb-1 flex items-center gap-2"><svg class="w-5 h-5 text-horizon-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>Secrets management</h4>
              <p class="text-sm text-neutral-500">Production secrets are stored in secure vault services, never in code or configuration files.</p>
            </div>
          </div>
        </div>

        <!-- Access control -->
        <div class="bg-light-pink-100 rounded-xl p-6 sm:p-8">
          <h2 class="text-xl sm:text-2xl md:text-3xl font-bold text-horizon-500 mb-6">Access control</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div class="bg-white rounded-xl p-5">
              <h4 class="text-base font-bold text-horizon-500 mb-1 flex items-center gap-2"><svg class="w-5 h-5 text-horizon-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>Role-based access control</h4>
              <p class="text-sm text-neutral-500">Distinct permission levels for different user types ensure appropriate access.</p>
            </div>
            <div class="bg-white rounded-xl p-5">
              <h4 class="text-base font-bold text-horizon-500 mb-1 flex items-center gap-2"><svg class="w-5 h-5 text-horizon-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>Principle of least privilege</h4>
              <p class="text-sm text-neutral-500">Users and systems only have access to the minimum data required for their function.</p>
            </div>
            <div class="bg-white rounded-xl p-5">
              <h4 class="text-base font-bold text-horizon-500 mb-1 flex items-center gap-2"><svg class="w-5 h-5 text-horizon-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>Internal access logging</h4>
              <p class="text-sm text-neutral-500">Every access to user data is logged and auditable for complete transparency.</p>
            </div>
          </div>
        </div>

        <!-- Auditability & monitoring -->
        <div class="bg-light-blue-100 rounded-xl p-6 sm:p-8">
          <h2 class="text-xl sm:text-2xl md:text-3xl font-bold text-horizon-500 mb-6">Auditability & monitoring</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div class="bg-white rounded-xl p-5">
              <h4 class="text-base font-bold text-horizon-500 mb-1 flex items-center gap-2"><svg class="w-5 h-5 text-horizon-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>Comprehensive audit logs</h4>
              <p class="text-sm text-neutral-500">All login attempts, data access, and changes to financial plans are logged.</p>
            </div>
            <div class="bg-white rounded-xl p-5">
              <h4 class="text-base font-bold text-horizon-500 mb-1 flex items-center gap-2"><svg class="w-5 h-5 text-horizon-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>Immutable logs</h4>
              <p class="text-sm text-neutral-500">Append-only, tamper-resistant log storage ensures audit trail integrity.</p>
            </div>
            <div class="bg-white rounded-xl p-5">
              <h4 class="text-base font-bold text-horizon-500 mb-1 flex items-center gap-2"><svg class="w-5 h-5 text-horizon-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>Suspicious activity alerts</h4>
              <p class="text-sm text-neutral-500">Automated alerts for unusual behaviour such as unexpected login locations.</p>
            </div>
          </div>
        </div>

        <!-- GDPR & privacy compliance -->
        <div class="bg-light-pink-100 rounded-xl p-6 sm:p-8">
          <h2 class="text-xl sm:text-2xl md:text-3xl font-bold text-horizon-500 mb-6">GDPR & privacy compliance</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div class="bg-white rounded-xl p-5">
              <h4 class="text-base font-bold text-horizon-500 mb-1 flex items-center gap-2"><svg class="w-5 h-5 text-horizon-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>Right to erasure</h4>
              <p class="text-sm text-neutral-500">Full deletion workflow including removal from backups after retention period.</p>
            </div>
            <div class="bg-white rounded-xl p-5">
              <h4 class="text-base font-bold text-horizon-500 mb-1 flex items-center gap-2"><svg class="w-5 h-5 text-horizon-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>Data minimisation</h4>
              <p class="text-sm text-neutral-500">We only collect and store data that is necessary for your financial planning.</p>
            </div>
            <div class="bg-white rounded-xl p-5">
              <h4 class="text-base font-bold text-horizon-500 mb-1 flex items-center gap-2"><svg class="w-5 h-5 text-horizon-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>Consent tracking</h4>
              <p class="text-sm text-neutral-500">Timestamped consent records for terms, privacy policy, and any marketing preferences.</p>
            </div>
            <div class="bg-white rounded-xl p-5">
              <h4 class="text-base font-bold text-horizon-500 mb-1 flex items-center gap-2"><svg class="w-5 h-5 text-horizon-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>Data export</h4>
              <p class="text-sm text-neutral-500">Export all your data in a portable format at any time from your account settings.</p>
            </div>
          </div>
        </div>

        <!-- API & application security -->
        <div class="bg-light-blue-100 rounded-xl p-6 sm:p-8">
          <h2 class="text-xl sm:text-2xl md:text-3xl font-bold text-horizon-500 mb-6">API & application security</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div class="bg-white rounded-xl p-5">
              <h4 class="text-base font-bold text-horizon-500 mb-1 flex items-center gap-2"><svg class="w-5 h-5 text-horizon-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>Rate limiting</h4>
              <p class="text-sm text-neutral-500">Per-user and per-token rate limits protect against abuse and ensure fair usage.</p>
            </div>
            <div class="bg-white rounded-xl p-5">
              <h4 class="text-base font-bold text-horizon-500 mb-1 flex items-center gap-2"><svg class="w-5 h-5 text-horizon-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>Token security</h4>
              <p class="text-sm text-neutral-500">Short-lived access tokens with automatic rotation maintain secure sessions.</p>
            </div>
            <div class="bg-white rounded-xl p-5">
              <h4 class="text-base font-bold text-horizon-500 mb-1 flex items-center gap-2"><svg class="w-5 h-5 text-horizon-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>Permission scoping</h4>
              <p class="text-sm text-neutral-500">Read-only vs write permissions ensure tokens only have necessary access.</p>
            </div>
          </div>
        </div>

        <!-- Business continuity -->
        <div class="bg-light-pink-100 rounded-xl p-6 sm:p-8">
          <h2 class="text-xl sm:text-2xl md:text-3xl font-bold text-horizon-500 mb-6">Business continuity</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div class="bg-white rounded-xl p-5">
              <h4 class="text-base font-bold text-horizon-500 mb-1 flex items-center gap-2"><svg class="w-5 h-5 text-horizon-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>Disaster recovery</h4>
              <p class="text-sm text-neutral-500">Defined recovery objectives with regular testing ensure rapid restoration.</p>
            </div>
            <div class="bg-white rounded-xl p-5">
              <h4 class="text-base font-bold text-horizon-500 mb-1 flex items-center gap-2"><svg class="w-5 h-5 text-horizon-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>Uptime monitoring</h4>
              <p class="text-sm text-neutral-500">24/7 monitoring with automatic failover ensures continuous availability.</p>
            </div>
          </div>
        </div>

      </div>
    </section>

    <!-- Important notice -->
    <section class="py-12 bg-horizon-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h3 class="text-lg font-bold text-white mb-3">Important notice</h3>
        <p class="text-sm text-white/70 mb-3">
          Fynla is a financial planning tool designed to help you organise and visualise your financial information. It does not constitute regulated financial advice. The projections and calculations provided are for illustrative purposes only and should not be relied upon as the sole basis for financial decisions.
        </p>
        <p class="text-sm text-white/70">
          We recommend consulting with a qualified financial adviser for personalised advice tailored to your specific circumstances.
        </p>
      </div>
    </section>

    <!-- Contact CTA -->
    <section class="py-10 bg-light-pink-100">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 mb-6">Have questions about our security practices?</h2>
        <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
          <router-link
            to="/contact"
            class="inline-block px-8 py-3 bg-raspberry-500 text-white text-sm font-semibold rounded-lg hover:bg-raspberry-600 transition-colors"
          >
            Contact us
          </router-link>
          <router-link
            to="/register"
            class="inline-block px-8 py-3 bg-white text-horizon-500 text-sm font-semibold rounded-lg border border-light-gray hover:bg-savannah-50 transition-colors"
          >
            Get started
          </router-link>
        </div>
      </div>
    </section>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default {
  name: 'SecurityPage',

  components: {
    PublicLayout,
  },

  mounted() {
    document.title = 'Security & privacy | Fynla';
    const meta = document.querySelector('meta[name="description"]');
    if (meta) {
      meta.setAttribute('content', 'Learn how Fynla protects your financial data. AES-256 encryption, multi-factor authentication, GDPR compliance, and UK-hosted servers. Your data is never sold or shared.');
    }
  },
};
</script>
