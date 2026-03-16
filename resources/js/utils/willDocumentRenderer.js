/**
 * Will Document Renderer
 *
 * Generates a formatted HTML string for a UK will document.
 * Uses formal legal language with hybrid styling: Fynla header + serif legal body.
 *
 * Print approach follows the existing LetterToSpouse.vue pattern (buildLetterHtml / generatePDF).
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

function formatCurrency(amount) {
  if (!amount && amount !== 0) return '';
  return new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP', minimumFractionDigits: 0 }).format(amount);
}

export function renderWillDocument(data) {
  const name = escapeHtml(data.testator_full_name);
  const address = escapeHtml(data.testator_address);
  const occupation = escapeHtml(data.testator_occupation);
  const dob = formatDate(data.testator_date_of_birth);
  const executors = data.executors || [];
  const guardians = data.guardians || [];
  const gifts = data.specific_gifts || [];
  const residuary = data.residuary_estate || [];
  const survDays = data.survivorship_days || 28;

  let clauseNum = 1;
  const clause = () => clauseNum++;

  let html = '';

  // Title
  html += `<h1>LAST WILL AND TESTAMENT</h1>`;
  html += `<h2>of ${name}</h2>`;
  html += `<hr class="title-rule" />`;

  // Opening declaration + revocation
  html += `<p class="clause"><strong>${clause()}.</strong> I, <strong>${name}</strong>`;
  if (address) html += `, of ${address}`;
  if (occupation) html += `, ${occupation}`;
  html += `, born on ${dob}, HEREBY REVOKE all former wills and testamentary dispositions made by me and DECLARE this to be my last Will and Testament.</p>`;

  // Executors
  html += `<h3>APPOINTMENT OF EXECUTORS</h3>`;
  if (executors.length === 1) {
    const e = executors[0];
    html += `<p class="clause"><strong>${clause()}.</strong> I APPOINT <strong>${escapeHtml(e.name)}</strong>`;
    if (e.address) html += ` of ${escapeHtml(e.address)}`;
    if (e.relationship) html += ` (my ${escapeHtml(e.relationship)})`;
    html += ` to be the sole Executor and Trustee of this my Will.</p>`;
  } else if (executors.length >= 2) {
    const primary = executors[0];
    const backup = executors[1];
    html += `<p class="clause"><strong>${clause()}.</strong> I APPOINT <strong>${escapeHtml(primary.name)}</strong>`;
    if (primary.address) html += ` of ${escapeHtml(primary.address)}`;
    if (primary.relationship) html += ` (my ${escapeHtml(primary.relationship)})`;
    html += ` to be the Executor and Trustee of this my Will.</p>`;

    html += `<p class="clause"><strong>${clause()}.</strong> If the above-named Executor is unable or unwilling to act, I APPOINT <strong>${escapeHtml(backup.name)}</strong>`;
    if (backup.address) html += ` of ${escapeHtml(backup.address)}`;
    if (backup.relationship) html += ` (my ${escapeHtml(backup.relationship)})`;
    html += ` to be the substitute Executor and Trustee of this my Will.</p>`;
  }

  // Guardians
  if (guardians.length > 0) {
    html += `<h3>APPOINTMENT OF GUARDIANS</h3>`;
    const primary = guardians[0];
    html += `<p class="clause"><strong>${clause()}.</strong> If at the date of my death any of my children are under the age of eighteen years, I APPOINT <strong>${escapeHtml(primary.name)}</strong>`;
    if (primary.address) html += ` of ${escapeHtml(primary.address)}`;
    if (primary.relationship) html += ` (my ${escapeHtml(primary.relationship)})`;
    html += ` to be the Guardian of such children.</p>`;

    if (guardians.length >= 2) {
      const backup = guardians[1];
      html += `<p class="clause"><strong>${clause()}.</strong> If the above-named Guardian is unable or unwilling to act, I APPOINT <strong>${escapeHtml(backup.name)}</strong>`;
      if (backup.address) html += ` of ${escapeHtml(backup.address)}`;
      html += ` to be the substitute Guardian of my minor children.</p>`;
    }
  }

  // Specific gifts
  if (gifts.length > 0) {
    html += `<h3>SPECIFIC GIFTS AND LEGACIES</h3>`;
    html += `<p class="clause"><strong>${clause()}.</strong> I GIVE AND BEQUEATH the following:</p>`;
    html += `<div class="sub-clauses">`;
    gifts.forEach((gift, i) => {
      const letter = String.fromCharCode(97 + i);
      if (gift.type === 'cash' && gift.amount) {
        html += `<p>(${letter}) The sum of <strong>${formatCurrency(gift.amount)}</strong> to <strong>${escapeHtml(gift.beneficiary_name)}</strong>`;
      } else {
        html += `<p>(${letter}) <strong>${escapeHtml(gift.description || 'as described')}</strong> to <strong>${escapeHtml(gift.beneficiary_name)}</strong>`;
      }
      if (gift.conditions) html += `, ${escapeHtml(gift.conditions)}`;
      html += `.</p>`;
    });
    html += `</div>`;
  }

  // Survivorship clause
  html += `<h3>SURVIVORSHIP</h3>`;
  html += `<p class="clause"><strong>${clause()}.</strong> If any person who would otherwise be entitled to benefit under this my Will dies within ${survDays} days after the date of my death, such person shall be deemed to have predeceased me and shall not be entitled to any benefit under this my Will.</p>`;

  // Residuary estate
  if (residuary.length > 0) {
    html += `<h3>RESIDUARY ESTATE</h3>`;
    html += `<p class="clause"><strong>${clause()}.</strong> Subject to the payment of my debts, funeral and testamentary expenses, and the above specific gifts, I GIVE DEVISE AND BEQUEATH the whole of the rest and residue of my estate whatsoever and wheresoever:</p>`;
    html += `<div class="sub-clauses">`;
    residuary.forEach((b, i) => {
      const letter = String.fromCharCode(97 + i);
      html += `<p>(${letter}) As to <strong>${b.percentage}%</strong> thereof to <strong>${escapeHtml(b.beneficiary_name)}</strong> absolutely`;
      if (b.substitution_beneficiary) {
        html += `, but if the said ${escapeHtml(b.beneficiary_name)} shall predecease me, then to ${escapeHtml(b.substitution_beneficiary)}`;
      }
      html += `.</p>`;
    });
    html += `</div>`;
  }

  // Administrative powers
  html += `<h3>ADMINISTRATIVE POWERS</h3>`;
  html += `<p class="clause"><strong>${clause()}.</strong> My Executor and Trustee shall have full power to sell, call in, and convert into money any of my estate at such time or times and in such manner as my Executor shall in their absolute discretion think fit, and to postpone such sale, calling in, and conversion for so long as my Executor shall in their absolute discretion think fit without being responsible for any loss occasioned thereby.</p>`;

  html += `<p class="clause"><strong>${clause()}.</strong> My Executor and Trustee shall have power to invest trust monies in any investments that my Executor may in their absolute discretion think fit as if my Executor were the beneficial owner of such monies.</p>`;

  // Funeral wishes
  if (data.funeral_preference || data.funeral_wishes_notes) {
    html += `<h3>FUNERAL WISHES</h3>`;
    let funeralText = `<p class="clause"><strong>${clause()}.</strong> I express the wish (without imposing any binding obligation) that `;
    if (data.funeral_preference === 'burial') {
      funeralText += `my remains be buried`;
    } else if (data.funeral_preference === 'cremation') {
      funeralText += `my remains be cremated`;
    } else {
      funeralText += `my funeral arrangements be made`;
    }
    if (data.funeral_wishes_notes) {
      funeralText += `. ${escapeHtml(data.funeral_wishes_notes)}`;
    }
    funeralText += `.</p>`;
    html += funeralText;
  }

  // Digital assets
  if (data.digital_executor_name || data.digital_assets_instructions) {
    html += `<h3>DIGITAL ASSETS</h3>`;
    let digitalText = `<p class="clause"><strong>${clause()}.</strong> `;
    if (data.digital_executor_name) {
      digitalText += `I direct that <strong>${escapeHtml(data.digital_executor_name)}</strong> shall manage the administration of my digital assets, including but not limited to email accounts, social media profiles, online financial accounts, cloud storage, and any digital property. `;
    }
    if (data.digital_assets_instructions) {
      digitalText += escapeHtml(data.digital_assets_instructions);
    }
    digitalText += `</p>`;
    html += digitalText;
  }

  // Attestation
  html += `<h3>ATTESTATION</h3>`;
  const signedDate = data.signed_date ? formatDate(data.signed_date) : '_______ day of _________________ 20_____';
  html += `<p class="clause">IN WITNESS WHEREOF I have hereunto set my hand this ${signedDate}</p>`;
  html += `<div class="signature-block">`;
  if (data.signed_date) {
    html += `<div class="sig-line"><div class="line signed-name">${escapeHtml(data.testator_full_name)}</div><p>SIGNED by the above named <strong>${name}</strong></p></div>`;
  } else {
    html += `<div class="sig-line"><div class="line"></div><p>SIGNED by the above named <strong>${name}</strong></p></div>`;
  }
  html += `</div>`;

  // Witnesses
  const witnesses = data.witnesses || [];
  html += `<p class="witness-intro">Signed by the Testator in our joint presence and then by us in the presence of the Testator and of each other:</p>`;
  html += `<div class="witnesses">`;
  for (let i = 0; i < 2; i++) {
    const w = witnesses[i];
    html += `<div class="witness">`;
    html += `<p class="witness-label">WITNESS ${i + 1}</p>`;
    if (w) {
      html += `<div class="witness-field"><span>Signature:</span><div class="line signed-name">${escapeHtml(w.name)}</div></div>`;
      html += `<div class="witness-field"><span>Full Name:</span><div class="line filled">${escapeHtml(w.name)}</div></div>`;
      html += `<div class="witness-field"><span>Address:</span><div class="line filled">${escapeHtml(w.address || '')}</div></div>`;
      html += `<div class="witness-field"><span>Occupation:</span><div class="line filled">${escapeHtml(w.occupation || '')}</div></div>`;
      html += `<div class="witness-field"><span>Date:</span><div class="line filled">${w.date ? formatDate(w.date) : ''}</div></div>`;
    } else {
      html += `<div class="witness-field"><span>Signature:</span><div class="line"></div></div>`;
      html += `<div class="witness-field"><span>Full Name:</span><div class="line"></div></div>`;
      html += `<div class="witness-field"><span>Address:</span><div class="line"></div></div>`;
      html += `<div class="witness-field"><span>Occupation:</span><div class="line"></div></div>`;
      html += `<div class="witness-field"><span>Date:</span><div class="line"></div></div>`;
    }
    html += `</div>`;
  }
  html += `</div>`;

  return html;
}

export function getWillDocumentStyles() {
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
    .fynla-header img { height: 40px; margin-bottom: 8px; }
    .fynla-header p { font-family: 'Segoe UI', sans-serif; font-size: 10pt; color: #717171; margin: 0; }
    h1 { text-align: center; font-size: 18pt; letter-spacing: 2px; margin-bottom: 5px; font-weight: 700; }
    h2 { text-align: center; font-size: 14pt; font-weight: 400; margin-bottom: 10px; }
    h3 { font-size: 12pt; text-transform: uppercase; letter-spacing: 1px; margin-top: 25px; margin-bottom: 10px; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
    .title-rule { border: none; border-top: 2px solid #1F2A44; margin: 15px 0 25px; }
    .clause { text-indent: 0; margin-bottom: 12px; text-align: justify; }
    .sub-clauses { margin-left: 30px; }
    .sub-clauses p { margin-bottom: 8px; }
    .signature-block { margin: 40px 0 30px; }
    .sig-line { margin-bottom: 10px; }
    .sig-line .line { border-bottom: 1px solid #000; width: 300px; height: 40px; }
    .sig-line p { margin-top: 4px; font-size: 10pt; }
    .witness-intro { margin-top: 30px; font-size: 11pt; font-style: italic; }
    .witnesses { display: flex; gap: 40px; margin-top: 20px; }
    .witness { flex: 1; }
    .witness-label { font-weight: 700; font-size: 11pt; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 1px; }
    .witness-field { display: flex; align-items: flex-end; margin-bottom: 15px; gap: 8px; }
    .witness-field span { font-size: 10pt; white-space: nowrap; min-width: 80px; }
    .witness-field .line { flex: 1; border-bottom: 1px solid #000; min-height: 20px; }
    .signed-name { font-family: 'Brush Script MT', 'Segoe Script', cursive; font-size: 18pt; padding-left: 6px; }
    .filled { font-size: 10pt; padding-left: 6px; }
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
 * Opens a print window with the rendered will document.
 * Follows the same pattern as LetterToSpouse.vue generatePDF().
 */
