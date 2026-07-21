<template>
  <AppLayout>
    <div class="max-w-2xl mx-auto px-4 py-10">
      <div class="card-lg">
        <p class="text-sm font-bold text-raspberry-500 uppercase tracking-wide">Available on a paid plan</p>
        <h1 class="mt-2 text-2xl font-black text-horizon-500">{{ content.title }}</h1>
        <p class="mt-3 text-neutral-600">{{ content.description }}</p>

        <ul class="mt-5 space-y-2">
          <li v-for="(point, i) in content.points" :key="i" class="text-sm text-horizon-500">
            {{ point }}
          </li>
        </ul>

        <div class="mt-8 flex flex-col-reverse sm:flex-row sm:items-center gap-3">
          <router-link
            to="/dashboard"
            class="inline-flex justify-center rounded-button border border-light-gray px-4 py-2 text-sm font-medium text-horizon-500 bg-white hover:bg-savannah-100"
          >
            Back to dashboard
          </router-link>
          <router-link
            :to="{ path: '/teaser', query: { ...$route.query, openPricing: '1' } }"
            class="inline-flex justify-center rounded-button px-4 py-2 text-sm font-medium text-white bg-raspberry-600 hover:bg-raspberry-700"
          >
            See plans and upgrade
          </router-link>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';

const MODULE_CONTENT = {
  estate: {
    title: 'Estate Planning',
    description: 'Model your inheritance tax exposure, build a will, set up trusts and lasting powers of attorney, and plan gifts that reduce what your estate owes.',
    points: [
      'See your projected inheritance tax liability and how to reduce it',
      'Build and store a will and expression of wishes',
      'Plan trusts, gifts and lasting powers of attorney',
    ],
  },
  letter_to_spouse: {
    title: 'Letter to Spouse',
    description: 'Leave a clear, personal letter and instructions for your spouse covering your accounts, wishes and the practical steps they would need to take.',
    points: [
      'Record where everything is and what to do',
      'Keep it alongside your wider estate plan',
    ],
  },
  holistic_plan: {
    title: 'Holistic Plan',
    description: 'Bring every module together into one cross-cutting financial plan — savings, investments, retirement, protection and estate — with coordinated recommendations.',
    points: [
      'A single plan across every part of your finances',
      'Coordinated, prioritised recommendations',
    ],
  },
  what_if: {
    title: 'What If Scenarios',
    description: 'Test how changes — a career break, a house move, early retirement, a market downturn — would affect your finances before you commit.',
    points: [
      'Model life and market changes side by side',
      'See the long-term impact on your plan',
    ],
  },
};

const FALLBACK = {
  title: 'Upgrade to unlock',
  description: 'This feature is available on a paid plan.',
  points: [],
};

export default {
  name: 'TierTeaserView',

  components: { AppLayout },

  computed: {
    moduleKey() {
      return this.$route.query.module || '';
    },
    content() {
      return MODULE_CONTENT[this.moduleKey] || FALLBACK;
    },
  },
};
</script>
