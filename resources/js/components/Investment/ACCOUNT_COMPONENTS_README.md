# Investment Account Management Components

This document provides guidance on using the account management components for the Investment module.

## Components Overview

### 1. AccountCard.vue
A card component that displays investment account information in a clean, organized layout.

**Features:**
- Display account provider and type with color-coded badges
- Show current account value and holdings count
- Display platform fees if applicable
- Special ISA information section for ISA accounts
- Edit and delete action buttons
- View holdings button

**Props:**
```javascript
{
  account: {
    type: Object,
    required: true,
    // Expected structure:
    // {
    //   id: Number,
    //   account_type: String, // 'isa', 'gia', 'sipp', 'pension', 'other'
    //   provider: String,
    //   platform: String,
    //   platform_fee_percent: Number,
    //   current_value: Number,
    //   holdings: Array,
    //   isa_type: String (optional),
    //   isa_subscription_current_year: Number (optional)
    // }
  }
}
```

**Events:**
- `@edit` - Emitted when edit button is clicked, passes account object
- `@delete` - Emitted when delete button is clicked, passes account object
- `@view-holdings` - Emitted when "View Holdings" button is clicked, passes account object

**Usage Example:**
```vue
<AccountCard
  :account="account"
  @edit="handleEdit"
  @delete="handleDelete"
  @view-holdings="handleViewHoldings"
/>
```

### 2. AccountForm.vue
A modal form component for creating and editing investment accounts.

**Features:**
- Create new accounts or edit existing ones
- Support for multiple account types (ISA, GIA, SIPP, Pension, Other)
- ISA-specific fields with allowance tracking
- Real-time ISA allowance calculation and visualization
- Client-side validation
- Tax year calculation for ISA subscriptions
- Responsive modal design

**Props:**
```javascript
{
  show: {
    type: Boolean,
    required: true,
    // Controls modal visibility
  },
  account: {
    type: Object,
    default: null,
    // If provided, form enters edit mode
    // If null, form is in create mode
  }
}
```

**Events:**
- `@save` - Emitted when form is submitted, passes form data object (Note: Use @save not @submit to avoid double submission)
- `@close` - Emitted when form is closed/cancelled

**Form Fields:**
- **account_type** (required): ISA, GIA, SIPP, Pension, or Other
- **provider** (required): Account provider name
- **platform** (optional): Platform or product name
- **platform_fee_percent** (optional): Annual platform fee percentage
- **isa_type** (ISA only): Stocks & Shares, Lifetime, or Innovative Finance
- **isa_subscription_current_year** (ISA only): Amount contributed this tax year
- **notes** (optional): Additional notes

**Usage Example:**
```vue
<AccountForm
  :show="showModal"
  :account="selectedAccount"
  @save="handleSubmit"
  @close="closeModal"
/>
```

### 3. Accounts.vue
A parent component that manages a list of investment accounts using AccountCard and AccountForm components.

**Features:**
- Grid layout of account cards
- Add new account functionality
- Edit and delete existing accounts
- Success and error message handling
- Delete confirmation modal with warning for accounts with holdings
- Empty state UI
- Loading state
- Integrates with Vuex store

**Events:**
- `@view-holdings` - Emitted when user wants to view holdings for an account

**Usage Example:**
```vue
<Accounts @view-holdings="navigateToHoldings" />
```

## Integration Guide

### Option 1: Add as a Separate Tab in Investment Dashboard

Edit `InvestmentDashboard.vue`:

```vue
<script>
import Accounts from '@/components/Investment/Accounts.vue';

export default {
  components: {
    // ... existing components
    Accounts,
  },

  data() {
    return {
      tabs: [
        { id: 'overview', label: 'Portfolio Overview' },
        { id: 'accounts', label: 'Accounts' },  // Add this
        { id: 'holdings', label: 'Holdings' },
        // ... rest of tabs
      ],
    };
  },
};
</script>

<template>
  <!-- In tab content section -->
  <Accounts v-else-if="activeTab === 'accounts'" />
</template>
```

### Option 2: Integrate into Portfolio Overview

Edit `PortfolioOverview.vue` to include account cards:

```vue
<script>
import { mapGetters } from 'vuex';
import AccountCard from './AccountCard.vue';

export default {
  components: {
    AccountCard,
  },

  computed: {
    ...mapGetters('investment', ['accounts']),
  },
};
</script>

<template>
  <div>
    <h3 class="text-xl font-semibold mb-4">Your Accounts</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <AccountCard
        v-for="account in accounts"
        :key="account.id"
        :account="account"
        @edit="handleEdit"
        @delete="handleDelete"
        @view-holdings="handleViewHoldings"
      />
    </div>
  </div>
</template>
```

