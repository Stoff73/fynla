import { currencyMixin } from '@/mixins/currencyMixin';
import logoImage from '@/assets/images/logoTransparent.png';

/**
 * Mixin for printing/PDF export of plans.
 * Follows the Letter to Spouse pattern for print window generation.
 * All user content is escaped via escapeHtml() before insertion.
 */
export const planPrintMixin = {
  mixins: [currencyMixin],

  data() {
    return {
      generatingPdf: false,
    };
  },

  computed: {
    logoUrl() {
      return logoImage;
    },
  },

  methods: {
    printPlan(plan, title) {
      if (!plan) return;
      this.generatingPdf = true;

      const printWindow = window.open('', '_blank', 'width=800,height=600');
      if (!printWindow) {
        alert('Please allow pop-ups to print the plan');
        this.generatingPdf = false;
        return;
      }

      const html = this.buildPlanHtml(plan, title);

      const doc = printWindow.document;
      doc.open();
      doc.write(html);
      doc.close();

      const triggerPrint = () => {
        printWindow.print();
        printWindow.onafterprint = () => {
          printWindow.close();
        };
        if (this.closeTimeout) clearTimeout(this.closeTimeout);
        this.closeTimeout = setTimeout(() => {
          if (!printWindow.closed) {
            printWindow.close();
          }
        }, 1000);
        this.generatingPdf = false;
      };

      const logos = printWindow.document.querySelectorAll('.logo, .page-header-logo');
      if (logos.length > 0) {
        let loadCount = 0;
        let imageHandled = false;
        const handleAllLoaded = () => {
          if (!imageHandled) {
            imageHandled = true;
            setTimeout(triggerPrint, 250);
          }
        };
        logos.forEach(img => {
          const onDone = () => {
            loadCount++;
            if (loadCount >= logos.length) handleAllLoaded();
          };
          img.addEventListener('load', onDone);
          img.addEventListener('error', onDone);
        });
        setTimeout(() => {
          if (!imageHandled) handleAllLoaded();
        }, 3000);
      } else {
        setTimeout(triggerPrint, 250);
      }
    },

    escapeHtml(str) {
      if (!str) return '';
      const s = String(str);
      return s
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    },

    fmtCurrency(val) {
      if (val === null || val === undefined) return 'N/A';
      return new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(val);
    },

    fmtDate(dateStr) {
      if (!dateStr) return 'N/A';
      const date = new Date(dateStr);
      return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
    },

    fmtPercentage(val) {
      if (val === null || val === undefined) return 'N/A';
      return Math.round(val * 10) / 10 + '%';
    },

    isEstatePlan(plan) {
      const summary = plan.executive_summary || {};
      return !!(summary.greeting || plan.personal_information || plan.current_situation?.iht_summary);
    },

    buildPlanHtml(plan, title) {
      const date = new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
      const userName = plan.metadata?.user_name || '';
      const summary = plan.executive_summary || {};
      const conclusion = plan.conclusion || {};
      const enabledActions = (plan.actions || []).filter(a => a.enabled);
      const disabledActions = (plan.actions || []).filter(a => !a.enabled);
      const whatIf = plan.what_if || {};
      const isEstate = this.isEstatePlan(plan);

      return `
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>${this.escapeHtml(title)}</title>
  <style>
    @page {
      size: A4;
      margin: 0;
    }

    @media print {
      html, body {
        margin: 0;
        padding: 0;
      }
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      font-size: 11px;
      line-height: 1.4;
      color: #1f2937;
      background: white;
      padding: 0;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
      position: relative;
      min-height: 100vh;
    }

    /* Running page header — repeats on every printed page */
    .page-header {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 10mm 15mm 4px 15mm;
      border-bottom: 1px solid #e2e8f0;
      background: white;
      z-index: 999;
    }

    .page-header-logo {
      height: 26px;
      width: auto;
    }

    .page-header-text {
      font-size: 9px;
      color: #94a3b8;
      letter-spacing: 0.3px;
    }

    /* Content wrapper — clears fixed header/footer */
    .plan-content {
      padding: 20mm 15mm 16mm 15mm;
    }

    /* Title page */
    .title-page {
      position: relative;
      padding: 10mm 15mm 0 15mm;
      page-break-after: always;
      break-after: page;
    }

    .title-page .logo {
      position: absolute;
      top: 10mm;
      right: 15mm;
      height: 110px;
      width: auto;
    }

    .header-content {
      text-align: center;
      padding-top: 280px;
    }

    .header-content h1 {
      font-size: 28px;
      font-weight: 700;
      color: #0f172a;
      margin-bottom: 8px;
    }

    .header-content .subtitle {
      font-size: 13px;
      color: #64748b;
      margin-bottom: 4px;
    }

    .header-content .date {
      font-size: 12px;
      color: #64748b;
    }

    .section {
      margin-bottom: 16px;
      page-break-inside: auto;
    }

    .section-title {
      font-size: 15px;
      font-weight: 700;
      color: #0f172a;
      padding-bottom: 6px;
      margin-bottom: 12px;
      border-bottom: 2px solid #e2e8f0;
      page-break-after: avoid;
      page-break-inside: avoid;
    }

    .section-subtitle {
      font-size: 10px;
      color: #64748b;
      margin-top: -8px;
      margin-bottom: 12px;
      page-break-after: avoid;
      page-break-inside: avoid;
    }

    .narrative {
      font-size: 11px;
      color: #374151;
      line-height: 1.6;
      white-space: pre-wrap;
    }

    .subsection-title {
      font-size: 12px;
      font-weight: 600;
      color: #374151;
      margin-bottom: 6px;
      margin-top: 14px;
      page-break-after: avoid;
    }

    .action-item {
      display: flex;
      align-items: flex-start;
      margin-bottom: 10px;
      break-inside: avoid;
    }

    .action-number {
      background: #f3f4f6;
      color: #374151;
      width: 18px;
      height: 18px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 10px;
      font-weight: 700;
      margin-right: 8px;
      flex-shrink: 0;
    }

    .action-text {
      font-size: 11px;
      color: #374151;
      line-height: 1.4;
    }

    .action-detail {
      font-size: 10px;
      color: #64748b;
      margin-top: 2px;
    }

    .badge {
      display: inline-block;
      padding: 2px 8px;
      border-radius: 10px;
      font-size: 9px;
      font-weight: 600;
    }

    .badge-red { background: #fee2e2; color: #991b1b; }
    .badge-blue { background: #dbeafe; color: #1e40af; }
    .badge-gray { background: #f3f4f6; color: #374151; }
    .badge-green { background: #dcfce7; color: #166534; }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 11px;
      margin-top: 6px;
      margin-bottom: 8px;
    }

    th, td {
      border: 1px solid #e5e7eb;
      padding: 6px 10px;
      text-align: left;
    }

    th {
      background: #f9fafb;
      font-weight: 600;
      color: #374151;
    }

    .conclusion-box {
      background: #f0f9ff;
      border: 1px solid #bae6fd;
      border-radius: 6px;
      padding: 12px;
      margin-top: 12px;
      font-size: 11px;
      line-height: 1.6;
      color: #374151;
    }

    .disabled-actions {
      margin-top: 12px;
    }

    .disabled-action {
      font-size: 10px;
      color: #6b7280;
      margin-bottom: 4px;
      padding-left: 12px;
      position: relative;
    }

    .disabled-action::before {
      content: '\\2014';
      position: absolute;
      left: 0;
    }

    .footer {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 9px;
      color: #94a3b8;
      padding: 4px 15mm 8mm 15mm;
      border-top: 1px solid #e2e8f0;
      background: white;
      z-index: 1000;
    }

    .footer-left {
      text-align: left;
    }

    .footer-right {
      text-align: right;
      font-size: 10px;
      color: #64748b;
    }

    /* Estate Plan Styles */
    .iht-table { font-size: 10px; }
    .iht-table th { font-size: 10px; padding: 5px 8px; }
    .iht-table td { padding: 4px 8px; font-size: 10px; }
    .iht-owner-header td { font-weight: 700; background: #f9fafb; }
    .iht-asset-row td:first-child { padding-left: 20px; }
    .iht-subtotal td { font-weight: 600; border-top: 1px solid #d1d5db; }
    .iht-total td { font-weight: 700; border-top: 2px solid #9ca3af; }
    .iht-allowance td { background: #f0fdf4; }
    .iht-liability td { color: #b91c1c; font-weight: 700; }

    .metric-grid { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
    .metric-card {
      flex: 1 1 45%; min-width: 140px;
      background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px;
    }
    .metric-card .metric-label { font-size: 9px; color: #6b7280; margin-bottom: 2px; }
    .metric-card .metric-value { font-size: 12px; font-weight: 700; color: #1f2937; }

    .info-grid { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 8px; }
    .info-card {
      flex: 1 1 45%; min-width: 200px;
      background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px 12px;
    }
    .info-card h4 {
      font-size: 11px; font-weight: 600; color: #374151;
      margin-bottom: 6px; padding-bottom: 4px; border-bottom: 1px solid #e5e7eb;
    }
    .info-card .info-row { display: flex; justify-content: space-between; font-size: 10px; padding: 2px 0; }
    .info-card .info-label { color: #6b7280; }
    .info-card .info-value { font-weight: 600; color: #1f2937; }

    .guidance-list { list-style: decimal; padding-left: 16px; margin-top: 4px; }
    .guidance-list li { font-size: 10px; color: #374151; margin-bottom: 3px; line-height: 1.4; }

    .gifting-grid { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
    .gifting-cell {
      flex: 1 1 22%; min-width: 100px;
      background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 4px; padding: 6px 8px;
    }
    .gifting-cell .gifting-label { font-size: 9px; color: #6b7280; }
    .gifting-cell .gifting-value { font-size: 11px; font-weight: 600; color: #1f2937; }
  </style>
</head>
<body>
  <!-- Running page header (repeats on every printed page) -->
  <div class="page-header">
    <img src="${this.logoUrl}" alt="Fynla" class="page-header-logo" />
    <span class="page-header-text">${this.escapeHtml(title)} &bull; ${this.escapeHtml(userName)}</span>
  </div>

  <!-- Running page footer (repeats on every printed page) -->
  <div class="footer">
    <div class="footer-left">
      This document was generated by Fynla Financial Planning Software &bull; www.fynla.org &bull; This is not financial advice
    </div>
    <div class="footer-right">
      Prepared by ${this.escapeHtml(userName)}
    </div>
  </div>

  <!-- Title page -->
  <div class="title-page">
    <img src="${this.logoUrl}" alt="Fynla" class="logo" />
    <div class="header-content">
      <h1>${this.escapeHtml(title)}</h1>
      <div class="subtitle">Prepared for ${this.escapeHtml(userName)}</div>
      <div class="date">${this.escapeHtml(date)}</div>
    </div>
  </div>

  <!-- Plan content (padded to clear running header/footer) -->
  <div class="plan-content">
    <!-- Executive Summary -->
    <div class="section">
      <div class="section-title">Executive Summary</div>
      <div class="section-subtitle">Your personalised plan overview</div>
      ${isEstate ? this.buildEstateExecutiveSummaryHtml(summary) : `<div class="narrative">${this.escapeHtml(summary.narrative || '')}</div>`}
    </div>

    ${isEstate && plan.personal_information ? this.buildEstatePersonalInformationHtml(plan.personal_information) : ''}

    <!-- Current Situation -->
    ${isEstate ? this.buildEstateCurrentSituationHtml(plan.current_situation) : this.buildCurrentSituationHtml(plan.current_situation)}

    <!-- Recommended Actions -->
    ${isEstate ? this.buildEstateActionsHtml(enabledActions, disabledActions) : this.buildActionsHtml(enabledActions, disabledActions)}

    <!-- Projected Outcomes -->
    ${isEstate ? this.buildEstateWhatIfHtml(whatIf, enabledActions) : this.buildWhatIfHtml(whatIf)}

    <!-- Conclusion -->
    ${this.buildConclusionHtml(conclusion)}
  </div>
</body>
</html>`;
    },

    // ── Current Situation ──────────────────────────────────────────────

    buildCurrentSituationHtml(situation) {
      if (!situation) return '';

      let content = '';

      // Protection: Coverage Analysis
      if (situation.coverage_analysis) {
        content += this.buildCoverageAnalysisHtml(situation.coverage_analysis);
      }

      // Protection: Existing Policies
      if (situation.current_coverage) {
        content += this.buildPoliciesHtml(situation.current_coverage);
      }

      // Protection: Debt Breakdown
      if (situation.debt_breakdown && situation.debt_breakdown.total > 0) {
        content += this.buildDebtHtml(situation.debt_breakdown);
      }

      // Investment: Accounts
      if (situation.investment_accounts && situation.investment_accounts.length) {
        content += this.buildInvestmentAccountsHtml(situation.investment_accounts, situation.total_investment_value);
      }
      if (situation.savings_accounts && situation.savings_accounts.length) {
        content += this.buildSavingsAccountsHtml(situation.savings_accounts, situation.total_savings_value);
      }

      // Retirement: Pensions
      if (situation.dc_pensions && situation.dc_pensions.length) {
        content += this.buildDCPensionsHtml(situation.dc_pensions);
      }
      if (situation.db_pensions && situation.db_pensions.length) {
        content += this.buildDBPensionsHtml(situation.db_pensions);
      }
      if (situation.state_pension) {
        content += this.buildStatePensionHtml(situation.state_pension);
      }

      // Goals: Details + Progress
      if (situation.goal_details) {
        content += this.buildGoalSituationHtml(situation);
      }

      // Key indicators (emergency fund, ISA, retirement summary)
      content += this.buildSituationIndicatorsHtml(situation);

      if (!content) return '';

      return `
      <div class="section">
        <div class="section-title">Current Situation</div>
        <div class="section-subtitle">Your current financial position</div>
        ${content}
      </div>`;
    },

    buildCoverageAnalysisHtml(analysis) {
      const types = [
        { key: 'life_insurance', label: 'Life Insurance', suffix: '' },
        { key: 'critical_illness', label: 'Critical Illness', suffix: '' },
        { key: 'income_protection', label: 'Income Protection', suffix: '/month' },
      ];

      const rows = types.map(t => {
        const data = analysis[t.key];
        if (!data) return '';

        const pct = Math.min(100, data.coverage_percentage || 0);
        const barColor = pct >= 80 ? '#22c55e' : pct >= 40 ? '#3b82f6' : '#ef4444';
        const gapColor = (data.gap || 0) > 0 ? '#b91c1c' : '#15803d';
        const statusColors = this.getStatusColors(data.status);

        return `
          <tr style="page-break-inside: avoid;">
            <td style="font-weight: 500;">${this.escapeHtml(t.label)}</td>
            <td>${this.fmtCurrency(data.need || 0)}${t.suffix}</td>
            <td>${this.fmtCurrency(data.coverage || 0)}${t.suffix}</td>
            <td style="color: ${gapColor}; font-weight: 600;">${this.fmtCurrency(data.gap || 0)}${t.suffix}</td>
            <td>
              <span style="display: inline-block; padding: 1px 6px; border-radius: 8px; font-size: 9px; font-weight: 600; background: ${statusColors.bg}; color: ${statusColors.text};">
                ${this.escapeHtml(data.status || 'Unknown')}
              </span>
            </td>
          </tr>
          <tr style="page-break-inside: avoid;">
            <td colspan="5" style="border-top: none; padding: 0 10px 6px;">
              <div style="background: #e5e7eb; border-radius: 4px; height: 6px; width: 100%;">
                <div style="background: ${barColor}; border-radius: 4px; height: 6px; width: ${pct}%;"></div>
              </div>
            </td>
          </tr>
        `;
      }).join('');

      if (!rows) return '';

      return `
        <div class="subsection-title">Coverage Analysis</div>
        <table>
          <thead>
            <tr>
              <th>Type</th>
              <th>Need</th>
              <th>Have</th>
              <th>Gap</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            ${rows}
          </tbody>
        </table>
      `;
    },

    getStatusColors(status) {
      const s = (status || '').toLowerCase();
      if (s === 'excellent' || s === 'good' || s === 'adequate')
        return { bg: '#dcfce7', text: '#166534' };
      if (s === 'fair')
        return { bg: '#dbeafe', text: '#1e40af' };
      return { bg: '#fee2e2', text: '#991b1b' };
    },

    buildPoliciesHtml(coverage) {
      const policies = [];

      (coverage.life_insurance?.policies || []).forEach(p => {
        policies.push({ type: 'Life Insurance' + (p.type ? ' - ' + p.type : ''), provider: p.provider, value: p.sum_assured || 0, suffix: '' });
      });
      (coverage.critical_illness?.policies || []).forEach(p => {
        policies.push({ type: 'Critical Illness' + (p.type ? ' - ' + p.type : ''), provider: p.provider, value: p.sum_assured || 0, suffix: '' });
      });
      (coverage.income_protection?.policies || []).forEach(p => {
        policies.push({ type: 'Income Protection', provider: p.provider, value: p.benefit_amount || 0, suffix: '/month' });
      });

      if (policies.length === 0) return '';

      const rows = policies.map(p => `
        <tr>
          <td style="font-weight: 500;">${this.escapeHtml(p.type)}</td>
          <td>${this.escapeHtml(p.provider || '')}</td>
          <td style="text-align: right; font-weight: 600;">${this.fmtCurrency(p.value)}${p.suffix}</td>
        </tr>
      `).join('');

      const premiumRow = coverage.total_monthly_premiums > 0 ? `
        <tr style="border-top: 2px solid #d1d5db;">
          <td colspan="2" style="font-weight: 600;">Total Monthly Premiums</td>
          <td style="text-align: right; font-weight: 700;">${this.fmtCurrency(coverage.total_monthly_premiums)}</td>
        </tr>
      ` : '';

      return `
        <div class="subsection-title" style="margin-top: 16px;">Existing Policies</div>
        <table>
          <thead>
            <tr><th>Policy</th><th>Provider</th><th style="text-align: right;">Cover Amount</th></tr>
          </thead>
          <tbody>${rows}${premiumRow}</tbody>
        </table>
      `;
    },

    buildDebtHtml(debt) {
      return `
        <div class="subsection-title" style="margin-top: 16px;">Debt Exposure</div>
        <table>
          <tbody>
            <tr>
              <td>Mortgage</td>
              <td style="text-align: right; font-weight: 500;">${this.fmtCurrency(debt.mortgage || 0)}</td>
            </tr>
            <tr>
              <td>Other Debts</td>
              <td style="text-align: right; font-weight: 500;">${this.fmtCurrency(debt.other || 0)}</td>
            </tr>
            <tr style="border-top: 2px solid #d1d5db;">
              <td style="font-weight: 600;">Total Debt</td>
              <td style="text-align: right; font-weight: 700;">${this.fmtCurrency(debt.total || 0)}</td>
            </tr>
          </tbody>
        </table>
      `;
    },

    buildInvestmentAccountsHtml(accounts, total) {
      const rows = accounts.map(a => `
        <tr>
          <td style="font-weight: 500;">${this.escapeHtml(a.name || 'Unknown')}</td>
          <td style="font-size: 10px; color: #64748b;">${this.escapeHtml(a.provider || '')}</td>
          <td style="text-align: right; font-weight: 600;">${this.fmtCurrency(a.value || 0)}</td>
        </tr>
      `).join('');

      const totalRow = total !== undefined ? `
        <tr style="border-top: 2px solid #d1d5db;">
          <td colspan="2" style="font-weight: 600;">Total Investment Value</td>
          <td style="text-align: right; font-weight: 700;">${this.fmtCurrency(total)}</td>
        </tr>
      ` : '';

      return `
        <div class="subsection-title">Investment Accounts</div>
        <table>
          <thead><tr><th>Account</th><th>Provider</th><th style="text-align: right;">Value</th></tr></thead>
          <tbody>${rows}${totalRow}</tbody>
        </table>
      `;
    },

    buildSavingsAccountsHtml(accounts, total) {
      const rows = accounts.map(a => `
        <tr>
          <td style="font-weight: 500;">${this.escapeHtml(a.institution || 'Unknown')}</td>
          <td style="font-size: 10px; color: #64748b;">${a.interest_rate ? a.interest_rate + '% interest' : ''}</td>
          <td style="text-align: right; font-weight: 600;">${this.fmtCurrency(a.balance || 0)}</td>
        </tr>
      `).join('');

      const totalRow = total !== undefined ? `
        <tr style="border-top: 2px solid #d1d5db;">
          <td colspan="2" style="font-weight: 600;">Total Savings Value</td>
          <td style="text-align: right; font-weight: 700;">${this.fmtCurrency(total)}</td>
        </tr>
      ` : '';

      return `
        <div class="subsection-title">Savings Accounts</div>
        <table>
          <thead><tr><th>Account</th><th>Details</th><th style="text-align: right;">Balance</th></tr></thead>
          <tbody>${rows}${totalRow}</tbody>
        </table>
      `;
    },

    buildDCPensionsHtml(pensions) {
      const rows = pensions.map(p => {
        const contributions = [
          p.monthly_contribution ? this.fmtCurrency(p.monthly_contribution) + '/month' : null,
          p.employer_contribution ? '+ ' + this.fmtCurrency(p.employer_contribution) + ' employer' : null,
        ].filter(Boolean).join(' ');

        return `
          <tr>
            <td style="font-weight: 500;">${this.escapeHtml(p.scheme_name || 'Unknown')}</td>
            <td style="font-size: 10px; color: #64748b;">${this.escapeHtml(p.provider || '')}</td>
            <td style="font-size: 10px; color: #64748b;">${this.escapeHtml(contributions)}</td>
            <td style="text-align: right; font-weight: 600;">${this.fmtCurrency(p.current_value || 0)}</td>
          </tr>
        `;
      }).join('');

      return `
        <div class="subsection-title">Defined Contribution Pensions</div>
        <table>
          <thead><tr><th>Scheme</th><th>Provider</th><th>Contributions</th><th style="text-align: right;">Value</th></tr></thead>
          <tbody>${rows}</tbody>
        </table>
      `;
    },

    buildDBPensionsHtml(pensions) {
      const rows = pensions.map(p => `
        <tr>
          <td style="font-weight: 500;">${this.escapeHtml(p.scheme_name || 'Unknown')}</td>
          <td>${p.normal_retirement_age ? 'Age ' + p.normal_retirement_age : ''}</td>
          <td style="text-align: right; font-weight: 600;">${this.fmtCurrency(p.projected_annual_pension || 0)}/year</td>
        </tr>
      `).join('');

      return `
        <div class="subsection-title">Defined Benefit Pensions</div>
        <table>
          <thead><tr><th>Scheme</th><th>Retirement Age</th><th style="text-align: right;">Annual Pension</th></tr></thead>
          <tbody>${rows}</tbody>
        </table>
      `;
    },

    buildStatePensionHtml(sp) {
      const rows = [
        ['Weekly Amount', this.fmtCurrency(sp.weekly_amount || 0)],
        ['Annual Amount', this.fmtCurrency(sp.annual_amount || 0)],
        ['National Insurance Years', String(sp.ni_years ?? 'N/A')],
      ];
      if (sp.state_pension_age) {
        rows.push(['State Pension Age', String(sp.state_pension_age)]);
      }

      return `
        <div class="subsection-title">State Pension</div>
        <table>
          <tbody>
            ${rows.map(([label, value]) => `
              <tr>
                <td>${this.escapeHtml(label)}</td>
                <td style="font-weight: 600; text-align: right;">${this.escapeHtml(value)}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      `;
    },

    buildGoalSituationHtml(situation) {
      const d = situation.goal_details || {};
      const p = situation.progress || {};

      const detailRows = [
        ['Goal Name', d.name || 'Unnamed Goal'],
        ['Target Amount', this.fmtCurrency(d.target_amount || 0)],
        ['Current Amount', this.fmtCurrency(d.current_amount || 0)],
      ];
      if (d.monthly_contribution > 0) detailRows.push(['Monthly Contribution', this.fmtCurrency(d.monthly_contribution)]);
      if (d.target_date) detailRows.push(['Target Date', this.fmtDate(d.target_date)]);

      let html = `
        <div class="subsection-title">Goal Details</div>
        <table>
          <tbody>
            ${detailRows.map(([label, value]) => `
              <tr>
                <td>${this.escapeHtml(label)}</td>
                <td style="font-weight: 600; text-align: right;">${this.escapeHtml(String(value))}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      `;

      if (p.progress_percentage !== undefined) {
        const pct = Math.min(100, Math.round(p.progress_percentage || 0));
        const remaining = Math.max(0, (d.target_amount || 0) - (d.current_amount || 0));
        const barColor = pct >= 75 ? '#22c55e' : pct >= 50 ? '#3b82f6' : '#9ca3af';

        html += `
          <div class="subsection-title">Progress</div>
          <div style="margin-bottom: 8px;">
            <div style="display: flex; justify-content: space-between; font-size: 10px; color: #374151; margin-bottom: 4px;">
              <span>${pct}% complete</span>
              <span style="font-weight: 500;">${this.fmtCurrency(remaining)} remaining</span>
            </div>
            <div style="background: #e5e7eb; border-radius: 4px; height: 8px;">
              <div style="background: ${barColor}; border-radius: 4px; height: 8px; width: ${pct}%;"></div>
            </div>
          </div>
          <table>
            <tbody>
              <tr>
                <td>On Track</td>
                <td style="color: ${p.is_on_track ? '#15803d' : '#b91c1c'}; font-weight: 600; text-align: right;">${p.is_on_track ? 'Yes' : 'No'}</td>
              </tr>
              ${p.months_remaining !== null && p.months_remaining !== undefined ? `<tr><td>Months Remaining</td><td style="text-align: right;">${p.months_remaining}</td></tr>` : ''}
              ${p.estimated_completion_date ? `<tr><td>Estimated Completion</td><td style="text-align: right;">${this.fmtDate(p.estimated_completion_date)}</td></tr>` : ''}
            </tbody>
          </table>
        `;
      }

      return html;
    },

    buildSituationIndicatorsHtml(situation) {
      const rows = [];

      if (situation.emergency_fund) {
        rows.push(['Emergency Fund', Math.round(situation.emergency_fund.runway_months || 0) + ' months']);
      }
      if (situation.isa_allowance) {
        rows.push(['ISA Used', this.fmtCurrency(situation.isa_allowance.used || 0)]);
        rows.push(['ISA Remaining', this.fmtCurrency(situation.isa_allowance.remaining || 0)]);
      }
      if (situation.summary) {
        if (situation.summary.years_to_retirement !== undefined) {
          rows.push(['Years to Retirement', String(situation.summary.years_to_retirement ?? 'N/A')]);
        }
        if (situation.summary.income_gap !== undefined) {
          rows.push(['Income Gap', this.fmtCurrency(Math.max(0, situation.summary.income_gap || 0)) + '/year']);
        }
        if (situation.summary.total_dc_value !== undefined) {
          rows.push(['Total Defined Contribution Value', this.fmtCurrency(situation.summary.total_dc_value || 0)]);
        }
      }
      if (situation.affordability) {
        if (situation.affordability.category) {
          const cat = situation.affordability.category;
          rows.push(['Affordability', cat.charAt(0).toUpperCase() + cat.slice(1)]);
        }
        if (situation.affordability.monthly_surplus !== undefined) {
          rows.push(['Monthly Surplus', this.fmtCurrency(situation.affordability.monthly_surplus)]);
        }
      }

      if (rows.length === 0) return '';

      return `
        <table style="margin-top: 12px;">
          <tbody>
            ${rows.map(([label, value]) => `
              <tr>
                <td>${this.escapeHtml(label)}</td>
                <td style="font-weight: 600; text-align: right;">${this.escapeHtml(value)}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      `;
    },

    // ── Estate Plan Builders ──────────────────────────────────────────

    buildEstateExecutiveSummaryHtml(summary) {
      if (!summary) return '';
      let html = '';

      if (summary.greeting) {
        html += `<div style="font-size: 12px; font-weight: 600; color: #1f2937; margin-bottom: 8px;">${this.escapeHtml(summary.greeting)}</div>`;
      }
      if (summary.opening) {
        html += `<div class="narrative" style="margin-bottom: 8px;">${this.escapeHtml(summary.opening)}</div>`;
      }
      if (summary.introduction) {
        html += `<div class="narrative" style="margin-bottom: 10px;">${this.escapeHtml(summary.introduction)}</div>`;
      }

      if (summary.actions_summary && summary.actions_summary.length > 0) {
        const rows = summary.actions_summary.map(a => {
          const priorityMap = { critical: 'badge-red', high: 'badge-blue', medium: 'badge-gray', low: 'badge-green' };
          const badgeClass = priorityMap[a.priority] || 'badge-gray';
          const priorityLabel = (a.priority || 'medium').charAt(0).toUpperCase() + (a.priority || 'medium').slice(1);
          return `<tr><td style="font-weight: 500;">${this.escapeHtml(a.title)}</td><td style="text-align: center;"><span class="badge ${badgeClass}">${this.escapeHtml(priorityLabel)}</span></td></tr>`;
        }).join('');

        html += `
          <div class="subsection-title">Key Actions</div>
          <table>
            <thead><tr><th>Action</th><th style="text-align: center;">Priority</th></tr></thead>
            <tbody>${rows}</tbody>
          </table>
        `;
        if (summary.total_actions) {
          html += `<div style="font-size: 10px; color: #64748b; margin-top: 2px;">${summary.total_actions} total recommendations in this plan</div>`;
        }
      }

      if (summary.closing) {
        html += `<div class="narrative" style="margin-top: 8px;">${this.escapeHtml(summary.closing)}</div>`;
      }
      return html;
    },

    buildEstatePersonalInformationHtml(info) {
      if (!info) return '';

      const personalRows = [
        ['Full Name', info.full_name],
        ['Date of Birth', this.fmtDate(info.date_of_birth)],
        ['Age', info.age],
        ['Marital Status', info.marital_status],
      ].filter(([, v]) => v !== null && v !== undefined && v !== '');

      const familyRows = [];
      if (info.spouse_name) familyRows.push(['Spouse', info.spouse_name]);
      if (info.children && info.children.length > 0) {
        familyRows.push(['Children', info.children.join(', ')]);
      } else {
        familyRows.push(['Children', 'None']);
      }

      const financialRows = [
        ['Gross Income', this.fmtCurrency(info.gross_income)],
        ['Net Income', this.fmtCurrency(info.net_income)],
        ['Annual Expenditure', this.fmtCurrency(info.annual_expenditure)],
        ['Disposable Income', `${this.fmtCurrency(info.disposable_income)} / year (${this.fmtCurrency(info.monthly_disposable)} / month)`],
      ].filter(([, v]) => v && v !== 'N/A');

      const estateRows = [];
      if (info.estimated_age_at_death) estateRows.push(['Estimated Age at Death', info.estimated_age_at_death]);
      if (info.years_to_death) estateRows.push(['Years to Planning Horizon', info.years_to_death]);
      if (info.marital_status_iht) {
        const ihtStatusMap = { married: 'Married / Civil Partnership', widowed: 'Widowed', single: 'Single', civil_partnership: 'Civil Partnership' };
        estateRows.push(['Inheritance Tax Status', ihtStatusMap[info.marital_status_iht] || info.marital_status_iht]);
      }
      estateRows.push(['Has Will', info.has_will ? 'Yes' : 'No']);

      const buildCard = (title, rows) => {
        if (rows.length === 0) return '';
        const rowsHtml = rows.map(([label, value]) => `
          <div class="info-row">
            <span class="info-label">${this.escapeHtml(String(label))}</span>
            <span class="info-value">${this.escapeHtml(String(value))}</span>
          </div>
        `).join('');
        return `<div class="info-card"><h4>${this.escapeHtml(title)}</h4>${rowsHtml}</div>`;
      };

      return `
      <div class="section">
        <div class="section-title">Personal Information</div>
        <div class="section-subtitle">Your personal and financial overview</div>
        <div class="info-grid">
          ${buildCard('Personal Details', personalRows)}
          ${buildCard('Family', familyRows)}
          ${buildCard('Financial Overview', financialRows)}
          ${buildCard('Estate Profile', estateRows)}
        </div>
      </div>`;
    },

    buildEstateCurrentSituationHtml(situation) {
      if (!situation) return '';
      const summary = situation.iht_summary;
      if (!summary) return '';

      const cur = summary.current || {};
      const proj = summary.projected || {};
      const estimatedAge = proj.estimated_age_at_death || 0;
      const showSpouse = situation.has_linked_spouse && !!situation.assets_breakdown?.spouse;
      const isWidowed = summary.is_widowed || false;

      const ownershipLabel = (item) => {
        if (item.is_joint) {
          const pct = Math.round(parseFloat(item.ownership_percentage) || 50);
          return pct === 50 ? ' (Joint)' : ` (Joint - ${pct}%)`;
        }
        if (item.ownership_type === 'tenants_in_common') {
          return item.ownership_percentage ? ` (Tenancy in Common - ${item.ownership_percentage}%)` : ' (Tenancy in Common)';
        }
        return '';
      };

      const ASSET_TYPES = [
        { key: 'property', label: 'Property' },
        { key: 'investment', label: 'Investment' },
        { key: 'cash', label: 'Cash/Savings' },
        { key: 'business', label: 'Business' },
        { key: 'chattel', label: 'Valuable' },
      ];

      const buildOwnerAssets = (ownerData) => {
        if (!ownerData?.assets) return '';
        let rows = '';
        rows += `<tr class="iht-owner-header"><td>${this.escapeHtml(ownerData.name)}'s Assets</td><td style="text-align: right;">${this.fmtCurrency(ownerData.total)}</td><td style="text-align: right;">${this.fmtCurrency(ownerData.projected_total)}</td></tr>`;

        ASSET_TYPES.forEach(({ key, label }) => {
          (ownerData.assets[key] || []).forEach(asset => {
            const joint = ownershipLabel(asset);
            rows += `<tr class="iht-asset-row"><td>${this.escapeHtml(label)}: ${this.escapeHtml(asset.name)}${this.escapeHtml(joint)}</td><td style="text-align: right;">${this.fmtCurrency(asset.value)}</td><td style="text-align: right;">${this.fmtCurrency(asset.projected_value ?? asset.value)}</td></tr>`;
          });
        });

        if (showSpouse) {
          rows += `<tr class="iht-subtotal"><td style="padding-left: 20px;">Subtotal</td><td style="text-align: right;">${this.fmtCurrency(ownerData.total)}</td><td style="text-align: right;">${this.fmtCurrency(ownerData.projected_total)}</td></tr>`;
        }
        return rows;
      };

      const buildOwnerLiabilities = (ownerData) => {
        if (!ownerData?.liabilities) return '';
        let rows = '';
        let projTotal = 0;
        (ownerData.liabilities.mortgages || []).forEach(m => {
          projTotal += (m.projected_balance !== undefined && m.projected_balance !== null) ? m.projected_balance : (m.outstanding_balance || 0);
        });
        (ownerData.liabilities.other_liabilities || []).forEach(l => {
          projTotal += (l.projected_balance !== undefined && l.projected_balance !== null) ? l.projected_balance : (l.current_balance || 0);
        });

        rows += `<tr class="iht-owner-header"><td>${this.escapeHtml(ownerData.name)}'s Liabilities</td><td style="text-align: right;">(${this.fmtCurrency(ownerData.total)})</td><td style="text-align: right;">(${this.fmtCurrency(projTotal)})</td></tr>`;

        (ownerData.liabilities.mortgages || []).forEach(m => {
          const joint = ownershipLabel(m);
          const projBal = (m.projected_balance !== undefined && m.projected_balance !== null) ? m.projected_balance : (m.outstanding_balance || 0);
          rows += `<tr class="iht-asset-row"><td>Mortgage: ${this.escapeHtml(m.property_address || 'Unknown')}${this.escapeHtml(joint)}</td><td style="text-align: right;">(${this.fmtCurrency(m.outstanding_balance)})</td><td style="text-align: right;">(${this.fmtCurrency(projBal)})</td></tr>`;
        });

        (ownerData.liabilities.other_liabilities || []).forEach(l => {
          const joint = ownershipLabel(l);
          const projBal = (l.projected_balance !== undefined && l.projected_balance !== null) ? l.projected_balance : (l.current_balance || 0);
          rows += `<tr class="iht-asset-row"><td>${this.escapeHtml(l.type || 'Other')}: ${this.escapeHtml(l.institution || 'Unknown')}${this.escapeHtml(joint)}</td><td style="text-align: right;">(${this.fmtCurrency(l.current_balance)})</td><td style="text-align: right;">(${this.fmtCurrency(projBal)})</td></tr>`;
        });

        if (showSpouse && ownerData.total > 0) {
          rows += `<tr class="iht-subtotal"><td style="padding-left: 20px;">Subtotal</td><td style="text-align: right;">(${this.fmtCurrency(ownerData.total)})</td><td style="text-align: right;">(${this.fmtCurrency(projTotal)})</td></tr>`;
        }
        return rows;
      };

      let tableRows = '';

      // User assets
      if (situation.assets_breakdown?.user) {
        tableRows += buildOwnerAssets(situation.assets_breakdown.user);
      }
      // Spouse assets
      if (showSpouse && situation.assets_breakdown?.spouse) {
        tableRows += buildOwnerAssets(situation.assets_breakdown.spouse);
      }

      // Total Gross Assets
      tableRows += `<tr class="iht-total"><td>Total Gross Assets</td><td style="text-align: right;">${this.fmtCurrency(cur.gross_assets)}</td><td style="text-align: right;">${this.fmtCurrency(proj.gross_assets)}</td></tr>`;

      // User liabilities
      if (situation.liabilities_breakdown?.user) {
        tableRows += buildOwnerLiabilities(situation.liabilities_breakdown.user);
      }
      // Spouse liabilities
      if (showSpouse && situation.liabilities_breakdown?.spouse) {
        tableRows += buildOwnerLiabilities(situation.liabilities_breakdown.spouse);
      }

      // Total Liabilities
      tableRows += `<tr class="iht-total"><td>Total Liabilities</td><td style="text-align: right;">(${this.fmtCurrency(cur.liabilities)})</td><td style="text-align: right;">(${this.fmtCurrency(proj.liabilities)})</td></tr>`;

      // Net Estate
      tableRows += `<tr class="iht-total"><td>Net Estate</td><td style="text-align: right;">${this.fmtCurrency(cur.net_estate)}</td><td style="text-align: right;">${this.fmtCurrency(proj.net_estate)}</td></tr>`;

      // Allowances
      const totalAllowances = (cur.nrb_available || 0) + (cur.rnrb_available || 0);
      const showSeparateAllowances = isWidowed && ((cur.nrb_transferred || 0) > 0 || (cur.rnrb_transferred || 0) > 0);

      tableRows += `<tr class="iht-allowance"><td colspan="3" style="font-weight: 600;">Less: Tax-Free Allowances</td></tr>`;

      if (showSeparateAllowances) {
        tableRows += `<tr class="iht-allowance iht-asset-row"><td>Nil Rate Band (Individual)</td><td style="text-align: right;">-${this.fmtCurrency(cur.nrb_individual)}</td><td style="text-align: right;">-${this.fmtCurrency(cur.nrb_individual)}</td></tr>`;
        if ((cur.nrb_transferred || 0) > 0) {
          tableRows += `<tr class="iht-allowance iht-asset-row"><td>Nil Rate Band (Transferred from Spouse)</td><td style="text-align: right;">-${this.fmtCurrency(cur.nrb_transferred)}</td><td style="text-align: right;">-${this.fmtCurrency(cur.nrb_transferred)}</td></tr>`;
        }
        tableRows += `<tr class="iht-allowance iht-asset-row"><td style="font-weight: 600;">Total Nil Rate Band</td><td style="text-align: right; font-weight: 600;">-${this.fmtCurrency(cur.nrb_available)}</td><td style="text-align: right; font-weight: 600;">-${this.fmtCurrency(cur.nrb_available)}</td></tr>`;

        if ((cur.rnrb_available || 0) > 0) {
          tableRows += `<tr class="iht-allowance iht-asset-row"><td>Residence Nil Rate Band (Individual)</td><td style="text-align: right;">-${this.fmtCurrency(cur.rnrb_individual)}</td><td style="text-align: right;">-${this.fmtCurrency(cur.rnrb_individual)}</td></tr>`;
          if ((cur.rnrb_transferred || 0) > 0) {
            tableRows += `<tr class="iht-allowance iht-asset-row"><td>Residence Nil Rate Band (Transferred from Spouse)</td><td style="text-align: right;">-${this.fmtCurrency(cur.rnrb_transferred)}</td><td style="text-align: right;">-${this.fmtCurrency(cur.rnrb_transferred)}</td></tr>`;
          }
          tableRows += `<tr class="iht-allowance iht-asset-row"><td style="font-weight: 600;">Total Residence Nil Rate Band</td><td style="text-align: right; font-weight: 600;">-${this.fmtCurrency(cur.rnrb_available)}</td><td style="text-align: right; font-weight: 600;">-${this.fmtCurrency(cur.rnrb_available)}</td></tr>`;
        }
      } else {
        tableRows += `<tr class="iht-allowance iht-asset-row"><td>Nil Rate Band</td><td style="text-align: right;">-${this.fmtCurrency(cur.nrb_available)}</td><td style="text-align: right;">-${this.fmtCurrency(cur.nrb_available)}</td></tr>`;
        if ((cur.rnrb_available || 0) > 0) {
          tableRows += `<tr class="iht-allowance iht-asset-row"><td>Residence Nil Rate Band</td><td style="text-align: right;">-${this.fmtCurrency(cur.rnrb_available)}</td><td style="text-align: right;">-${this.fmtCurrency(cur.rnrb_available)}</td></tr>`;
        }
      }

      tableRows += `<tr class="iht-allowance"><td style="font-weight: 700;">Total Allowances</td><td style="text-align: right; font-weight: 700;">-${this.fmtCurrency(totalAllowances)}</td><td style="text-align: right; font-weight: 700;">-${this.fmtCurrency(totalAllowances)}</td></tr>`;

      // Taxable Estate
      tableRows += `<tr class="iht-total"><td>Taxable Estate</td><td style="text-align: right;">${this.fmtCurrency(cur.taxable_estate)}</td><td style="text-align: right;">${this.fmtCurrency(proj.taxable_estate)}</td></tr>`;

      // IHT Liability (red)
      tableRows += `<tr class="iht-liability"><td>Inheritance Tax Liability</td><td style="text-align: right;">${this.fmtCurrency(cur.iht_liability)}</td><td style="text-align: right;">${this.fmtCurrency(proj.iht_liability)}</td></tr>`;

      // Effective Rate
      const curRate = cur.effective_rate !== undefined ? cur.effective_rate : (cur.gross_assets > 0 ? (cur.iht_liability / cur.gross_assets) * 100 : 0);
      const projRate = proj.effective_rate !== undefined ? proj.effective_rate : (proj.gross_assets > 0 ? (proj.iht_liability / proj.gross_assets) * 100 : 0);
      tableRows += `<tr><td>Effective Rate</td><td style="text-align: right;">${this.fmtPercentage(curRate)}</td><td style="text-align: right;">${this.fmtPercentage(projRate)}</td></tr>`;

      // Messages
      let messagesHtml = '';
      if (situation.iht_rate_message) {
        messagesHtml += `<p style="font-size: 10px; color: #6b7280; margin-top: 6px;">${this.escapeHtml(situation.iht_rate_message)}</p>`;
      }
      if (situation.nrb_message) {
        messagesHtml += `<p style="font-size: 10px; color: #6b7280; margin-top: 3px;"><strong style="color: #4b5563;">Nil Rate Band:</strong> ${this.escapeHtml(situation.nrb_message)}</p>`;
      }
      if (situation.rnrb_message) {
        messagesHtml += `<p style="font-size: 10px; color: #6b7280; margin-top: 3px;"><strong style="color: #4b5563;">Residence Nil Rate Band:</strong> ${this.escapeHtml(situation.rnrb_message)}</p>`;
      }

      // Supplementary cards
      let cardsHtml = '';

      if (situation.asset_breakdown) {
        cardsHtml += `
          <div class="metric-card">
            <div style="font-size: 11px; font-weight: 600; color: #374151; margin-bottom: 6px;">Asset Breakdown</div>
            <div class="metric-label">Liquid Assets</div>
            <div class="metric-value">${this.fmtCurrency(situation.asset_breakdown.liquid)}</div>
            <div class="metric-label" style="margin-top: 4px;">Semi-Liquid Assets</div>
            <div class="metric-value">${this.fmtCurrency(situation.asset_breakdown.semi_liquid)}</div>
            <div class="metric-label" style="margin-top: 4px;">Illiquid Assets</div>
            <div class="metric-value">${this.fmtCurrency(situation.asset_breakdown.illiquid)}</div>
          </div>
        `;
      }

      const lc = situation.life_cover;
      if (lc && (lc.cover_in_trust > 0 || lc.cover_not_in_trust > 0 || lc.policy_count > 0)) {
        cardsHtml += `
          <div class="metric-card">
            <div style="font-size: 11px; font-weight: 600; color: #374151; margin-bottom: 6px;">Life Cover</div>
            <div class="metric-label">Cover in Trust</div>
            <div class="metric-value" style="color: #15803d;">${this.fmtCurrency(lc.cover_in_trust)}</div>
            <div class="metric-label" style="margin-top: 4px;">Cover Not in Trust</div>
            <div class="metric-value">${this.fmtCurrency(lc.cover_not_in_trust)}</div>
            <div class="metric-label" style="margin-top: 4px;">Total Policies</div>
            <div class="metric-value">${lc.policy_count}</div>
          </div>
        `;
      }

      const cg = situation.charitable_giving;
      if (cg) {
        cardsHtml += `
          <div class="metric-card">
            <div style="font-size: 11px; font-weight: 600; color: #374151; margin-bottom: 6px;">Charitable Giving</div>
            <div class="metric-label">Current Charitable Rate</div>
            <div class="metric-value">${this.fmtPercentage(cg.current_percentage)}</div>
            <div class="metric-label" style="margin-top: 4px;">Threshold for 36% Rate</div>
            <div class="metric-value">${this.fmtPercentage(cg.threshold)}</div>
            ${(cg.shortfall || 0) > 0 ? `<div class="metric-label" style="margin-top: 4px;">Shortfall to Qualify</div><div class="metric-value">${this.fmtCurrency(cg.shortfall)}</div>` : ''}
            ${(cg.potential_saving || 0) > 0 ? `<div class="metric-label" style="margin-top: 4px;">Potential Saving</div><div class="metric-value" style="color: #15803d;">${this.fmtCurrency(cg.potential_saving)}</div>` : ''}
          </div>
        `;
      }

      return `
      <div class="section">
        <div class="section-title">Current Situation</div>
        <div class="section-subtitle">Your estate and Inheritance Tax overview</div>
        <div class="subsection-title">Inheritance Tax Calculation</div>
        <table class="iht-table">
          <thead>
            <tr>
              <th>Asset / Liability</th>
              <th style="text-align: right;">Now</th>
              <th style="text-align: right;">Age ${estimatedAge}<br><span style="font-size: 9px; font-weight: 400; color: #9ca3af;">Life expectancy</span></th>
            </tr>
          </thead>
          <tbody>${tableRows}</tbody>
        </table>
        ${messagesHtml}
        ${cardsHtml ? `<div class="metric-grid">${cardsHtml}</div>` : ''}
      </div>`;
    },

    buildEstateActionsHtml(enabledActions, disabledActions) {
      if (enabledActions.length === 0 && disabledActions.length === 0) return '';

      const priorityMap = { critical: 'badge-red', high: 'badge-blue', medium: 'badge-gray', low: 'badge-green' };
      const priorityOrder = { critical: 0, high: 1, medium: 2, low: 3 };
      const sorted = [...enabledActions].sort((a, b) => (priorityOrder[a.priority] ?? 2) - (priorityOrder[b.priority] ?? 2));

      const enabledHtml = sorted.map((a, i) => {
        const badgeClass = priorityMap[a.priority] || 'badge-gray';
        const priorityLabel = (a.priority || 'medium').charAt(0).toUpperCase() + (a.priority || 'medium').slice(1);
        const categoryLabel = (a.category || '').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());

        let details = '';

        // Estimated impact
        if (a.estimated_impact) {
          details += `<div style="font-size: 10px; color: #15803d; margin-top: 4px; font-weight: 500;">Estimated impact: ${this.fmtCurrency(a.estimated_impact)}</div>`;
        }

        // Affordability
        if (a.affordability) {
          const affordable = a.affordability.is_affordable;
          const color = affordable ? '#15803d' : '#b91c1c';
          const bg = affordable ? '#dcfce7' : '#fee2e2';
          const label = affordable ? 'Affordable' : 'May exceed budget';
          details += `<div style="margin-top: 4px;"><span style="display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 9px; font-weight: 600; background: ${bg}; color: ${color};">${label} (${this.fmtCurrency(a.affordability.monthly_premium_estimate)}/month)</span></div>`;
          if (a.affordability_warning) {
            details += `<div style="font-size: 9px; color: #b91c1c; margin-top: 2px;">${this.escapeHtml(a.affordability_warning)}</div>`;
          }
        }

        // Funding source
        if (a.funding_source) {
          details += `<div style="margin-top: 4px; padding: 4px 8px; background: #f3f4f6; border-radius: 4px; font-size: 10px; color: #4b5563;"><strong>Funding:</strong> ${this.escapeHtml(a.funding_source.note)}`;
          if (a.funding_source.liquid_assets_available > 0) {
            details += ` <span style="color: #6b7280;">(${this.fmtCurrency(a.funding_source.liquid_assets_available)} liquid assets available)</span>`;
          }
          details += `</div>`;
        }

        // PET gifting schedule
        if (a.category === 'pet_gifting' && a.gift_schedule && a.gift_schedule.length > 0) {
          const scheduleRows = a.gift_schedule.map(entry => `
            <tr>
              <td>Year ${entry.year + 1}</td>
              <td style="text-align: right;">${this.fmtCurrency(entry.amount)}</td>
              <td style="text-align: right; color: #15803d;">${this.fmtCurrency(entry.iht_reduction)}</td>
              <td style="text-align: right;">Year ${entry.becomes_exempt}</td>
            </tr>
          `).join('');

          details += `
            <div style="margin-top: 6px;">
              <div style="font-size: 10px; font-weight: 600; color: #374151; margin-bottom: 3px;">Year-by-Year Gifting Schedule</div>
              <table style="font-size: 10px;">
                <thead>
                  <tr>
                    <th>Year</th>
                    <th style="text-align: right;">Gift Amount</th>
                    <th style="text-align: right;">Inheritance Tax Reduction</th>
                    <th style="text-align: right;">Exempt After Year</th>
                  </tr>
                </thead>
                <tbody>${scheduleRows}</tbody>
              </table>
            </div>
          `;

          if (a.seven_year_cycles) {
            details += `<div style="font-size: 9px; color: #6b7280; margin-top: 2px;">${a.seven_year_cycles} complete 7-year cycle${a.seven_year_cycles !== 1 ? 's' : ''} of ${this.fmtCurrency(a.amount_per_cycle)} each.</div>`;
          }
        }

        // Annual gifting detail
        if (a.category === 'annual_gifting' && a.annual_gifting_detail) {
          const d = a.annual_gifting_detail;
          details += `
            <div class="gifting-grid">
              <div class="gifting-cell">
                <div class="gifting-label">Annual Amount</div>
                <div class="gifting-value">${this.fmtCurrency(d.annual_amount)}</div>
              </div>
              <div class="gifting-cell">
                <div class="gifting-label">Over</div>
                <div class="gifting-value">${d.years} years</div>
              </div>
              <div class="gifting-cell">
                <div class="gifting-label">Total Gifted</div>
                <div class="gifting-value">${this.fmtCurrency(d.total_gifted)}</div>
              </div>
              <div class="gifting-cell">
                <div class="gifting-label">Inheritance Tax Saved</div>
                <div class="gifting-value" style="color: #15803d;">${this.fmtCurrency(d.iht_saved)}</div>
              </div>
            </div>
          `;
        }

        // Guidance steps
        if (a.guidance && a.guidance.steps && a.guidance.steps.length > 0) {
          const stepsHtml = a.guidance.steps.map(step => `<li>${this.escapeHtml(step)}</li>`).join('');
          details += `
            <div style="margin-top: 6px;">
              <div style="font-size: 10px; font-weight: 600; color: #374151; margin-bottom: 2px;">Step-by-Step Guidance</div>
              <ol class="guidance-list">${stepsHtml}</ol>
            </div>
          `;
          const metaParts = [];
          if (a.guidance.timeframe) metaParts.push(`<strong>Timeframe:</strong> ${this.escapeHtml(a.guidance.timeframe)}`);
          if (a.guidance.professional_advice) metaParts.push(`<strong>Advice:</strong> ${this.escapeHtml(a.guidance.professional_advice)}`);
          if (metaParts.length > 0) {
            details += `<div style="font-size: 9px; color: #6b7280; margin-top: 4px;">${metaParts.join(' &bull; ')}</div>`;
          }
        }

        return `
          <div class="action-item" style="flex-direction: column;">
            <div style="display: flex; align-items: flex-start;">
              <div class="action-number">${i + 1}</div>
              <div>
                <div class="action-text">
                  <strong>${this.escapeHtml(a.title)}</strong>
                  <span class="badge ${badgeClass}" style="margin-left: 6px;">${this.escapeHtml(priorityLabel)}</span>
                  <span style="font-size: 9px; color: #6b7280; margin-left: 6px;">${this.escapeHtml(categoryLabel)}</span>
                </div>
                <div class="action-detail">${this.escapeHtml(a.description)}</div>
                ${details}
              </div>
            </div>
          </div>
        `;
      }).join('');

      const disabledHtml = disabledActions.length > 0 ? `
        <div class="disabled-actions">
          <div class="subsection-title">Actions Not Taken</div>
          ${disabledActions.map(a => `
            <div class="disabled-action">${this.escapeHtml(a.title)}</div>
          `).join('')}
        </div>
      ` : '';

      return `
      <div class="section">
        <div class="section-title">Recommended Actions</div>
        <div class="section-subtitle">${enabledActions.length} action${enabledActions.length !== 1 ? 's' : ''} enabled</div>
        ${enabledHtml}
        ${disabledHtml}
      </div>
      `;
    },

    buildEstateWhatIfHtml(whatIf, enabledActions) {
      if (!whatIf?.current_scenario) return '';

      const cur = whatIf.current_scenario;
      let proj;

      // Recalculate projected scenario based on enabled actions (matches EstateGroupedActions logic)
      if (whatIf.frontend_calc_params) {
        const params = whatIf.frontend_calc_params;
        const savingsMap = params.savings_map || {};
        const grossEstate = params.gross_estate || 0;
        const netEstate = params.net_estate || 0;
        const currentLiability = params.current_iht_liability || 0;

        let totalSavings = 0;
        (enabledActions || []).forEach(action => {
          totalSavings += savingsMap[action.id] || action.estimated_impact || 0;
        });

        const projectedLiability = Math.max(0, currentLiability - totalSavings);
        const projectedRate = grossEstate > 0 ? (projectedLiability / grossEstate) * 100 : 0;
        const projectedToBeneficiaries = Math.max(0, netEstate - projectedLiability);

        proj = {
          iht_liability: projectedLiability,
          effective_tax_rate: Math.round(projectedRate * 10) / 10,
          estate_to_beneficiaries: projectedToBeneficiaries,
          total_mitigation_savings: totalSavings,
        };
      } else {
        proj = whatIf.projected_scenario || {};
      }

      return `
      <div class="section">
        <div class="section-title">Projected Outcomes</div>
        <div class="section-subtitle">Impact of recommended actions on your estate</div>
        <table>
          <thead>
            <tr>
              <th>Metric</th>
              <th style="text-align: right;">Current Position</th>
              <th style="text-align: right;">With Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>Inheritance Tax Liability</td>
              <td style="text-align: right; color: #b91c1c; font-weight: 600;">${this.fmtCurrency(cur.iht_liability)}</td>
              <td style="text-align: right; color: #b91c1c; font-weight: 600;">${this.fmtCurrency(proj.iht_liability)}</td>
            </tr>
            <tr>
              <td>Effective Tax Rate</td>
              <td style="text-align: right;">${this.fmtPercentage(cur.effective_tax_rate)}</td>
              <td style="text-align: right;">${this.fmtPercentage(proj.effective_tax_rate)}</td>
            </tr>
            <tr>
              <td>Estate to Beneficiaries</td>
              <td style="text-align: right; font-weight: 600;">${this.fmtCurrency(cur.estate_to_beneficiaries)}</td>
              <td style="text-align: right; color: #15803d; font-weight: 600;">${this.fmtCurrency(proj.estate_to_beneficiaries)}</td>
            </tr>
            ${proj.total_mitigation_savings ? `
            <tr>
              <td>Total Mitigation Savings</td>
              <td style="text-align: right; color: #6b7280;">&mdash;</td>
              <td style="text-align: right; color: #15803d; font-weight: 600;">${this.fmtCurrency(proj.total_mitigation_savings)}</td>
            </tr>
            ` : ''}
          </tbody>
        </table>
      </div>
      `;
    },

    // ── Recommended Actions ────────────────────────────────────────────

    buildActionsHtml(enabledActions, disabledActions) {
      if (enabledActions.length === 0 && disabledActions.length === 0) return '';

      const enabledHtml = enabledActions.map((a, i) => {
        const priorityMap = { critical: 'badge-red', high: 'badge-blue', medium: 'badge-gray', low: 'badge-green' };
        const badgeClass = priorityMap[a.priority] || 'badge-gray';
        const priorityLabel = (a.priority || 'medium').charAt(0).toUpperCase() + (a.priority || 'medium').slice(1);

        return `
          <div class="action-item">
            <div class="action-number">${i + 1}</div>
            <div>
              <div class="action-text">
                <strong>${this.escapeHtml(a.title)}</strong>
                <span class="badge ${badgeClass}" style="margin-left: 6px;">${this.escapeHtml(priorityLabel)}</span>
              </div>
              <div class="action-detail">${this.escapeHtml(a.description)}</div>
            </div>
          </div>
        `;
      }).join('');

      const disabledHtml = disabledActions.length > 0 ? `
        <div class="disabled-actions">
          <div class="subsection-title">Actions Not Taken</div>
          ${disabledActions.map(a => `
            <div class="disabled-action">${this.escapeHtml(a.title)}</div>
          `).join('')}
        </div>
      ` : '';

      return `
      <div class="section">
        <div class="section-title">Recommended Actions</div>
        <div class="section-subtitle">${enabledActions.length} action${enabledActions.length !== 1 ? 's' : ''} enabled</div>
        ${enabledHtml}
        ${disabledHtml}
      </div>
      `;
    },

    // ── Projected Outcomes (What-If) ───────────────────────────────────

    buildWhatIfHtml(whatIf) {
      if (!whatIf.current_scenario || !whatIf.projected_scenario) return '';

      // Internal keys used for chart data only — exclude from the table and chart
      const EXCLUDED_KEYS = [
        'life_insurance_coverage', 'critical_illness_coverage', 'income_protection_coverage',
        'life_insurance_need', 'critical_illness_need', 'income_protection_need',
      ];
      const NUMBER_KEYS = ['emergency_fund_months', 'months_to_goal'];
      const SUFFIX_MAP = {
        income_protection_gap: '/month',
        emergency_fund_months: ' months',
        months_to_goal: ' months',
      };

      const keys = Object.keys(whatIf.current_scenario).filter(key =>
        whatIf.projected_scenario[key] !== undefined &&
        typeof whatIf.current_scenario[key] === 'number' &&
        !EXCLUDED_KEYS.includes(key),
      );

      if (keys.length === 0) return '';

      // Build bar chart
      const chartHtml = this.buildBarChartHtml(whatIf.current_scenario, whatIf.projected_scenario, keys, SUFFIX_MAP, NUMBER_KEYS);

      // Build table
      const rows = keys.map(key => {
        const label = key.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
        const suffix = SUFFIX_MAP[key] || '';
        const isNumber = NUMBER_KEYS.includes(key);
        const curVal = isNumber ? (whatIf.current_scenario[key] + suffix) : (this.fmtCurrency(whatIf.current_scenario[key]) + suffix);
        const projVal = isNumber ? (whatIf.projected_scenario[key] + suffix) : (this.fmtCurrency(whatIf.projected_scenario[key]) + suffix);
        return `
          <tr>
            <td>${this.escapeHtml(label)}</td>
            <td>${this.escapeHtml(curVal)}</td>
            <td>${this.escapeHtml(projVal)}</td>
          </tr>
        `;
      }).join('');

      return `
      <div class="section">
        <div class="section-title">Projected Outcomes</div>
        <div class="section-subtitle">Current position compared with projected outcomes if actions are taken</div>
        ${chartHtml}
        <table>
          <thead>
            <tr>
              <th>Metric</th>
              <th>Current</th>
              <th>With Actions</th>
            </tr>
          </thead>
          <tbody>
            ${rows}
          </tbody>
        </table>
      </div>
      `;
    },

    buildBarChartHtml(current, projected, keys, suffixMap, numberKeys) {
      const legend = `
        <div style="display: flex; gap: 16px; margin-bottom: 10px; font-size: 10px; color: #374151;">
          <span style="display: flex; align-items: center;">
            <span style="display: inline-block; width: 12px; height: 12px; background: #475569; border-radius: 2px; margin-right: 4px;"></span>
            Current
          </span>
          <span style="display: flex; align-items: center;">
            <span style="display: inline-block; width: 12px; height: 12px; background: #15803D; border-radius: 2px; margin-right: 4px;"></span>
            With Actions
          </span>
        </div>
      `;

      const bars = keys.map(key => {
        const label = key.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
        const curAbs = Math.abs(current[key] || 0);
        const projAbs = Math.abs(projected[key] || 0);
        const rowMax = Math.max(curAbs, projAbs, 1);
        const curWidth = Math.max(1, (curAbs / rowMax) * 100);
        const projWidth = Math.max(1, (projAbs / rowMax) * 100);

        const suffix = suffixMap[key] || '';
        const isNumber = numberKeys.includes(key);
        const curLabel = isNumber ? (current[key] + suffix) : (this.fmtCurrency(current[key]) + suffix);
        const projLabel = isNumber ? (projected[key] + suffix) : (this.fmtCurrency(projected[key]) + suffix);

        return `
          <div style="margin-bottom: 10px; page-break-inside: avoid;">
            <div style="font-size: 10px; color: #374151; margin-bottom: 3px; font-weight: 500;">${this.escapeHtml(label)}</div>
            <div style="display: flex; align-items: center; height: 14px; margin-bottom: 2px;">
              <div style="background: #475569; height: 12px; border-radius: 2px; width: ${curWidth}%; min-width: 2px;"></div>
              <span style="font-size: 9px; color: #64748b; margin-left: 6px; white-space: nowrap;">${this.escapeHtml(curLabel)}</span>
            </div>
            <div style="display: flex; align-items: center; height: 14px;">
              <div style="background: #15803D; height: 12px; border-radius: 2px; width: ${projWidth}%; min-width: 2px;"></div>
              <span style="font-size: 9px; color: #64748b; margin-left: 6px; white-space: nowrap;">${this.escapeHtml(projLabel)}</span>
            </div>
          </div>
        `;
      }).join('');

      return `
        <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px; margin-bottom: 14px;">
          ${legend}
          ${bars}
        </div>
      `;
    },

    // ── Conclusion ─────────────────────────────────────────────────────

    buildConclusionHtml(conclusion) {
      if (!conclusion || !conclusion.summary_text) return '';

      const badges = [];
      if (conclusion.critical_actions > 0) {
        badges.push(`<span class="badge badge-red">${conclusion.critical_actions} critical</span>`);
      }
      if (conclusion.high_priority_actions > 0) {
        badges.push(`<span class="badge badge-blue">${conclusion.high_priority_actions} high priority</span>`);
      }
      if (conclusion.total_actions > 0) {
        badges.push(`<span class="badge badge-gray">${conclusion.total_actions} total actions</span>`);
      }

      const breakdownHtml = (conclusion.detailed_breakdown || []).map(group => {
        const actions = (group.actions || []).map(action =>
          `<li style="font-size: 10px; color: #374151; margin-bottom: 2px; padding-left: 14px; position: relative;">
            <span style="color: #15803D; position: absolute; left: 0;">&#10003;</span>
            ${this.escapeHtml(action)}
          </li>`,
        ).join('');

        return `
          <div style="background: #f9fafb; border-radius: 6px; padding: 10px 12px; margin-bottom: 6px;">
            <div style="font-size: 11px; font-weight: 600; color: #1f2937;">
              ${this.escapeHtml(group.category)}
              <span style="font-weight: 400; color: #64748b;">(${group.action_count} action${group.action_count !== 1 ? 's' : ''})</span>
            </div>
            <ul style="list-style: none; margin: 4px 0 0 0; padding: 0;">
              ${actions}
            </ul>
          </div>
        `;
      }).join('');

      return `
      <div class="section">
        <div class="section-title">Conclusion</div>
        <div class="section-subtitle">Summary of your plan and next steps</div>
        <div class="conclusion-box">${this.escapeHtml(conclusion.summary_text)}</div>
        ${badges.length > 0 ? `<div style="margin-top: 10px; display: flex; gap: 8px;">${badges.join('')}</div>` : ''}
        ${breakdownHtml ? `<div style="margin-top: 12px;">${breakdownHtml}</div>` : ''}
      </div>
      `;
    },
  },
};
