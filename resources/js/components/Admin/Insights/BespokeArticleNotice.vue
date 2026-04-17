<template>
  <aside class="p-6 bg-violet-50 border-l-4 border-violet-500 rounded-r-lg">
    <h4 class="text-sm font-bold text-violet-700 uppercase tracking-wide mb-2">Bespoke article</h4>
    <p class="text-sm text-horizon-500 leading-relaxed mb-2">
      This article is rendered by a bespoke Vue component
      (<code class="text-xs bg-white px-1.5 py-0.5 rounded">{{ component }}</code>).
      To edit the article&rsquo;s content, update the component file directly in
      <code class="text-xs bg-white px-1.5 py-0.5 rounded">resources/js/views/Public/insights/{{ component }}.vue</code>.
    </p>
    <p class="text-sm text-horizon-500">
      You can still edit the title, summary, tags, hero image, and featured status here.
    </p>
  </aside>
</template>

<script>
export default {
  name: 'BespokeArticleNotice',
  props: { component: { type: String, required: true } },
};
</script>
