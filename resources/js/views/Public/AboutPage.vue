<template>
  <PublicLayout>
    <!-- Hero Section -->
    <div class="relative min-h-[40vh] flex items-center bg-gradient-to-br from-slate-900 via-blue-900 to-slate-900 overflow-hidden">
      <!-- Background Elements -->
      <div class="absolute inset-0 overflow-hidden">
        <div class="absolute top-1/4 left-1/4 w-72 h-72 bg-blue-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-pulse"></div>
        <div class="absolute top-1/3 right-1/4 w-72 h-72 bg-emerald-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-pulse" style="animation-delay: 1s;"></div>
      </div>

      <!-- Grid Pattern Overlay -->
      <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxwYXRoIGQ9Ik0wIDBoNjB2NjBIMHoiLz48cGF0aCBkPSJNMzAgMzBtLTEgMGExIDEgMCAxIDAgMiAwYTEgMSAwIDEgMCAtMiAwIiBmaWxsPSJyZ2JhKDI1NSwyNTUsMjU1LDAuMSkiLz48L2c+PC9zdmc+')] opacity-40"></div>

      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center w-full">
        <h1 class="text-4xl md:text-6xl font-bold text-white mb-6">
          About
          <span class="bg-gradient-to-r from-emerald-400 to-blue-400 bg-clip-text text-transparent">
            Fynla
          </span>
        </h1>
        <p class="text-xl text-slate-300 max-w-3xl mx-auto">
          Built by financial professionals who believe clarity shouldn't be a luxury
        </p>
      </div>
    </div>

    <!-- About Fynla & Why We Built This - Side by Side -->
    <div class="relative bg-gradient-to-b from-slate-900 to-slate-800 py-20 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
          <!-- About Fynla -->
          <div>
            <h2 class="text-3xl md:text-4xl font-bold text-white mb-6">About Fynla</h2>
            <div class="space-y-6 text-lg text-slate-300 leading-relaxed">
              <p>
                Fynla is a comprehensive UK financial planning platform designed to give you clarity and control over your financial future. We bring together all aspects of your finances—from pensions and investments to property and protection—into one unified dashboard.
              </p>
              <p>
                Built by financial professionals with decades of experience, Fynla combines professional-grade planning tools with an intuitive interface that makes complex financial decisions accessible to everyone.
              </p>
              <p>
                Whether you're planning for retirement, protecting your family, or coordinating finances with your spouse, Fynla provides the insights and projections you need to make informed decisions about your financial life.
              </p>
            </div>
          </div>

          <!-- Why We Built This -->
          <div>
            <h2 class="text-3xl md:text-4xl font-bold text-white mb-6">Why We Built This</h2>
            <div class="space-y-6 text-lg text-slate-300 leading-relaxed">
              <p>
                We didn't build Fynla because the world needed another fintech app; we built it because, after 40 combined years in the heart of the financial industry, we saw a gap that shouldn't exist.
              </p>
              <p>
                Between us, we have advised thousands of families, steered global companies through complex mergers, and navigated the highest levels of UK financial regulation. Yet, we both kept noticing the same problem: the tools required to actually plan a financial future were either locked behind expensive professional gatekeepers or too complex for a kitchen-table conversation.
              </p>
              <p>
                We believe financial clarity shouldn't be a luxury for the wealthy. Whether you are a graduate facing student debt, a squeezed family managing a mortgage, or a retiree navigating new tax laws, you deserve a flight simulator for your life. Fynla is our way of taking the professional-grade tools we've used in boardrooms and advisory offices and putting them into your hands.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- The Founders -->
    <div class="relative bg-slate-800 py-20 overflow-hidden">
      <div class="absolute inset-0 bg-gradient-to-b from-slate-800 to-slate-900"></div>

      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
          <h2 class="text-3xl md:text-4xl font-bold text-white mb-4">The Founders</h2>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
          <!-- Chris Slater-Jones -->
          <div class="bg-white/5 backdrop-blur-md rounded-2xl p-8 border border-white/10">
            <div class="flex flex-col items-center mb-6">
              <img
                :src="csjImageUrl"
                alt="Chris Slater-Jones"
                class="w-32 h-32 rounded-full object-cover border-4 border-blue-500/30 mb-4"
              />
              <h3 class="text-2xl font-bold text-white">Chris Slater-Jones</h3>
              <p class="text-blue-400 font-medium">Co-Founder</p>
            </div>
            <div class="space-y-4 text-slate-300 leading-relaxed">
              <p>
                I've spent over 20 years in financial services — as an adviser, a director, and a compliance lead — across the UK and South Africa. My career has taken me from the advisory front line at St. James's Place to the boardrooms of capital markets consultancies. I hold a Post Graduate Diploma in Financial Planning and am a Diploma-qualified member of the Chartered Insurance Institute (DipPFS).
              </p>
              <p>
                Throughout my time as an FCA-registered adviser, my greatest frustration was seeing families struggle with fragmented finances — pensions in one place, ISAs in another, and no clear way to see how they worked together. I built Fynla to be the platform I always wished my clients had: a UK-centric, comprehensive space where you can link with your spouse, model your retirement, and ensure your loved ones are protected.
              </p>
            </div>
          </div>

          <!-- Brett Isenberg -->
          <div class="bg-white/5 backdrop-blur-md rounded-2xl p-8 border border-white/10">
            <div class="flex flex-col items-center mb-6">
              <img
                :src="brettImageUrl"
                alt="Brett Isenberg"
                class="w-32 h-32 rounded-full object-cover border-4 border-emerald-500/30 mb-4"
              />
              <h3 class="text-2xl font-bold text-white">Brett Isenberg, FCA</h3>
              <p class="text-emerald-400 font-medium">Co-Founder</p>
            </div>
            <div class="space-y-4 text-slate-300 leading-relaxed">
              <p>
                As a Fellow of the Institute of Chartered Accountants in England and Wales (FCA) and a former Big 4 accountant, I have spent two decades at the intersection of business and finance. From founding and selling a high-growth marketing group to serving as a CFO and COO across EMEA for global giants like Dentsu and Merkle, I've seen how the right data can transform a business.
              </p>
              <p>
                However, I noticed that even the most successful professionals often felt blind when it came to their personal wealth. I co-founded Fynla because I believe that starting early changes everything, but you can't start if you can't see the path. My mission is to bring the same financial rigour used by global enterprises to your personal dashboard, helping you move from guessing to knowing exactly where you stand.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default {
  name: 'AboutPage',

  components: {
    PublicLayout,
  },

  data() {
    return {
      csjImageUrl: '/images/portraits/csj.png',
      brettImageUrl: '/images/portraits/brett.png',
    };
  },
};
</script>
