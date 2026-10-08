import { describe, expect, it } from 'vitest';

import PropertyForm from '../PropertyForm.vue';

// Both owners of a joint property own it (CSJ 2026-10-08). "Your Ownership
// Share" is the viewer's own; the stored percentage is the primary owner's.
function populate(property) {
  const ctx = {
    property,
    form: {},
    mortgageForm: {},
    formatDateForInput: () => null,
  };
  PropertyForm.methods.populateForm.call(ctx);
  return ctx.form.ownership_percentage;
}

describe('PropertyForm share', () => {
  it('shows the primary owner their stored share', () => {
    expect(populate({ ownership_type: 'tenants_in_common', ownership_percentage: 60, is_primary_owner: true })).toBe(60);
  });

  it('shows the joint owner the other side of the split', () => {
    expect(populate({ ownership_type: 'tenants_in_common', ownership_percentage: 60, is_primary_owner: false })).toBe(40);
  });

  it('shows 100 on an individually owned property', () => {
    expect(populate({ ownership_type: 'individual', ownership_percentage: 100 })).toBe(100);
  });
});