### Option 3: Use in Holdings View

Edit `Holdings.vue` to add account filtering:

```vue
<script>
import AccountCard from './AccountCard.vue';

export default {
  components: {
    AccountCard,
    // ... existing components
  },

  data() {
    return {
      selectedAccountId: null,
    };
  },

  computed: {
    filteredHoldings() {
      if (!this.selectedAccountId) return this.allHoldings;
      return this.allHoldings.filter(
        h => h.investment_account_id === this.selectedAccountId
      );
    },
  },
};
</script>
```

## Vuex Integration

The components rely on the following Vuex actions and getters from the `investment` module:

**Actions:**
- `fetchInvestmentData()` - Fetch all investment data including accounts
- `createAccount(accountData)` - Create a new account
- `updateAccount({ id, accountData })` - Update an existing account
- `deleteAccount(id)` - Delete an account
- `analyzeInvestment()` - Re-run investment analysis after changes

**Getters:**
- `accounts` - Array of all investment accounts
- `loading` - Loading state
- `error` - Error message if any

**State:**
```javascript
{
  accounts: [
    {
      id: 1,
      account_type: 'isa',
      provider: 'Vanguard',
      platform: 'Investment Account',
      platform_fee_percent: 0.15,
      current_value: 25000,
      holdings: [...],
      isa_type: 'stocks_and_shares',
      isa_subscription_current_year: 5000,
    },
    // ... more accounts
  ],
}
```

## ISA Allowance Tracking

The AccountForm component includes built-in ISA allowance tracking:

- **Current Allowance**: £20,000 (2024/25 tax year)
- **Tax Year Calculation**: Automatically determines current UK tax year (April 6 - April 5)
- **Visual Indicators**:
  - Green: <50% used
  - Yellow: 50-75% used
  - Orange: 75-100% used
  - Red: 100% used (limit reached)
- **Cross-Module Integration**: ISA subscriptions should be coordinated with the Savings module (Cash ISAs)

## Styling and Design

All components follow the FPS design system:

- **Color Scheme**: Traffic light system (green = good, amber = caution, red = critical)
- **Typography**: Consistent with design system
- **Spacing**: Tailwind CSS utility classes
- **Responsive**: Mobile-first design with breakpoints at 640px, 768px, 1024px
- **Hover States**: Smooth transitions for interactive elements

## Account Type Badges

Account types are color-coded for quick identification:

- **ISA**: Green badge (bg-green-100 text-green-800)
- **GIA**: Blue badge (bg-blue-100 text-blue-800)
- **SIPP/Pension**: Purple badge (bg-purple-100 text-purple-800)
- **Other**: Gray badge (bg-gray-100 text-gray-800)

## Error Handling

Components include comprehensive error handling:

1. **Client-side validation** in form submission
2. **Server error display** in parent component
3. **Success messages** with auto-dismiss (5 seconds)
4. **Delete confirmations** with warning for accounts containing holdings
5. **Loading states** during async operations

## Future Enhancements

Potential improvements for account management:

- [ ] Bulk import from CSV
- [ ] Account performance comparison
- [ ] Historical value tracking and charts
- [ ] Account rebalancing recommendations
- [ ] Integration with bank/platform APIs
- [ ] Account consolidation suggestions
- [ ] Fee comparison across accounts
- [ ] Tax wrapper optimization (ISA vs GIA allocation)

## Testing

Test coverage should include:

- Component rendering with various account types
- Form validation (required fields, ISA allowance limits)
- CRUD operations (create, read, update, delete)
- ISA allowance calculations
- Tax year determination
- Badge color coding
- Modal open/close behavior
- Success/error message display
- Empty state and loading state rendering

## Related Files

- `resources/js/store/modules/investment.js` - Vuex store
- `resources/js/services/investmentService.js` - API service
- `resources/js/components/Investment/Holdings.vue` - Related component
- `resources/js/components/Investment/HoldingForm.vue` - Similar form pattern
- `resources/js/components/Investment/PortfolioOverview.vue` - Potential integration point
- `resources/js/views/Investment/InvestmentDashboard.vue` - Main dashboard

## Support

For questions or issues with account management components:

1. Check this documentation
2. Review the inline code comments
3. Consult the CLAUDE.md file for project-wide guidelines
4. Review similar components (GoalCard.vue, HoldingForm.vue) for patterns
