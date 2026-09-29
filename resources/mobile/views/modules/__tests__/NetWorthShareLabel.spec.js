import { describe, expect, it } from 'vitest';
import NetWorthCategory from '../NetWorthCategory.vue';

// L3 (29 Sep /m run) printed "Your 50.00% of £…" on shared records. At most two
// decimals, and none when the share is whole.
const label = (item) => NetWorthCategory.methods.shareLabel.call({ fmt: (v) => `£${v}` }, item);

describe('/m net worth share label', () => {
  it('says a whole share without decimals', () => {
    expect(label({ ownership_type: 'joint', ownership_percentage: 50, full_value: 400000 })).toBe('Your 50% of £400000');
  });

  it('keeps the decimals a tenancy in common share really has', () => {
    expect(label({ ownership_type: 'tenants_in_common', ownership_percentage: 33.33, full_value: 300000 })).toBe('Your 33.33% of £300000');
  });
});
