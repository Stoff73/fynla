# Property Expenses Ownership Percentage Fix

## Date: January 17, 2026

## Issue
Property expenses were showing as 50/50 for "Tenants in Common" properties, which is incorrect. Tenants in common can have any ownership percentage split, and expenses MUST match the ownership percentage, not a hardcoded 50/50 split.

## Solution
Updated PropertyFinancials.vue to calculate and display the user's share of all expenses based on their `ownership_percentage` for both `joint` and `tenants_in_common` ownership types.

## Files Changed

### Frontend

**resources/js/components/NetWorth/Property/PropertyFinancials.vue**

Added computed properties:
- `isSharedOwnership` - Returns true if ownership_type is 'joint' or 'tenants_in_common'
- `userMonthlyCosts` - Calculates user's share of total monthly costs based on ownership_percentage
- `userMonthlyRentalIncome` - Calculates user's share of rental income based on ownership_percentage
- `userNetMonthlyIncome` - User's net monthly income (rental income - costs)
- `userNetAnnualIncome` - User's net annual income

Updated template sections:
1. **Total Monthly Costs** - Now shows "Full Property Costs" and "Your Share (X%)" for shared ownership
2. **BTL Summary Cards** - Each card now shows both full amounts and user's share with visual separation
3. **Rental Income Analysis Breakdown** - Shows full property figures and user's share with clear labeling

## Key Pattern Used

```javascript
computed: {
  isSharedOwnership() {
    return this.property?.ownership_type === 'joint' ||
           this.property?.ownership_type === 'tenants_in_common';
  },

  userMonthlyCosts() {
    if (this.isSharedOwnership && this.property?.ownership_percentage) {
      return this.totalMonthlyCosts * (this.property.ownership_percentage / 100);
    }
    return this.totalMonthlyCosts;
  },

  // Similar pattern for rental income, net income, etc.
}
```

## UI Changes

### Monthly Costs Section
- Shows "Full Property Costs" with total amount
- For shared ownership, shows additional "Your Share (X%)" row in blue highlighting

### BTL Summary Cards (for Buy to Let properties)
Each card now displays:
- Full property amount (with label like "Full Monthly Rental Income")
- User's share percentage and calculated amount below a divider

### Rental Income Analysis Breakdown
- Full rental income with user's share shown separately
- Full costs with user's share shown separately
- Full net income with user's share shown separately
- Full annual projection with user's share shown separately

## Testing
1. Login as a user with a tenants_in_common property (e.g., David Mitchell in peak_earners persona)
2. Navigate to Property tab in Net Worth
3. Click on a shared ownership property
4. Go to "Financials" tab
5. Verify:
   - "Full Property Costs" shows total costs
   - "Your Share (X%)" shows costs * ownership_percentage
   - For BTL properties, rental income analysis shows both full and user shares
   - All calculations match the ownership percentage (not hardcoded 50/50)

## Notes
- PropertyDetail.vue already had `isSharedOwnership()` method and proper share calculations for property value and mortgage payments
- This fix ensures the expense display in PropertyFinancials.vue follows the same pattern
- The ownership_percentage field is used consistently throughout the application for share calculations
