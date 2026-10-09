import { describe, it, expect } from 'vitest';
import AiChatPanel from '@/components/Shared/AiChatPanel.vue';

// Which set of chips is still clickable: the newest one, until the user says
// anything after it (regression walk 2026-10-09: "Yes, that's right" stayed
// live under the forms that followed it and could be sent twice).
const latest = (roles) => AiChatPanel.computed.latestQuickRepliesIndex.call({
    messages: roles.map(role => ({ role })),
});

describe('AiChatPanel latestQuickRepliesIndex', () => {
    it('keeps the newest unanswered chips clickable', () => {
        expect(latest(['assistant', 'quick_replies'])).toBe(1);
        expect(latest(['quick_replies', 'assistant', 'quick_replies', 'assistant'])).toBe(2);
    });

    it('greys answered chips even when only forms follow them', () => {
        expect(latest(['quick_replies', 'user', 'assistant', 'capture_form'])).toBe(-1);
    });

    it('returns -1 when there are no chips', () => {
        expect(latest(['assistant', 'user'])).toBe(-1);
    });
});
