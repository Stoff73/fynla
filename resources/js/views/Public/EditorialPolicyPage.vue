<template>
  <PublicLayout>
    <!-- Hero Header -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">
          Editorial
          <span class="text-raspberry-300">Policy</span>
        </h1>
        <p class="text-lg text-white/70">
          How we create, review and maintain the financial content on Fynla.
        </p>
      </div>
    </div>

    <div class="bg-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">

        <p class="text-sm text-horizon-400 mb-10 pb-8 border-b border-light-gray">Last updated: 4 April 2026</p>

        <!-- Our Commitment to Accuracy -->
        <section class="mb-10">
          <h2 class="text-xl font-bold text-horizon-500 mb-4 pb-2 border-b border-light-gray">Our Commitment to Accuracy</h2>
          <p class="text-neutral-500 leading-relaxed mb-4">
            Fynla operates in the personal finance space, which Google classifies as "Your Money or Your Life" (YMYL) content. We take this responsibility seriously. Every piece of financial content we publish is held to the highest standards of accuracy, clarity and regulatory compliance.
          </p>
          <p class="text-neutral-500 leading-relaxed">
            Our editorial policy is designed to ensure that readers can trust the information on our platform and make informed decisions about their financial lives.
          </p>
        </section>

        <!-- Who Writes Our Content -->
        <section class="mb-10">
          <h2 class="text-xl font-bold text-horizon-500 mb-4 pb-2 border-b border-light-gray">Who Writes Our Content</h2>
          <p class="text-neutral-500 leading-relaxed">
            All financial content on Fynla is written by our co-founders, <router-link to="/about#founders" class="text-raspberry-500 hover:text-raspberry-600 underline">Chris Slater-Jones and Brett Isenberg</router-link>, who bring over 40 years of combined experience in UK financial services, financial advice, accounting and business leadership. Where we engage external contributors, all content is reviewed and approved by at least one co-founder before publication.
          </p>
        </section>

        <!-- Our Editorial Process -->
        <section class="mb-10">
          <h2 class="text-xl font-bold text-horizon-500 mb-4 pb-2 border-b border-light-gray">Our Editorial Process</h2>
          <div class="space-y-6">
            <div>
              <h3 class="text-base font-semibold text-horizon-500 mb-1">Research</h3>
              <p class="text-neutral-500 leading-relaxed">
                Every article begins with research drawn from primary, authoritative sources including GOV.UK, the Financial Conduct Authority (FCA), HM Revenue &amp; Customs (HMRC), MoneyHelper and the Pensions Regulator.
              </p>
            </div>
            <div>
              <h3 class="text-base font-semibold text-horizon-500 mb-1">Drafting</h3>
              <p class="text-neutral-500 leading-relaxed">
                Content is written in clear, jargon-free language. We aim for a reading level that is accessible to a broad audience while remaining technically accurate.
              </p>
            </div>
            <div>
              <h3 class="text-base font-semibold text-horizon-500 mb-1">Expert Review</h3>
              <p class="text-neutral-500 leading-relaxed">
                All financial content is reviewed by at least one qualified co-founder before publication. We check for factual accuracy, regulatory compliance and clarity.
              </p>
            </div>
            <div>
              <h3 class="text-base font-semibold text-horizon-500 mb-1">Publication</h3>
              <p class="text-neutral-500 leading-relaxed">
                Every article displays the author's name, credentials and the date of publication or last review.
              </p>
            </div>
            <div>
              <h3 class="text-base font-semibold text-horizon-500 mb-1">Ongoing Maintenance</h3>
              <p class="text-neutral-500 leading-relaxed">
                We review published content at least annually, or sooner when UK tax rules, allowances or regulations change. All articles display a "Last Reviewed" date.
              </p>
            </div>
          </div>
        </section>

        <!-- Sources and Citations -->
        <section class="mb-10">
          <h2 class="text-xl font-bold text-horizon-500 mb-4 pb-2 border-b border-light-gray">Sources and Citations</h2>
          <p class="text-neutral-500 leading-relaxed">
            We cite primary sources wherever possible. Our preferred sources include GOV.UK for tax and benefits information, FCA for regulatory guidance, HMRC for tax rules and thresholds, MoneyHelper for consumer guidance, and the Pensions Regulator for pension-related content. We link to these sources within our articles so readers can verify information independently.
          </p>
        </section>

        <!-- What We Do Not Do -->
        <section class="mb-10">
          <h2 class="text-xl font-bold text-horizon-500 mb-4 pb-2 border-b border-light-gray">What We Do Not Do</h2>
          <p class="text-neutral-500 leading-relaxed mb-4">
            Fynla provides financial information and planning tools. We do not provide personalised financial advice. Our content is educational and informational in nature. We are not regulated by the FCA as financial advisers, and our content should not be treated as a substitute for professional advice tailored to your individual circumstances.
          </p>
          <p class="text-neutral-500 leading-relaxed">
            Where relevant, we include appropriate risk warnings on investment-related content in line with FCA expectations.
          </p>
        </section>

        <!-- Independence and Advertising -->
        <section class="mb-10">
          <h2 class="text-xl font-bold text-horizon-500 mb-4 pb-2 border-b border-light-gray">Independence and Advertising</h2>
          <p class="text-neutral-500 leading-relaxed">
            Our editorial content is independent. We do not accept payment from financial product providers to influence our content. If we publish comparison articles or product reviews, our assessments are based on our professional experience and publicly available product information. Any commercial relationships are clearly disclosed.
          </p>
        </section>

        <!-- Corrections -->
        <section class="mb-10">
          <h2 class="text-xl font-bold text-horizon-500 mb-4 pb-2 border-b border-light-gray">Corrections</h2>
          <p class="text-neutral-500 leading-relaxed">
            If we identify an error in published content, we correct it promptly and note the correction with the date it was made. If you spot something that looks incorrect, we welcome feedback — please <router-link to="/contact" class="text-raspberry-500 hover:text-raspberry-600 underline">contact us</router-link>.
          </p>
        </section>

        <!-- Contact Us -->
        <section class="mb-10">
          <h2 class="text-xl font-bold text-horizon-500 mb-4 pb-2 border-b border-light-gray">Contact Us</h2>
          <p class="text-neutral-500 leading-relaxed">
            If you have questions about our editorial policy or the content on our platform, please <router-link to="/contact" class="text-raspberry-500 hover:text-raspberry-600 underline">get in touch</router-link> — we are always happy to hear from customers.
          </p>
        </section>

      </div>
    </div>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default {
  name: 'EditorialPolicyPage',

  components: {
    PublicLayout,
  },

  mounted() {
    document.title = 'Editorial Policy | How We Create Trusted Financial Content — Fynla';
    document.querySelector('meta[name="description"]')?.setAttribute('content', 'Learn how Fynla creates, reviews and maintains its financial content. Our editorial policy ensures accuracy, transparency and compliance with UK regulations.');
  },
};
</script>
