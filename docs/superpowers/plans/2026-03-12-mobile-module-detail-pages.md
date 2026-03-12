# Mobile Module Detail Pages Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace mobile module summary stubs with full read-only detail pages showing all financial data, removing all "view on website" references.

**Architecture:** Individual Vue detail pages per module, loading data from existing web Vuex stores. Shared `MobileAccordionSection` and `MobileDataRow` components provide consistent expand/collapse and data display. Two chart components (`MobileAllocationChart`, `MobileProjectionChart`) for high-impact visuals.

**Tech Stack:** Vue 3 Options API, Vuex, VueApexCharts, Tailwind CSS, currencyMixin

**Spec:** `docs/superpowers/specs/2026-03-12-mobile-module-detail-pages-design.md`

---

## Dependency Order

```
Task 1: Shared components (MobileAccordionSection, MobileDataRow)
   ↓
Tasks 2-8: Card + Chart components (parallel — no cross-dependencies)
   ↓
Tasks 9-15: Detail pages (parallel — each independent)
   ↓
Task 16: Router, navigation, removals
```

---

## Chunk 1: Shared Components

### Task 1: MobileAccordionSection + MobileDataRow

**Files:**
- Create: `resources/js/mobile/components/MobileAccordionSection.vue`
- Create: `resources/js/mobile/components/MobileDataRow.vue`

- [ ] **Step 1: Create MobileAccordionSection**

```vue
<template>
  <div class="bg-white rounded-xl border border-light-gray overflow-hidden">
    <button
      class="w-full px-4 py-3.5 flex items-center justify-between active:bg-savannah-100 transition-colors"
      @click="toggle"
    >
      <div class="flex items-center gap-2">
        <span v-if="icon" class="text-base">{{ icon }}</span>
        <h3 class="text-sm font-bold text-horizon-500">{{ title }}</h3>
        <span
          v-if="badge != null"
          class="ml-1 px-1.5 py-0.5 rounded-full bg-savannah-100 text-xs font-semibold text-horizon-500"
        >
          {{ badge }}
        </span>
      </div>
      <svg
        class="w-4 h-4 text-neutral-500 transition-transform duration-200"
        :class="{ 'rotate-180': isOpen }"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
      >
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
      </svg>
    </button>
    <transition name="expand">
      <div v-show="isOpen" class="border-t border-light-gray">
        <slot />
      </div>
    </transition>
  </div>
</template>

<script>
export default {
  name: 'MobileAccordionSection',

  props: {
    title: { type: String, required: true },
    icon: { type: String, default: null },
    defaultOpen: { type: Boolean, default: false },
    badge: { type: [String, Number], default: null },
  },

  data() {
    return {
      isOpen: this.defaultOpen,
    };
  },

  methods: {
    toggle() {
      this.isOpen = !this.isOpen;
    },
  },
};
</script>
```

- [ ] **Step 2: Create MobileDataRow**

```vue
<template>
  <div class="px-4 py-3 flex justify-between items-center">
    <span class="text-sm text-neutral-500">{{ label }}</span>
    <span
      class="text-sm font-medium text-right ml-4"
      :class="valueClass"
    >
      {{ formattedValue }}
    </span>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'MobileDataRow',

  mixins: [currencyMixin],

  props: {
    label: { type: String, required: true },
    value: { type: [String, Number], default: '—' },
    type: {
      type: String,
      default: 'text',
      validator: (v) => ['currency', 'percentage', 'text', 'status'].includes(v),
    },
    status: {
      type: String,
      default: null,
      validator: (v) => !v || ['good', 'warning', 'danger'].includes(v),
    },
  },

  computed: {
    formattedValue() {
      if (this.value == null || this.value === '') return '—';

      switch (this.type) {
      case 'currency':
        return this.formatCurrency(this.value);
      case 'percentage':
        return typeof this.value === 'number' ? `${this.value.toFixed(1)}%` : this.value;
      default:
        return String(this.value);
      }
    },

    valueClass() {
      if (this.status === 'good') return 'text-spring-500';
      if (this.status === 'warning') return 'text-violet-500';
      if (this.status === 'danger') return 'text-raspberry-500';
      return 'text-horizon-500';
    },
  },
};
</script>
```

- [ ] **Step 3: Verify build**

Run: `./dev.sh` (check terminal for compile errors)
Expected: No errors — components not yet imported anywhere

- [ ] **Step 4: Commit**

```bash
git add resources/js/mobile/components/MobileAccordionSection.vue resources/js/mobile/components/MobileDataRow.vue
git commit -m "feat(mobile): add MobileAccordionSection and MobileDataRow shared components"
```

---

## Chunk 2: Card Components

### Task 2: MobilePolicyCard

**Files:**
- Create: `resources/js/mobile/components/MobilePolicyCard.vue`

**Dependencies:** None (standalone component)

- [ ] **Step 1: Create MobilePolicyCard**

```vue
<template>
  <div class="px-4 py-3 flex items-start gap-3">
    <span class="text-xl mt-0.5">{{ policyIcon }}</span>
    <div class="flex-1 min-w-0">
      <h4 class="text-sm font-bold text-horizon-500 truncate">{{ policy.provider || policy.policy_name || 'Policy' }}</h4>
      <p class="text-xs text-neutral-500 mt-0.5">{{ policyTypeLabel }}</p>
      <div class="flex items-center gap-3 mt-1.5">
        <div>
          <p class="text-xs text-neutral-400">Cover</p>
          <p class="text-sm font-semibold text-horizon-500">{{ formatCurrency(policy.coverage_amount || policy.sum_assured || 0) }}</p>
        </div>
        <div>
          <p class="text-xs text-neutral-400">Premium</p>
          <p class="text-sm font-semibold text-horizon-500">{{ formatCurrency(policy.premium || 0) }}/mo</p>
        </div>
      </div>
      <p v-if="policy.end_date" class="text-xs text-neutral-400 mt-1">
        Expires {{ formatDate(policy.end_date) }}
      </p>
    </div>
    <span
      class="px-2 py-0.5 rounded-full text-xs font-semibold"
      :class="statusClass"
    >
      {{ statusLabel }}
    </span>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'MobilePolicyCard',

  mixins: [currencyMixin],

  props: {
    policy: { type: Object, required: true },
    policyType: { type: String, default: 'life' },
  },

  computed: {
    policyIcon() {
      const icons = {
        life: '\uD83D\uDEE1\uFE0F',
        criticalIllness: '\u2764\uFE0F',
        incomeProtection: '\uD83D\uDCB8',
        disability: '\u267F',
        sicknessIllness: '\uD83E\uDE7A',
      };
      return icons[this.policyType] || '\uD83D\uDEE1\uFE0F';
    },

    policyTypeLabel() {
      const labels = {
        life: 'Life insurance',
        criticalIllness: 'Critical illness',
        incomeProtection: 'Income protection',
        disability: 'Disability',
        sicknessIllness: 'Sickness & illness',
      };
      return labels[this.policyType] || this.policyType;
    },

    statusLabel() {
      if (this.policy.status === 'active') return 'Active';
      if (this.policy.status === 'lapsed') return 'Lapsed';
      if (this.policy.status === 'expired') return 'Expired';
      return 'Active';
    },

    statusClass() {
      if (this.policy.status === 'lapsed' || this.policy.status === 'expired') {
        return 'bg-raspberry-50 text-raspberry-500';
      }
      return 'bg-spring-50 text-spring-500';
    },
  },

  methods: {
    formatDate(dateStr) {
      if (!dateStr) return '';
      const d = new Date(dateStr);
      return d.toLocaleDateString('en-GB', { month: 'short', year: 'numeric' });
    },
  },
};
</script>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/mobile/components/MobilePolicyCard.vue
git commit -m "feat(mobile): add MobilePolicyCard component"
```

---

### Task 3: MobileAccountCard

**Files:**
- Create: `resources/js/mobile/components/MobileAccountCard.vue`

- [ ] **Step 1: Create MobileAccountCard**

```vue
<template>
  <div class="px-4 py-3">
    <div class="flex items-start justify-between">
      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2">
          <h4 class="text-sm font-bold text-horizon-500 truncate">{{ account.provider || account.platform || account.name || 'Account' }}</h4>
          <span
            v-if="typeBadge"
            class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase"
            :class="typeBadgeClass"
          >
            {{ typeBadge }}
          </span>
        </div>
        <p v-if="account.account_name && account.account_name !== account.provider" class="text-xs text-neutral-500 mt-0.5 truncate">
          {{ account.account_name }}
        </p>
      </div>
      <div class="text-right ml-3">
        <p class="text-sm font-bold text-horizon-500">{{ formatCurrency(account.balance || account.current_value || account.value || 0) }}</p>
        <p v-if="secondaryMetric" class="text-xs text-neutral-500 mt-0.5">{{ secondaryMetric }}</p>
      </div>
    </div>
    <div v-if="detailChips.length" class="flex flex-wrap gap-2 mt-2">
      <span
        v-for="chip in detailChips"
        :key="chip"
        class="text-xs text-neutral-400"
      >
        {{ chip }}
      </span>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'MobileAccountCard',

  mixins: [currencyMixin],

  props: {
    account: { type: Object, required: true },
    variant: {
      type: String,
      default: 'savings',
      validator: (v) => ['savings', 'investment'].includes(v),
    },
  },

  computed: {
    typeBadge() {
      const type = (this.account.account_type || this.account.type || '').toLowerCase();
      if (type.includes('isa')) return 'ISA';
      if (type.includes('sipp')) return 'SIPP';
      if (type.includes('gia')) return 'GIA';
      if (type.includes('lisa')) return 'LISA';
      if (type.includes('junior')) return 'JISA';
      return null;
    },

    typeBadgeClass() {
      const type = (this.account.account_type || this.account.type || '').toLowerCase();
      if (type.includes('isa')) return 'bg-light-blue-100 text-light-blue-700';
      if (type.includes('sipp')) return 'bg-violet-50 text-violet-500';
      if (type.includes('gia')) return 'bg-savannah-100 text-horizon-500';
      return 'bg-savannah-100 text-horizon-500';
    },

    secondaryMetric() {
      if (this.variant === 'savings' && this.account.interest_rate) {
        return `${this.account.interest_rate}% AER`;
      }
      if (this.variant === 'investment') {
        const count = this.account.holdings?.length || this.account.holdings_count || 0;
        return `${count} holding${count !== 1 ? 's' : ''}`;
      }
      return null;
    },

    detailChips() {
      const chips = [];
      if (this.account.ownership_type && this.account.ownership_type !== 'individual') {
        chips.push(this.account.ownership_type === 'joint' ? 'Joint' : this.account.ownership_type);
      }
      if (this.variant === 'investment' && this.account.risk_level) {
        chips.push(`Risk: ${this.account.risk_level}`);
      }
      if (this.variant === 'savings' && this.account.access_type) {
        const labels = { easy_access: 'Easy access', notice: 'Notice', fixed_rate: 'Fixed rate' };
        chips.push(labels[this.account.access_type] || this.account.access_type);
      }
      return chips;
    },
  },
};
</script>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/mobile/components/MobileAccountCard.vue
git commit -m "feat(mobile): add MobileAccountCard component"
```

