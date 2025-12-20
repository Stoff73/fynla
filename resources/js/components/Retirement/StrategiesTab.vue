<template>
  <div class="strategies-tab">
    <!-- Content (blurred/faded) -->
    <div class="strategies-content">
      <div class="intro-text">
        <h3>Optimise Your Retirement Strategy</h3>
        <p>Based on your Future Value projections, we can suggest strategies to help you reach your retirement goals.</p>
      </div>

      <div class="strategy-cards">
        <div class="strategy-card">
          <div class="strategy-icon blue">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941" />
            </svg>
          </div>
          <h4>Increase Contributions</h4>
          <p>Optimise your monthly pension contributions to maximise growth</p>
        </div>

        <div class="strategy-card">
          <div class="strategy-icon purple">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <h4>Adjust Retirement Age</h4>
          <p>See the impact of retiring earlier or later on your income</p>
        </div>

        <div class="strategy-card">
          <div class="strategy-icon green">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
            </svg>
          </div>
          <h4>Risk Adjustment</h4>
          <p>Explore different investment strategies and risk profiles</p>
        </div>

        <div class="strategy-card">
          <div class="strategy-icon amber">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <h4>Lump Sum Investments</h4>
          <p>Model the impact of additional one-off contributions</p>
        </div>
      </div>
    </div>

    <!-- Coming Soon Banner -->
    <div class="coming-soon-overlay">
      <div class="coming-soon-banner">
        <p class="banner-text">Coming Soon</p>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'StrategiesTab',
};
</script>

<style scoped>
.strategies-tab {
  position: relative;
  min-height: 400px;
}

.strategies-content {
  opacity: 0.3;
  pointer-events: none;
  filter: blur(1px);
}

.intro-text {
  text-align: center;
  margin-bottom: 32px;
}

.intro-text h3 {
  font-size: 24px;
  font-weight: 700;
  color: #111827;
  margin: 0 0 12px 0;
}

.intro-text p {
  font-size: 16px;
  color: #6b7280;
  margin: 0;
  max-width: 600px;
  margin-left: auto;
  margin-right: auto;
}

.strategy-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 20px;
}

.strategy-card {
  background: white;
  border-radius: 12px;
  padding: 24px;
  border: 1px solid #e5e7eb;
  text-align: center;
}

.strategy-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 16px;
}

.strategy-icon svg {
  width: 24px;
  height: 24px;
}

.strategy-icon.blue {
  background: #dbeafe;
  color: #2563eb;
}

.strategy-icon.purple {
  background: #e9d5ff;
  color: #7c3aed;
}

.strategy-icon.green {
  background: #d1fae5;
  color: #059669;
}

.strategy-icon.amber {
  background: #fef3c7;
  color: #d97706;
}

.strategy-card h4 {
  font-size: 16px;
  font-weight: 600;
  color: #111827;
  margin: 0 0 8px 0;
}

.strategy-card p {
  font-size: 14px;
  color: #6b7280;
  margin: 0;
  line-height: 1.5;
}

/* Coming Soon Overlay */
.coming-soon-overlay {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 10;
  pointer-events: none;
}

.coming-soon-banner {
  background: #fef3c7;
  border: 2px solid #f59e0b;
  border-radius: 12px;
  padding: 16px 48px;
  transform: rotate(-12deg);
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
}

.banner-text {
  font-size: 32px;
  font-weight: 700;
  color: #b45309;
  margin: 0;
  text-transform: uppercase;
  letter-spacing: 2px;
}

@media (max-width: 768px) {
  .strategy-cards {
    grid-template-columns: 1fr;
  }

  .banner-text {
    font-size: 24px;
  }

  .coming-soon-banner {
    padding: 12px 32px;
  }
}
</style>
