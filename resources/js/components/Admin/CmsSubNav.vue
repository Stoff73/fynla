<template>
  <div class="flex items-center gap-1 border-b border-light-gray mb-6">
    <router-link
      v-for="tab in subTabs"
      :key="tab.path"
      :to="tab.path"
      :class="[
        'px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px transition-colors',
        isActive(tab)
          ? 'border-raspberry-600 text-raspberry-600'
          : 'border-transparent text-neutral-500 hover:text-horizon-500 hover:border-horizon-300',
      ]"
    >
      {{ tab.label }}
    </router-link>
  </div>
</template>

<script>
/**
 * Sub-navigation for the CMS section: Articles / Pages / Emails.
 * Rendered under the shared AdminNav on every CMS page. Active tab is
 * derived from the current route and highlighted in raspberry (pink).
 */
export default {
  name: 'CmsSubNav',
  data() {
    return {
      subTabs: [
        { label: 'Articles', path: '/admin/documents' },
        { label: 'Pages', path: '/admin/cms/pages' },
        { label: 'Emails', path: '/admin/cms/emails' },
      ],
    };
  },
  methods: {
    isActive(tab) {
      return this.$route.path === tab.path || this.$route.path.startsWith(tab.path + '/');
    },
  },
};
</script>
