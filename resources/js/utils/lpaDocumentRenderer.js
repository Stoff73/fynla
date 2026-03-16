/**
 * LPA Document Renderer
 *
 * Generates a formatted HTML string for a UK Lasting Power of Attorney
 * in the official Office of the Public Guardian legal format.
 * All user content is escaped via escapeHtml() to prevent injection.
 */

function escapeHtml(text) {
  if (!text) return '';
  const div = document.createElement('div');
  div.appendChild(document.createTextNode(String(text)));
  return div.innerHTML;
}

function formatDate(dateStr) {
  if (!dateStr) return '___________';
  const d = new Date(dateStr);
  return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
}

function formatAddress(attorney) {
  const parts = [
    attorney.address_line_1,
    attorney.address_line_2,
    attorney.address_city,
    attorney.address_county,
    attorney.address_postcode,
  ].filter(Boolean);
  return parts.join(', ') || attorney.address || '';
}

function formatDonorAddress(lpa) {
  const parts = [
    lpa.donor_address_line_1,
    lpa.donor_address_line_2,
    lpa.donor_address_city,
    lpa.donor_address_county,
    lpa.donor_address_postcode,
  ].filter(Boolean);
  return parts.join(', ');
}

export function renderLpaDocument(lpa) {
  const isProperty = lpa.lpa_type === 'property_financial';
  const typeName = isProperty ? 'Property and Financial Affairs' : 'Health and Welfare';
  const donorName = escapeHtml(lpa.donor_full_name);
  const donorAddress = escapeHtml(formatDonorAddress(lpa));
  const donorDob = formatDate(lpa.donor_date_of_birth);
  const primaryAttorneys = (lpa.attorneys || []).filter(a => a.attorney_type === 'primary');
  const replacementAttorneys = (lpa.attorneys || []).filter(a => a.attorney_type === 'replacement');
  const notificationPersons = lpa.notification_persons || [];
  const signedDate = lpa.completed_at || lpa.registration_date;
  const isRegistered = lpa.is_registered_with_opg;

  let html = '';

  // Header
  html += `<h1>LASTING POWER OF ATTORNEY</h1>`;
  html += `<h2>${typeName}</h2>`;
  if (isRegistered && lpa.opg_reference) {
    html += `<p class="opg-ref">Office of the Public Guardian Reference: <strong>${escapeHtml(lpa.opg_reference)}</strong></p>`;
  }
  html += `<hr class="title-rule" />`;

  // Section 1: The Donor
  html += `<h3>SECTION 1 — THE DONOR</h3>`;
  html += `<p class="clause">I, <strong>${donorName}</strong>`;
  if (donorAddress) html += `, of ${donorAddress}`;
  html += `, born on ${donorDob}, appoint the attorney(s) named below to make decisions on my behalf in relation to my ${typeName.toLowerCase()}, subject to the conditions and restrictions set out in this instrument.</p>`;

  // Section 2: The Attorneys
  html += `<h3>SECTION 2 — THE ATTORNEY(S)</h3>`;
  primaryAttorneys.forEach((att, i) => {
    html += `<div class="attorney-block">`;
    html += `<p class="clause"><strong>Attorney ${i + 1}:</strong> <strong>${escapeHtml(att.full_name)}</strong>`;
    if (att.date_of_birth) html += `, born ${formatDate(att.date_of_birth)}`;
    const addr = formatAddress(att);
    if (addr) html += `, of ${escapeHtml(addr)}`;
    if (att.relationship_to_donor) html += ` (${escapeHtml(att.relationship_to_donor)})`;
    html += `.</p>`;
    html += `</div>`;
  });

  if (primaryAttorneys.length > 1 && lpa.attorney_decision_type) {
    html += `<p class="clause"><strong>How attorneys must make decisions:</strong> `;
    if (lpa.attorney_decision_type === 'jointly') {
      html += `The attorneys appointed above shall act <strong>jointly</strong> — they must all agree on every decision.`;
    } else if (lpa.attorney_decision_type === 'jointly_and_severally') {
      html += `The attorneys appointed above shall act <strong>jointly and severally</strong> — they may make decisions together or independently.`;
    } else if (lpa.attorney_decision_type === 'jointly_for_some') {
      html += `The attorneys appointed above shall act <strong>jointly for some decisions and severally for others</strong>.`;
      if (lpa.jointly_for_some_details) {
        html += ` ${escapeHtml(lpa.jointly_for_some_details)}`;
      }
    }
    html += `</p>`;
  }

  // Section 3: Replacement Attorneys
  if (replacementAttorneys.length > 0) {
    html += `<h3>SECTION 3 — REPLACEMENT ATTORNEY(S)</h3>`;
    html += `<p class="clause">If any of the above-named attorneys are unable or unwilling to act, I appoint the following replacement attorney(s):</p>`;
    replacementAttorneys.forEach((att, i) => {
      html += `<div class="attorney-block">`;
      html += `<p class="clause"><strong>Replacement Attorney ${i + 1}:</strong> <strong>${escapeHtml(att.full_name)}</strong>`;
      if (att.date_of_birth) html += `, born ${formatDate(att.date_of_birth)}`;
      const addr = formatAddress(att);
      if (addr) html += `, of ${escapeHtml(addr)}`;
      if (att.relationship_to_donor) html += ` (${escapeHtml(att.relationship_to_donor)})`;
      html += `.</p>`;
      html += `</div>`;
    });
  }

  // Section 4: When attorneys can act (Property only)
  if (isProperty) {
    html += `<h3>SECTION 4 — WHEN ATTORNEYS CAN ACT</h3>`;
    if (lpa.when_attorneys_can_act === 'while_has_capacity') {
      html += `<p class="clause">I wish my attorneys to be able to act on my behalf as soon as this Lasting Power of Attorney is registered, <strong>whilst I still have mental capacity</strong>, as well as when I have lost mental capacity.</p>`;
    } else {
      html += `<p class="clause">I wish my attorneys to be able to act on my behalf <strong>only when I have lost mental capacity</strong> to make decisions about my property and financial affairs.</p>`;
    }
  }

  // Preferences & Instructions
  const sectionNum = isProperty ? '5' : '4';
  html += `<h3>SECTION ${sectionNum} — PREFERENCES AND INSTRUCTIONS</h3>`;

  if (lpa.preferences) {
    html += `<p class="sub-heading">Preferences</p>`;
    html += `<p class="clause clause-indent">${escapeHtml(lpa.preferences)}</p>`;
  }

  if (lpa.instructions) {
    html += `<p class="sub-heading">Instructions</p>`;
    html += `<p class="clause clause-indent">${escapeHtml(lpa.instructions)}</p>`;
  }

  if (!lpa.preferences && !lpa.instructions) {
    html += `<p class="clause">No preferences or instructions have been specified.</p>`;
  }

  // Life-sustaining treatment (Health only)
  if (!isProperty) {
    html += `<h3>SECTION 5 — LIFE-SUSTAINING TREATMENT</h3>`;
    if (lpa.life_sustaining_treatment === 'can_consent') {
      html += `<p class="clause">I give my attorneys authority to give or refuse consent to life-sustaining treatment on my behalf.</p>`;
    } else if (lpa.life_sustaining_treatment === 'cannot_consent') {
      html += `<p class="clause">I <strong>do not</strong> give my attorneys authority to give or refuse consent to life-sustaining treatment on my behalf.</p>`;
    } else {
      html += `<p class="clause">Not specified.</p>`;
    }
  }

  // Certificate Provider
  html += `<h3>CERTIFICATE PROVIDER</h3>`;
  html += `<p class="clause">I, <strong>${escapeHtml(lpa.certificate_provider_name)}</strong>`;
  if (lpa.certificate_provider_relationship) {
    html += ` (${escapeHtml(lpa.certificate_provider_relationship)} to the donor)`;
  }
  html += `, certify that:</p>`;
  html += `<div class="sub-clauses">`;
  html += `<p>(a) I have discussed this Lasting Power of Attorney with the donor and I am satisfied that the donor understands its purpose and the scope of the authority conferred under it.</p>`;
  html += `<p>(b) No fraud or undue pressure is being used to induce the donor to create this Lasting Power of Attorney.</p>`;
  html += `<p>(c) There is nothing else which would prevent this Lasting Power of Attorney from being created.</p>`;
  html += `</div>`;
  if (lpa.certificate_provider_known_years) {
    html += `<p class="clause known-years">I have known the donor for <strong>${lpa.certificate_provider_known_years} years</strong>.</p>`;
  }

  // People to notify
  if (notificationPersons.length > 0) {
    html += `<h3>PEOPLE TO NOTIFY</h3>`;
    html += `<p class="clause">The following persons are to be notified when an application is made to register this Lasting Power of Attorney:</p>`;
    notificationPersons.forEach((person, i) => {
      const addr = formatAddress(person);
      html += `<p class="clause">${i + 1}. <strong>${escapeHtml(person.full_name)}</strong>`;
      if (addr) html += `, of ${escapeHtml(addr)}`;
      html += `</p>`;
    });
  }

  // Signatures
  html += `<h3>SIGNATURES</h3>`;

  // Donor signature
  html += `<div class="signature-block">`;
  html += `<p class="sig-label">Signed by the Donor</p>`;
  if (signedDate) {
    html += `<div class="sig-line"><div class="line signed-name">${donorName}</div></div>`;
    html += `<p class="sig-meta"><strong>${donorName}</strong> — ${formatDate(signedDate)}</p>`;
  } else {
    html += `<div class="sig-line"><div class="line"></div></div>`;
    html += `<p class="sig-meta">Signature &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Date: ___________</p>`;
  }
  html += `</div>`;

  // Attorney signatures
  primaryAttorneys.forEach((att, i) => {
    html += `<div class="signature-block">`;
    html += `<p class="sig-label">Signed by Attorney ${i + 1}</p>`;
    if (signedDate) {
      html += `<div class="sig-line"><div class="line signed-name">${escapeHtml(att.full_name)}</div></div>`;
      html += `<p class="sig-meta"><strong>${escapeHtml(att.full_name)}</strong> — ${formatDate(signedDate)}</p>`;
    } else {
      html += `<div class="sig-line"><div class="line"></div></div>`;
      html += `<p class="sig-meta">Signature &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Date: ___________</p>`;
    }
    html += `</div>`;
  });

  // Replacement attorney signatures
  replacementAttorneys.forEach((att, i) => {
    html += `<div class="signature-block">`;
    html += `<p class="sig-label">Signed by Replacement Attorney ${i + 1}</p>`;
    if (signedDate) {
      html += `<div class="sig-line"><div class="line signed-name">${escapeHtml(att.full_name)}</div></div>`;
      html += `<p class="sig-meta"><strong>${escapeHtml(att.full_name)}</strong> — ${formatDate(signedDate)}</p>`;
    } else {
      html += `<div class="sig-line"><div class="line"></div></div>`;
      html += `<p class="sig-meta">Signature &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Date: ___________</p>`;
    }
    html += `</div>`;
  });

  // Certificate provider signature
  html += `<div class="signature-block">`;
  html += `<p class="sig-label">Signed by the Certificate Provider</p>`;
  if (signedDate) {
    html += `<div class="sig-line"><div class="line signed-name">${escapeHtml(lpa.certificate_provider_name)}</div></div>`;
    html += `<p class="sig-meta"><strong>${escapeHtml(lpa.certificate_provider_name)}</strong> — ${formatDate(signedDate)}</p>`;
  } else {
    html += `<div class="sig-line"><div class="line"></div></div>`;
    html += `<p class="sig-meta">Signature &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Date: ___________</p>`;
  }
  html += `</div>`;

  // Registration stamp
  if (isRegistered) {
    html += `<div class="registration-stamp">`;
    html += `<h3>REGISTRATION</h3>`;
    html += `<p class="clause">This Lasting Power of Attorney was registered by the Office of the Public Guardian on <strong>${formatDate(lpa.registration_date)}</strong>.`;
    if (lpa.opg_reference) {
      html += ` Reference: <strong>${escapeHtml(lpa.opg_reference)}</strong>.`;
    }
    html += `</p>`;
    html += `<p class="clause">This instrument is now a valid Lasting Power of Attorney under the Mental Capacity Act 2005.</p>`;
    html += `</div>`;
  }

  return html;
}