---

### Task 4: MobilePensionCard

**Files:**
- Create: `resources/js/mobile/components/MobilePensionCard.vue`

- [ ] **Step 1: Create MobilePensionCard**

```vue
<template>
  <div class="px-4 py-3">
    <div class="flex items-start justify-between">
      <div class="flex-1 min-w-0">
        <h4 class="text-sm font-bold text-horizon-500 truncate">{{ pension.scheme_name || pension.name || 'Pension' }}</h4>
        <p class="text-xs text-neutral-500 mt-0.5">{{ typeLabel }}</p>
      </div>
      <div class="text-right ml-3">
        <p class="text-sm font-bold text-horizon-500">{{ primaryValue }}</p>
        <p class="text-xs text-neutral-500 mt-0.5">{{ primaryLabel }}</p>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-x-4 gap-y-1.5 mt-3 pt-3 border-t border-light-gray">
      <div v-for="detail in details" :key="detail.label">
        <p class="text-xs text-neutral-400">{{ detail.label }}</p>
        <p class="text-xs font-medium text-horizon-500">{{ detail.value }}</p>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'MobilePensionCard',

  mixins: [currencyMixin],

  props: {
    pension: { type: Object, required: true },
    pensionType: {
      type: String,
      required: true,
      validator: (v) => ['dc', 'db', 'state'].includes(v),
    },
  },

  computed: {
    typeLabel() {
      if (this.pensionType === 'dc') {
        const sub = this.pension.pension_type || this.pension.type || '';
        if (sub.toLowerCase().includes('sipp')) return 'Self-invested personal pension';
        if (sub.toLowerCase().includes('workplace')) return 'Workplace pension';
        return 'Defined contribution';
      }
      if (this.pensionType === 'db') return 'Defined benefit';
      return 'State pension';
    },

    primaryValue() {
      if (this.pensionType === 'dc') return this.formatCurrency(this.pension.fund_value || this.pension.current_value || 0);
      if (this.pensionType === 'db') return this.formatCurrency(this.pension.annual_income || this.pension.annual_pension || 0);
      return this.formatCurrency((this.pension.weekly_amount || 0) * 52);
    },

    primaryLabel() {
      if (this.pensionType === 'dc') return 'Fund value';
      if (this.pensionType === 'db') return 'Annual income';
      return 'Annual forecast';
    },

    details() {
      if (this.pensionType === 'dc') {
        const items = [];
        if (this.pension.provider) items.push({ label: 'Provider', value: this.pension.provider });
        if (this.pension.employee_contribution_pct != null) {
          items.push({ label: 'Employee', value: `${this.pension.employee_contribution_pct}%` });
        }
        if (this.pension.employer_contribution_pct != null) {
          items.push({ label: 'Employer', value: `${this.pension.employer_contribution_pct}%` });
        }
        if (this.pension.projected_value) {
          items.push({ label: 'Projected', value: this.formatCurrency(this.pension.projected_value) });
        }
        return items;
      }

      if (this.pensionType === 'db') {
        const items = [];
        if (this.pension.employer) items.push({ label: 'Employer', value: this.pension.employer });
        if (this.pension.accrual_rate) items.push({ label: 'Accrual rate', value: this.pension.accrual_rate });
        if (this.pension.normal_retirement_age) items.push({ label: 'Retirement age', value: String(this.pension.normal_retirement_age) });
        if (this.pension.spouse_benefit_pct != null) items.push({ label: 'Spouse benefit', value: `${this.pension.spouse_benefit_pct}%` });
        return items;
      }

      // State pension
      const items = [];
      if (this.pension.weekly_amount) items.push({ label: 'Weekly', value: this.formatCurrency(this.pension.weekly_amount) });
      if (this.pension.qualifying_years != null) items.push({ label: 'Qualifying years', value: `${this.pension.qualifying_years} of 35` });
      if (this.pension.state_pension_age) items.push({ label: 'Pension age', value: String(this.pension.state_pension_age) });
      return items;
    },
  },
};
</script>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/mobile/components/MobilePensionCard.vue
git commit -m "feat(mobile): add MobilePensionCard component"
```

---

### Task 5: MobileHoldingRow

**Files:**
- Create: `resources/js/mobile/components/MobileHoldingRow.vue`

- [ ] **Step 1: Create MobileHoldingRow**

```vue
<template>
  <div class="px-4 py-2.5 flex items-center justify-between">
    <div class="flex-1 min-w-0">
      <p class="text-sm font-medium text-horizon-500 truncate">{{ holding.security_name || holding.name || 'Holding' }}</p>
      <p v-if="holding.ticker || holding.asset_type" class="text-xs text-neutral-400 mt-0.5">
        <span v-if="holding.ticker">{{ holding.ticker }}</span>
        <span v-if="holding.ticker && holding.asset_type"> &middot; </span>
        <span v-if="holding.asset_type">{{ holding.asset_type }}</span>
      </p>
    </div>
    <div class="text-right ml-3">
      <p class="text-sm font-semibold text-horizon-500">{{ formatCurrency(holding.current_value || holding.value || 0) }}</p>
      <p v-if="allocationPct != null" class="text-xs text-neutral-400 mt-0.5">{{ allocationPct.toFixed(1) }}%</p>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'MobileHoldingRow',

  mixins: [currencyMixin],

  props: {
    holding: { type: Object, required: true },
    allocationPct: { type: Number, default: null },
  },
};
</script>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/mobile/components/MobileHoldingRow.vue
git commit -m "feat(mobile): add MobileHoldingRow component"
```

---

### Task 6: MobileEstateAssetCard, MobileTrustCard, MobileGiftCard

**Files:**
- Create: `resources/js/mobile/components/MobileEstateAssetCard.vue`
- Create: `resources/js/mobile/components/MobileTrustCard.vue`
- Create: `resources/js/mobile/components/MobileGiftCard.vue`

- [ ] **Step 1: Create MobileEstateAssetCard**

```vue
<template>
  <div class="px-4 py-3 flex items-start justify-between">
    <div class="flex-1 min-w-0">
      <div class="flex items-center gap-2">
        <span class="text-base">{{ assetIcon }}</span>
        <h4 class="text-sm font-bold text-horizon-500 truncate">{{ asset.description || asset.name || 'Asset' }}</h4>
      </div>
      <p class="text-xs text-neutral-500 mt-0.5">{{ assetTypeLabel }}</p>
      <p v-if="ownershipLabel" class="text-xs text-neutral-400 mt-0.5">{{ ownershipLabel }}</p>
    </div>
    <p class="text-sm font-bold text-horizon-500 ml-3">{{ formatCurrency(asset.value || asset.current_value || 0) }}</p>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'MobileEstateAssetCard',

  mixins: [currencyMixin],

  props: {
    asset: { type: Object, required: true },
  },

  computed: {
    assetIcon() {
      const icons = {
        property: '\uD83C\uDFE0',
        main_residence: '\uD83C\uDFE0',
        secondary_residence: '\uD83C\uDFE1',
        buy_to_let: '\uD83C\uDFE2',
        collectible: '\uD83C\uDFA8',
        business: '\uD83C\uDFED',
        chattels: '\uD83D\uDC8E',
      };
      return icons[this.asset.asset_type || this.asset.type] || '\uD83D\uDCE6';
    },

    assetTypeLabel() {
      const labels = {
        property: 'Property',
        main_residence: 'Main residence',
        secondary_residence: 'Secondary residence',
        buy_to_let: 'Buy to let',
        collectible: 'Collectible',
        business: 'Business interest',
        chattels: 'Chattels',
      };
      return labels[this.asset.asset_type || this.asset.type] || 'Asset';
    },

    ownershipLabel() {
      if (!this.asset.ownership_type || this.asset.ownership_type === 'individual') return null;
      if (this.asset.ownership_type === 'joint') return 'Joint ownership';
      if (this.asset.ownership_type === 'tenants_in_common') return 'Tenants in common';
      if (this.asset.ownership_type === 'trust') return 'Held in trust';
      return this.asset.ownership_type;
    },
  },
};
</script>
```

- [ ] **Step 2: Create MobileTrustCard**

