<template>
  <div class="gift-card" :class="statusClass">
    <div class="card-header">
      <div class="gift-icon" :class="iconClass">
        <i :class="giftIcon"></i>
      </div>
      <div class="gift-info">
        <h4 class="recipient-name">{{ gift.recipient || 'Unknown Recipient' }}</h4>
        <p class="gift-date">
          <i class="fas fa-calendar"></i>
          {{ formatDate(gift.gift_date) }}
        </p>
      </div>
      <div class="gift-value">
        <span class="value-label">Gift Value</span>
        <span class="value-amount">{{ formatCurrency(gift.gift_value) }}</span>
      </div>
    </div>

    <div class="card-body">
      <div class="info-row">
        <span class="label">Gift Type:</span>
        <span class="value">{{ giftTypeDisplay }}</span>
      </div>

      <div class="info-row">
        <span class="label">Years Elapsed:</span>
        <span class="value">{{ yearsElapsed.toFixed(1) }} years</span>
      </div>

      <div class="info-row">
        <span class="label">Years Remaining:</span>
        <span class="value">{{ yearsRemaining }} years</span>
      </div>

      <!-- Progress Bar for 7-Year Timeline -->
      <div class="timeline-progress">
        <div class="progress-label-row">
          <span class="progress-label">7-Year Survival Timeline</span>
          <span class="progress-percentage">{{ survivalPercentage }}%</span>
        </div>
        <div class="progress-bar-container">
          <div
            class="progress-bar"
            :class="progressBarClass"
            :style="{ width: survivalPercentage + '%' }"
          ></div>
        </div>
      </div>

      <!-- Taper Relief -->
      <div v-if="showTaperRelief" class="taper-relief">
        <div class="relief-badge" :class="reliefBadgeClass">
          <i class="fas fa-percentage"></i>
          <span>{{ taperReliefPercentage }}% Taper Relief</span>
        </div>
        <p class="relief-description">
          Effective Inheritance Tax rate: {{ effectiveIhtRate }}% (instead of 40%)
        </p>
      </div>

      <!-- Status Banner -->
      <div class="status-banner" :class="statusBannerClass">
        <i :class="statusIcon"></i>
        <strong>{{ statusText }}</strong>
      </div>
    </div>

    <div class="card-footer">
      <button class="btn btn-secondary btn-sm" @click="handleEdit">
        <i class="fas fa-edit"></i>
        Edit
      </button>
      <button class="btn btn-danger btn-sm" @click="handleDelete">
        <i class="fas fa-trash"></i>
        Delete
      </button>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'GiftCard',
  mixins: [currencyMixin],

  props: {
    gift: {
      type: Object,
      required: true,
    },
  },

  emits: ['edit', 'delete'],

  computed: {
    giftDate() {
      return new Date(this.gift.gift_date);
    },

    yearsElapsed() {
      const now = new Date();
      const diffTime = Math.abs(now - this.giftDate);
      return diffTime / (1000 * 60 * 60 * 24 * 365.25);
    },

    yearsRemaining() {
      const remaining = Math.max(0, 7 - this.yearsElapsed);
      return remaining.toFixed(1);
    },

    survivalPercentage() {
      return Math.min(100, (this.yearsElapsed / 7) * 100).toFixed(0);
    },

    showTaperRelief() {
      return this.yearsElapsed >= 3 && this.yearsElapsed < 7;
    },

    taperReliefPercentage() {
      if (this.yearsElapsed < 3) return 0;
      if (this.yearsElapsed < 4) return 20;
      if (this.yearsElapsed < 5) return 40;
      if (this.yearsElapsed < 6) return 60;
      if (this.yearsElapsed < 7) return 80;
      return 100;
    },

    effectiveIhtRate() {
      const baseRate = 40;
      const relief = this.taperReliefPercentage;
      return (baseRate * (100 - relief) / 100).toFixed(0);
    },

    giftTypeDisplay() {
      const typeMap = {
        pet: 'Potentially Exempt Transfer',
        clt: 'Chargeable Lifetime Transfer',
        exempt: 'Exempt Gift',
        small_gift: 'Small Gift Exemption',
        annual_exemption: 'Annual Exemption',
      };
      return typeMap[this.gift.gift_type] || this.gift.gift_type || 'General Gift';
    },

    giftIcon() {
      if (this.yearsElapsed >= 7) {
        return 'fas fa-check-circle';
      } else if (this.yearsElapsed >= 3) {
        return 'fas fa-clock';
      } else {
        return 'fas fa-gift';
      }
    },

    iconClass() {
      if (this.yearsElapsed >= 7) {
        return 'icon-success';
      } else if (this.yearsElapsed >= 3) {
        return 'icon-warning';
      } else {
        return 'icon-danger';
      }
    },

    statusClass() {
      if (this.yearsElapsed >= 7) {
        return 'status-exempt';
      } else if (this.yearsElapsed >= 3) {
        return 'status-taper';
      } else {
        return 'status-taxable';
      }
    },

    progressBarClass() {
      if (this.yearsElapsed >= 7) {
        return 'progress-complete';
      } else if (this.yearsElapsed >= 3) {
        return 'progress-partial';
      } else {
        return 'progress-early';
      }
    },

    reliefBadgeClass() {
      const relief = this.taperReliefPercentage;
      if (relief >= 80) return 'relief-high';
      if (relief >= 40) return 'relief-medium';
      return 'relief-low';
    },

    statusText() {
      if (this.yearsElapsed >= 7) {
        return 'Inheritance Tax-Exempt (Survived 7 Years)';
      } else if (this.yearsElapsed >= 3) {
        return `Taper Relief Applies (${this.taperReliefPercentage}%)`;
      } else {
        return 'Potentially Taxable (Within 3 Years)';
      }
    },

    statusIcon() {
      if (this.yearsElapsed >= 7) {
        return 'fas fa-shield-check';
      } else if (this.yearsElapsed >= 3) {
        return 'fas fa-hourglass-half';
      } else {
        return 'fas fa-exclamation-triangle';
      }
    },

    statusBannerClass() {
      if (this.yearsElapsed >= 7) {
        return 'banner-success';
      } else if (this.yearsElapsed >= 3) {
        return 'banner-warning';
      } else {
        return 'banner-danger';
      }
    },
  },

  methods: {
    formatDate(dateString) {
      if (!dateString) return 'Unknown Date';
      const date = new Date(dateString);
      return date.toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
      });
    },

    handleEdit() {
      this.$emit('edit', this.gift);
    },

    handleDelete() {
      if (confirm(`Are you sure you want to delete this gift to ${this.gift.recipient}?`)) {
        this.$emit('delete', this.gift.id);
      }
    },
  },
};
</script>

