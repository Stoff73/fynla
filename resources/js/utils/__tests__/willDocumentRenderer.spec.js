import { describe, expect, it } from 'vitest';
import { renderWillDocument } from '@/utils/willDocumentRenderer';

/**
 * W-0101. Two defects that compounded and had to land together: the renderer drew
 * the testator's and both witnesses' signatures, while the footer told the user
 * their will becomes legally valid once signed and witnessed. One supplied the
 * instruction, the other supplied the apparent evidence.
 *
 * The shared "never draws a signature" rule is enforced for every renderer in
 * `documentSignatures.spec.js`. This file covers what is specific to the will.
 */

const executedWill = () => ({
  testator_full_name: 'Patricia Bennett',
  testator_address: '14 Rowan Close, Bristol',
  testator_occupation: 'Retired teacher',
  testator_date_of_birth: '1955-04-12',
  executors: [{ name: 'Harold Bennett' }],
  specific_gifts: [],
  residuary_estate: [{ beneficiary_name: 'Nadia Bennett', share_percentage: 100 }],
  signed_date: '2026-03-02',
  witnesses: [
    { name: 'Aisha Rahman', address: '3 Elm Row', occupation: 'Nurse', date: '2026-03-02' },
    { name: 'Peter Nkemelu', address: '9 Oak Way', occupation: 'Engineer', date: '2026-03-02' },
  ],
});

describe('what the will document claims', () => {
  it('no longer states that the will is legally valid once signed and witnessed', () => {
    // Section 9(1) opens "No will shall be valid unless" — necessary conditions.
    // "only legally valid once …" stated a sufficient one, which is its converse,
    // and hid the missing limbs behind the undefined word "properly".
    expect(renderWillDocument(executedWill())).not.toContain('legally valid');
  });

  it('says what validity depends on, and cites where the requirements live', () => {
    const html = renderWillDocument(executedWill());

    expect(html).toContain('Whether it takes effect as a will depends on how it is signed and witnessed');
    expect(html).toContain('matters Fynla cannot see');
    expect(html).toContain('Section 9 of the Wills Act 1837');
    expect(html).toContain('has recorded no signature');
  });

  /**
   * Pinned to the TOP, not merely present — and that is a dependency, not neatness.
   * The witness's printed name is typed by the testator in advance, before that
   * witness has done anything: a plan, not a record. It is safe only because the
   * document qualifies itself before the reader reaches it. Move this to a footer in
   * some future redesign and those printed names beside blank signature lines become
   * assertive again — which is exactly how the old disclaimer ended up in a footer
   * the first time.
   */
  it('qualifies itself in the header block, above the title rule, never in a footer', () => {
    const html = renderWillDocument(executedWill());

    expect(html).toContain('doc-qualification');
    expect(html.indexOf('does not constitute legal advice'))
      .toBeLessThan(html.indexOf('<hr class="title-rule" />'));
    expect(html.indexOf('Whether it takes effect as a will depends'))
      .toBeLessThan(html.indexOf('<hr class="title-rule" />'));
    expect(html.indexOf('does not constitute legal advice'))
      .toBeLessThan(html.indexOf('HEREBY REVOKE'));
  });

  // The trap. The sibling record correctly says it is NOT a Lasting Power of
  // Attorney, because that instrument has a statutorily prescribed form. A will has
  // none, so this document, printed and properly executed, could take effect as a
  // will — and saying otherwise would replace one false statement with another.
  it('does not claim the document cannot be a will', () => {
    const html = renderWillDocument(executedWill());

    expect(html).not.toContain('is not a will');
    expect(html).not.toContain('cannot be used as one');
    expect(html).not.toContain('RECORD OF DETAILS');
  });

  it('uses no acronyms', () => {
    const text = renderWillDocument(executedWill()).replace(/<[^>]*>/g, ' ');

    expect(text).not.toMatch(/\bLPA\b/);
    expect(text).not.toMatch(/\bOPG\b/);
  });
});

describe('execution details the user typed', () => {
  it('leaves the attestation date blank even when a signing date is recorded', () => {
    // The clause is in the testator's own voice — "I have hereunto set my hand this
    // …" — so filling it in asserts an execution event Fynla did not witness. A
    // typed date is not a signature.
    const html = renderWillDocument(executedWill());

    expect(html).toContain('I have hereunto set my hand this _______ day of _________________ 20_____');
    expect(html).not.toContain('hereunto set my hand this 2 March 2026');
  });

  it('keeps a witness name in the Full Name field, which labels a person', () => {
    const html = renderWillDocument(executedWill());

    expect(html).toContain('<div class="line filled">Aisha Rahman</div>');
    expect(html).toContain('<div class="line filled">Nurse</div>');
  });

  it('leaves both witness signature and witness date blank', () => {
    const html = renderWillDocument(executedWill());

    expect(html).toContain('<span>Signature:</span><div class="line"></div>');
    expect(html).toContain('<span>Date:</span><div class="line"></div>');
    expect(html).not.toContain('<div class="line filled">2 March 2026</div>');
  });

  it('renders a blank witness block when no witness has been named', () => {
    const html = renderWillDocument({ ...executedWill(), witnesses: [] });

    expect(html).toContain('WITNESS 1');
    expect(html).toContain('WITNESS 2');
    expect(html).not.toContain('Aisha Rahman');
  });
});
