<template>
  <div class="card p-4 -mt-2 border-violet-200 bg-violet-50">
    <div class="flex items-center gap-3">
      <input
        id="is_gift_aid"
        :checked="modelValue"
        type="checkbox"
        class="h-4 w-4 rounded border-light-gray text-violet-500 focus:ring-violet-500"
        @change="$emit('update:modelValue', $event.target.checked)"
      >
      <label for="is_gift_aid" class="text-body-sm text-horizon-500 font-medium">
        I use Gift Aid for my charitable donations
      </label>
    </div>
    <p class="mt-1 ml-7 text-body-sm text-neutral-500">
      Gift Aid lets charities claim 25p for every £1 you donate, and higher-rate taxpayers can claim back the difference
    </p>
  </div>
</template>

<script>
// The one Gift Aid question, shown beside the donations figure in both entry modes.
export default {
  name: 'GiftAidToggle',
  props: { modelValue: { type: Boolean, default: false } },
  emits: ['update:modelValue'],
};
</script>
