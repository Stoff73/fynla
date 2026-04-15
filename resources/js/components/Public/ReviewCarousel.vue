<template>
  <div class="bg-horizon-500 py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-white text-center mb-8">What our customers say</h2>

      <div class="overflow-hidden">
        <!-- Desktop: 3 per page -->
        <div
          class="hidden md:flex transition-transform duration-500 ease-in-out"
          :style="{ transform: `translateX(-${desktopPage * 100}%)` }"
        >
          <div v-for="(page, pi) in desktopPages" :key="'dp-' + pi" class="flex gap-5 min-w-full">
            <div
              v-for="review in page"
              :key="review.name"
              class="flex-1 bg-white/[0.08] border border-white/[0.12] rounded-2xl px-6 py-5 flex flex-col"
            >
              <div class="flex gap-0.5 mb-3">
                <svg v-for="s in 5" :key="s" class="w-4 h-4 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                  <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                </svg>
              </div>
              <p class="text-sm text-white/80 leading-relaxed flex-1 mb-3">{{ review.text }}</p>
              <div class="border-t border-white/10 pt-3">
                <span class="text-sm font-semibold text-white">{{ review.name }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Mobile: 1 per page -->
        <div
          class="flex md:hidden transition-transform duration-500 ease-in-out"
          :style="{ transform: `translateX(-${mobilePage * 100}%)` }"
        >
          <div
            v-for="review in reviews"
            :key="'m-' + review.name"
            class="min-w-full px-1"
          >
            <div class="bg-white/[0.08] border border-white/[0.12] rounded-2xl px-6 py-5 flex flex-col">
              <div class="flex gap-0.5 mb-3">
                <svg v-for="s in 5" :key="s" class="w-4 h-4 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                  <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                </svg>
              </div>
              <p class="text-sm text-white/80 leading-relaxed flex-1 mb-3">{{ review.text }}</p>
              <div class="border-t border-white/10 pt-3">
                <span class="text-sm font-semibold text-white">{{ review.name }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Navigation -->
      <div class="flex justify-center items-center gap-5 mt-7">
        <button
          @click="prev"
          class="w-10 h-10 rounded-full border border-white/25 bg-white/[0.08] text-white flex items-center justify-center hover:bg-white/[0.15] hover:border-white/40 transition-all"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
        </button>
        <div class="flex gap-2">
          <button
            v-for="i in totalPages"
            :key="i"
            @click="goToPage(i - 1)"
            :class="[
              'h-2.5 rounded-full transition-all duration-300 cursor-pointer',
              currentPage === i - 1 ? 'w-7 bg-raspberry-500' : 'w-2.5 bg-white/25'
            ]"
          />
        </div>
        <button
          @click="next"
          class="w-10 h-10 rounded-full border border-white/25 bg-white/[0.08] text-white flex items-center justify-center hover:bg-white/[0.15] hover:border-white/40 transition-all"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
        </button>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ReviewCarousel',

  data() {
    return {
      desktopPage: 0,
      mobilePage: 0,
      isMobile: false,
      autoTimer: null,
      reviews: [
        {
          name: 'Stephen D.',
          plan: 'Fynla user',
          text: '"I found the dashboard screens interesting, but the chat agent stole the show. Absolutely incredible. I spent an hour or so with it on Friday night and I left with the information I needed — very technical analysis of workplace pension options broken down in a very easy to understand way."',
        },
        {
          name: 'Mia R.',
          plan: 'Fynla user',
          text: '"It was so easy to see all my finances in one place. I had all current and savings accounts in different locations and had no idea what to do with my money. Fyn was really helpful in getting my information into Fynla, and then I had clear next steps on what I needed to do with my money."',
        },
        {
          name: 'David W.',
          plan: 'Standard plan user',
          text: '"I\'d been putting off sorting my estate planning for years. Fynla walked me through it step by step. Now I actually understand my inheritance tax position."',
        },
        {
          name: 'Richard T.',
          plan: 'Family plan user',
          text: '"My wife and I use the Family plan together. Being able to see our joint assets and individual pensions side by side has completely changed how we plan. Worth every penny."',
        },
        {
          name: 'Laura P.',
          plan: 'Standard plan user',
          text: '"As someone who\'s self-employed, keeping track of multiple pensions and ISAs was a nightmare. Fynla brought it all together beautifully. The retirement projections alone are worth the subscription."',
        },
        {
          name: 'Michael H.',
          plan: 'Pro plan user',
          text: '"I was sceptical about another finance app, but Fynla is different. It actually understands UK tax rules — ISA allowances, pension annual allowance, inheritance tax. Everything is calculated correctly."',
        },
      ],
    };
  },

  computed: {
    desktopPages() {
      const pages = [];
      for (let i = 0; i < this.reviews.length; i += 3) {
        pages.push(this.reviews.slice(i, i + 3));
      }
      return pages;
    },

    totalPages() {
      return this.isMobile ? this.reviews.length : this.desktopPages.length;
    },

    currentPage() {
      return this.isMobile ? this.mobilePage : this.desktopPage;
    },
  },

  mounted() {
    this.checkMobile();
    window.addEventListener('resize', this.checkMobile);
    this.startAutoScroll();
  },

  beforeUnmount() {
    window.removeEventListener('resize', this.checkMobile);
    this.stopAutoScroll();
  },

  methods: {
    checkMobile() {
      const wasMobile = this.isMobile;
      this.isMobile = window.innerWidth < 768;
      if (wasMobile !== this.isMobile) {
        this.desktopPage = 0;
        this.mobilePage = 0;
      }
    },

    goToPage(page) {
      if (this.isMobile) {
        this.mobilePage = page;
      } else {
        this.desktopPage = page;
      }
      this.resetAutoScroll();
    },

    next() {
      const total = this.totalPages;
      this.goToPage((this.currentPage + 1) % total);
    },

    prev() {
      const total = this.totalPages;
      this.goToPage((this.currentPage - 1 + total) % total);
    },

    startAutoScroll() {
      this.autoTimer = setInterval(() => this.next(), 10000);
    },

    stopAutoScroll() {
      if (this.autoTimer) {
        clearInterval(this.autoTimer);
        this.autoTimer = null;
      }
    },

    resetAutoScroll() {
      this.stopAutoScroll();
      this.startAutoScroll();
    },
  },
};
</script>
