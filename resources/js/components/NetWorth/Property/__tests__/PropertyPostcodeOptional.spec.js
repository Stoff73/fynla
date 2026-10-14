import { describe, expect, it } from 'vitest';

import PropertyForm from '../PropertyForm.vue';

// T77 (T69 walk, Robin 509): Fyn saves a property with a town and no postcode
// (T24, T62), but the web form refused to save one ("Please fill in all
// required fields in Basic Information (Step 1).") though the postcode field
// is not marked required and the store allows none.
function validate(form) {
  const ctx = { error: null, currentStep: 4, form };
  const ok = PropertyForm.methods.validateForm.call(ctx);
  return { ok, ctx };
}

describe('PropertyForm validation', () => {
  it('saves a property with a town and no postcode', () => {
    const { ok, ctx } = validate({ property_type: 'buy_to_let', address_line_1: 'Buy to let', city: 'Woking', postcode: '', current_value: 320000, ownership_type: 'individual', ownership_percentage: 100 });
    expect(ok).toBe(true);
    expect(ctx.error).toBeNull();
  });

  it('still needs the town', () => {
    const { ok, ctx } = validate({ property_type: 'buy_to_let', address_line_1: 'Buy to let', city: '', postcode: '', current_value: 320000, ownership_type: 'individual', ownership_percentage: 100 });
    expect(ok).toBe(false);
    expect(ctx.currentStep).toBe(1);
  });
});
