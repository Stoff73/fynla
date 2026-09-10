import { describe, it, expect } from 'vitest';
import { familyMemberManagementNotice, spouseInvitationSentMessage } from '../familyMember';

/**
 * W-0543. A spouse invitation succeeded and the list then told the user to
 * "add them again": the notice had no branch for an invitation that is out
 * and unanswered, and the list payload could not express one.
 */
describe('familyMemberManagementNotice', () => {
  it('names a pending invitation instead of telling the user to add them again', () => {
    const notice = familyMemberManagementNotice({ relationship: 'spouse', invitation_pending: true });

    expect(notice).toMatch(/Invitation sent/);
    expect(notice).not.toMatch(/Add them again/);
  });

  it('still tells an unlinked spouse with no invitation how to link', () => {
    expect(familyMemberManagementNotice({ relationship: 'spouse', invitation_pending: false }))
      .toMatch(/Add them again/);
  });

  it('says nothing for a child', () => {
    expect(familyMemberManagementNotice({ relationship: 'child' })).toBe('');
  });
});

describe('spouseInvitationSentMessage', () => {
  it('names the address the user typed and says the other account decides', () => {
    const message = spouseInvitationSentMessage('jane@example.com');

    expect(message).toContain('jane@example.com');
    expect(message).toMatch(/once they accept/);
  });
});
