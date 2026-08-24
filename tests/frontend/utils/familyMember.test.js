import { describe, it, expect } from 'vitest';
import {
  canManageFamilyMember,
  familyMemberManagementNotice,
  familyMemberRelationshipLabel,
  familyMemberRelationshipTitle,
  isLinkedAccount,
  isSharedFromSpouse,
} from '@/utils/familyMember';

// The two spouse rows W-0051 left on one household: the onboarding orphan that
// linked nobody, and the real link created later from /settings/family. Both
// used to render as "Account Linked" with no Edit and no Delete.
const orphanSpouse = {
  id: 25,
  user_id: 20,
  relationship: 'spouse',
  name: 'Arjun Raman',
  linked_user_id: null,
  is_linked_account: false,
  is_shared: false,
};

const linkedSpouse = {
  id: 46,
  user_id: 20,
  relationship: 'spouse',
  name: 'Arjun Raman',
  linked_user_id: 30,
  is_linked_account: true,
  is_shared: false,
  email: 'arjun@example.com',
};

const child = {
  id: 26,
  user_id: 20,
  relationship: 'child',
  name: 'Meera Raman',
  linked_user_id: null,
  is_linked_account: false,
  is_shared: false,
};

const sharedChild = { ...child, id: 99, is_shared: true, owner: 'spouse' };

describe('isLinkedAccount', () => {
  it('is true only when an account is actually linked', () => {
    expect(isLinkedAccount(linkedSpouse)).toBe(true);
    expect(isLinkedAccount(orphanSpouse)).toBe(false);
    expect(isLinkedAccount(child)).toBe(false);
  });

  it('does not treat the spouse relationship as evidence of a link', () => {
    expect(isLinkedAccount({ relationship: 'spouse' })).toBe(false);
  });

  it('survives a missing member', () => {
    expect(isLinkedAccount(undefined)).toBe(false);
  });
});

describe('isSharedFromSpouse', () => {
  it('identifies records owned by the spouse', () => {
    expect(isSharedFromSpouse(sharedChild)).toBe(true);
    expect(isSharedFromSpouse(child)).toBe(false);
  });
});

describe('canManageFamilyMember', () => {
  it('gives the orphan spouse back its Edit and Delete', () => {
    expect(canManageFamilyMember(orphanSpouse)).toBe(true);
  });

  it('withholds them from a genuinely linked account', () => {
    expect(canManageFamilyMember(linkedSpouse)).toBe(false);
  });

  it('withholds them from a record the spouse owns', () => {
    expect(canManageFamilyMember(sharedChild)).toBe(false);
  });

  it('leaves every ordinary record manageable', () => {
    expect(canManageFamilyMember(child)).toBe(true);
  });
});

describe('familyMemberManagementNotice', () => {
  it('claims a link only for a linked account', () => {
    expect(familyMemberManagementNotice(linkedSpouse)).toContain('Linked account');
    expect(familyMemberManagementNotice(orphanSpouse)).not.toContain('Linked account');
  });

  it('tells the user plainly when a spouse on file is not linked', () => {
    expect(familyMemberManagementNotice(orphanSpouse)).toContain('not linked');
  });

  it('says nothing about an ordinary record', () => {
    expect(familyMemberManagementNotice(child)).toBe('');
  });

  it('names the spouse as the owner of a shared record', () => {
    expect(familyMemberManagementNotice(sharedChild)).toBe('Managed by your spouse');
  });
});

describe('familyMemberRelationshipLabel', () => {
  // W-0114 — `partner` is stored as `other_dependent` because the column has no
  // partner value. Showing the stored value back tells someone their partner is
  // a dependent, which is a false statement about their household.
  const partner = {
    relationship: 'other_dependent',
    stated_relationship: 'partner',
    display_relationship: 'partner',
  };

  const stepChild = {
    relationship: 'child',
    stated_relationship: 'step_child',
    display_relationship: 'step child',
  };

  it('never calls a partner a dependent', () => {
    expect(familyMemberRelationshipLabel(partner)).toBe('partner');
    expect(familyMemberRelationshipLabel(partner)).not.toContain('dependent');
  });

  it('calls a step child a step child', () => {
    expect(familyMemberRelationshipLabel(stepChild)).toBe('step child');
  });

  it('falls back to the stored value when nothing was translated', () => {
    expect(familyMemberRelationshipLabel({ relationship: 'spouse', stated_relationship: null }))
      .toBe('spouse');
  });

  it('reads a hand-built row the server did not serialise', () => {
    // The virtual spouse the profile payload synthesises carries no appended
    // fields, so the fallback chain has to cope rather than render nothing.
    expect(familyMemberRelationshipLabel({ relationship: 'spouse' })).toBe('spouse');
  });

  it('carries the server wording through, including British spelling', () => {
    // `display_relationship` is computed server-side and already says
    // "dependant" — the noun — because CLAUDE.md requires British user-facing
    // text. This helper must pass it through, never restate it (W-0115).
    expect(familyMemberRelationshipLabel({
      relationship: 'other_dependent',
      display_relationship: 'other dependant',
    })).toBe('other dependant');
  });

  it('falls back to the column words without inventing a second vocabulary', () => {
    // Degraded path only. It deliberately does NOT know that the noun is
    // "dependant" — that lives in one place, on the server.
    expect(familyMemberRelationshipLabel({ relationship: 'other_dependent' }))
      .toBe('other dependent');
  });

  it('survives a missing member', () => {
    expect(familyMemberRelationshipLabel(undefined)).toBe('');
  });
});

describe('familyMemberRelationshipTitle', () => {
  // A <select> option does not reliably take CSS capitalisation, so that surface
  // needs the cased form from here rather than a local formatter (W-0115).
  it('title-cases every word', () => {
    expect(familyMemberRelationshipTitle({
      relationship: 'other_dependent',
      display_relationship: 'other dependant',
    })).toBe('Other Dependant');
    expect(familyMemberRelationshipTitle({ relationship: 'child' })).toBe('Child');
  });

  it('still never calls a partner a dependent', () => {
    expect(familyMemberRelationshipTitle({
      relationship: 'other_dependent',
      display_relationship: 'partner',
    })).toBe('Partner');
  });

  it('title-cases a step child correctly', () => {
    expect(familyMemberRelationshipTitle({
      relationship: 'child',
      display_relationship: 'step child',
    })).toBe('Step Child');
  });

  it('survives a missing member', () => {
    expect(familyMemberRelationshipTitle(undefined)).toBe('');
  });
});