export function getLpaDocumentStyles() {
  return `
    @page { size: A4; margin: 25mm 20mm; }
    body {
      font-family: 'Times New Roman', Georgia, serif;
      font-size: 12pt;
      line-height: 1.6;
      color: #1a1a1a;
      max-width: 700px;
      margin: 0 auto;
      padding: 40px 20px;
    }
    .fynla-header {
      text-align: center;
      padding-bottom: 20px;
      margin-bottom: 30px;
      border-bottom: 2px solid #1F2A44;
    }
    .fynla-header p { font-family: 'Segoe UI', sans-serif; font-size: 10pt; color: #717171; margin: 0; }
    h1 { text-align: center; font-size: 18pt; letter-spacing: 2px; margin-bottom: 5px; font-weight: 700; }
    h2 { text-align: center; font-size: 14pt; font-weight: 400; margin-bottom: 10px; }
    h3 { font-size: 12pt; text-transform: uppercase; letter-spacing: 1px; margin-top: 25px; margin-bottom: 10px; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
    .title-rule { border: none; border-top: 2px solid #1F2A44; margin: 15px 0 25px; }
    .opg-ref { text-align: center; font-size: 11pt; margin-bottom: 5px; }
    .clause { text-indent: 0; margin-bottom: 12px; text-align: justify; }
    .clause-indent { margin-left: 20px; font-style: italic; }
    .sub-heading { font-weight: 700; font-size: 11pt; margin-top: 12px; margin-bottom: 4px; text-decoration: underline; }
    .sub-clauses { margin-left: 30px; }
    .sub-clauses p { margin-bottom: 8px; }
    .known-years { margin-top: 8px; font-size: 11pt; }
    .attorney-block { margin-bottom: 8px; }
    .signature-block { margin: 20px 0; }
    .sig-label { font-weight: 700; font-size: 10pt; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
    .sig-line { margin-bottom: 4px; }
    .sig-line .line { border-bottom: 1px solid #000; width: 300px; height: 35px; }
    .sig-meta { font-size: 10pt; color: #555; }
    .signed-name { font-family: 'Brush Script MT', 'Segoe Script', cursive; font-size: 18pt; padding-left: 6px; }
    .registration-stamp { margin-top: 30px; padding: 15px; border: 2px solid #1F2A44; border-radius: 4px; }
    .registration-stamp h3 { border-bottom: none; margin-top: 0; }
    .disclaimer {
      margin-top: 50px;
      padding-top: 15px;
      border-top: 1px solid #ccc;
      font-family: 'Segoe UI', sans-serif;
      font-size: 8pt;
      color: #717171;
      text-align: center;
    }
    @media print {
      body { padding: 0; }
      .no-print { display: none; }
    }
  `;
}

