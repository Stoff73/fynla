import { describe, it, expect } from 'vitest';
import {
  calculateTotalUserShare,
  calculateUserShare,
  coOwnerName,
  isSharedRecord,
  userSharePercent,
} from '@/utils/ownership';

// The joint General Investment Account from the peak_earners persona run:
// ONE record, £95,000 full value, David (16) primary, Sarah (17) joint owner.
const jointGia = {
  id: 14,
  user_id: 16,
  joint_owner_id: 17,
  ownership_type: 'joint',
  ownership_percentage: 50,
  current_value: 95000,
  full_value: 95000,
  owner_name: 'David Jones',
  joint_owner_name: 'Sarah Jones',
};

const asPrimaryOwner = { ...jointGia, is_primary_owner: true, user_share: 47500 };
const asJointOwner = { ...jointGia, is_primary_owner: false, user_share: 47500 };

describe('isSharedRecord', () => {
  it('treats any record with a second party as shared', () => {
    expect(isSharedRecord(jointGia)).toBe(true);
    expect(isSharedRecord({ ownership_type: 'tenants_in_common' })).toBe(true);
    expect(isSharedRecord({ ownership_type: 'individual', joint_owner_id: 17 })).toBe(true);
    expect(isSharedRecord({ is_shared: true })).toBe(true);
  });

  it('does not treat a solely owned record as shared', () => {
    expect(isSharedRecord({ ownership_type: 'individual' })).toBe(false);
    expect(isSharedRecord(null)).toBe(false);
  });

  it('still reports a joint record stored at 100 as shared', () => {
    // The investments card gated its Joint badge on `percentage < 100`, so the
    // accounts stored wrong were exactly the ones that never showed as joint.
    expect(isSharedRecord({ ...jointGia, ownership_percentage: 100 })).toBe(true);
  });
});

describe('userSharePercent', () => {
  it('gives the primary owner the stored percentage', () => {
    expect(userSharePercent(asPrimaryOwner)).toBe(50);
    expect(userSharePercent({ ...asPrimaryOwner, ownership_percentage: 60 })).toBe(60);
  });

  it('gives the joint owner the complement, not the stored percentage', () => {
    // Rendering the stored percentage to both sides is what told BOTH spouses
    // they owned 100% of the same account (W-0015).
    expect(userSharePercent(asJointOwner)).toBe(50);
    expect(userSharePercent({ ...asJointOwner, ownership_percentage: 60 })).toBe(40);
  });

  it('falls back to a viewer id when the payload carries no is_primary_owner', () => {
    expect(userSharePercent(jointGia, 16)).toBe(50);
    expect(userSharePercent({ ...jointGia, ownership_percentage: 70 }, 17)).toBe(30);
  });

  it('returns 100 for a solely owned record', () => {
    expect(userSharePercent({ ownership_type: 'individual', current_value: 1000 })).toBe(100);
  });
});

describe('calculateUserShare', () => {
  it('prefers the share the API computed', () => {
    expect(calculateUserShare(asPrimaryOwner)).toBe(47500);
    expect(calculateUserShare(asJointOwner)).toBe(47500);
  });

  it('never gives both sides the full value', () => {
    const bare = { ...jointGia };
    const primary = calculateUserShare({ ...bare, is_primary_owner: true });
    const joint = calculateUserShare({ ...bare, is_primary_owner: false });

    expect(primary + joint).toBe(95000);
  });

  it('computes from the full value when the API did not supply a share', () => {
    expect(calculateUserShare(jointGia, { viewerId: 17 })).toBe(47500);
    expect(calculateUserShare(
      { ownership_type: 'individual', current_balance: 12000 },
      { valueField: 'current_balance' },
    )).toBe(12000);
  });

  it('returns 0 for a missing record', () => {
    expect(calculateUserShare(null)).toBe(0);
  });
});

describe('calculateTotalUserShare', () => {
  it('totals only this viewer’s shares', () => {
    const accounts = [
      { ownership_type: 'individual', current_value: 85000, user_share: 85000 },
      asPrimaryOwner,
    ];

    expect(calculateTotalUserShare(accounts, { valueField: 'current_value' })).toBe(132500);
  });

  it('returns 0 for anything that is not a list', () => {
    expect(calculateTotalUserShare(null)).toBe(0);
  });
});

describe('coOwnerName', () => {
  it('names the other party, never the viewer', () => {
    // Viewed by David it is held with Sarah; viewed by Sarah it is held with
    // David. Rendering the stored joint_owner_name to both told Sarah the
    // property was "Joint with Sarah Jones" (W-0016).
    expect(coOwnerName(asPrimaryOwner)).toBe('Sarah Jones');
    expect(coOwnerName(asJointOwner)).toBe('David Jones');
  });

  it('reads a nested user relation when there is no flat name', () => {
    expect(coOwnerName({
      ownership_type: 'joint',
      is_primary_owner: true,
      joint_owner: { first_name: 'Sarah', surname: 'Jones' },
    })).toBe('Sarah Jones');
  });

  it('returns null for a solely owned record or an unknown counterparty', () => {
    expect(coOwnerName({ ownership_type: 'individual' })).toBeNull();
    expect(coOwnerName({ ownership_type: 'joint', is_primary_owner: true })).toBeNull();
  });
});
