import { describe, it, expect } from 'vitest';
import { matchNavigationIntent } from '@/utils/chatNavigationRouter';

describe('chatNavigationRouter — matchNavigationIntent', () => {
  describe('the reported bug conversation (Fyn savings/protection)', () => {
    it('msg 1 "am i protected for my savings" goes to the LLM (no trigger)', () => {
      expect(matchNavigationIntent('am i protected for my savings')).toBeNull();
    });

    it('msg 2 "cant you see my financials?" goes to the LLM', () => {
      expect(matchNavigationIntent('cant you see my financials?')).toBeNull();
    });

    it('msg 3 — a question to Fyn must reach the LLM, NOT be hijacked to /protection', () => {
      // This is the exact message that was wrongly routed to the Protection
      // page. It is an interrogative complaint directed at Fyn, not a
      // navigation command. It contains the bare substring "view" (NAV
      // trigger) and the keyword "protection" — the old router matched both.
      const result = matchNavigationIntent(
        'so why cant you give me a personalised view when I ask about the protection for my savings?',
      );
      expect(result).toBeNull();
    });
  });

  describe('genuine navigation commands still work (no regression)', () => {
    it('"show me my protection" → /protection', () => {
      expect(matchNavigationIntent('show me my protection')).toMatchObject({ route: '/protection' });
    });

    it('"take me to my pensions" → /net-worth/retirement', () => {
      expect(matchNavigationIntent('take me to my pensions')).toMatchObject({ route: '/net-worth/retirement' });
    });

    it('"go to estate planning" → /estate', () => {
      expect(matchNavigationIntent('go to estate planning')).toMatchObject({ route: '/estate' });
    });

    it('"view my investments" → /net-worth/investments (bare "view" trigger still navigates)', () => {
      expect(matchNavigationIntent('view my investments')).toMatchObject({ route: '/net-worth/investments' });
    });

    it('"where is my estate planning page?" → /estate (question-form but genuinely navigational)', () => {
      expect(matchNavigationIntent('where is my estate planning page?')).toMatchObject({ route: '/estate' });
    });
  });

  describe('other interrogatives/complaints to Fyn go to the LLM', () => {
    it('"why didnt you show me my pensions" → null (complaint about Fyn)', () => {
      expect(matchNavigationIntent('why didnt you show me my pensions')).toBeNull();
    });

    it('"you said my protection was fine, why?" → null', () => {
      expect(matchNavigationIntent('you said my protection was fine, why?')).toBeNull();
    });
  });
});
