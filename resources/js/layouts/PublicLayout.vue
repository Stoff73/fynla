<template>
  <div class="min-h-screen bg-eggshell-500">
    <!-- Navigation -->
    <nav class="bg-white shadow-sm border-b border-light-gray sticky top-0 z-50">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
          <div class="flex items-center">
            <!-- Logo -->
            <router-link to="/" class="flex items-center">
              <img :src="logoUrl" alt="Fynla" class="h-14 w-auto" />
            </router-link>

            <!-- Desktop Navigation -->
            <div class="hidden lg:ml-8 lg:flex lg:space-x-6">
              <router-link
                to="/"
                class="inline-flex items-center px-1 pt-1 text-sm font-medium text-horizon-500 hover:text-raspberry-500 transition-colors"
              >
                Home
              </router-link>

              <!-- Your stage dropdown -->
              <div class="relative" @mouseenter="stageOpen = true" @mouseleave="stageOpen = false">
                <button
                  type="button"
                  class="inline-flex items-center gap-1 px-1 pt-1 text-sm font-medium text-neutral-500 hover:text-raspberry-500 transition-colors"
                  :class="{ 'text-raspberry-500': stageOpen }"
                >
                  Your stage
                  <svg class="w-3.5 h-3.5 transition-transform duration-200" :class="{ 'rotate-180': stageOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                  </svg>
                </button>
                <div v-if="stageOpen" class="absolute left-1/2 -translate-x-1/2 top-full w-[400px] z-50 pt-2">
                  <div class="bg-white rounded-xl shadow-lg border border-light-gray p-4">
                    <router-link
                      v-for="stage in stages"
                      :key="stage.slug"
                      :to="`/stage/${stage.slug}`"
                      class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-savannah-50 transition-colors group"
                      @click="stageOpen = false"
                    >
                      <div class="w-2.5 h-2.5 rounded-full flex-shrink-0" :style="{ backgroundColor: stage.colour }"></div>
                      <div>
                        <p class="text-sm font-semibold text-horizon-500 group-hover:text-raspberry-500 transition-colors">{{ stage.name }}</p>
                        <p class="text-xs text-neutral-500">{{ stage.sub }}</p>
                      </div>
                    </router-link>
                  </div>
                </div>
              </div>

              <router-link
                to="/how-it-works"
                class="inline-flex items-center px-1 pt-1 text-sm font-medium text-neutral-500 hover:text-raspberry-500 transition-colors"
              >
                How it works
              </router-link>

              <!-- Learn dropdown -->
              <div class="relative" @mouseenter="learnOpen = true" @mouseleave="learnOpen = false">
                <button
                  type="button"
                  class="inline-flex items-center gap-1 px-1 pt-1 text-sm font-medium text-neutral-500 hover:text-raspberry-500 transition-colors"
                  :class="{ 'text-raspberry-500': learnOpen }"
                >
                  Learn
                  <svg class="w-3.5 h-3.5 transition-transform duration-200" :class="{ 'rotate-180': learnOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                  </svg>
                </button>
                <div v-if="learnOpen" class="absolute left-1/2 -translate-x-1/2 top-full w-56 z-50 pt-2">
                  <div class="bg-white rounded-xl shadow-lg border border-light-gray py-2">
                    <router-link to="/learn" class="block px-4 py-2 text-sm text-neutral-500 hover:bg-savannah-50 hover:text-raspberry-500 transition-colors" @click="learnOpen = false">Guides &amp; Explainers</router-link>
                    <router-link to="/learn/glossary" class="block px-4 py-2 text-sm text-neutral-500 hover:bg-savannah-50 hover:text-raspberry-500 transition-colors" @click="learnOpen = false">Glossary A-Z</router-link>
                    <router-link to="/insights" class="block px-4 py-2 text-sm text-neutral-500 hover:bg-savannah-50 hover:text-raspberry-500 transition-colors" @click="learnOpen = false">Latest Insights</router-link>
                    <router-link to="/faq" class="block px-4 py-2 text-sm text-neutral-500 hover:bg-savannah-50 hover:text-raspberry-500 transition-colors" @click="learnOpen = false">FAQ</router-link>
                  </div>
                </div>
              </div>

              <router-link
                to="/calculators"
                class="inline-flex items-center px-1 pt-1 text-sm font-medium text-neutral-500 hover:text-raspberry-500 transition-colors"
              >
                Calculators
              </router-link>

              <!-- Why Fynla dropdown -->
              <div class="relative" @mouseenter="whyOpen = true" @mouseleave="whyOpen = false">
                <button
                  type="button"
                  class="inline-flex items-center gap-1 px-1 pt-1 text-sm font-medium text-neutral-500 hover:text-raspberry-500 transition-colors"
                  :class="{ 'text-raspberry-500': whyOpen }"
                >
                  Why Fynla
                  <svg class="w-3.5 h-3.5 transition-transform duration-200" :class="{ 'rotate-180': whyOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                  </svg>
                </button>
                <div v-if="whyOpen" class="absolute left-1/2 -translate-x-1/2 top-full w-56 z-50 pt-2">
                  <div class="bg-white rounded-xl shadow-lg border border-light-gray py-2">
                    <router-link to="/why-fynla/our-approach" class="block px-4 py-2 text-sm text-neutral-500 hover:bg-savannah-50 hover:text-raspberry-500 transition-colors" @click="whyOpen = false">Our Approach</router-link>
                    <router-link to="/why-fynla/one-platform" class="block px-4 py-2 text-sm text-neutral-500 hover:bg-savannah-50 hover:text-raspberry-500 transition-colors" @click="whyOpen = false">One Platform Story</router-link>
                    <router-link to="/why-fynla/independent" class="block px-4 py-2 text-sm text-neutral-500 hover:bg-savannah-50 hover:text-raspberry-500 transition-colors" @click="whyOpen = false">Not Tied to an Adviser</router-link>
                    <router-link to="/security" class="block px-4 py-2 text-sm text-neutral-500 hover:bg-savannah-50 hover:text-raspberry-500 transition-colors" @click="whyOpen = false">Security &amp; Privacy</router-link>
                    <router-link to="/why-fynla/alternatives" class="block px-4 py-2 text-sm text-neutral-500 hover:bg-savannah-50 hover:text-raspberry-500 transition-colors" @click="whyOpen = false">Fynla vs Alternatives</router-link>
                  </div>
                </div>
              </div>

              <router-link
                to="/pricing"
                class="inline-flex items-center px-1 pt-1 text-sm font-medium text-neutral-500 hover:text-raspberry-500 transition-colors"
              >
                Pricing
              </router-link>
            </div>
          </div>

          <!-- Right side - Sign in -->
          <div class="hidden lg:flex items-center">
            <router-link
              to="/login"
              class="min-w-[100px] px-4 py-2 bg-spring-500 text-white text-sm font-semibold rounded-lg hover:bg-spring-600 transition-colors text-center"
            >
              Sign in
            </router-link>
          </div>

          <!-- Mobile menu button -->
          <div class="flex items-center lg:hidden">
            <button
              @click="mobileMenuOpen = !mobileMenuOpen"
              class="inline-flex items-center justify-center p-2 rounded-md text-horizon-400 hover:text-neutral-500 hover:bg-savannah-100"
            >
              <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path v-if="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>
      </div>

      <!-- Mobile menu -->
      <div v-if="mobileMenuOpen" class="lg:hidden border-t border-light-gray">
        <div class="pt-2 pb-3 space-y-1">
          <router-link to="/" class="block pl-3 pr-4 py-2 text-base font-medium text-horizon-500 hover:bg-savannah-100 hover:text-raspberry-500" @click="mobileMenuOpen = false">Home</router-link>

          <!-- Mobile: Your stage accordion -->
          <div>
            <button
              type="button"
              class="flex w-full items-center justify-between pl-3 pr-4 py-2 text-base font-medium text-horizon-500 hover:bg-savannah-100 hover:text-raspberry-500"
              @click="stageOpen = !stageOpen"
            >
              Your stage
              <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': stageOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
            <div v-if="stageOpen" class="pl-6 pb-1 space-y-0.5">
              <router-link
                v-for="stage in stages"
                :key="stage.slug"
                :to="`/stage/${stage.slug}`"
                class="flex items-center gap-2 py-1.5 text-sm text-neutral-500 hover:text-raspberry-500"
                @click="mobileMenuOpen = false; stageOpen = false"
              >
                <div class="w-2 h-2 rounded-full" :style="{ backgroundColor: stage.colour }"></div>
                {{ stage.name }}
              </router-link>
            </div>
          </div>

          <router-link to="/how-it-works" class="block pl-3 pr-4 py-2 text-base font-medium text-horizon-500 hover:bg-savannah-100 hover:text-raspberry-500" @click="mobileMenuOpen = false">How it works</router-link>

          <!-- Mobile: Learn accordion -->
          <div>
            <button
              type="button"
              class="flex w-full items-center justify-between pl-3 pr-4 py-2 text-base font-medium text-horizon-500 hover:bg-savannah-100 hover:text-raspberry-500"
              @click="learnOpen = !learnOpen"
            >
              Learn
              <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': learnOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
            <div v-if="learnOpen" class="pl-6 pb-1 space-y-0.5">
              <router-link to="/learn" class="block py-1.5 text-sm text-neutral-500 hover:text-raspberry-500" @click="mobileMenuOpen = false; learnOpen = false">Guides &amp; Explainers</router-link>
              <router-link to="/learn/glossary" class="block py-1.5 text-sm text-neutral-500 hover:text-raspberry-500" @click="mobileMenuOpen = false; learnOpen = false">Glossary A-Z</router-link>
              <router-link to="/insights" class="block py-1.5 text-sm text-neutral-500 hover:text-raspberry-500" @click="mobileMenuOpen = false; learnOpen = false">Latest Insights</router-link>
              <router-link to="/faq" class="block py-1.5 text-sm text-neutral-500 hover:text-raspberry-500" @click="mobileMenuOpen = false; learnOpen = false">FAQ</router-link>
            </div>
          </div>

          <router-link to="/calculators" class="block pl-3 pr-4 py-2 text-base font-medium text-horizon-500 hover:bg-savannah-100 hover:text-raspberry-500" @click="mobileMenuOpen = false">Calculators</router-link>

          <!-- Mobile: Why Fynla accordion -->
          <div>
            <button
              type="button"
              class="flex w-full items-center justify-between pl-3 pr-4 py-2 text-base font-medium text-horizon-500 hover:bg-savannah-100 hover:text-raspberry-500"
              @click="whyOpen = !whyOpen"
            >
              Why Fynla
              <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': whyOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
            <div v-if="whyOpen" class="pl-6 pb-1 space-y-0.5">
              <router-link to="/why-fynla/our-approach" class="block py-1.5 text-sm text-neutral-500 hover:text-raspberry-500" @click="mobileMenuOpen = false; whyOpen = false">Our Approach</router-link>
              <router-link to="/why-fynla/one-platform" class="block py-1.5 text-sm text-neutral-500 hover:text-raspberry-500" @click="mobileMenuOpen = false; whyOpen = false">One Platform Story</router-link>
              <router-link to="/why-fynla/independent" class="block py-1.5 text-sm text-neutral-500 hover:text-raspberry-500" @click="mobileMenuOpen = false; whyOpen = false">Not Tied to an Adviser</router-link>
              <router-link to="/security" class="block py-1.5 text-sm text-neutral-500 hover:text-raspberry-500" @click="mobileMenuOpen = false; whyOpen = false">Security &amp; Privacy</router-link>
              <router-link to="/why-fynla/alternatives" class="block py-1.5 text-sm text-neutral-500 hover:text-raspberry-500" @click="mobileMenuOpen = false; whyOpen = false">Fynla vs Alternatives</router-link>
            </div>
          </div>

          <router-link to="/pricing" class="block pl-3 pr-4 py-2 text-base font-medium text-horizon-500 hover:bg-savannah-100 hover:text-raspberry-500" @click="mobileMenuOpen = false">Pricing</router-link>
          <router-link to="/login" class="block pl-3 pr-4 py-2 text-base font-medium text-horizon-500 hover:bg-savannah-100 hover:text-raspberry-500" @click="mobileMenuOpen = false">Sign in</router-link>
        </div>
      </div>
    </nav>

    <!-- Main Content -->
    <main>
      <slot />
    </main>

    <!-- Footer -->
    <footer class="bg-savannah-100 pt-16">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-12">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-8">
          <!-- Company Info -->
          <div class="lg:col-span-2">
            <div class="flex items-center mb-4">
              <img :src="logoUrl" alt="Fynla" class="h-16 w-auto" />
            </div>
            <p class="text-sm text-neutral-500 leading-relaxed">
              Your financial companion for life — one platform for every stage.
            </p>
          </div>

          <!-- Your Stage -->
          <div>
            <h3 class="text-sm font-bold text-horizon-500 mb-4">Your stage</h3>
            <ul class="space-y-2">
              <li v-for="stage in stages" :key="stage.slug">
                <router-link :to="`/stage/${stage.slug}`" class="text-sm text-neutral-500 hover:text-raspberry-500 transition-colors">{{ stage.name }}</router-link>
              </li>
            </ul>
          </div>

          <!-- Help Centre -->
          <div>
            <h3 class="text-sm font-bold text-horizon-500 mb-4">Help centre</h3>
            <ul class="space-y-2">
              <li><router-link to="/faq" class="text-sm text-neutral-500 hover:text-raspberry-500 transition-colors">FAQ</router-link></li>
              <li><router-link to="/learning-centre" class="text-sm text-neutral-500 hover:text-raspberry-500 transition-colors">Learning Centre</router-link></li>
              <li><router-link to="/calculators" class="text-sm text-neutral-500 hover:text-raspberry-500 transition-colors">Calculators</router-link></li>
              <li><a href="/?demo=true" class="text-sm text-neutral-500 hover:text-raspberry-500 transition-colors">View demo</a></li>
            </ul>
          </div>

          <!-- About -->
          <div>
            <h3 class="text-sm font-bold text-horizon-500 mb-4">About Fynla</h3>
            <ul class="space-y-2">
              <li><router-link to="/about" class="text-sm text-neutral-500 hover:text-raspberry-500 transition-colors">About us</router-link></li>
              <li><router-link to="/security" class="text-sm text-neutral-500 hover:text-raspberry-500 transition-colors">Security &amp; Privacy</router-link></li>
              <li><router-link to="/pricing" class="text-sm text-neutral-500 hover:text-raspberry-500 transition-colors">Pricing</router-link></li>
            </ul>
          </div>

          <!-- Legal -->
          <div>
            <h3 class="text-sm font-bold text-horizon-500 mb-4">Legal</h3>
            <ul class="space-y-2">
              <li><router-link to="/terms" class="text-sm text-neutral-500 hover:text-raspberry-500 transition-colors">Terms &amp; conditions</router-link></li>
              <li><router-link to="/privacy" class="text-sm text-neutral-500 hover:text-raspberry-500 transition-colors">Privacy policy</router-link></li>
            </ul>
          </div>
        </div>

        <div class="border-t border-light-gray mt-8 pt-8">
          <p class="text-sm text-neutral-500">
            &copy; Fynla 2026
          </p>
        </div>
      </div>
    </footer>
  </div>