```vue
<template>
  <div class="px-4 py-3">
    <div class="flex items-start justify-between">
      <div class="flex-1 min-w-0">
        <h4 class="text-sm font-bold text-horizon-500 truncate">{{ trust.name || 'Trust' }}</h4>
        <p class="text-xs text-neutral-500 mt-0.5">{{ trustTypeLabel }}</p>
      </div>
      <p class="text-sm font-bold text-horizon-500 ml-3">{{ formatCurrency(trust.value || trust.total_value || 0) }}</p>
    </div>
    <div v-if="details.length" class="grid grid-cols-2 gap-x-4 gap-y-1 mt-2 pt-2 border-t border-light-gray">
      <div v-for="detail in details" :key="detail.label">
        <p class="text-xs text-neutral-400">{{ detail.label }}</p>
        <p class="text-xs font-medium text-horizon-500 truncate">{{ detail.value }}</p>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'MobileTrustCard',

  mixins: [currencyMixin],

  props: {
    trust: { type: Object, required: true },
  },

  computed: {
    trustTypeLabel() {
      const labels = {
        bare: 'Bare trust',
        discretionary: 'Discretionary trust',
        interest_in_possession: 'Interest in possession',
        life_interest: 'Life interest trust',
        accumulation_maintenance: 'Accumulation & maintenance',
      };
      return labels[this.trust.type || this.trust.trust_type] || 'Trust';
    },

    details() {
      const items = [];
      if (this.trust.settlor) items.push({ label: 'Settlor', value: this.trust.settlor });
      if (this.trust.trustees) {
        const trustees = Array.isArray(this.trust.trustees) ? this.trust.trustees.join(', ') : this.trust.trustees;
        items.push({ label: 'Trustees', value: trustees });
      }
      if (this.trust.beneficiaries) {
        const bens = Array.isArray(this.trust.beneficiaries) ? this.trust.beneficiaries.join(', ') : this.trust.beneficiaries;
        items.push({ label: 'Beneficiaries', value: bens });
      }
      return items;
    },
  },
};
</script>
```

- [ ] **Step 3: Create MobileGiftCard**

```vue
<template>
  <div class="px-4 py-3 flex items-start justify-between">
    <div class="flex-1 min-w-0">
      <h4 class="text-sm font-bold text-horizon-500 truncate">{{ gift.recipient || 'Gift' }}</h4>
      <p class="text-xs text-neutral-500 mt-0.5">{{ giftDate }}</p>
      <div class="flex items-center gap-2 mt-1">
        <span
          class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase"
          :class="gift.is_exempt ? 'bg-spring-50 text-spring-500' : 'bg-violet-50 text-violet-500'"
        >
          {{ gift.is_exempt ? 'Exempt' : 'PET' }}
        </span>
        <span v-if="yearsSinceGift != null" class="text-xs text-neutral-400">
          {{ yearsSinceGift }} year{{ yearsSinceGift !== 1 ? 's' : '' }} ago
        </span>
        <span v-if="taperPct != null" class="text-xs text-neutral-400">
          &middot; {{ taperPct }}% taper
        </span>
      </div>
    </div>
    <p class="text-sm font-bold text-horizon-500 ml-3">{{ formatCurrency(gift.value || gift.amount || 0) }}</p>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'MobileGiftCard',

  mixins: [currencyMixin],

  props: {
    gift: { type: Object, required: true },
  },

  computed: {
    giftDate() {
      if (!this.gift.date && !this.gift.gift_date) return '';
      const d = new Date(this.gift.date || this.gift.gift_date);
      return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
    },

    yearsSinceGift() {
      const dateStr = this.gift.date || this.gift.gift_date;
      if (!dateStr) return null;
      const d = new Date(dateStr);
      const years = Math.floor((Date.now() - d.getTime()) / (365.25 * 24 * 60 * 60 * 1000));
      return years;
    },

    taperPct() {
      if (this.gift.taper_percentage != null) return this.gift.taper_percentage;
      if (this.gift.taper_relief != null) return this.gift.taper_relief;
      return null;
    },
  },
};
</script>
```

- [ ] **Step 4: Commit**

```bash
git add resources/js/mobile/components/MobileEstateAssetCard.vue resources/js/mobile/components/MobileTrustCard.vue resources/js/mobile/components/MobileGiftCard.vue
git commit -m "feat(mobile): add estate card components (asset, trust, gift)"
```

---

### Task 7: MobileAllocationChart

**Files:**
- Create: `resources/js/mobile/components/MobileAllocationChart.vue`

**Dependencies:** VueApexCharts (already registered globally)

- [ ] **Step 1: Create MobileAllocationChart**

```vue
<template>
  <div class="px-4 py-3">
    <apexchart
      v-if="hasData"
      type="donut"
      height="200"
      :options="chartOptions"
      :series="series"
    />
    <p v-else class="text-sm text-neutral-500 text-center py-4">No allocation data</p>

    <!-- Legend rows -->
    <div v-if="hasData" class="mt-3 space-y-1.5">
      <div
        v-for="(item, index) in items"
        :key="item.label"
        class="flex items-center justify-between"
      >
        <div class="flex items-center gap-2">
          <span class="w-2.5 h-2.5 rounded-full" :style="{ backgroundColor: colors[index % colors.length] }"></span>
          <span class="text-xs text-neutral-500">{{ item.label }}</span>
        </div>
        <span class="text-xs font-medium text-horizon-500">{{ item.percentage.toFixed(1) }}%</span>
      </div>
    </div>
  </div>
</template>

<script>
import { CHART_COLORS } from '@/constants/designSystem';

export default {
  name: 'MobileAllocationChart',

  props: {
    items: {
      type: Array,
      required: true,
      // Each item: { label: String, value: Number, percentage: Number }
    },
  },

  computed: {
    colors() {
      return CHART_COLORS;
    },

    hasData() {
      return this.items && this.items.length > 0;
    },

    series() {
      return this.items.map(i => i.value || 0);
    },

    chartOptions() {
      return {
        chart: {
          type: 'donut',
          sparkline: { enabled: true },
        },
        colors: this.colors,
        labels: this.items.map(i => i.label),
        legend: { show: false },
        dataLabels: { enabled: false },
        tooltip: {
          enabled: true,
          y: {
            formatter: (val) => `${val.toFixed(1)}%`,
          },
        },
        plotOptions: {
          pie: {
            donut: {
              size: '55%',
            },
          },
        },
        stroke: {
          width: 2,
          colors: ['#fff'],
        },
      };
    },
  },
};
</script>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/mobile/components/MobileAllocationChart.vue
git commit -m "feat(mobile): add MobileAllocationChart donut component"
```

---

### Task 8: MobileProjectionChart

**Files:**
- Create: `resources/js/mobile/components/MobileProjectionChart.vue`

- [ ] **Step 1: Create MobileProjectionChart**

```vue
<template>
  <div class="px-4 py-3">
    <apexchart
      v-if="hasSeries"
      type="area"
      height="200"
      :options="chartOptions"
      :series="series"
    />
    <p v-else class="text-sm text-neutral-500 text-center py-4">No projection data</p>
  </div>
</template>

<script>
import { CHART_COLORS } from '@/constants/designSystem';
import { formatCurrency } from '@/utils/currency';

export default {
  name: 'MobileProjectionChart',

  props: {
    series: {
      type: Array,
      required: true,
      // Array of { name: String, data: Array<Number> }
    },
    categories: {
      type: Array,
      required: true,
      // Array of labels (years, dates, etc.)
    },
    yAxisLabel: { type: String, default: '' },
  },

  computed: {
    hasSeries() {
      return this.series && this.series.length > 0 && this.series.some(s => s.data?.length > 0);
    },

    chartOptions() {
      return {
        chart: {
          type: 'area',
          toolbar: { show: false },
          zoom: { enabled: false },
          fontFamily: 'Segoe UI, Inter, sans-serif',
        },
        colors: CHART_COLORS,
        dataLabels: { enabled: false },
        stroke: {
          curve: 'smooth',
          width: 2,
        },
        fill: {
          type: 'gradient',
          gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.3,
            opacityTo: 0.05,
            stops: [0, 100],
          },
        },
        xaxis: {
          categories: this.categories,
          labels: {
            style: { colors: '#717171', fontSize: '10px' },
            rotate: 0,
          },
          axisBorder: { show: false },
          axisTicks: { show: false },
        },
        yaxis: {
          labels: {
            style: { colors: '#717171', fontSize: '10px' },
            formatter: (val) => formatCurrency(val),
          },
          title: { text: this.yAxisLabel, style: { color: '#717171', fontSize: '10px' } },
        },
        grid: {
          borderColor: '#EEEEEE',
          strokeDashArray: 3,
          xaxis: { lines: { show: false } },
        },
        legend: {
          position: 'bottom',
          fontSize: '11px',
          fontFamily: 'Segoe UI, Inter, sans-serif',
          labels: { colors: '#717171' },
          markers: { size: 4, shape: 'circle' },
        },
        tooltip: {
          y: {
            formatter: (val) => formatCurrency(val),
          },
        },
      };
    },
  },
};
</script>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/mobile/components/MobileProjectionChart.vue
git commit -m "feat(mobile): add MobileProjectionChart line/area component"
```

---

## Chunk 3: Detail Pages (Protection, Savings, Investment)

### Task 9: ProtectionDetail

**Files:**
- Create: `resources/js/mobile/views/ProtectionDetail.vue`

**Store:** `protection` — `fetchProtectionData`, `analyseProtection`

- [ ] **Step 1: Create ProtectionDetail.vue**

