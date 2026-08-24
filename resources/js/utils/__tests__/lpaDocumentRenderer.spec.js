import { describe, expect, it } from 'vitest';
import { getLpaDocumentStyles, renderLpaDocument } from '@/utils/lpaDocumentRenderer';

/**
 * W-0100. The renderer produced two things it was not entitled to produce:
 * a facsimile of the donor's, every attorney's and the certificate provider's
 * signature in a script font whenever `completed_at` was set, and the sentence
 * "This instrument is now a valid Lasting Power of Attorney under the Mental
 * Capacity Act 2005" on the strength of a self-declared checkbox.
 *
 * `completed_at` is set by the user clicking "Complete" in the wizard
 * (LpaWizard.saveComplete). None of the people whose signatures were drawn had
 * done anything at all.
 */

const baseLpa = () => ({
  id: 1,
  lpa_type: 'property_financial',
  status: 'completed',
  donor_full_name: 'Patricia Bennett',
  donor_date_of_birth: '1955-04-12',
  donor_address_line_1: '14 Rowan Close',
  donor_address_postcode: 'BS1 4TR',
  when_attorneys_can_act: 'only_when_lost_capacity',
  certificate_provider_name: 'Dr Alice Okafor',
  certificate_provider_relationship: 'General Practitioner',
  certificate_provider_known_years: 9,
  attorneys: [
    { attorney_type: 'primary', full_name: 'Harold Bennett', relationship_to_donor: 'Husband' },
    { attorney_type: 'replacement', full_name: 'Nadia Bennett', relationship_to_donor: 'Daughter' },
  ],
  notification_persons: [{ full_name: 'Thomas Bennett' }],
  completed_at: '2026-03-02T10:00:00Z',
  registration_date: null,
  opg_reference: null,
  is_registered_with_opg: false,
});

describe('renderLpaDocument — signatures', () => {
  it('never renders anybody\'s name on a signature line, even once completed', () => {
    const html = renderLpaDocument(baseLpa());

    expect(html).toContain('SIGNATURES');
    expect(html).not.toContain('signed-name');
    expect(html).toContain('Fynla has not recorded any of these signatures.');
  });

  it('renders a blank signature line for every party', () => {
    const html = renderLpaDocument(baseLpa());

    // Donor, one primary attorney, one replacement attorney, certificate provider.
    expect(html.match(/class="sig-line"/g)).toHaveLength(4);
    expect(html).toContain('Signed by the Donor');
    expect(html).toContain('Signed by Attorney 1');
    expect(html).toContain('Signed by Replacement Attorney 1');
    expect(html).toContain('Signed by the Certificate Provider');
  });

  it('drops the script font that drew the facsimile signatures', () => {
    expect(getLpaDocumentStyles()).not.toContain('Brush Script');
  });
});

describe('renderLpaDocument — what it claims', () => {
  // Pinned to the header block, above the document's own title rule — not merely
  // present somewhere. Same dependency as the will: names printed beside blank
  // signature lines are safe only while the document says what it is before the
  // reader reaches them.
  it('qualifies itself in the header block, above the title rule, not in a footer', () => {
    const html = renderLpaDocument(baseLpa());

    expect(html).toContain('RECORD OF DETAILS');
    expect(html).toContain('It is not a Lasting Power of Attorney and cannot be used as one.');
    expect(html.indexOf('cannot be used as one')).toBeLessThan(html.indexOf('<hr class="title-rule" />'));
    expect(html.indexOf('cannot be used as one')).toBeLessThan(html.indexOf('SECTION 1'));
  });

  it('never asserts the instrument is valid or registered by anyone', () => {
    const lpa = {
      ...baseLpa(),
      status: 'registered',
      is_registered_with_opg: true,
      registration_date: '2026-05-20',
      opg_reference: 'OPG-7781234',
    };

    const html = renderLpaDocument(lpa);

    expect(html).not.toContain('is now a valid Lasting Power of Attorney');
    expect(html).toContain('You have recorded that this was registered');
    expect(html).toContain('Fynla has not verified this with the Office of the Public Guardian.');
    expect(html).toContain('OPG-7781234');
  });

  it('uses no acronyms in the text it renders', () => {
    const lpa = { ...baseLpa(), is_registered_with_opg: true, registration_date: '2026-05-20' };
    const text = renderLpaDocument(lpa).replace(/<[^>]*>/g, ' ');

    expect(text).not.toMatch(/\bLPA\b/);
    // "OPG-" prefixed references are user-entered data, not Fynla's prose.
    expect(text).not.toMatch(/\bOPG\b(?!-)/);
  });
});

describe('renderLpaDocument — elections the donor has not made', () => {
  it('does not state when attorneys can act when the donor has not chosen', () => {
    const html = renderLpaDocument({ ...baseLpa(), when_attorneys_can_act: null });

    expect(html).toContain('SECTION 4 — WHEN ATTORNEYS CAN ACT');
    expect(html).not.toContain('only when I have lost mental capacity');
    expect(html).toContain('Not specified.');
  });

  it('still states the election the donor has made', () => {
    const html = renderLpaDocument({ ...baseLpa(), when_attorneys_can_act: 'while_has_capacity' });

    expect(html).toContain('whilst I still have mental capacity');
  });

  it('leaves the health and welfare life-sustaining election unstated when unchosen', () => {
    const html = renderLpaDocument({
      ...baseLpa(),
      lpa_type: 'health_welfare',
      life_sustaining_treatment: null,
    });

    expect(html).toContain('SECTION 5 — LIFE-SUSTAINING TREATMENT');
    expect(html).not.toContain('I give my attorneys authority');
    expect(html).not.toContain('do not</strong> give my attorneys authority');
  });
});
