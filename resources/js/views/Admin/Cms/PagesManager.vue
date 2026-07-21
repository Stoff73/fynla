<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <AdminNav />
      <CmsSubNav />

      <header class="mb-6">
        <h1 class="text-3xl font-black text-horizon-500" style="letter-spacing:-0.02em;">Pages</h1>
        <p class="text-sm text-neutral-500 mt-1">
          The public marketing and footer pages. These are server-rendered for speed and SEO
          (<code class="text-xs">public/pages/*.php</code>) — open one to view it live.
        </p>
      </header>

      <div class="card overflow-hidden p-0">
        <table class="w-full text-sm">
          <thead class="bg-eggshell-50 text-horizon-500 font-semibold">
            <tr>
              <th class="text-left px-4 py-3">Page</th>
              <th class="text-left px-4 py-3 w-40">Path</th>
              <th class="text-left px-4 py-3 w-32">Group</th>
              <th class="text-right px-4 py-3 w-28">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="page in pages" :key="page.path" class="border-t border-savannah-100 hover:bg-eggshell-50">
              <td class="px-4 py-3 font-semibold text-horizon-500">{{ page.label }}</td>
              <td class="px-4 py-3 font-mono text-xs text-horizon-400">{{ page.path }}</td>
              <td class="px-4 py-3">
                <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-savannah-100 text-horizon-500">{{ page.group }}</span>
              </td>
              <td class="px-4 py-3 text-right">
                <a
                  :href="page.path"
                  target="_blank"
                  rel="noopener"
                  class="inline-block px-3 py-1.5 text-xs border border-horizon-500 text-horizon-500 rounded hover:bg-horizon-50"
                >View live ↗</a>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <p class="text-xs text-neutral-500 mt-4">
        Editing page content is a developer task for now (the pages are hand-built HTML/PHP).
        In-CMS page editing is planned — see the Emails tab pattern.
      </p>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import AdminNav from '@/components/Admin/AdminNav.vue';
import CmsSubNav from '@/components/Admin/CmsSubNav.vue';

export default {
  name: 'PagesManager',
  components: { AppLayout, AdminNav, CmsSubNav },
  data() {
    return {
      // The live public pages (public/pages/*.php), grouped by where they surface.
      pages: [
        { label: 'Home', path: '/', group: 'Core' },
        { label: 'How it works', path: '/how-it-works', group: 'Core' },
        { label: 'Features', path: '/features', group: 'Core' },
        { label: 'Pricing', path: '/pricing', group: 'Core' },
        { label: 'Calculators', path: '/calculators', group: 'Core' },
        { label: 'About us', path: '/about', group: 'Footer' },
        { label: 'Contact', path: '/contact', group: 'Footer' },
        { label: 'FAQ', path: '/faq', group: 'Footer' },
        { label: 'Security', path: '/security', group: 'Footer' },
        { label: 'Help', path: '/help', group: 'Footer' },
        { label: 'Privacy Policy', path: '/privacy', group: 'Footer' },
        { label: 'Terms of Service', path: '/terms', group: 'Footer' },
        { label: 'Sitemap', path: '/sitemap', group: 'Footer' },
      ],
    };
  },
};
</script>
