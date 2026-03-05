<template>
  <div class="print-header">
    <img :src="logoUrl" alt="Fynla" class="print-logo" />
    <div class="print-title">{{ title }}</div>
  </div>
</template>

<script>
export default {
  name: 'PrintHeader',

  props: {
    title: {
      type: String,
      default: 'Financial Planning Report',
    },
  },

  data() {
    return {
      logoUrl: '/images/logos/LogoHiResFynlaDark.png',
    };
  },
};
</script>

<style scoped>
.print-header {
  display: none;
}

@media print {
  .print-header {
    display: flex !important;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 1rem;
    margin-bottom: 1.5rem;
    @apply border-b-2 border-light-gray;
  }

  .print-logo {
    height: 48px;
    width: auto;
  }

  .print-title {
    font-size: 1.25rem;
    font-weight: 600;
    @apply text-horizon-500;
  }
}
</style>