/**
 * Opens a print window with the rendered LPA document.
 * Content is fully escaped via escapeHtml() - safe for print rendering.
 * Follows the same DOMParser + srcdoc pattern used for will documents.
 */
export function printLpaDocument(lpa) {
  const content = renderLpaDocument(lpa);
  const styles = getLpaDocumentStyles();
  const typeName = lpa.lpa_type === 'property_financial' ? 'Property & Financial Affairs' : 'Health & Welfare';
  const safeName = escapeHtml(lpa.donor_full_name);

  const fullHtml = [
    '<!DOCTYPE html>',
    '<html lang="en">',
    '<head>',
    '<meta charset="UTF-8">',
    `<title>Lasting Power of Attorney - ${typeName} - ${safeName}</title>`,
    '<style>' + styles + '</style>',
    '</head>',
    '<body>',
    '<div class="fynla-header">',
    '<p>Generated using Fynla</p>',
    '</div>',
    content,
    '<div class="disclaimer">',
    'This document was prepared using Fynla and is a record of your Lasting Power of Attorney details.',
    ' To make your LPA legally valid, you must print and sign the official forms and register them',
    ' with the Office of the Public Guardian (gov.uk/lasting-power-of-attorney).',
    '</div>',
    '</body>',
    '</html>',
  ].join('\n');

  const printWindow = window.open('', '_blank', 'width=800,height=600');
  if (!printWindow) {
    alert('Please allow pop-ups to print the Lasting Power of Attorney');
    return;
  }

  // Use srcdoc-style approach: parse and adopt nodes safely
  const parser = new DOMParser();
  const parsed = parser.parseFromString(fullHtml, 'text/html');
  const adoptedHead = printWindow.document.adoptNode(parsed.head);
  const adoptedBody = printWindow.document.adoptNode(parsed.body);

  printWindow.document.head.replaceWith(adoptedHead);
  printWindow.document.body.replaceWith(adoptedBody);

  setTimeout(() => {
    printWindow.focus();
    printWindow.print();
  }, 500);
}
