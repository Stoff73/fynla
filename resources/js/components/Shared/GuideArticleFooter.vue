<template>
  <div>
    <!-- Back to Guides & Explainers -->
    <section class="bg-eggshell-500 pt-6 pb-2">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <router-link
          to="/learn"
          class="inline-flex items-center text-sm font-medium text-raspberry-500 hover:text-raspberry-600 transition-colors"
        >
          <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
          </svg>
          Back to Guides &amp; Explainers
        </router-link>
      </div>
    </section>

    <!-- Glossary / Insights / FAQ -->
    <section class="bg-eggshell-500 py-8">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <router-link
            to="/learn/glossary"
            class="resource-card group"
          >
            <h3 class="text-lg font-bold text-white group-hover:text-white/90 transition-colors mb-1">Glossary A&ndash;Z</h3>
            <p class="text-xs text-white/70">37 financial terms explained in plain English</p>
          </router-link>
          <router-link
            to="/insights"
            class="resource-card group"
          >
            <h3 class="text-lg font-bold text-white group-hover:text-white/90 transition-colors mb-1">Latest Insights</h3>
            <p class="text-xs text-white/70">Timely commentary on tax changes and financial news</p>
          </router-link>
          <router-link
            to="/faq"
            class="resource-card group"
          >
            <h3 class="text-lg font-bold text-white group-hover:text-white/90 transition-colors mb-1">FAQ</h3>
            <p class="text-xs text-white/70">Common questions about Fynla answered</p>
          </router-link>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="py-10 bg-light-pink-100">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-gradient-to-r from-horizon-500 to-raspberry-500 rounded-xl px-6 py-6">
          <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-white mb-2">Ready to put knowledge into action?</h2>
          <p class="text-lg text-white/70 mb-4">Try Fynla free and see your full financial picture in minutes.</p>
          <router-link to="/?demo=true" class="inline-block px-6 py-2.5 bg-spring-500 text-white text-sm font-semibold rounded-lg hover:bg-spring-600 transition-colors">
            Try the demo
          </router-link>
        </div>
      </div>
    </section>
  </div>
</template>

<script>
export default {
  name: 'GuideArticleFooter',
};
</script>

<style scoped>
.resource-card {
  @apply bg-horizon-500 rounded-lg;
  padding: 20px;
  text-decoration: none;
  display: block;
  transition: all 0.2s;
}

.resource-card:hover {
  @apply bg-horizon-600;
}
</style>
