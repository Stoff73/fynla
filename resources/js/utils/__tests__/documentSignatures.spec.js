import { describe, expect, it } from 'vitest';
import { drawnSignatureLines } from '@/utils/documentSignatures';
import { getLpaDocumentStyles, renderLpaDocument } from '@/utils/lpaDocumentRenderer';
import { getWillDocumentStyles, renderWillDocument } from '@/utils/willDocumentRenderer';

/**
 * THE REGISTER. Fynla never draws a signature — on any document, for any party.
 *
 * This file is the one place that rule is enforced across every document generator.
 * It exists because the rule was broken twice, independently, in two renderers that
 * each looked fine on its own review: the Lasting Power of Attorney renderer drew
 * the donor's, every attorney's and the certificate provider's name once the user
 * pressed "Complete" (W-0100), and the will renderer drew the testator's name once a
 * date was typed and **each witness's name the moment a witness row existed**
 * (W-0101).
 *
 * **Adding a new document renderer means adding it to `renderers` below.** Nothing
 * else. If it draws a mark, this file goes red before anybody has to notice.
 *
 * Every entry is fed data in which every party is named and every date is set —
 * the state in which a renderer is most tempted to draw.
 */

const renderers = [
  {
    name: 'Lasting Power of Attorney',
    styles: getLpaDocumentStyles,
    render: () => renderLpaDocument({
      id: 1,
      lpa_type: 'property_financial',
      status: 'registered',
      donor_full_name: 'Patricia Bennett',
      donor_date_of_birth: '1955-04-12',
      when_attorneys_can_act: 'only_when_lost_capacity',
      certificate_provider_name: 'Dr Alice Okafor',
      certificate_provider_known_years: 9,
      attorneys: [
        { attorney_type: 'primary', full_name: 'Harold Bennett' },
        { attorney_type: 'replacement', full_name: 'Nadia Bennett' },
      ],
      notification_persons: [{ full_name: 'Thomas Bennett' }],
      completed_at: '2026-03-02T10:00:00Z',
      registration_date: '2026-05-20',
      opg_reference: 'OPG-7781234',
      is_registered_with_opg: true,
    }),
    parties: ['Patricia Bennett', 'Harold Bennett', 'Nadia Bennett', 'Dr Alice Okafor'],
  },
  {
    name: 'will',
    styles: getWillDocumentStyles,
    render: () => renderWillDocument({
      testator_full_name: 'Patricia Bennett',
      testator_address: '14 Rowan Close, Bristol',
      testator_occupation: 'Retired teacher',
      testator_date_of_birth: '1955-04-12',
      executors: [{ name: 'Harold Bennett', address: '14 Rowan Close' }],
      specific_gifts: [],
      residuary_estate: [{ beneficiary_name: 'Nadia Bennett', share_percentage: 100 }],
      signed_date: '2026-03-02',
      witnesses: [
        { name: 'Aisha Rahman', address: '3 Elm Row', occupation: 'Nurse', date: '2026-03-02' },
        { name: 'Peter Nkemelu', address: '9 Oak Way', occupation: 'Engineer', date: '2026-03-02' },
      ],
    }),
    parties: ['Aisha Rahman', 'Peter Nkemelu'],
  },
];

describe.each(renderers)('$name document', ({ render, styles, parties }) => {
  it('leaves every signature line blank', () => {
    expect(drawnSignatureLines(render())).toEqual([]);
  });

  it('carries no handwriting face for a name to be drawn in', () => {
    const css = styles();

    expect(css).not.toContain('Brush Script');
    expect(css).not.toContain('Segoe Script');
    expect(css).not.toContain('cursive');
    expect(css).not.toContain('signed-name');
  });

  it('never marks a name as signed', () => {
    expect(render()).not.toContain('signed-name');
  });

  it('says that Fynla has recorded no signature', () => {
    expect(render()).toContain('Fynla has not recorded any of these signatures.');
  });

  it('does not place any party name immediately inside a signature line', () => {
    const html = render();

    for (const party of parties) {
      // A name may appear as a clause, a label or a "Full Name" field. It may never
      // be the content of a bare signature line.
      expect(html).not.toContain(`<div class="line">${party}`);
    }
  });
});

describe('the rule detector itself', () => {
  it('ignores a field the user legitimately filled in', () => {
    expect(drawnSignatureLines('<div class="line filled">Aisha Rahman</div>')).toEqual([]);
  });

  it('catches a name drawn on a bare signature line', () => {
    expect(drawnSignatureLines('<div class="line">Aisha Rahman</div>')).toEqual(['Aisha Rahman']);
  });

  it('treats an empty line as what a signature line should be', () => {
    expect(drawnSignatureLines('<div class="line"></div>')).toEqual([]);
  });
});
