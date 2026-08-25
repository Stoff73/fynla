import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import WillBuilderSigningStep from '@/components/Estate/WillBuilder/steps/WillBuilderSigningStep.vue';

/**
 * W-0143 (the sentence shape) and W-0157 (three unsourced facts, one wrong by more
 * than a factor of three). Same component, same pass, different mechanisms.
 *
 * Both were found by scanning every string in the component rather than the ones the
 * item was raised about. These tests exist because none of the three numbers was
 * caught by a test, a review or a sweep — all were caught by one person reading the
 * publisher's page.
 */
const render = () => mount(WillBuilderSigningStep, {
  props: { formData: { testator_full_name: 'Patricia Bennett' } },
  global: { stubs: { 'router-link': { template: '<a><slot /></a>' } } },
});

describe('what the signing step claims about validity', () => {
  it('states a necessary condition, not a sufficient one', () => {
    const text = render().text();

    // Wills Act 1837 s.9(1) opens "No will shall be valid unless". The old copy said
    // "Follow the steps below to make it legally binding" under "How to Make Your
    // Will Legally Valid" — the converse of the statute.
    expect(text).toContain('It is a draft until it is signed and witnessed');
    expect(text).toContain('Section 9 of the Wills Act 1837');
    expect(text).not.toContain('make it legally binding');
    expect(text).not.toContain('How to Make Your Will Legally Valid');
  });

  // s.9(1)(b) — that the testator appears to have intended their signature to give
  // effect to the will — is a state of mind, so no checklist can reach it. The copy
  // must not promise what the format cannot deliver.
  it('does not present the checklist as the whole of what the law requires', () => {
    const text = render().text();

    expect(text).toContain('the parts you can prepare for');
    expect(text).toContain('Fynla does not check any of them');
  });

  it('names the act rather than asserting the outcome in its heading', () => {
    expect(render().text()).toContain('Before your will can take effect');
  });
});

describe('facts stated to the user', () => {
  // W-0157. Fynla said £75; HM Courts and Tribunals Service publishes £24. The
  // provenance lives in the constant's docblock and in sources.md row C3.
  it('states the will storage charge published by the Probate Service', () => {
    const text = render().text();

    expect(text).toContain('£24');
    expect(text).not.toContain('£75');
  });

  // s.9 sets no witness age. The figure was unsourced, so the copy says less and
  // says whose suggestion it is.
  it('does not state an unsourced witness age as a requirement', () => {
    const text = render().text();

    expect(text).not.toContain('18 years or older');
    expect(text).toContain('The Wills Act 1837 does not set an age for witnesses');
    expect(text).toContain("Fynla's guidance, not a legal requirement");
  });

  it('uses no acronyms', () => {
    const text = render().text();

    expect(text).not.toMatch(/\bLPA\b/);
    expect(text).not.toMatch(/\bHMCTS\b/);
  });
});