</template>

<script>
export default {
  name: 'PublicLayout',

  data() {
    return {
      mobileMenuOpen: false,
      stageOpen: false,
      learnOpen: false,
      whyOpen: false,
      logoUrl: '/images/logos/LogoHiResFynlaDark.png',
      stages: [
        { slug: 'starting-out', name: 'Starting Out', sub: 'First job, first steps', colour: '#1D9E75' },
        { slug: 'building-foundations', name: 'Building Foundations', sub: 'Saving, buying, growing', colour: '#5DCAA5' },
        { slug: 'protecting-and-growing', name: 'Protecting and Growing', sub: 'Family, home, investments', colour: '#378ADD' },
        { slug: 'planning-your-future', name: 'Planning Your Future', sub: 'Peak earning, retirement prep', colour: '#7F77DD' },
        { slug: 'enjoying-your-wealth', name: 'Enjoying Your Wealth', sub: 'Later life, legacy, estate', colour: '#EF9F27' },
      ],
    };
  },

  watch: {
    $route() {
      this.mobileMenuOpen = false;
      this.stageOpen = false;
      this.learnOpen = false;
      this.whyOpen = false;
    }
  }
};
</script>

<style scoped>
nav .lg\:space-x-6 .router-link-active {
  @apply text-raspberry-500;
}
</style>
