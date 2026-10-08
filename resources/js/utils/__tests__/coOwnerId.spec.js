import { describe, expect, it } from 'vitest';

import { coOwnerId } from '../ownership';

// A form's "joint owner" is the viewer's co-owner. Either owner may change a
// joint account (CSJ 2026-10-08), so the joint owner's co-owner is the primary.
describe('coOwnerId', () => {
  it('gives the primary owner the joint owner', () => {
    expect(coOwnerId({ is_primary_owner: true, user: { id: 142 }, joint_owner: { id: 143 } })).toBe(143);
  });

  it('gives the joint owner the primary owner', () => {
    expect(coOwnerId({ is_primary_owner: false, user: { id: 142 }, joint_owner: { id: 143 } })).toBe(142);
  });

  it('reads flat ids where the payload has them', () => {
    expect(coOwnerId({ is_primary_owner: false, user_id: 142, joint_owner_id: 143 })).toBe(142);
  });

  it('gives nothing on an individual account', () => {
    expect(coOwnerId({ is_primary_owner: true, user: { id: 142 }, joint_owner: null })).toBeNull();
  });
});
