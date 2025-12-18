<template>
  <div class="print-header">
    <img :src="logoUrl" alt="Fynla" class="print-logo" />
    <div class="print-title">{{ title }}</div>
  </div>
</template>

<script>
import logoImage from '@/assets/logo.png';

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
      logoUrl: logoImage,
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
    border-bottom: 2px solid #e5e7eb;
  }

  .print-logo {
    height: 48px;
    width: auto;
  }

  .print-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #374151;
  }
}
</style>
