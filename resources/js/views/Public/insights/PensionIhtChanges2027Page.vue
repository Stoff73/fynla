<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">
          Pension Inheritance Tax Changes: April 2027
        </h1>
      </div>
    </div>

    <!-- Article -->
    <section class="py-12 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Back link + categories -->
        <div class="flex items-center gap-3 mb-2">
          <router-link to="/insights" class="text-sm text-raspberry-500 hover:underline font-medium">&larr; Back to Insights</router-link>
          <span class="text-[0.6rem] font-semibold px-1.5 py-0.5 rounded-md uppercase tracking-wide bg-violet-50 text-violet-700">Pensions</span>
          <span class="text-[0.6rem] font-semibold px-1.5 py-0.5 rounded-md uppercase tracking-wide bg-violet-50 text-violet-700">Estate planning</span>
        </div>
        <p class="text-xs text-neutral-400 mb-8">Published March 2026</p>

        <article class="space-y-10">

          <!-- What's Changing -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">What's changing</h2>
            <p class="text-sm text-neutral-500 leading-relaxed mb-3">
              Currently, unused pension pots pass to your beneficiaries outside your estate. If you die before 75, recipients typically pay no tax at all. If you die after 75, they pay income tax at their marginal rate when they draw down.
            </p>
            <p class="text-sm text-neutral-500 leading-relaxed">
              From April 2027, unused pension pots will be included in your estate for Inheritance Tax purposes. This is the biggest change to pension death benefits in a generation.
            </p>
          </div>

          <!-- Quote callout -->
          <blockquote class="border-l-4 border-raspberry-500 bg-light-pink-100 rounded-r-xl px-6 py-5 my-2">
            <p class="text-lg font-semibold text-horizon-500 italic leading-relaxed">
              "From April 2027, unused pension pots will be included in your estate for inheritance tax purposes &mdash; the biggest change to pension death benefits in a generation."
            </p>
          </blockquote>

          <!-- Who's Affected -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">Who's affected</h2>
            <p class="text-sm text-neutral-500 leading-relaxed">
              Anyone with a Defined Contribution pension pot and an estate that, with the pension included, exceeds the nil rate band (&pound;325,000) plus the residence nil rate band (&pound;175,000). If you have a property, savings, investments, <em>and</em> a pension pot, your combined estate could now face an Inheritance Tax bill that wouldn't exist under current rules.
            </p>
          </div>

          <!-- The Numbers -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">The numbers</h2>
            <p class="text-sm text-neutral-500 leading-relaxed mb-3">
              Here's a simple example. Suppose you have:
            </p>
            <ul class="text-sm text-neutral-500 leading-relaxed list-disc pl-5 space-y-1 mb-3">
              <li>A &pound;300,000 house</li>
              <li>&pound;100,000 in savings</li>
              <li>A &pound;200,000 pension pot</li>
            </ul>
            <p class="text-sm text-neutral-500 leading-relaxed mb-3">
              Under current rules, your pension sits outside the estate. Your taxable estate is &pound;400,000 &mdash; within the combined nil rate band and residence nil rate band of &pound;500,000. No Inheritance Tax is due.
            </p>
            <p class="text-sm text-neutral-500 leading-relaxed">
              From April 2027, your estate becomes &pound;600,000. Inheritance Tax is due on &pound;100,000 at 40% = <strong class="text-horizon-500">&pound;40,000</strong>.
            </p>
          </div>

          <!-- What Can You Do -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">What can you do?</h2>
            <ol class="text-sm text-neutral-500 leading-relaxed list-decimal pl-5 space-y-3">
              <li>
                <strong class="text-horizon-500">Draw down your pension faster in retirement.</strong>
                This reduces the pot sitting in your estate. It needs to be balanced against income tax, but the maths may now favour drawing sooner.
              </li>
              <li>
                <strong class="text-horizon-500">Consider gifting strategies using the seven-year rule.</strong>
                Gifts made more than seven years before death fall outside your estate entirely.
              </li>
              <li>
                <strong class="text-horizon-500">Review your life insurance &mdash; is it in trust?</strong>
                Policies written in trust sit outside your estate. If yours isn't, now is the time to fix that.
              </li>
              <li>
                <strong class="text-horizon-500">Model the impact now so you're not surprised.</strong>
                Use Fynla's estate planning tools to see how the 2027 changes affect your position before they take effect.
              </li>
            </ol>
          </div>

          <!-- Double Taxation Risk -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">Double taxation risk</h2>
            <p class="text-sm text-neutral-500 leading-relaxed mb-3">
              There is a risk of double taxation: your pension pot could be subject to both Inheritance Tax <em>and</em> income tax when beneficiaries draw it down. The government has said they will address this, but details are not yet confirmed.
            </p>
            <p class="text-sm text-neutral-500 leading-relaxed">
              This is worth watching closely. Until the rules are finalised, planning around worst-case assumptions is prudent.
            </p>
          </div>

          <!-- Related Links -->
          <div class="bg-light-blue-100 rounded-2xl p-6">
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">Related</h2>
            <ul class="space-y-2">
              <li>
                <router-link to="/features/iht-planning" class="text-sm text-raspberry-500 hover:underline">Inheritance Tax Planning in Fynla</router-link>
              </li>
              <li>
                <router-link to="/features/pension-tracker" class="text-sm text-raspberry-500 hover:underline">Pension Tracker</router-link>
              </li>
              <li>
                <router-link to="/learn/glossary#inheritance-tax" class="text-sm text-raspberry-500 hover:underline">Glossary: Inheritance Tax</router-link>
              </li>
              <li>
                <router-link to="/learn/what-is-drawdown" class="text-sm text-raspberry-500 hover:underline">What is Drawdown?</router-link>
              </li>
            </ul>
          </div>
        </article>
      </div>
    </section>

    <!-- CTA -->
    <section class="py-12 bg-light-pink-100">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 mb-3">Model the 2027 pension changes on your estate</h2>
        <p class="text-sm text-neutral-500 mb-6 max-w-md mx-auto">
          See how the new rules affect your Inheritance Tax position &mdash; before they take effect.
        </p>
        <a
          href="#" @click.prevent="$router.push({ query: { demo: 'true' } })"
          class="inline-block px-6 py-2.5 bg-spring-500 text-white text-sm font-semibold rounded-lg hover:bg-spring-600 transition-colors"
        >
          Try the demo
        </a>
      </div>
    </section>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default {
  name: 'PensionIhtChanges2027Page',
  components: { PublicLayout },

  mounted() {
    document.title = 'Pension Inheritance Tax Changes April 2027 \u2014 What You Need to Know | Fynla';
  },
};
</script>