```vue
<template>
  <div class="px-4 pt-4 pb-6">
    <!-- Loading -->
    <div v-if="loading" class="space-y-3">
      <div class="bg-white rounded-xl p-6 animate-pulse">
        <div class="w-24 h-8 bg-savannah-100 rounded mx-auto"></div>
        <div class="w-32 h-4 bg-savannah-100 rounded mx-auto mt-2"></div>
      </div>
      <div v-for="n in 3" :key="n" class="bg-white rounded-xl p-4 animate-pulse">
        <div class="w-40 h-4 bg-savannah-100 rounded"></div>
      </div>
    </div>

    <template v-else-if="hasData">
      <!-- Hero card -->
      <div class="bg-white rounded-xl border border-light-gray p-6 text-center mb-4">
        <span class="text-3xl block mb-2">{{'🛡️'}}</span>
        <h2 class="text-lg font-bold text-horizon-500">Protection</h2>
        <p class="text-2xl font-black text-horizon-500 mt-3">{{ formatCurrency(totalCoverage) }}</p>
        <p class="text-xs text-neutral-500 mt-1">Total cover</p>
        <div v-if="coverageGaps.length" class="mt-2">
          <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-violet-50 text-violet-500">
            {{ coverageGaps.length }} gap{{ coverageGaps.length > 1 ? 's' : '' }} identified
          </span>
        </div>
      </div>

      <!-- Fyn summary -->
      <div class="bg-horizon-500 rounded-xl p-4 flex items-start gap-3 mb-4">
        <img src="/images/logos/favicon.png" alt="Fyn" class="w-8 h-8 rounded-full flex-shrink-0" />
        <p class="text-white text-sm leading-relaxed">{{ fynSummary }}</p>
      </div>

      <!-- Policies -->
      <MobileAccordionSection
        title="Policies"
        icon="📋"
        :badge="allPolicies.length || null"
        :default-open="true"
        class="mb-3"
      >
        <template v-if="allPolicies.length">
          <div class="divide-y divide-light-gray">
            <MobilePolicyCard
              v-for="policy in allPolicies"
              :key="policy.id"
              :policy="policy"
              :policy-type="policy._type"
            />
          </div>
        </template>
        <p v-else class="px-4 py-6 text-sm text-neutral-500 text-center">No policies added yet</p>
      </MobileAccordionSection>

      <!-- Coverage Analysis -->
      <MobileAccordionSection title="Coverage analysis" icon="📊" class="mb-3">
        <div class="divide-y divide-light-gray">
          <MobileDataRow label="Total cover" :value="totalCoverage" type="currency" />
          <MobileDataRow label="Monthly premiums" :value="totalPremium" type="currency" />
          <MobileDataRow
            label="Income protection"
            :value="hasIncomeProtection ? 'Yes' : 'No'"
            :status="hasIncomeProtection ? 'good' : 'warning'"
          />
          <MobileDataRow
            label="Critical illness cover"
            :value="hasCriticalIllness ? 'Yes' : 'No'"
            :status="hasCriticalIllness ? 'good' : 'warning'"
          />
        </div>
      </MobileAccordionSection>

      <!-- Gaps & Recommendations -->
      <MobileAccordionSection
        v-if="coverageGaps.length || recommendations.length"
        title="Gaps & recommendations"
        icon="⚠️"
        :badge="coverageGaps.length + recommendations.length || null"
        class="mb-3"
      >
        <div class="divide-y divide-light-gray">
          <div v-for="gap in coverageGaps" :key="'gap-' + gap.type" class="px-4 py-3">
            <div class="flex items-center gap-2 mb-1">
              <span class="w-2 h-2 rounded-full bg-raspberry-500"></span>
              <p class="text-sm font-medium text-horizon-500">{{ gap.description || gap.type }}</p>
            </div>
            <p v-if="gap.recommendation" class="text-xs text-neutral-500 ml-4">{{ gap.recommendation }}</p>
          </div>
          <div v-for="rec in recommendations" :key="'rec-' + rec.id" class="px-4 py-3">
            <div class="flex items-center gap-2 mb-1">
              <span
                class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase"
                :class="rec.priority === 'high' ? 'bg-raspberry-50 text-raspberry-500' : 'bg-violet-50 text-violet-500'"
              >
                {{ rec.priority || 'medium' }}
              </span>
              <p class="text-sm font-medium text-horizon-500">{{ rec.title || rec.description }}</p>
            </div>
            <p v-if="rec.description && rec.title" class="text-xs text-neutral-500 ml-4">{{ rec.description }}</p>
          </div>
        </div>
      </MobileAccordionSection>
    </template>

    <!-- Empty state -->
    <div v-else class="text-center py-16">
      <span class="text-4xl block mb-3">{{'🛡️'}}</span>
      <h3 class="text-base font-bold text-horizon-500 mb-1">No protection data yet</h3>
      <p class="text-sm text-neutral-500">Your protection policies will appear here</p>
    </div>
  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import MobileAccordionSection from '@/mobile/components/MobileAccordionSection.vue';
import MobileDataRow from '@/mobile/components/MobileDataRow.vue';
import MobilePolicyCard from '@/mobile/components/MobilePolicyCard.vue';

export default {
  name: 'ProtectionDetail',

  components: { MobileAccordionSection, MobileDataRow, MobilePolicyCard },

  mixins: [currencyMixin],

  data() {
    return {
      loading: false,
    };
  },

  computed: {
    ...mapGetters('protection', [
      'allPolicies',
      'totalCoverage',
      'totalPremium',
      'coverageGaps',
      'hasIncomeProtection',
      'hasCriticalIllness',
      'priorityRecommendations',
    ]),

    recommendations() {
      return this.priorityRecommendations || [];
    },

    hasData() {
      return this.allPolicies?.length > 0 || this.totalCoverage > 0;
    },

    fynSummary() {
      if (this.coverageGaps?.length > 0) {
        return `You have ${this.coverageGaps.length} protection gap${this.coverageGaps.length > 1 ? 's' : ''} that may need attention.`;
      }
      return 'Your protection cover looks solid.';
    },
  },

  async created() {
    await this.loadData();
  },

  methods: {
    async loadData() {
      this.loading = true;
      try {
        await this.$store.dispatch('protection/fetchProtectionData');
      } catch {
        // Data unavailable
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>
```

- [ ] **Step 2: Verify build**

Run: `./dev.sh` (check for compile errors)

- [ ] **Step 3: Commit**

```bash
git add resources/js/mobile/views/ProtectionDetail.vue
git commit -m "feat(mobile): add ProtectionDetail full read-only view"
```

---

### Task 10: SavingsDetail

**Files:**
- Create: `resources/js/mobile/views/SavingsDetail.vue`

**Store:** `savings` — `fetchSavingsData`

- [ ] **Step 1: Create SavingsDetail.vue**

```vue
<template>
  <div class="px-4 pt-4 pb-6">
    <div v-if="loading" class="space-y-3">
      <div class="bg-white rounded-xl p-6 animate-pulse">
        <div class="w-24 h-8 bg-savannah-100 rounded mx-auto"></div>
      </div>
      <div v-for="n in 3" :key="n" class="bg-white rounded-xl p-4 animate-pulse">
        <div class="w-40 h-4 bg-savannah-100 rounded"></div>
      </div>
    </div>

    <template v-else-if="hasData">
      <!-- Hero -->
      <div class="bg-white rounded-xl border border-light-gray p-6 text-center mb-4">
        <span class="text-3xl block mb-2">{{'💰'}}</span>
        <h2 class="text-lg font-bold text-horizon-500">Savings</h2>
        <p class="text-2xl font-black text-horizon-500 mt-3">{{ formatCurrency(totalSavings) }}</p>
        <p class="text-xs text-neutral-500 mt-1">Total savings</p>
      </div>

      <!-- Fyn -->
      <div class="bg-horizon-500 rounded-xl p-4 flex items-start gap-3 mb-4">
        <img src="/images/logos/favicon.png" alt="Fyn" class="w-8 h-8 rounded-full flex-shrink-0" />
        <p class="text-white text-sm leading-relaxed">{{ fynSummary }}</p>
      </div>

      <!-- Accounts -->
      <MobileAccordionSection
        title="Accounts"
        icon="🏦"
        :badge="accounts.length || null"
        :default-open="true"
        class="mb-3"
      >
        <template v-if="accounts.length">
          <div class="divide-y divide-light-gray">
            <MobileAccountCard
              v-for="account in accounts"
              :key="account.id"
              :account="account"
              variant="savings"
            />
          </div>
        </template>
        <p v-else class="px-4 py-6 text-sm text-neutral-500 text-center">No accounts added yet</p>
      </MobileAccordionSection>

      <!-- Emergency Fund -->
      <MobileAccordionSection title="Emergency fund" icon="🆘" class="mb-3">
        <div class="divide-y divide-light-gray">
          <MobileDataRow label="Emergency savings" :value="emergencyFundTotal" type="currency" />
          <MobileDataRow
            label="Months covered"
            :value="emergencyFundMonths"
            :status="emergencyFundMonths < 3 ? 'warning' : 'good'"
          />
          <MobileDataRow label="Target" value="3-6 months of expenditure" />
          <MobileDataRow
            v-if="monthlyExpenditure"
            label="Monthly expenditure"
            :value="monthlyExpenditure"
            type="currency"
          />
        </div>
      </MobileAccordionSection>

      <!-- ISA Allowance -->
      <MobileAccordionSection title="ISA allowance" icon="📊" class="mb-3">
        <div class="divide-y divide-light-gray">
          <MobileDataRow label="Total allowance" :value="isaTotal" type="currency" />
          <MobileDataRow label="Used this year" :value="isaUsed" type="currency" />
          <MobileDataRow
            label="Remaining"
            :value="isaRemaining"
            type="currency"
            :status="isaRemaining > 0 ? 'good' : 'warning'"
          />
          <MobileDataRow label="Usage" :value="isaUsagePercent" type="percentage" />
        </div>
      </MobileAccordionSection>
    </template>

    <div v-else class="text-center py-16">
      <span class="text-4xl block mb-3">{{'💰'}}</span>
      <h3 class="text-base font-bold text-horizon-500 mb-1">No savings data yet</h3>
      <p class="text-sm text-neutral-500">Your savings accounts will appear here</p>
    </div>
  </div>
</template>

<script>
import { mapState, mapGetters } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import MobileAccordionSection from '@/mobile/components/MobileAccordionSection.vue';
import MobileDataRow from '@/mobile/components/MobileDataRow.vue';
import MobileAccountCard from '@/mobile/components/MobileAccountCard.vue';

export default {
  name: 'SavingsDetail',

  components: { MobileAccordionSection, MobileDataRow, MobileAccountCard },

  mixins: [currencyMixin],

  data() {
    return { loading: false };
  },

  computed: {
    ...mapState('savings', ['accounts']),
    ...mapGetters('savings', [
      'totalSavings',
      'emergencyFundTotal',
      'emergencyFundRunway',
      'isaAllowanceRemaining',
      'isaUsagePercent',
      'currentYearISASubscription',
      'monthlyExpenditure',
    ]),

    hasData() {
      return this.accounts?.length > 0 || this.totalSavings > 0;
    },

    emergencyFundMonths() {
      return typeof this.emergencyFundRunway === 'number' ? parseFloat(this.emergencyFundRunway.toFixed(1)) : 0;
    },

    isaTotal() {
      const allowance = this.$store.state.savings.isaAllowance;
      return allowance?.total_allowance || 20000;
    },

    isaUsed() {
      return this.currentYearISASubscription || 0;
    },

    isaRemaining() {
      return this.isaAllowanceRemaining || 0;
    },

    fynSummary() {
      if (this.emergencyFundMonths < 3) {
        return `Your emergency fund covers ${this.emergencyFundMonths.toFixed(1)} months of expenditure. Building towards 3-6 months is recommended.`;
      }
      return `Your emergency fund covers ${this.emergencyFundMonths.toFixed(1)} months of expenditure. Well done!`;
    },
  },

  async created() {
    this.loading = true;
    try {
      await this.$store.dispatch('savings/fetchSavingsData');
    } catch {
      // Data unavailable
    } finally {
      this.loading = false;
    }
  },
};
</script>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/mobile/views/SavingsDetail.vue
git commit -m "feat(mobile): add SavingsDetail full read-only view"
```

