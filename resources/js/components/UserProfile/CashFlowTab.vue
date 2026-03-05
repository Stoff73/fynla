<template>
  <div class="relative">
    <!-- Coming Soon Watermark -->
    <div class="absolute inset-0 flex items-center justify-center z-10 pointer-events-none">
      <div class="bg-blue-100 border-2 border-blue-400 rounded-lg px-8 py-4 transform -rotate-12 shadow-lg">
        <p class="text-2xl font-bold text-blue-700">Coming Soon</p>
      </div>
    </div>

    <div class="opacity-50">
      <div class="mb-6">
        <h2 class="text-h4 font-semibold text-horizon-500">Cash Flow Statement</h2>
        <p class="mt-1 text-body-sm text-neutral-500">
          Track your cash inflows and outflows
        </p>
      </div>

      <!-- Placeholder content -->
      <div class="card p-8 text-center">
        <p class="text-body-base text-neutral-500">
          Cash Flow statement functionality will be available soon.
        </p>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'CashFlowTab',
};
</script>
