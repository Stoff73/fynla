<template>
  <PublicLayout>
    <!-- Hero Section -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">
          Everything you need,
          <span class="text-raspberry-300">one platform</span>
        </h1>
        <p class="text-lg text-white/70">
          Compare plans and see exactly what's included at every level.
        </p>
      </div>
    </div>

    <!-- Comparison Table -->
    <div class="relative bg-light-pink-100 pt-10 pb-20 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="overflow-x-auto">
          <table class="w-full border-collapse">
            <thead>
              <tr>
                <th class="text-left py-4 px-6 text-sm font-bold text-horizon-500 w-1/3">Feature</th>
                <th class="text-center py-4 px-4 w-1/6">
                  <div class="bg-horizon-500 rounded-xl py-4 px-3">
                    <p class="text-white font-semibold text-sm">Student</p>
                    <p class="text-white/60 text-xs mt-0.5">From £3.99/mo</p>
                  </div>
                </th>
                <th class="text-center py-4 px-4 w-1/6">
                  <div class="bg-raspberry-500 rounded-xl py-4 px-3 ring-2 ring-raspberry-300 ring-offset-2">
                    <p class="text-white font-semibold text-sm">Pro</p>
                    <p class="text-white/60 text-xs mt-0.5">From £7.99/mo</p>
                  </div>
                </th>
                <th class="text-center py-4 px-4 w-1/6">
                  <div class="bg-horizon-500 rounded-xl py-4 px-3">
                    <p class="text-white font-semibold text-sm">Family</p>
                    <p class="text-white/60 text-xs mt-0.5">From £10.99/mo</p>
                  </div>
                </th>
              </tr>
            </thead>
            <tbody>
              <template v-for="(section, sIdx) in featureSections" :key="'s-'+sIdx">
                <tr class="border-b border-light-gray/50">
                  <td colspan="4" class="pt-6 pb-2 px-6">
                    <p class="text-xs font-semibold text-neutral-400 uppercase tracking-wider">{{ section.heading }}</p>
                  </td>
                </tr>
                <tr v-for="(feat, fIdx) in section.features" :key="sIdx+'-'+fIdx" class="border-b border-light-gray/30 hover:bg-white/60 transition-colors">
                  <td class="py-3.5 px-6">
                    <p class="text-sm text-horizon-500 font-medium">{{ feat.name }}</p>
                    <p v-if="feat.desc" class="text-xs text-neutral-500 mt-0.5">{{ feat.desc }}</p>
                  </td>
                  <td class="py-3.5 px-4 text-center">
                    <span v-if="feat.student === true" class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-spring-100">
                      <svg class="w-4 h-4 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    </span>
                    <span v-else-if="feat.student === false" class="text-neutral-300">&mdash;</span>
                    <span v-else class="text-xs text-neutral-500 font-medium">{{ feat.student }}</span>
                  </td>
                  <td class="py-3.5 px-4 text-center">
                    <span v-if="feat.pro === true" class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-spring-100">
                      <svg class="w-4 h-4 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    </span>
                    <span v-else-if="feat.pro === false" class="text-neutral-300">&mdash;</span>
                    <span v-else class="text-xs text-neutral-500 font-medium">{{ feat.pro }}</span>
                  </td>
                  <td class="py-3.5 px-4 text-center">
                    <span v-if="feat.family === true" class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-spring-100">
                      <svg class="w-4 h-4 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    </span>
                    <span v-else-if="feat.family === false" class="text-neutral-300">&mdash;</span>
                    <span v-else class="text-xs text-neutral-500 font-medium">{{ feat.family }}</span>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <!-- CTA -->
        <div class="text-center mt-12">
          <router-link
            to="/register"
            class="inline-block px-8 py-3 bg-raspberry-500 text-white font-semibold rounded-lg hover:bg-raspberry-600 transition-colors shadow-sm hover:shadow-md"
          >
            Start your free trial
          </router-link>
          <p class="text-sm text-neutral-500 mt-3">7-day free trial on any plan. No credit card required.</p>
        </div>
      </div>
    </div>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default {
  name: 'FeaturesPage',
  components: { PublicLayout },

  data() {
    return {
      featureSections: [
        {
          heading: 'Dashboard & Overview',
          features: [
            { name: 'Net worth dashboard', desc: 'See your full financial picture at a glance', student: true, pro: true, family: true },
            { name: 'Module summary cards', desc: 'Protection, savings, investment, retirement, estate', student: true, pro: true, family: true },
            { name: 'Life events timeline', student: true, pro: true, family: true },
            { name: 'Goals tracking', student: true, pro: true, family: true },
            { name: 'Fyn — AI financial companion', desc: 'Ask Fyn anything about your finances', student: '5 chats/mo', pro: 'Unlimited', family: 'Unlimited' },
          ],
        },
        {
          heading: 'Protection',
          features: [
            { name: 'Life insurance tracking', student: true, pro: true, family: true },
            { name: 'Critical illness cover', student: false, pro: true, family: true },
            { name: 'Income protection analysis', student: false, pro: true, family: true },
            { name: 'Coverage gap analysis', student: false, pro: true, family: true },
          ],
        },
        {
          heading: 'Savings & Cash',
          features: [
            { name: 'Bank account tracking', student: true, pro: true, family: true },
            { name: 'ISA allowance tracker', student: true, pro: true, family: true },
            { name: 'Emergency fund gauge', student: true, pro: true, family: true },
            { name: 'Interest rate comparison', student: false, pro: true, family: true },
          ],
        },
        {
          heading: 'Investment',
          features: [
            { name: 'Portfolio overview', student: true, pro: true, family: true },
            { name: 'Holdings tracking', student: true, pro: true, family: true },
            { name: 'Risk profiling', student: false, pro: true, family: true },
            { name: 'Monte Carlo projections', student: false, pro: true, family: true },
            { name: 'Fee analysis & optimisation', student: false, pro: true, family: true },
          ],
        },
        {
          heading: 'Retirement',
          features: [
            { name: 'Defined Contribution pensions', student: true, pro: true, family: true },
            { name: 'State Pension forecast', student: true, pro: true, family: true },
            { name: 'Defined Benefit pensions', student: false, pro: true, family: true },
            { name: 'Retirement income projection', student: false, pro: true, family: true },
            { name: 'Drawdown simulator', student: false, pro: true, family: true },
          ],
        },
        {
          heading: 'Estate Planning',
          features: [
            { name: 'Inheritance Tax calculator', student: false, pro: true, family: true },
            { name: 'Gifting strategy planner', student: false, pro: true, family: true },
            { name: 'Estate value projections', student: false, pro: true, family: true },
            { name: 'In Case of Emergency letters', student: false, pro: true, family: true },
          ],
        },
        {
          heading: 'Household & Sharing',
          features: [
            { name: 'Joint asset tracking', student: false, pro: true, family: true },
            { name: 'Spouse/partner account', student: false, pro: false, family: true },
            { name: 'Household dashboard', student: false, pro: false, family: true },
            { name: 'Shared goals', student: false, pro: false, family: true },
          ],
        },
        {
          heading: 'Plans & Actions',
          features: [
            { name: 'Personalised action plans', student: false, pro: true, family: true },
            { name: 'Retirement plan', student: false, pro: true, family: true },
            { name: 'Investment plan', student: false, pro: true, family: true },
            { name: 'What-if scenarios', student: false, pro: true, family: true },
          ],
        },
      ],
    };
  },
};
</script>