---

### Task 11: InvestmentDetail

**Files:**
- Create: `resources/js/mobile/views/InvestmentDetail.vue`

**Store:** `investment` — `fetchAccounts`, `analyseInvestment`

- [ ] **Step 1: Create InvestmentDetail.vue**

```vue
<template>
  <div class="px-4 pt-4 pb-6">
    <div v-if="loading" class="space-y-3">
      <div class="bg-white rounded-xl p-6 animate-pulse">
        <div class="w-24 h-8 bg-savannah-100 rounded mx-auto"></div>
      </div>
      <div v-for="n in 5" :key="n" class="bg-white rounded-xl p-4 animate-pulse">
        <div class="w-40 h-4 bg-savannah-100 rounded"></div>
      </div>
    </div>

    <template v-else-if="hasData">
      <!-- Hero -->
      <div class="bg-white rounded-xl border border-light-gray p-6 text-center mb-4">
        <span class="text-3xl block mb-2">{{'📈'}}</span>
        <h2 class="text-lg font-bold text-horizon-500">Investment</h2>
        <p class="text-2xl font-black text-horizon-500 mt-3">{{ formatCurrency(totalPortfolioValue) }}</p>
        <p class="text-xs text-neutral-500 mt-1">Portfolio value</p>
      </div>

      <!-- Fyn -->
      <div class="bg-horizon-500 rounded-xl p-4 flex items-start gap-3 mb-4">
        <img src="/images/logos/favicon.png" alt="Fyn" class="w-8 h-8 rounded-full flex-shrink-0" />
        <p class="text-white text-sm leading-relaxed">{{ fynSummary }}</p>
      </div>

      <!-- Accounts -->
      <MobileAccordionSection
        title="Accounts"
        icon="🏦"
        :badge="accounts.length || null"
        :default-open="true"
        class="mb-3"
      >
        <template v-if="accounts.length">
          <div class="divide-y divide-light-gray">
            <MobileAccountCard
              v-for="account in accounts"
              :key="account.id"
              :account="account"
              variant="investment"
            />
          </div>
        </template>
        <p v-else class="px-4 py-6 text-sm text-neutral-500 text-center">No accounts added yet</p>
      </MobileAccordionSection>

      <!-- Holdings -->
      <MobileAccordionSection
        title="Holdings"
        icon="📄"
        :badge="holdingsCount || null"
        class="mb-3"
      >
        <template v-if="allHoldings.length">
          <div class="divide-y divide-light-gray">
            <MobileHoldingRow
              v-for="holding in allHoldings"
              :key="holding.id"
              :holding="holding"
              :allocation-pct="holdingAllocation(holding)"
            />
          </div>
        </template>
        <p v-else class="px-4 py-6 text-sm text-neutral-500 text-center">No holdings data</p>
      </MobileAccordionSection>

      <!-- Allocation -->
      <MobileAccordionSection title="Allocation" icon="🎯" class="mb-3">
        <MobileAllocationChart v-if="allocationItems.length" :items="allocationItems" />
        <p v-else class="px-4 py-6 text-sm text-neutral-500 text-center">No allocation data</p>
      </MobileAccordionSection>

      <!-- Performance -->
      <MobileAccordionSection title="Performance" icon="📊" class="mb-3">
        <div class="divide-y divide-light-gray">
          <MobileDataRow label="Portfolio value" :value="totalPortfolioValue" type="currency" />
          <MobileDataRow label="Unrealised gains" :value="unrealisedGains" type="currency" :status="unrealisedGains >= 0 ? 'good' : 'danger'" />
          <MobileDataRow label="Accounts" :value="accountsCount" />
          <MobileDataRow label="Holdings" :value="holdingsCount" />
        </div>
      </MobileAccordionSection>

      <!-- Fees -->
      <MobileAccordionSection title="Fees" icon="💷" class="mb-3">
        <div class="divide-y divide-light-gray">
          <MobileDataRow label="Total annual fees" :value="totalFees" type="currency" />
          <MobileDataRow label="Fee drag" :value="feeDragPercent" type="percentage" :status="feeDragPercent > 1 ? 'warning' : 'good'" />
        </div>
      </MobileAccordionSection>
    </template>

    <div v-else class="text-center py-16">
      <span class="text-4xl block mb-3">{{'📈'}}</span>
      <h3 class="text-base font-bold text-horizon-500 mb-1">No investment data yet</h3>
      <p class="text-sm text-neutral-500">Your investment portfolio will appear here</p>
    </div>
  </div>
</template>

<script>
import { mapState, mapGetters } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import MobileAccordionSection from '@/mobile/components/MobileAccordionSection.vue';
import MobileDataRow from '@/mobile/components/MobileDataRow.vue';
import MobileAccountCard from '@/mobile/components/MobileAccountCard.vue';
import MobileHoldingRow from '@/mobile/components/MobileHoldingRow.vue';
import MobileAllocationChart from '@/mobile/components/MobileAllocationChart.vue';

export default {
  name: 'InvestmentDetail',

  components: { MobileAccordionSection, MobileDataRow, MobileAccountCard, MobileHoldingRow, MobileAllocationChart },

  mixins: [currencyMixin],

  data() {
    return { loading: false };
  },

  computed: {
    ...mapState('investment', ['accounts']),
    ...mapGetters('investment', [
      'totalPortfolioValue',
      'allHoldings',
      'holdingsCount',
      'accountsCount',
      'totalFees',
      'feeDragPercent',
      'unrealisedGains',
      'assetAllocation',
    ]),

    hasData() {
      return this.accounts?.length > 0 || this.totalPortfolioValue > 0;
    },

    allocationItems() {
      if (!this.assetAllocation) return [];
      return Object.entries(this.assetAllocation)
        .filter(([, val]) => val > 0)
        .map(([label, value]) => ({
          label: label.charAt(0).toUpperCase() + label.slice(1).replace(/_/g, ' '),
          value,
          percentage: value,
        }));
    },

    fynSummary() {
      return 'Your investment portfolio is working to grow your wealth over time.';
    },
  },

  methods: {
    holdingAllocation(holding) {
      if (!this.totalPortfolioValue || !holding.current_value) return null;
      return (holding.current_value / this.totalPortfolioValue) * 100;
    },
  },

  async created() {
    this.loading = true;
    try {
      await this.$store.dispatch('investment/fetchAccounts');
    } catch {
      // Data unavailable
    } finally {
      this.loading = false;
    }
  },
};
</script>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/mobile/views/InvestmentDetail.vue
git commit -m "feat(mobile): add InvestmentDetail full read-only view"
```

---

## Chunk 4: Detail Pages (Retirement, Estate, Goals, Coordination)

### Task 12: RetirementDetail

**Files:**
- Create: `resources/js/mobile/views/RetirementDetail.vue`

**Store:** `retirement` — `fetchRetirementData`, `fetchProjections`

- [ ] **Step 1: Create RetirementDetail.vue**