<style scoped>
.gift-card {
  background: white;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  transition: transform 0.2s, box-shadow 0.2s;
  overflow: hidden;
  @apply border border-light-gray;
}

.gift-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  @apply bg-light-gray;
}

.gift-card.status-exempt {
  @apply border-spring-500;
}

.gift-card.status-taper {
  @apply border-violet-500;
}

.gift-card.status-taxable {
  @apply border-raspberry-500;
}

.card-header {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 20px;
  @apply bg-eggshell-500;
  @apply border-b border-light-gray;
}

.gift-icon {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
}

.icon-success {
  @apply bg-spring-100;
  @apply text-spring-600;
}

.icon-warning {
  @apply bg-violet-100;
  @apply text-violet-600;
}

.icon-danger {
  @apply bg-raspberry-100;
  @apply text-raspberry-600;
}

.gift-info {
  flex: 1;
}

.recipient-name {
  font-size: 16px;
  font-weight: 600;
  @apply text-horizon-500;
  margin: 0 0 4px 0;
}

.gift-date {
  font-size: 13px;
  @apply text-neutral-500;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 6px;
}

.gift-value {
  text-align: right;
}

.value-label {
  display: block;
  font-size: 12px;
  @apply text-neutral-500;
  margin-bottom: 4px;
}

.value-amount {
  display: block;
  font-size: 18px;
  font-weight: 700;
  @apply text-horizon-500;
}

.card-body {
  padding: 20px;
}

.info-row {
  display: flex;
  justify-content: space-between;
  padding: 8px 0;
  font-size: 14px;
}

.info-row .label {
  @apply text-neutral-500;
  font-weight: 500;
}

.info-row .value {
  @apply text-horizon-500;
  font-weight: 600;
}

.timeline-progress {
  margin: 20px 0;
}

.progress-label-row {
  display: flex;
  justify-content: space-between;
  margin-bottom: 8px;
  font-size: 13px;
}

.progress-label {
  @apply text-neutral-500;
  font-weight: 500;
}

.progress-percentage {
  @apply text-horizon-500;
  font-weight: 600;
}

.progress-bar-container {
  height: 12px;
  @apply bg-savannah-100;
  border-radius: 6px;
  overflow: hidden;
}

.progress-bar {
  height: 100%;
  border-radius: 6px;
  transition: width 0.3s ease;
}

.progress-early {
  @apply bg-gradient-to-r from-raspberry-500 to-raspberry-600;
}

.progress-partial {
  @apply bg-gradient-to-r from-violet-500 to-horizon-500;
}

.progress-complete {
  @apply bg-gradient-to-r from-spring-500 to-spring-600;
}

.taper-relief {
  margin: 16px 0;
  padding: 12px;
  @apply bg-violet-50;
  border-radius: 6px;
}

.relief-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 12px;
  border-radius: 12px;
  font-size: 13px;
  font-weight: 600;
  margin-bottom: 8px;
}

.relief-low {
  @apply bg-raspberry-200;
  @apply text-raspberry-800;
}

.relief-medium {
  @apply bg-violet-200;
  @apply text-violet-800;
}

.relief-high {
  @apply bg-spring-100;
  @apply text-spring-800;
}

.relief-description {
  font-size: 12px;
  @apply text-violet-900;
  margin: 0;
}

.status-banner {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  border-radius: 6px;
  font-size: 14px;
  margin-top: 16px;
}

.status-banner i {
  font-size: 16px;
}

.banner-success {
  @apply bg-spring-100 text-spring-800 border border-spring-500;
}

.banner-warning {
  @apply bg-violet-100 text-violet-800 border border-violet-500;
}

.banner-danger {
  @apply bg-raspberry-100 text-raspberry-800 border border-raspberry-500;
}

.card-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 16px 20px;
  @apply bg-eggshell-500;
  @apply border-t border-light-gray;
}

.btn {
  padding: 8px 16px;
  border: none;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.btn-sm {
  padding: 6px 12px;
  font-size: 12px;
}

.btn-secondary {
  @apply bg-savannah-200;
  @apply text-neutral-500;
}

.btn-secondary:hover {
  @apply bg-savannah-300;
}

.btn-danger {
  @apply bg-raspberry-200;
  @apply text-raspberry-800;
}

.btn-danger:hover {
  @apply bg-raspberry-300;
}
</style>