export function printWillDocument(data) {
  const printWindow = window.open('', '_blank', 'width=800,height=600');
  if (!printWindow) {
    alert('Please allow pop-ups to print the will document');
    return;
  }

  const content = renderWillDocument(data);
  const styles = getWillDocumentStyles();
  const safeName = escapeHtml(data.testator_full_name);

  const fullHtml = [
    '<!DOCTYPE html>',
    '<html lang="en">',
    '<head>',
    '<meta charset="UTF-8">',
    '<title>Last Will and Testament - ' + safeName + '</title>',
    '<style>' + styles + '</style>',
    '</head>',
    '<body>',
    '<div class="fynla-header">',
    '<p>Generated using Fynla</p>',
    '</div>',
    content,
    '<div class="disclaimer">',
    'This document was prepared using Fynla\'s Will Builder tool and does not constitute legal advice.',
    ' This will is only legally valid once properly signed and witnessed in accordance with the Wills Act 1837.',
    '</div>',
    '</body>',
    '</html>',
  ].join('\n');

  // Using the same print-window approach as LetterToSpouse.vue
  // Content is fully escaped via escapeHtml() - safe for print rendering
  printWindow.document.open();
  printWindow.document.write(fullHtml); // eslint-disable-line no-restricted-syntax
  printWindow.document.close();

  setTimeout(() => {
    printWindow.focus();
    printWindow.print();
  }, 500);
}