```vue
<template>
  <div class="px-4 pt-4 pb-6">
    <div v-if="loading" class="space-y-3">
      <div class="bg-white rounded-xl p-6 animate-pulse">
        <div class="w-24 h-8 bg-savannah-100 rounded mx-auto"></div>
      </div>
      <div v-for="n in 5" :key="n" class="bg-white rounded-xl p-4 animate-pulse">
        <div class="w-40 h-4 bg-savannah-100 rounded"></div>
      </div>
    </div>

    <template v-else-if="hasData">
      <!-- Hero -->
      <div class="bg-white rounded-xl border border-light-gray p-6 text-center mb-4">
        <span class="text-3xl block mb-2">{{'🏦'}}</span>
        <h2 class="text-lg font-bold text-horizon-500">Retirement</h2>
        <p class="text-2xl font-black text-horizon-500 mt-3">{{ formatCurrency(projectedIncome) }}</p>
        <p class="text-xs text-neutral-500 mt-1">Projected retirement income</p>
        <p v-if="yearsToRetirement" class="text-xs text-neutral-400 mt-1">{{ yearsToRetirement }} years to retirement</p>
      </div>

      <!-- Fyn -->
      <div class="bg-horizon-500 rounded-xl p-4 flex items-start gap-3 mb-4">
        <img src="/images/logos/favicon.png" alt="Fyn" class="w-8 h-8 rounded-full flex-shrink-0" />
        <p class="text-white text-sm leading-relaxed">{{ fynSummary }}</p>
      </div>

      <!-- DC Pensions -->
      <MobileAccordionSection
        v-if="dcPensions.length"
        title="Defined contribution pensions"
        icon="💼"
        :badge="dcPensions.length"
        :default-open="true"
        class="mb-3"
      >
        <div class="divide-y divide-light-gray">
          <MobilePensionCard
            v-for="pension in dcPensions"
            :key="pension.id"
            :pension="pension"
            pension-type="dc"
          />
        </div>
      </MobileAccordionSection>

      <!-- DB Pensions -->
      <MobileAccordionSection
        v-if="dbPensions.length"
        title="Defined benefit pensions"
        icon="🏛️"
        :badge="dbPensions.length"
        class="mb-3"
      >
        <div class="divide-y divide-light-gray">
          <MobilePensionCard
            v-for="pension in dbPensions"
            :key="pension.id"
            :pension="pension"
            pension-type="db"
          />
        </div>
      </MobileAccordionSection>

      <!-- State Pension -->
      <MobileAccordionSection
        v-if="statePension"
        title="State pension"
        icon="🇬🇧"
        class="mb-3"
      >
        <div class="divide-y divide-light-gray">
          <MobilePensionCard :pension="statePension" pension-type="state" />
        </div>
      </MobileAccordionSection>

      <!-- Projections -->
      <MobileAccordionSection title="Projections" icon="📊" class="mb-3">
        <div class="divide-y divide-light-gray">
          <MobileDataRow label="Projected annual income" :value="projectedIncome" type="currency" />
          <MobileDataRow label="Target income" :value="targetIncome" type="currency" />
          <MobileDataRow
            label="Income gap"
            :value="incomeGap > 0 ? incomeGap : 0"
            type="currency"
            :status="incomeGap > 0 ? 'warning' : 'good'"
          />
          <MobileDataRow label="Total pension wealth" :value="totalPensionWealth" type="currency" />
          <MobileDataRow label="Years to retirement" :value="yearsToRetirement" />
        </div>
      </MobileAccordionSection>

      <!-- Annual Allowance -->
      <MobileAccordionSection title="Annual allowance" icon="📋" class="mb-3">
        <div v-if="annualAllowance" class="divide-y divide-light-gray">
          <MobileDataRow label="Standard allowance" :value="annualAllowance.standard_allowance || 60000" type="currency" />
          <MobileDataRow label="Used this year" :value="annualAllowance.used || 0" type="currency" />
          <MobileDataRow
            label="Remaining"
            :value="(annualAllowance.standard_allowance || 60000) - (annualAllowance.used || 0)"
            type="currency"
            :status="(annualAllowance.standard_allowance || 60000) - (annualAllowance.used || 0) > 0 ? 'good' : 'warning'"
          />
          <MobileDataRow v-if="annualAllowance.carry_forward" label="Carry forward available" :value="annualAllowance.carry_forward" type="currency" />
        </div>
        <p v-else class="px-4 py-6 text-sm text-neutral-500 text-center">No annual allowance data</p>
      </MobileAccordionSection>
    </template>

    <div v-else class="text-center py-16">
      <span class="text-4xl block mb-3">{{'🏦'}}</span>
      <h3 class="text-base font-bold text-horizon-500 mb-1">No retirement data yet</h3>
      <p class="text-sm text-neutral-500">Your pensions and projections will appear here</p>
    </div>
  </div>
</template>

<script>
import { mapState, mapGetters } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import MobileAccordionSection from '@/mobile/components/MobileAccordionSection.vue';
import MobileDataRow from '@/mobile/components/MobileDataRow.vue';
import MobilePensionCard from '@/mobile/components/MobilePensionCard.vue';

export default {
  name: 'RetirementDetail',

  components: { MobileAccordionSection, MobileDataRow, MobilePensionCard },

  mixins: [currencyMixin],

  data() {
    return { loading: false };
  },

  computed: {
    ...mapGetters('retirement', [
      'dcPensions',
      'dbPensions',
      'totalPensionWealth',
      'projectedIncome',
      'targetIncome',
      'incomeGap',
      'yearsToRetirement',
    ]),

    statePension() {
      return this.$store.state.retirement.statePension;
    },

    annualAllowance() {
      return this.$store.state.retirement.annualAllowance;
    },

    hasData() {
      return this.dcPensions?.length > 0 || this.dbPensions?.length > 0 || this.statePension;
    },

    fynSummary() {
      if (this.incomeGap > 0) {
        return `Your projected retirement income is ${this.formatCurrency(this.incomeGap)} below your target.`;
      }
      return 'Your projected retirement income meets your target.';
    },
  },

  async created() {
    this.loading = true;
    try {
      await this.$store.dispatch('retirement/fetchRetirementData');
      await this.$store.dispatch('retirement/fetchAnnualAllowance').catch(() => {});
    } catch {
      // Data unavailable
    } finally {
      this.loading = false;
    }
  },
};
</script>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/mobile/views/RetirementDetail.vue
git commit -m "feat(mobile): add RetirementDetail full read-only view"
```

---

### Task 13: EstateDetail

**Files:**
- Create: `resources/js/mobile/views/EstateDetail.vue`

**Store:** `estate` — `fetchEstateData`, `analyseEstate`

- [ ] **Step 1: Create EstateDetail.vue**

```vue
<template>
  <div class="px-4 pt-4 pb-6">
    <div v-if="loading" class="space-y-3">
      <div class="bg-white rounded-xl p-6 animate-pulse">
        <div class="w-24 h-8 bg-savannah-100 rounded mx-auto"></div>
      </div>
      <div v-for="n in 4" :key="n" class="bg-white rounded-xl p-4 animate-pulse">
        <div class="w-40 h-4 bg-savannah-100 rounded"></div>
      </div>
    </div>

    <template v-else-if="hasData">
      <!-- Hero -->
      <div class="bg-white rounded-xl border border-light-gray p-6 text-center mb-4">
        <span class="text-3xl block mb-2">{{'🏠'}}</span>
        <h2 class="text-lg font-bold text-horizon-500">Estate Planning</h2>
        <p class="text-2xl font-black text-horizon-500 mt-3">{{ formatCurrency(netWorthValue) }}</p>
        <p class="text-xs text-neutral-500 mt-1">Net estate value</p>
        <div v-if="ihtLiability > 0" class="mt-2">
          <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-violet-50 text-violet-500">
            IHT: {{ formatCurrency(ihtLiability) }}
          </span>
        </div>
      </div>

      <!-- Fyn -->
      <div class="bg-horizon-500 rounded-xl p-4 flex items-start gap-3 mb-4">
        <img src="/images/logos/favicon.png" alt="Fyn" class="w-8 h-8 rounded-full flex-shrink-0" />
        <p class="text-white text-sm leading-relaxed">{{ fynSummary }}</p>
      </div>

      <!-- Estate Assets -->
      <MobileAccordionSection
        title="Estate assets"
        icon="🏘️"
        :badge="assets.length || null"
        :default-open="true"
        class="mb-3"
      >
        <template v-if="assets.length">
          <div class="divide-y divide-light-gray">
            <MobileEstateAssetCard v-for="asset in assets" :key="asset.id" :asset="asset" />
          </div>
        </template>
        <p v-else class="px-4 py-6 text-sm text-neutral-500 text-center">No estate assets added yet</p>
      </MobileAccordionSection>

      <!-- IHT Analysis -->
      <MobileAccordionSection title="Inheritance tax analysis" icon="📊" class="mb-3">
        <div class="divide-y divide-light-gray">
          <MobileDataRow label="Gross estate" :value="grossEstate" type="currency" />
          <MobileDataRow label="Nil-rate band" :value="325000" type="currency" />
          <MobileDataRow label="Residence nil-rate band" :value="175000" type="currency" />
          <MobileDataRow label="Taxable estate" :value="taxableEstate" type="currency" />
          <MobileDataRow
            label="Inheritance tax liability"
            :value="ihtLiability"
            type="currency"
            :status="ihtLiability > 0 ? 'warning' : 'good'"
          />
        </div>
      </MobileAccordionSection>

      <!-- Gifts -->
      <MobileAccordionSection
        title="Gifts (within 7 years)"
        icon="🎁"
        :badge="giftsWithin7Years.length || null"
        class="mb-3"
      >
        <template v-if="giftsWithin7Years.length">
          <div class="divide-y divide-light-gray">
            <MobileGiftCard v-for="gift in giftsWithin7Years" :key="gift.id" :gift="gift" />
          </div>
        </template>
        <p v-else class="px-4 py-6 text-sm text-neutral-500 text-center">No gifts recorded in the last 7 years</p>
      </MobileAccordionSection>

      <!-- Trusts -->
      <MobileAccordionSection
        title="Trusts"
        icon="📜"
        :badge="trusts.length || null"
        class="mb-3"
      >
        <template v-if="trusts.length">
          <div class="divide-y divide-light-gray">
            <MobileTrustCard v-for="trust in trusts" :key="trust.id" :trust="trust" />
          </div>
        </template>
        <p v-else class="px-4 py-6 text-sm text-neutral-500 text-center">No trusts set up yet</p>
      </MobileAccordionSection>
    </template>

    <div v-else class="text-center py-16">
      <span class="text-4xl block mb-3">{{'🏠'}}</span>
      <h3 class="text-base font-bold text-horizon-500 mb-1">No estate data yet</h3>
      <p class="text-sm text-neutral-500">Your estate planning details will appear here</p>
    </div>
  </div>
</template>

<script>
import { mapState, mapGetters } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import MobileAccordionSection from '@/mobile/components/MobileAccordionSection.vue';
import MobileDataRow from '@/mobile/components/MobileDataRow.vue';
import MobileEstateAssetCard from '@/mobile/components/MobileEstateAssetCard.vue';
import MobileGiftCard from '@/mobile/components/MobileGiftCard.vue';
import MobileTrustCard from '@/mobile/components/MobileTrustCard.vue';

export default {
  name: 'EstateDetail',

  components: { MobileAccordionSection, MobileDataRow, MobileEstateAssetCard, MobileGiftCard, MobileTrustCard },

  mixins: [currencyMixin],

  data() {
    return { loading: false };
  },

  computed: {
    ...mapState('estate', ['assets', 'trusts']),
    ...mapGetters('estate', [
      'netWorthValue',
      'ihtLiability',
      'grossEstate',
      'taxableEstate',
      'giftsWithin7Years',
    ]),

    hasData() {
      return this.assets?.length > 0 || this.trusts?.length > 0 || this.netWorthValue > 0;
    },

    fynSummary() {
      if (this.ihtLiability > 0) {
        return `Your estate has an estimated inheritance tax liability of ${this.formatCurrency(this.ihtLiability)}.`;
      }
      return 'Your estate currently has no inheritance tax liability.';
    },
  },

  async created() {
    this.loading = true;
    try {
      await this.$store.dispatch('estate/fetchEstateData');
    } catch {
      // Data unavailable
    } finally {
      this.loading = false;
    }
  },
};
</script>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/mobile/views/EstateDetail.vue
git commit -m "feat(mobile): add EstateDetail full read-only view"
```

---

### Task 14: GoalsDetail

