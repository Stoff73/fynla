<template>
  <div class="space-y-2">
    <select
      :value="block.stat_key"
      @change="update('stat_key', $event.target.value)"
      class="text-sm border border-light-gray rounded px-2 py-1 w-full"
    >
      <option value="">&mdash; select a stat &mdash;</option>
      <option value="isa_annual_allowance">ISA annual allowance</option>
      <option value="personal_allowance">Personal allowance</option>
      <option value="pension_annual_allowance">Pension annual allowance</option>
      <option value="iht_nil_rate_band">IHT nil rate band</option>
      <option value="cgt_annual_allowance">CGT annual allowance</option>
    </select>
    <input
      :value="block.label"
      @input="update('label', $event.target.value)"
      placeholder="Display label (e.g. &lsquo;This year&rsquo;s ISA allowance&rsquo;)"
      class="w-full text-sm px-3 py-2 border border-light-gray rounded"
    />
  </div>
</template>

<script>
export default {
  name: 'EditTaxYearStatBlock',
  props: { block: { type: Object, required: true } },
  emits: ['update'],
  methods: {
    update(f, v) { this.$emit('update', { ...this.block, [f]: v }); },
  },
};
</script>