**Files:**
- Create: `resources/js/mobile/views/GoalsDetail.vue`

**Store:** `goals` — `fetchGoals`, `fetchLifeEvents`

- [ ] **Step 1: Create GoalsDetail.vue**

```vue
<template>
  <div class="px-4 pt-4 pb-6">
    <div v-if="loading" class="space-y-3">
      <div class="bg-white rounded-xl p-6 animate-pulse">
        <div class="w-24 h-8 bg-savannah-100 rounded mx-auto"></div>
      </div>
      <div v-for="n in 3" :key="n" class="bg-white rounded-xl p-4 animate-pulse">
        <div class="w-40 h-4 bg-savannah-100 rounded"></div>
      </div>
    </div>

    <template v-else-if="hasData">
      <!-- Hero -->
      <div class="bg-white rounded-xl border border-light-gray p-6 text-center mb-4">
        <span class="text-3xl block mb-2">{{'🎯'}}</span>
        <h2 class="text-lg font-bold text-horizon-500">Goals & Life Events</h2>
        <p class="text-2xl font-black text-horizon-500 mt-3">{{ completedGoals.length }} of {{ allGoals.length }}</p>
        <p class="text-xs text-neutral-500 mt-1">Goals completed</p>
        <p v-if="totalCurrentAmount > 0" class="text-xs text-neutral-400 mt-1">{{ formatCurrency(totalCurrentAmount) }} saved</p>
      </div>

      <!-- Fyn -->
      <div class="bg-horizon-500 rounded-xl p-4 flex items-start gap-3 mb-4">
        <img src="/images/logos/favicon.png" alt="Fyn" class="w-8 h-8 rounded-full flex-shrink-0" />
        <p class="text-white text-sm leading-relaxed">{{ fynSummary }}</p>
      </div>

      <!-- Active Goals -->
      <MobileAccordionSection
        title="Active goals"
        icon="🏃"
        :badge="activeGoals.length || null"
        :default-open="true"
        class="mb-3"
      >
        <template v-if="activeGoals.length">
          <div class="divide-y divide-light-gray">
            <MobileGoalCard
              v-for="goal in activeGoals"
              :key="goal.id"
              :goal="goal"
              @click="navigateToGoal(goal.id)"
            />
          </div>
        </template>
        <p v-else class="px-4 py-6 text-sm text-neutral-500 text-center">No active goals</p>
      </MobileAccordionSection>

      <!-- Completed Goals -->
      <MobileAccordionSection
        v-if="completedGoals.length"
        title="Completed goals"
        icon="✅"
        :badge="completedGoals.length"
        class="mb-3"
      >
        <div class="divide-y divide-light-gray">
          <MobileGoalCard
            v-for="goal in completedGoals"
            :key="goal.id"
            :goal="goal"
            @click="navigateToGoal(goal.id)"
          />
        </div>
      </MobileAccordionSection>

      <!-- Life Events -->
      <MobileAccordionSection
        title="Life events"
        icon="📅"
        :badge="lifeEvents.length || null"
        class="mb-3"
      >
        <template v-if="lifeEvents.length">
          <div class="divide-y divide-light-gray">
            <div v-for="event in lifeEvents" :key="event.id" class="px-4 py-3">
              <div class="flex items-start justify-between">
                <div class="flex-1 min-w-0">
                  <h4 class="text-sm font-bold text-horizon-500 truncate">{{ event.name || event.type }}</h4>
                  <p v-if="event.date" class="text-xs text-neutral-500 mt-0.5">{{ formatEventDate(event.date) }}</p>
                  <p v-if="event.description" class="text-xs text-neutral-400 mt-0.5 line-clamp-2">{{ event.description }}</p>
                </div>
                <span
                  v-if="event.priority"
                  class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase ml-2"
                  :class="priorityClass(event.priority)"
                >
                  {{ event.priority }}
                </span>
              </div>
            </div>
          </div>
        </template>
        <p v-else class="px-4 py-6 text-sm text-neutral-500 text-center">No life events recorded</p>
      </MobileAccordionSection>
    </template>

    <div v-else class="text-center py-16">
      <span class="text-4xl block mb-3">{{'🎯'}}</span>
      <h3 class="text-base font-bold text-horizon-500 mb-1">No goals yet</h3>
      <p class="text-sm text-neutral-500">Your financial goals and life events will appear here</p>
    </div>
  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import MobileAccordionSection from '@/mobile/components/MobileAccordionSection.vue';
import MobileGoalCard from '@/mobile/goals/MobileGoalCard.vue';

export default {
  name: 'GoalsDetail',

  components: { MobileAccordionSection, MobileGoalCard },

  mixins: [currencyMixin],

  data() {
    return { loading: false };
  },

  computed: {
    ...mapGetters('goals', [
      'activeGoals',
      'completedGoals',
      'totalCurrentAmount',
      'totalTargetAmount',
      'hasGoals',
    ]),

    allGoals() {
      return [...(this.activeGoals || []), ...(this.completedGoals || [])];
    },

    lifeEvents() {
      return this.$store.state.goals.lifeEvents || [];
    },

    hasData() {
      return this.hasGoals || this.lifeEvents.length > 0;
    },

    fynSummary() {
      if (this.completedGoals?.length > 0) {
        return `Well done — you have completed ${this.completedGoals.length} of your ${this.allGoals.length} financial goals.`;
      }
      if (this.allGoals.length > 0) {
        return `You have ${this.allGoals.length} financial goal${this.allGoals.length > 1 ? 's' : ''} in progress.`;
      }
      return 'Setting financial goals gives direction and purpose to your planning.';
    },
  },

  async created() {
    this.loading = true;
    try {
      await Promise.all([
        this.$store.dispatch('goals/fetchGoals'),
        this.$store.dispatch('goals/fetchLifeEvents').catch(() => {}),
      ]);
    } catch {
      // Data unavailable
    } finally {
      this.loading = false;
    }
  },

  methods: {
    navigateToGoal(goalId) {
      this.$router.push(`/m/goals/${goalId}`);
    },

    formatEventDate(dateStr) {
      if (!dateStr) return '';
      const d = new Date(dateStr);
      return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
    },

    priorityClass(priority) {
      if (priority === 'high') return 'bg-raspberry-50 text-raspberry-500';
      if (priority === 'medium') return 'bg-violet-50 text-violet-500';
      return 'bg-savannah-100 text-horizon-500';
    },
  },
};
</script>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/mobile/views/GoalsDetail.vue
git commit -m "feat(mobile): add GoalsDetail full read-only view"
```

---

### Task 15: CoordinationDetail

**Files:**
- Create: `resources/js/mobile/views/CoordinationDetail.vue`

**Stores:** `netWorth`, `plans`, `recommendations`

- [ ] **Step 1: Create CoordinationDetail.vue**

```vue
<template>
  <div class="px-4 pt-4 pb-6">
    <div v-if="loading" class="space-y-3">
      <div class="bg-white rounded-xl p-6 animate-pulse">
        <div class="w-24 h-8 bg-savannah-100 rounded mx-auto"></div>
      </div>
      <div v-for="n in 3" :key="n" class="bg-white rounded-xl p-4 animate-pulse">
        <div class="w-40 h-4 bg-savannah-100 rounded"></div>
      </div>
    </div>

    <template v-else-if="hasData">
      <!-- Hero -->
      <div class="bg-white rounded-xl border border-light-gray p-6 text-center mb-4">
        <span class="text-3xl block mb-2">{{'🔗'}}</span>
        <h2 class="text-lg font-bold text-horizon-500">Coordination</h2>
        <p class="text-2xl font-black text-horizon-500 mt-3">{{ formatCurrency(netWorthTotal) }}</p>
        <p class="text-xs text-neutral-500 mt-1">Net worth</p>
      </div>

      <!-- Fyn -->
      <div class="bg-horizon-500 rounded-xl p-4 flex items-start gap-3 mb-4">
        <img src="/images/logos/favicon.png" alt="Fyn" class="w-8 h-8 rounded-full flex-shrink-0" />
        <p class="text-white text-sm leading-relaxed">{{ fynSummary }}</p>
      </div>

      <!-- Financial Plans -->
      <MobileAccordionSection
        title="Financial plans"
        icon="📋"
        :default-open="true"
        class="mb-3"
      >
        <template v-if="plansList.length">
          <div class="divide-y divide-light-gray">
            <div v-for="plan in plansList" :key="plan.type" class="px-4 py-3">
              <div class="flex items-center justify-between mb-1.5">
                <h4 class="text-sm font-medium text-horizon-500">{{ plan.label }}</h4>
                <span class="text-xs font-semibold" :class="plan.completeness >= 80 ? 'text-spring-500' : 'text-violet-500'">
                  {{ plan.completeness }}%
                </span>
              </div>
              <div class="w-full h-1.5 bg-savannah-100 rounded-full overflow-hidden">
                <div
                  class="h-full rounded-full transition-all duration-300"
                  :class="plan.completeness >= 80 ? 'bg-spring-500' : 'bg-violet-500'"
                  :style="{ width: plan.completeness + '%' }"
                ></div>
              </div>
            </div>
          </div>
        </template>
        <p v-else class="px-4 py-6 text-sm text-neutral-500 text-center">No financial plans generated yet</p>
      </MobileAccordionSection>

      <!-- Cross-Module Insights -->
      <MobileAccordionSection
        title="Recommendations"
        icon="💡"
        :badge="topRecommendations.length || null"
        class="mb-3"
      >
        <template v-if="topRecommendations.length">
          <div class="divide-y divide-light-gray">
            <div v-for="rec in topRecommendations" :key="rec.id" class="px-4 py-3">
              <div class="flex items-center gap-2 mb-1">
                <span
                  class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase"
                  :class="rec.priority === 'high' ? 'bg-raspberry-50 text-raspberry-500' : 'bg-violet-50 text-violet-500'"
                >
                  {{ rec.priority || 'medium' }}
                </span>
                <span v-if="rec.module" class="text-xs text-neutral-400">{{ rec.module }}</span>
              </div>
              <p class="text-sm text-horizon-500">{{ rec.title || rec.description }}</p>
              <p v-if="rec.potential_benefit" class="text-xs text-spring-500 mt-0.5">
                Potential benefit: {{ formatCurrency(rec.potential_benefit) }}
              </p>
            </div>
          </div>
        </template>
        <p v-else class="px-4 py-6 text-sm text-neutral-500 text-center">No recommendations available</p>
      </MobileAccordionSection>

      <!-- Net Worth Breakdown -->
      <MobileAccordionSection title="Net worth breakdown" icon="📊" class="mb-3">
        <div class="divide-y divide-light-gray">
          <MobileDataRow label="Total assets" :value="totalAssets" type="currency" />
          <MobileDataRow label="Total liabilities" :value="totalLiabilities" type="currency" />
          <MobileDataRow label="Net worth" :value="netWorthTotal" type="currency" status="good" />
          <template v-if="assetBreakdown">
            <div class="px-4 py-2 bg-savannah-100">
              <p class="text-xs font-semibold text-neutral-500 uppercase">Asset breakdown</p>
            </div>
            <MobileDataRow
              v-for="(item, key) in assetBreakdown"
              :key="key"
              :label="formatBreakdownLabel(key)"
              :value="item.total_value || item"
              type="currency"
            />
          </template>
        </div>
      </MobileAccordionSection>
    </template>

    <div v-else class="text-center py-16">
      <span class="text-4xl block mb-3">{{'🔗'}}</span>
      <h3 class="text-base font-bold text-horizon-500 mb-1">No coordination data yet</h3>
      <p class="text-sm text-neutral-500">Your holistic financial picture will appear here</p>
    </div>
  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import MobileAccordionSection from '@/mobile/components/MobileAccordionSection.vue';
import MobileDataRow from '@/mobile/components/MobileDataRow.vue';

export default {
  name: 'CoordinationDetail',

  components: { MobileAccordionSection, MobileDataRow },

  mixins: [currencyMixin],

  data() {
    return { loading: false };
  },

  computed: {
    ...mapGetters('netWorth', {
      netWorthTotal: 'netWorth',
      totalAssets: 'totalAssets',
      totalLiabilities: 'totalLiabilities',
      assetBreakdown: 'assetBreakdown',
    }),

    planStatuses() {
      return this.$store.getters['plans/planStatuses'];
    },

    topRecommendations() {
      return this.$store.state.recommendations?.topRecommendations || [];
    },

    plansList() {
      if (!this.planStatuses) return [];
      const labels = {
        investment: 'Investment plan',
        protection: 'Protection plan',
        retirement: 'Retirement plan',
        estate: 'Estate plan',
        savings: 'Savings plan',
      };
      return Object.entries(this.planStatuses)
        .filter(([, status]) => status != null)
        .map(([type, status]) => ({
          type,
          label: labels[type] || type,
          completeness: status.completeness || status.progress || 0,
        }));
    },

    hasData() {
      return this.netWorthTotal > 0 || this.plansList.length > 0 || this.topRecommendations.length > 0;
    },

    fynSummary() {
      return 'Coordination brings together all your financial modules for a complete picture.';
    },
  },

  async created() {
    this.loading = true;
    try {
      await Promise.all([
        this.$store.dispatch('netWorth/fetchOverview').catch(() => {}),
        this.$store.dispatch('plans/fetchDashboardStatuses').catch(() => {}),
        this.$store.dispatch('recommendations/fetchTopRecommendations').catch(() => {}),
      ]);
    } catch {
      // Data unavailable
    } finally {
      this.loading = false;
    }
  },

  methods: {
    formatBreakdownLabel(key) {
      const labels = {
        pensions: 'Pensions',
        property: 'Property',
        investments: 'Investments',
        cash: 'Cash & savings',
        business: 'Business interests',
        chattels: 'Chattels & collectibles',
      };
      return labels[key] || key.charAt(0).toUpperCase() + key.slice(1);
    },
  },
};
</script>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/mobile/views/CoordinationDetail.vue
git commit -m "feat(mobile): add CoordinationDetail full read-only view"
```

---

## Chunk 5: Router, Navigation, and Removals

### Task 16: Router Changes

**Files:**
- Modify: `resources/js/router/index.js` (mobile routes section ~lines 840-876)

- [ ] **Step 1: Add new module detail routes**

In the mobile routes under the MobileLayout children array, **replace** the `ModuleSummary` route:

```js
// REMOVE this route:
{
    path: 'more/summary/:module',
    name: 'MobileModuleSummary',
    component: () => import('@/mobile/views/ModuleSummary.vue'),
},

// ADD these routes (in the same children array):
{
    path: 'module/protection',
    name: 'MobileProtectionDetail',
    component: () => import('@/mobile/views/ProtectionDetail.vue'),
    meta: { title: 'Protection' },
},
{
    path: 'module/savings',
    name: 'MobileSavingsDetail',
    component: () => import('@/mobile/views/SavingsDetail.vue'),
    meta: { title: 'Savings' },
},
{
    path: 'module/investment',
    name: 'MobileInvestmentDetail',
    component: () => import('@/mobile/views/InvestmentDetail.vue'),
    meta: { title: 'Investment' },
},
{
    path: 'module/retirement',
    name: 'MobileRetirementDetail',
    component: () => import('@/mobile/views/RetirementDetail.vue'),
    meta: { title: 'Retirement' },
},
{
    path: 'module/estate',
    name: 'MobileEstateDetail',
    component: () => import('@/mobile/views/EstateDetail.vue'),
    meta: { title: 'Estate Planning' },
},
{
    path: 'module/goals',
    name: 'MobileGoalsDetail',
    component: () => import('@/mobile/views/GoalsDetail.vue'),
    meta: { title: 'Goals' },
},
{
    path: 'module/coordination',
    name: 'MobileCoordinationDetail',
    component: () => import('@/mobile/views/CoordinationDetail.vue'),
    meta: { title: 'Coordination' },
},
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/router/index.js
git commit -m "feat(mobile): add module detail routes, remove ModuleSummary route"
```

---

### Task 17: Update Navigation (Dashboard + MoreMenu)

**Files:**
- Modify: `resources/js/mobile/views/MobileDashboard.vue` (~line 102-104)
- Modify: `resources/js/mobile/views/MoreMenu.vue` (~lines 80-82, and "Open full web app" button)

- [ ] **Step 1: Update MobileDashboard navigateToModule**

Change:
```js
navigateToModule(moduleName) {
  this.$router.push(`/m/more/summary/${moduleName}`);
}
```

To:
```js
navigateToModule(moduleName) {
  this.$router.push(`/m/module/${moduleName}`);
}
```

- [ ] **Step 2: Update MoreMenu navigateToModule**

Change:
```js
navigateToModule(moduleId) {
  this.$router.push(`/m/more/summary/${moduleId}`);
}
```

To:
```js
navigateToModule(moduleId) {
  this.$router.push(`/m/module/${moduleId}`);
}
```

- [ ] **Step 3: Remove "Open full web app" button from MoreMenu**

Find and remove the "Open full web app" button (around line 30-35 area). It uses `Browser.open()` to open the web dashboard.

- [ ] **Step 4: Update MobileDashboard empty state**

Change the empty state text from:
```
Welcome to Fynla! Start by adding your financial details on the web app.
```

To:
```
Welcome to Fynla! Your financial data will appear here once added.
```

- [ ] **Step 5: Commit**

```bash
git add resources/js/mobile/views/MobileDashboard.vue resources/js/mobile/views/MoreMenu.vue
git commit -m "feat(mobile): update navigation to module detail pages, remove web app references"
```

---

### Task 18: Update MobileGoalsList Empty State + Delete ModuleSummary

**Files:**
- Modify: `resources/js/mobile/views/MobileGoalsList.vue` (~lines 36-39)
- Delete: `resources/js/mobile/views/ModuleSummary.vue`

- [ ] **Step 1: Update MobileGoalsList empty state**

Change the subtitle from:
```
Set up your financial goals on the web app to track them here
```

To:
```
Your financial goals will appear here once added
```

- [ ] **Step 2: Delete ModuleSummary.vue**

```bash
git rm resources/js/mobile/views/ModuleSummary.vue
```

- [ ] **Step 3: Verify build compiles**

Run: `./dev.sh` (check terminal for errors)
Expected: No import errors — ModuleSummary was only imported via lazy route which is now removed

- [ ] **Step 4: Commit**

```bash
git add resources/js/mobile/views/MobileGoalsList.vue
git commit -m "feat(mobile): remove web app references from empty states, delete ModuleSummary stub"
```

---

## Chunk 6: Final Verification

### Task 19: Build and Visual Verification

- [ ] **Step 1: Run dev build**

Run: `./dev.sh`
Expected: No compile errors

- [ ] **Step 2: Run iOS build**

Run: `./deploy/mobile/build-ios.sh`
Expected: Build succeeds

- [ ] **Step 3: Clear cache**

Run: `php artisan cache:clear`

- [ ] **Step 4: Seed database**

Run: `php artisan db:seed`

- [ ] **Step 5: Visual verification checklist**

Test in browser at `http://localhost:8000/m/home` (or iOS simulator):

1. Dashboard module cards → tap each one → navigates to `/m/module/{name}`
2. Protection detail → hero card, policies accordion, coverage analysis, gaps section
3. Savings detail → hero card, accounts accordion, emergency fund, ISA allowance
4. Investment detail → hero card, accounts, holdings, allocation chart, performance, fees
5. Retirement detail → hero card, DC/DB/State pensions, projections, annual allowance
6. Estate detail → hero card, assets, IHT analysis, gifts, trusts
7. Goals detail → hero card, active/completed goals, life events
8. Coordination detail → hero card, plans with progress bars, recommendations, net worth breakdown
9. More menu → module grid → navigates to detail pages
10. More menu → no "Open full web app" button
11. Dashboard empty state → no "web app" reference
12. Goals list empty state → no "web app" reference
13. Back button works from all detail pages
14. Accordions expand/collapse smoothly
15. Currency values formatted correctly (£ symbol, commas)

- [ ] **Step 6: Final commit if any fixes needed**

```bash
git add -A
git commit -m "fix(mobile): address visual verification issues"
```
