import { currencyMixin } from '@/mixins/currencyMixin';
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
      return '/images/logos/LogoHiResFynlaDark.png';
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

    fmtCurrencyCompact(val) {
      if (val === null || val === undefined) return '£0';
      const abs = Math.abs(val);
      if (abs >= 1000000) return '£' + (val / 1000000).toFixed(1) + 'M';
      if (abs >= 1000) return '£' + Math.round(val / 1000) + 'k';
      return '£' + Math.round(val);
    },

    detectPlanType(plan) {
      const situation = plan.current_situation || {};
      const summary = plan.executive_summary || {};

      if (situation.iht_summary) return 'estate';
      if (summary.coverage_summary) return 'protection';
      if (situation.investment_accounts || situation.savings_accounts) return 'investment';
      if (situation.dc_pensions || situation.db_pensions) return 'retirement';
      if (summary.greeting) return 'structured';
      return 'generic';
    },

    buildPlanHtml(plan, title) {
      const date = new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
      const userName = plan.metadata?.user_name || '';
      const summary = plan.executive_summary || {};
      const conclusion = plan.conclusion || {};
      const enabledActions = (plan.actions || []).filter(a => a.enabled);
      const disabledActions = (plan.actions || []).filter(a => !a.enabled);
      const whatIf = plan.what_if || {};
      const planType = this.detectPlanType(plan);

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
      ${planType !== 'generic' ? this.buildStructuredExecutiveSummaryHtml(summary, planType) : `<div class="narrative">${this.escapeHtml(summary.narrative || '')}</div>`}
    </div>

    ${planType !== 'generic' && plan.personal_information ? this.buildPersonalInformationHtml(plan.personal_information, planType) : ''}

    <!-- Current Situation -->
    ${this.buildCurrentSituationByType(plan, planType)}

    <!-- Recommended Actions -->
    ${this.buildActionsByType(plan, planType)}

    <!-- Projected Outcomes -->
    ${this.buildWhatIfByType(plan, planType)}

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

      // Protection: Needs Breakdown
      if (situation.needs) {
        content += this.buildProtectionNeedsHtml(situation.needs);
      }

      // Protection: Coverage Analysis
      if (situation.coverage_analysis) {
        content += this.buildCoverageAnalysisHtml(situation.coverage_analysis);
      }

      // Protection: Existing Policies
      if (situation.current_coverage) {
        content += this.buildPoliciesHtml(situation.current_coverage);
      }

      // Protection: Scenario Analysis
      if (situation.scenario_analysis) {
        content += this.buildScenarioAnalysisHtml(situation.scenario_analysis);
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

      // Investment: Asset Allocation
      if (situation.asset_allocation) {
        content += this.buildAssetAllocationHtml(situation.asset_allocation);
      }

      // Investment: Fee Analysis
      if (situation.fee_analysis) {
        content += this.buildFeeAnalysisHtml(situation.fee_analysis);
      }

      // Investment: Tax Wrappers
      if (situation.tax_wrappers) {
        content += this.buildTaxWrappersHtml(situation.tax_wrappers);
      }

      // Retirement: Summary
      if (situation.summary && situation.summary.years_to_retirement !== undefined) {
        content += this.buildRetirementSummaryHtml(situation.summary);
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

      // Retirement: Income Projection
      if (situation.income_projection) {
        content += this.buildIncomeProjectionHtml(situation.income_projection);
      }

      // Retirement: Annual Allowance
      if (situation.annual_allowance) {
        content += this.buildAnnualAllowanceHtml(situation.annual_allowance);
      }

      // Goals: Details + Progress
      if (situation.goal_details) {
        content += this.buildGoalSituationHtml(situation);
      }

      // Key indicators (emergency fund, ISA)
      content += this.buildSituationIndicatorsHtml(situation);

      if (!content) return '';

      return `
      <div class="section">
        <div class="section-title">Current Situation</div>
        <div class="section-subtitle">Your current financial position</div>
        ${content}
      </div>`;
    },

    // ── Plan-Type Routing ────────────────────────────────────────────

    buildCurrentSituationByType(plan, planType) {
      const situation = plan.current_situation;
      switch (planType) {
        case 'estate': return this.buildEstateCurrentSituationHtml(situation);
        case 'protection': return this.buildProtectionCurrentSituationHtml(situation);
        case 'investment': return this.buildInvestmentCurrentSituationHtml(situation);
        case 'retirement': return this.buildRetirementCurrentSituationHtml(situation);
        default: return this.buildCurrentSituationHtml(situation);
      }
    },

    buildActionsByType(plan, planType) {
      const allActions = plan.actions || [];
      const enabledActions = allActions.filter(a => a.enabled);
      const disabledActions = allActions.filter(a => !a.enabled);
      switch (planType) {
        case 'estate': return this.buildEstateActionsHtml(enabledActions, disabledActions);
        case 'protection': return this.buildSimpleActionsHtml(allActions);
        case 'investment':
        case 'retirement': return this.buildGroupedActionsHtml(allActions, plan.what_if, planType);
        default: return this.buildActionsHtml(enabledActions, disabledActions);
      }
    },

    buildWhatIfByType(plan, planType) {
      const whatIf = plan.what_if || {};
      const enabledActions = (plan.actions || []).filter(a => a.enabled);
      switch (planType) {
        case 'estate': return this.buildEstateWhatIfHtml(whatIf, enabledActions);
        case 'protection': return this.buildProtectionWhatIfHtml(plan);
        case 'investment': return this.buildInvestmentWhatIfHtml(whatIf);
        case 'retirement': return this.buildRetirementWhatIfHtml(whatIf);
        default: return this.buildWhatIfHtml(whatIf);
      }
    },

    // ── Protection Current Situation (matches ProtectionCurrentSituation.vue) ──

    buildProtectionCurrentSituationHtml(situation) {
      if (!situation) return '';
      let content = '';

      const analysis = situation.coverage_analysis;
      if (analysis) {
        content += this.buildProtectionCoverageDetailHtml(analysis, situation.needs);
      }

      if (situation.current_coverage) {
        content += this.buildPoliciesHtml(situation.current_coverage);
      }

      if (situation.debt_breakdown && situation.debt_breakdown.total > 0) {
        content += this.buildDebtHtml(situation.debt_breakdown);
      }

      if (!content) return '';

      return `
      <div class="section">
        <div class="section-title">Current Situation</div>
        <div class="section-subtitle">Your protection coverage overview</div>
        ${content}
      </div>`;
    },

    buildProtectionCoverageDetailHtml(analysis, needs) {
      const needsBreakdown = needs?.breakdown || {};
      const incomeAnalysis = needs?.income_analysis || {};

      const types = [
        {
          key: 'life_insurance',
          label: 'Life Insurance',
          suffix: '',
          buildBreakdown: () => {
            const rows = [];
            if (needsBreakdown.human_capital > 0) {
              const desc = incomeAnalysis.net_income_difference > 0
                ? `Income replacement capital (${this.fmtCurrency(incomeAnalysis.net_income_difference)}/year at 4.7% drawdown)`
                : 'Income replacement capital (net income at 4.7% drawdown)';
              rows.push([desc, this.fmtCurrency(needsBreakdown.human_capital)]);
            }
            if (needsBreakdown.debt_protection > 0) rows.push(['Outstanding debts (mortgage + other)', this.fmtCurrency(needsBreakdown.debt_protection)]);
            if (needsBreakdown.education_funding > 0) rows.push(['Education funding for dependants', this.fmtCurrency(needsBreakdown.education_funding)]);
            if (needsBreakdown.final_expenses > 0) rows.push(['Final expenses (funeral and administration)', this.fmtCurrency(needsBreakdown.final_expenses)]);
            return rows;
          },
        },
        {
          key: 'critical_illness',
          label: 'Critical Illness',
          suffix: '',
          buildBreakdown: () => {
            if (!incomeAnalysis.gross_income) return [];
            return [[`3 × your gross annual income of ${this.fmtCurrency(incomeAnalysis.gross_income)}`, this.fmtCurrency(analysis.critical_illness?.need || 0)]];
          },
        },
        {
          key: 'income_protection',
          label: 'Income Protection',
          suffix: '/month',
          buildBreakdown: () => {
            if (!incomeAnalysis.net_income) return [];
            return [[`70% of your net monthly income (${this.fmtCurrency(incomeAnalysis.net_income / 12)}/month)`, `${this.fmtCurrency(analysis.income_protection?.need || 0)}/month`]];
          },
        },
      ];

      let html = '<div class="subsection-title">Coverage Analysis</div>';

      types.forEach(t => {
        const data = analysis[t.key];
        if (!data) return;

        const pct = Math.min(100, data.coverage_percentage || 0);
        const barColor = pct >= 80 ? '#22c55e' : pct >= 60 ? '#3b82f6' : '#ef4444';
        const statusColors = this.getStatusColors(data.status);
        const gapColor = (data.gap || 0) > 0 ? '#b91c1c' : '#15803d';

        html += `
          <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px 12px; margin-bottom: 8px; page-break-inside: avoid;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
              <span style="font-size: 11px; font-weight: 600; color: #1f2937;">${this.escapeHtml(t.label)}</span>
              <span style="display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 9px; font-weight: 600; background: ${statusColors.bg}; color: ${statusColors.text};">${this.escapeHtml(data.status || 'Unknown')}</span>
            </div>
            <div style="display: flex; justify-content: space-around; text-align: center; margin-bottom: 6px;">
              <div>
                <div style="font-size: 9px; color: #6b7280;">Need</div>
                <div style="font-size: 11px; font-weight: 600; color: #1f2937;">${this.fmtCurrency(data.need || 0)}${t.suffix}</div>
              </div>
              <div>
                <div style="font-size: 9px; color: #6b7280;">Have</div>
                <div style="font-size: 11px; font-weight: 600; color: #1f2937;">${this.fmtCurrency(data.coverage || 0)}${t.suffix}</div>
              </div>
              <div>
                <div style="font-size: 9px; color: #6b7280;">Gap</div>
                <div style="font-size: 11px; font-weight: 600; color: ${gapColor};">${this.fmtCurrency(data.gap || 0)}${t.suffix}</div>
              </div>
            </div>
            <div style="background: #e5e7eb; border-radius: 4px; height: 6px; width: 100%;">
              <div style="background: ${barColor}; border-radius: 4px; height: 6px; width: ${pct}%;"></div>
            </div>`;

        const breakdownRows = t.buildBreakdown();
        if (breakdownRows.length > 0) {
          html += `
            <div style="margin-top: 8px; padding-top: 6px; border-top: 1px solid #e5e7eb;">
              <div style="font-size: 9px; font-weight: 600; color: #6b7280; text-transform: uppercase; margin-bottom: 4px;">How we calculated your need</div>`;

          breakdownRows.forEach(([label, value]) => {
            html += `
              <div style="display: flex; justify-content: space-between; font-size: 10px; margin-bottom: 2px;">
                <span style="color: #4b5563;">${this.escapeHtml(label)}</span>
                <span style="color: #1f2937; font-weight: 500;">${this.escapeHtml(value)}</span>
              </div>`;
          });

          html += `
              <div style="display: flex; justify-content: space-between; font-size: 10px; padding-top: 3px; border-top: 1px solid #e5e7eb; font-weight: 500; margin-top: 2px;">
                <span style="color: #374151;">Total need</span>
                <span style="color: #1f2937;">${this.fmtCurrency(data.need || 0)}${t.suffix}</span>
              </div>`;

          if ((data.coverage || 0) > 0) {
            html += `
              <div style="display: flex; justify-content: space-between; font-size: 10px; margin-top: 2px;">
                <span style="color: #4b5563;">Less: existing cover</span>
                <span style="color: #15803d; font-weight: 500;">-${this.fmtCurrency(data.coverage)}${t.suffix}</span>
              </div>`;
          }

          if ((data.gap || 0) > 0) {
            html += `
              <div style="display: flex; justify-content: space-between; font-size: 10px; padding-top: 3px; border-top: 1px solid #e5e7eb; font-weight: 600; margin-top: 2px;">
                <span style="color: #b91c1c;">Shortfall</span>
                <span style="color: #b91c1c;">${this.fmtCurrency(data.gap)}${t.suffix}</span>
              </div>`;
          }

          html += `</div>`;
        }

        html += `</div>`;
      });

      return html;
    },

    // ── Investment Current Situation (matches InvestmentCurrentSituation.vue) ──

    buildInvestmentCurrentSituationHtml(situation) {
      if (!situation) return '';
      let content = '';

      if (situation.investment_accounts && situation.investment_accounts.length) {
        content += this.buildInvestmentAccountsHtml(situation.investment_accounts, situation.total_investment_value);
      }

      if (situation.savings_accounts && situation.savings_accounts.length) {
        content += this.buildSavingsAccountsHtml(situation.savings_accounts, situation.total_savings_value);
      }

      const emergencyMonths = Math.round(situation.emergency_fund?.runway_months || 0);
      const emergencyColor = emergencyMonths >= 6 ? '#15803d' : emergencyMonths >= 3 ? '#1e40af' : '#b91c1c';

      content += `
        <div class="metric-grid" style="margin-top: 12px;">
          <div class="metric-card" style="text-align: center;">
            <div class="metric-label">Emergency Fund</div>
            <div class="metric-value" style="color: ${emergencyColor};">${emergencyMonths} months</div>
            ${situation.emergency_fund?.category ? `<div style="font-size: 9px; color: #6b7280;">${this.escapeHtml(situation.emergency_fund.category)}</div>` : ''}
          </div>
          <div class="metric-card" style="text-align: center;">
            <div class="metric-label">ISA Used</div>
            <div class="metric-value">${this.fmtCurrency(situation.isa_allowance?.used || 0)}</div>
          </div>
          <div class="metric-card" style="text-align: center;">
            <div class="metric-label">ISA Remaining</div>
            <div class="metric-value" style="color: #15803d;">${this.fmtCurrency(situation.isa_allowance?.remaining || 0)}</div>
          </div>
        </div>
      `;

      if (!content) return '';

      return `
      <div class="section">
        <div class="section-title">Current Situation</div>
        <div class="section-subtitle">Your investment and savings overview</div>
        ${content}
      </div>`;
    },

    // ── Retirement Current Situation (matches RetirementCurrentSituation.vue) ──

    buildRetirementCurrentSituationHtml(situation) {
      if (!situation) return '';
      let content = '';

      if (situation.dc_pensions && situation.dc_pensions.length) {
        content += this.buildDCPensionsHtml(situation.dc_pensions);
      }

      if (situation.db_pensions && situation.db_pensions.length) {
        content += this.buildDBPensionsHtml(situation.db_pensions);
      }

      if (situation.state_pension) {
        content += this.buildStatePensionHtml(situation.state_pension);
      }

      const summary = situation.summary || {};
      const incomeGap = Math.max(0, summary.income_gap || 0);
      const gapColor = incomeGap <= 0 ? '#15803d' : '#b91c1c';

      content += `
        <div class="metric-grid" style="margin-top: 12px;">
          <div class="metric-card" style="text-align: center;">
            <div class="metric-label">Years to Retirement</div>
            <div class="metric-value">${summary.years_to_retirement ?? 'N/A'}</div>
          </div>
          <div class="metric-card" style="text-align: center;">
            <div class="metric-label">Income Gap at Retirement</div>
            <div class="metric-value" style="color: ${gapColor};">${this.fmtCurrency(incomeGap)}/year</div>
          </div>
          <div class="metric-card" style="text-align: center;">
            <div class="metric-label">Pension Value at Retirement</div>
            <div class="metric-value">${this.fmtCurrency(summary.total_dc_value || 0)}</div>
          </div>
        </div>
      `;

      if (!content) return '';

      return `
      <div class="section">
        <div class="section-title">Your Pension Plans</div>
        <div class="section-subtitle">Your pension and retirement overview</div>
        ${content}
      </div>`;
    },

    // ── Simple Actions (matches PlanActionsList.vue — used by Protection) ──

    buildSimpleActionsHtml(actions) {
      if (!actions || !actions.length) return '';

      const priorityOrder = { critical: 0, high: 1, medium: 2, low: 3 };
      const sorted = [...actions].sort((a, b) => (priorityOrder[a.priority] ?? 2) - (priorityOrder[b.priority] ?? 2));
      const enabledCount = actions.filter(a => a.enabled).length;
      const priorityMap = { critical: 'badge-red', high: 'badge-blue', medium: 'badge-gray', low: 'badge-green' };

      const actionsHtml = sorted.map((a, i) => {
        const badgeClass = priorityMap[a.priority] || 'badge-gray';
        const priorityLabel = (a.priority || 'medium').charAt(0).toUpperCase() + (a.priority || 'medium').slice(1);
        const enabledStyle = a.enabled ? '' : 'opacity: 0.6;';

        return `
          <div class="action-item" style="${enabledStyle}">
            <div class="action-number">${i + 1}</div>
            <div>
              <div class="action-text">
                <span class="badge ${badgeClass}">${this.escapeHtml(priorityLabel)}</span>
                <span style="font-size: 9px; color: #6b7280; margin-left: 4px;">${this.escapeHtml(a.category || '')}</span>
              </div>
              <div style="font-size: 11px; font-weight: 600; color: #1f2937; margin-top: 2px;">${this.escapeHtml(a.title)}</div>
              <div class="action-detail">${this.escapeHtml(a.description)}</div>
              ${a.estimated_impact ? `<div style="font-size: 10px; color: #15803d; margin-top: 2px; font-weight: 500;">Estimated impact: ${this.fmtCurrency(a.estimated_impact)}</div>` : ''}
              ${!a.enabled ? '<div style="font-size: 9px; color: #6b7280; margin-top: 2px; font-style: italic;">Not enabled</div>' : ''}
            </div>
          </div>
        `;
      }).join('');

      return `
      <div class="section">
        <div class="section-title">Recommended Actions</div>
        <div class="section-subtitle">${enabledCount} of ${actions.length} actions enabled</div>
        ${actionsHtml}
      </div>`;
    },

    // ── Grouped Actions (matches InvestmentGroupedActions / RetirementGroupedActions) ──

    buildGroupedActionsHtml(actions, whatIf, planType) {
      if (!actions || !actions.length) return '';

      const priorityOrder = { critical: 0, high: 1, medium: 2, low: 3 };
      const sortByPriority = (arr) => [...arr].sort((a, b) => (priorityOrder[a.priority] ?? 2) - (priorityOrder[b.priority] ?? 2));
      const enabledCount = actions.filter(a => a.enabled).length;
      const priorityMap = { critical: 'badge-red', high: 'badge-blue', medium: 'badge-gray', low: 'badge-green' };

      // Compute cascading projection data for each action (matches cascadedActions computed in Vue)
      const params = whatIf?.frontend_calc_params || {};
      const baseValue = planType === 'retirement' ? (params.current_dc_value || 0) : (params.current_value || 0);
      const growthRate = params.growth_rate || 0.05;
      const years = params.years || 10;
      const baseAnnualContrib = params.current_annual_contribution || 0;

      const cascadeMap = {};
      let cumulativeAdditionalMonthly = 0;
      const sortedAll = sortByPriority(actions);

      sortedAll.forEach(action => {
        const beforeMonthly = cumulativeAdditionalMonthly;
        const beforeSeries = this.projectSeries(baseValue, baseAnnualContrib, beforeMonthly, growthRate, years);

        const actionMonthly = action.cascade_params?.additional_monthly || 0;
        const afterMonthly = action.enabled ? (beforeMonthly + actionMonthly) : beforeMonthly;
        const afterSeries = this.projectSeries(baseValue, baseAnnualContrib, afterMonthly, growthRate, years);

        if (action.enabled) {
          cumulativeAdditionalMonthly += actionMonthly;
        }

        const diff = afterSeries[afterSeries.length - 1] - beforeSeries[beforeSeries.length - 1];
        cascadeMap[action.id] = {
          beforeSeries,
          afterSeries,
          differenceAmount: diff > 0 ? diff : 0,
        };
      });

      const hasCascadeData = baseValue > 0;

      const buildCard = (a) => {
        const badgeClass = priorityMap[a.priority] || 'badge-gray';
        const priorityLabel = (a.priority || 'medium').charAt(0).toUpperCase() + (a.priority || 'medium').slice(1);
        const enabledStyle = a.enabled ? '' : 'opacity: 0.6;';

        const cascade = cascadeMap[a.id];
        const chartHtml = (hasCascadeData && cascade)
          ? this.buildLineChartHtml(cascade.beforeSeries, cascade.afterSeries, years, cascade.differenceAmount)
          : '';

        return `
          <div style="${enabledStyle} page-break-inside: avoid; margin-bottom: 10px;">
            <div class="action-item">
              <div>
                <div class="action-text">
                  <span class="badge ${badgeClass}">${this.escapeHtml(priorityLabel)}</span>
                  <span style="font-size: 9px; color: #6b7280; margin-left: 4px;">${this.escapeHtml(a.category || '')}</span>
                </div>
                <div style="font-size: 11px; font-weight: 600; color: #1f2937; margin-top: 2px;">${this.escapeHtml(a.title)}</div>
                <div class="action-detail">${this.escapeHtml(a.description)}</div>
                ${a.estimated_impact ? `<div style="font-size: 10px; color: #15803d; margin-top: 2px; font-weight: 500;">Estimated impact: ${this.fmtCurrency(a.estimated_impact)}</div>` : ''}
                ${!a.enabled ? '<div style="font-size: 9px; color: #6b7280; margin-top: 2px; font-style: italic;">Not enabled</div>' : ''}
              </div>
            </div>
            ${chartHtml}
          </div>
        `;
      };

      const accountActions = actions.filter(a => a.scope === 'account' && a.account_id);
      const portfolioActions = actions.filter(a => !a.scope || a.scope === 'portfolio');

      const groups = {};
      accountActions.forEach(a => {
        const id = a.account_id;
        if (!groups[id]) {
          groups[id] = { name: a.account_name || 'Unknown Account', actions: [] };
        }
        groups[id].actions.push(a);
      });

      let groupsHtml = '';

      Object.values(groups).forEach(group => {
        groupsHtml += `<div class="subsection-title">${this.escapeHtml(group.name)}</div>`;
        sortByPriority(group.actions).forEach(a => { groupsHtml += buildCard(a); });
      });

      if (portfolioActions.length) {
        if (Object.keys(groups).length > 0) {
          groupsHtml += `<div class="subsection-title">Portfolio Actions</div>`;
        }
        sortByPriority(portfolioActions).forEach(a => { groupsHtml += buildCard(a); });
      }

      return `
      <div class="section">
        <div class="section-title">Recommended Actions</div>
        <div class="section-subtitle">${enabledCount} of ${actions.length} actions enabled</div>
        ${groupsHtml}
      </div>`;
    },

    // ── Cascading projection helpers (matches CascadingActionChart.vue computation) ──

    projectSeries(startValue, baseAnnualContrib, additionalMonthly, growthRate, years) {
      const totalAnnual = baseAnnualContrib + (additionalMonthly * 12);
      const series = [];
      let value = startValue;
      for (let y = 0; y <= years; y++) {
        series.push(Math.round(value));
        value = (value + totalAnnual) * (1 + growthRate);
      }
      return series;
    },

    buildLineChartHtml(beforeSeries, afterSeries, years, differenceAmount) {
      if (!beforeSeries || !afterSeries || beforeSeries.length < 2) return '';

      const width = 500;
      const height = 140;
      const pad = { top: 15, right: 10, bottom: 25, left: 55 };
      const chartW = width - pad.left - pad.right;
      const chartH = height - pad.top - pad.bottom;

      const allValues = [...beforeSeries, ...afterSeries];
      const minVal = Math.min(...allValues);
      const maxVal = Math.max(...allValues);
      const range = maxVal - minVal || 1;

      const toX = (i) => pad.left + (i / years) * chartW;
      const toY = (v) => pad.top + chartH - ((v - minVal) / range) * chartH;

      const beforePoints = beforeSeries.map((v, i) => `${toX(i).toFixed(1)},${toY(v).toFixed(1)}`).join(' ');
      const afterPoints = afterSeries.map((v, i) => `${toX(i).toFixed(1)},${toY(v).toFixed(1)}`).join(' ');

      // Y-axis grid lines and labels (4 ticks)
      let gridHtml = '';
      for (let i = 0; i <= 4; i++) {
        const val = minVal + (range * i / 4);
        const y = toY(val).toFixed(1);
        gridHtml += `<line x1="${pad.left}" y1="${y}" x2="${pad.left + chartW}" y2="${y}" stroke="#E2E8F0" stroke-dasharray="3"/>`;
        gridHtml += `<text x="${pad.left - 5}" y="${Number(y) + 3}" text-anchor="end" font-size="8" fill="#64748B" font-family="Inter, system-ui, sans-serif">${this.fmtCurrencyCompact(val)}</text>`;
      }

      // X-axis labels
      let xHtml = '';
      const step = years <= 10 ? 2 : 5;
      for (let i = 0; i <= years; i += step) {
        const x = toX(i).toFixed(1);
        xHtml += `<text x="${x}" y="${height - 5}" text-anchor="middle" font-size="8" fill="#64748B" font-family="Inter, system-ui, sans-serif">Yr ${i}</text>`;
      }

      const badge = differenceAmount > 0
        ? `<span style="display: inline-flex; padding: 1px 6px; border-radius: 3px; font-size: 9px; font-weight: 500; background: #dcfce7; color: #166534;">+${this.fmtCurrency(differenceAmount)} at retirement</span>`
        : '';

      return `
        <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 8px 10px; margin-top: 4px; margin-bottom: 4px; page-break-inside: avoid;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
            <div style="display: flex; gap: 12px; font-size: 9px; color: #374151;">
              <span style="display: flex; align-items: center;">
                <span style="display: inline-block; width: 14px; height: 2px; background: #475569; margin-right: 4px;"></span>
                Before
              </span>
              <span style="display: flex; align-items: center;">
                <span style="display: inline-block; width: 14px; height: 2px; background: #15803D; margin-right: 4px;"></span>
                After this action
              </span>
            </div>
            ${badge}
          </div>
          <svg width="100%" viewBox="0 0 ${width} ${height}" preserveAspectRatio="xMidYMid meet" style="display: block;">
            ${gridHtml}
            ${xHtml}
            <polyline points="${beforePoints}" fill="none" stroke="#475569" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <polyline points="${afterPoints}" fill="none" stroke="#15803D" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
      `;
    },

    // ── What-If Helpers ──────────────────────────────────────────────

    buildWhatIfSectionHtml(chartHtml, metricRows, title, subtitle) {
      const rowsHtml = metricRows.map(r => `
        <tr>
          <td style="font-weight: 500;">${this.escapeHtml(r.label)}</td>
          <td style="text-align: right; font-weight: 600;">${this.escapeHtml(r.curVal)}</td>
          <td style="text-align: right; font-weight: 600;">${this.escapeHtml(r.projVal)}</td>
        </tr>
      `).join('');

      return `
      <div class="section">
        <div class="section-title">${this.escapeHtml(title)}</div>
        <div class="section-subtitle">${this.escapeHtml(subtitle)}</div>
        ${chartHtml || ''}
        <table>
          <thead>
            <tr>
              <th>Metric</th>
              <th style="text-align: right;">Current Position</th>
              <th style="text-align: right;">With Actions</th>
            </tr>
          </thead>
          <tbody>${rowsHtml}</tbody>
        </table>
      </div>`;
    },

    // ── Protection What-If (matches PlanWhatIfComparison + ProtectionWhatIfControls) ──

    computeProtectionProjectedScenario(plan) {
      const current = plan.what_if?.current_scenario;
      if (!current) return plan.what_if?.projected_scenario || null;

      const lifeGap = current.life_insurance_gap || 0;
      const ciGap = current.critical_illness_gap || 0;
      const ipGap = current.income_protection_gap || 0;
      const lifeCoverage = current.life_insurance_coverage || 0;
      const ciCoverage = current.critical_illness_coverage || 0;
      const ipCoverage = current.income_protection_coverage || 0;

      let lifeReduction = 0;
      let ciReduction = 0;
      let ipReduction = 0;
      let additionalPremium = 0;

      (plan.actions || []).forEach(action => {
        if (!action.enabled) return;
        const category = (action.category || '').toLowerCase();
        const coverageAmount = action.impact_parameters?.coverage_amount || 0;
        const premium = action.impact_parameters?.premium || 0;

        if (category.includes('life')) {
          lifeReduction += coverageAmount || lifeGap;
          additionalPremium += premium;
        } else if (category.includes('critical')) {
          ciReduction += coverageAmount || ciGap;
          additionalPremium += premium;
        } else if (category.includes('income')) {
          ipReduction += coverageAmount || ipGap;
          additionalPremium += premium;
        }
      });

      return {
        total_coverage_gap: Math.max(0, lifeGap - lifeReduction) + Math.max(0, ciGap - ciReduction) + (Math.max(0, ipGap - ipReduction) * 12),
        life_insurance_gap: Math.max(0, lifeGap - lifeReduction),
        critical_illness_gap: Math.max(0, ciGap - ciReduction),
        income_protection_gap: Math.max(0, ipGap - ipReduction),
        life_insurance_coverage: lifeCoverage + lifeReduction,
        critical_illness_coverage: ciCoverage + ciReduction,
        income_protection_coverage: ipCoverage + ipReduction,
        estimated_additional_premium: additionalPremium || null,
      };
    },

    buildProtectionWhatIfHtml(plan) {
      const current = plan.what_if?.current_scenario;
      const projected = this.computeProtectionProjectedScenario(plan);
      if (!current || !projected) return '';

      const chartHtml = this.buildBarChartHtml(
        current, projected,
        ['life_insurance_coverage', 'critical_illness_coverage', 'income_protection_coverage'],
        {}, [],
      );

      const metricRows = [
        { label: 'Total Coverage Gap', curVal: this.fmtCurrency(current.total_coverage_gap || 0), projVal: this.fmtCurrency(projected.total_coverage_gap || 0) },
        { label: 'Life Insurance Gap', curVal: this.fmtCurrency(current.life_insurance_gap || 0), projVal: this.fmtCurrency(projected.life_insurance_gap || 0) },
        { label: 'Critical Illness Gap', curVal: this.fmtCurrency(current.critical_illness_gap || 0), projVal: this.fmtCurrency(projected.critical_illness_gap || 0) },
        { label: 'Income Protection Gap', curVal: this.fmtCurrency(current.income_protection_gap || 0) + '/month', projVal: this.fmtCurrency(projected.income_protection_gap || 0) + '/month' },
      ];

      if (projected.estimated_additional_premium) {
        metricRows.push({ label: 'Additional Monthly Premium', curVal: '\u2014', projVal: this.fmtCurrency(projected.estimated_additional_premium) });
      }

      return this.buildWhatIfSectionHtml(chartHtml, metricRows, 'What-If Comparison', 'See how your plan changes with recommended actions');
    },

    // ── Investment What-If (matches InvestmentWhatIfControls.vue) ──

    buildInvestmentWhatIfHtml(whatIf) {
      if (!whatIf?.current_scenario || !whatIf?.projected_scenario) return '';

      const cur = whatIf.current_scenario;
      const proj = whatIf.projected_scenario;

      const metricRows = [
        { label: 'Total Wealth', curVal: this.fmtCurrency(cur.total_wealth || 0), projVal: this.fmtCurrency(proj.total_wealth || 0) },
        { label: 'Annual Fees', curVal: this.fmtCurrency(cur.annual_fees || 0), projVal: this.fmtCurrency(proj.annual_fees || 0) },
        { label: 'Emergency Fund', curVal: Math.round(cur.emergency_fund_months || 0) + ' months', projVal: Math.round(proj.emergency_fund_months || 0) + ' months' },
      ];

      if (proj.additional_monthly_savings != null) {
        metricRows.push({ label: 'Additional Monthly Savings', curVal: this.fmtCurrency(cur.additional_monthly_savings || 0), projVal: this.fmtCurrency(proj.additional_monthly_savings || 0) });
      }

      metricRows.push({ label: 'At Retirement', curVal: this.fmtCurrency(cur.projected_value || 0), projVal: this.fmtCurrency(proj.projected_value || 0) });

      return this.buildWhatIfSectionHtml('', metricRows, 'Projected Outcomes', 'Current position compared with projected outcomes if actions are taken');
    },

    // ── Retirement What-If (matches RetirementWhatIfControls.vue) ──

    buildRetirementWhatIfHtml(whatIf) {
      if (!whatIf?.current_scenario || !whatIf?.projected_scenario) return '';

      const cur = whatIf.current_scenario;
      const proj = whatIf.projected_scenario;

      const metricRows = [
        { label: 'Projected Annual Income', curVal: this.fmtCurrency(cur.projected_annual_income || 0), projVal: this.fmtCurrency(proj.projected_annual_income || 0) },
        { label: 'Income Gap', curVal: this.fmtCurrency(cur.income_gap || 0), projVal: this.fmtCurrency(proj.income_gap || 0) },
        { label: 'Total Pension Value', curVal: this.fmtCurrency(cur.total_dc_value || 0), projVal: this.fmtCurrency(proj.total_dc_value || 0) },
        { label: 'At Retirement', curVal: this.fmtCurrency(cur.dc_value_at_retirement || 0), projVal: this.fmtCurrency(proj.dc_value_at_retirement || 0) },
      ];

      if (proj.additional_monthly_contribution) {
        metricRows.push({ label: 'Additional Monthly Contribution', curVal: this.fmtCurrency(cur.additional_monthly_contribution || 0), projVal: this.fmtCurrency(proj.additional_monthly_contribution || 0) });
      }

      return this.buildWhatIfSectionHtml('', metricRows, 'Projected Outcomes', 'Current position compared with projected outcomes if actions are taken');
    },

    // ── Legacy Coverage Analysis (generic fallback) ──────────────────

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

    // ── Protection Builders ───────────────────────────────────────────

    buildProtectionNeedsHtml(needs) {
      if (!needs) return '';
      const bd = needs.breakdown || {};
      const rows = [
        ['Income Replacement (Human Capital)', this.fmtCurrency(bd.human_capital || 0)],
        ['Debt Protection', this.fmtCurrency(bd.debt_protection || 0)],
        ['Education Funding', this.fmtCurrency(bd.education_funding || 0)],
        ['Final Expenses', this.fmtCurrency(bd.final_expenses || 0)],
      ].filter(([, v]) => v !== '£0');

      const totalRow = `
        <tr style="border-top: 2px solid #d1d5db;">
          <td style="font-weight: 600;">Total Protection Need</td>
          <td style="text-align: right; font-weight: 700;">${this.fmtCurrency(needs.total_need || 0)}</td>
        </tr>
      `;

      return `
        <div class="subsection-title">Protection Needs</div>
        <table>
          <tbody>
            ${rows.map(([label, value]) => `
              <tr>
                <td>${this.escapeHtml(label)}</td>
                <td style="text-align: right; font-weight: 500;">${this.escapeHtml(value)}</td>
              </tr>
            `).join('')}
            ${totalRow}
          </tbody>
        </table>
      `;
    },

    buildScenarioAnalysisHtml(scenarios) {
      if (!scenarios) return '';
      const types = [
        { key: 'death', label: 'Death' },
        { key: 'critical_illness', label: 'Critical Illness' },
        { key: 'disability', label: 'Long-Term Disability' },
      ];

      let html = '<div class="subsection-title" style="margin-top: 16px;">Scenario Analysis</div>';

      types.forEach(({ key, label }) => {
        const s = scenarios[key];
        if (!s) return;

        const statusColors = this.getStatusColors(s.adequacy);
        html += `
          <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px 12px; margin-bottom: 8px; page-break-inside: avoid;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
              <span style="font-size: 11px; font-weight: 600; color: #1f2937;">${this.escapeHtml(label)}</span>
              <span style="display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 9px; font-weight: 600; background: ${statusColors.bg}; color: ${statusColors.text};">${this.escapeHtml(s.adequacy || 'Unknown')}</span>
            </div>
        `;

        if (s.payout !== undefined) {
          html += `<div style="font-size: 10px; color: #374151;">Payout: <strong>${this.fmtCurrency(s.payout)}</strong></div>`;
        }
        if (s.monthly_benefit !== undefined && key === 'disability') {
          html += `<div style="font-size: 10px; color: #374151;">Monthly Benefit: <strong>${this.fmtCurrency(s.monthly_benefit)}</strong></div>`;
        }
        if (s.months_of_support > 0) {
          html += `<div style="font-size: 10px; color: #374151;">Months of Support: <strong>${Math.round(s.months_of_support)}</strong></div>`;
        }

        if (s.insights && s.insights.length > 0) {
          html += `<ul style="margin: 4px 0 0 16px; padding: 0;">`;
          s.insights.forEach(insight => {
            html += `<li style="font-size: 10px; color: #6b7280; margin-bottom: 2px;">${this.escapeHtml(insight)}</li>`;
          });
          html += `</ul>`;
        }

        html += `</div>`;
      });

      return html;
    },

    // ── Investment Builders ────────────────────────────────────────────

    buildAssetAllocationHtml(allocation) {
      if (!allocation || !Array.isArray(allocation) || allocation.length === 0) return '';

      const rows = allocation.map(a => {
        const label = (a.asset_type || 'Unknown').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
        return `
          <tr>
            <td style="font-weight: 500;">${this.escapeHtml(label)}</td>
            <td style="text-align: right;">${this.fmtCurrency(a.value || 0)}</td>
            <td style="text-align: right; font-weight: 600;">${this.fmtPercentage(a.percentage || 0)}</td>
          </tr>
        `;
      }).join('');

      return `
        <div class="subsection-title" style="margin-top: 16px;">Asset Allocation</div>
        <table>
          <thead><tr><th>Asset Class</th><th style="text-align: right;">Value</th><th style="text-align: right;">Allocation</th></tr></thead>
          <tbody>${rows}</tbody>
        </table>
      `;
    },

    buildFeeAnalysisHtml(feeAnalysis) {
      if (!feeAnalysis) return '';

      let html = '<div class="subsection-title" style="margin-top: 16px;">Fee Analysis</div>';

      // Fee breakdown by type
      if (feeAnalysis.fee_breakdown && feeAnalysis.fee_breakdown.length > 0) {
        const rows = feeAnalysis.fee_breakdown.map(f => `
          <tr>
            <td style="font-weight: 500;">${this.escapeHtml(f.type || 'Unknown')}</td>
            <td style="text-align: right;">${this.fmtPercentage(f.percent_of_portfolio || 0)}</td>
            <td style="text-align: right; font-weight: 600;">${this.fmtCurrency(f.amount || 0)}/year</td>
          </tr>
        `).join('');

        const totalRow = feeAnalysis.total_annual_fees !== undefined ? `
          <tr style="border-top: 2px solid #d1d5db;">
            <td style="font-weight: 600;">Total</td>
            <td style="text-align: right; font-weight: 600;">${this.fmtPercentage(feeAnalysis.fee_drag_percent || 0)}</td>
            <td style="text-align: right; font-weight: 700;">${this.fmtCurrency(feeAnalysis.total_annual_fees)}/year</td>
          </tr>
        ` : '';

        html += `
          <table>
            <thead><tr><th>Fee Type</th><th style="text-align: right;">Rate</th><th style="text-align: right;">Annual Cost</th></tr></thead>
            <tbody>${rows}${totalRow}</tbody>
          </table>
        `;
      }

      // Long-term fee impact
      if (feeAnalysis.fees_over_10_years || feeAnalysis.fees_over_20_years) {
        html += `<div style="font-size: 10px; color: #6b7280; margin-top: 4px;">`;
        if (feeAnalysis.fees_over_10_years) html += `10-year fee impact: <strong>${this.fmtCurrency(feeAnalysis.fees_over_10_years)}</strong>`;
        if (feeAnalysis.fees_over_20_years) html += ` &bull; 20-year: <strong>${this.fmtCurrency(feeAnalysis.fees_over_20_years)}</strong>`;
        html += `</div>`;
      }

      // High-fee holdings
      if (feeAnalysis.high_fee_holdings && feeAnalysis.high_fee_holdings.length > 0) {
        const holdingRows = feeAnalysis.high_fee_holdings.map(h => `
          <tr>
            <td style="font-weight: 500;">${this.escapeHtml(h.security_name || 'Unknown')}</td>
            <td style="text-align: right;">${this.fmtPercentage(h.ocf_percent || 0)}</td>
            <td style="text-align: right;">${this.fmtCurrency(h.annual_cost || 0)}/year</td>
            <td style="font-size: 10px; color: #6b7280;">${this.escapeHtml(h.recommendation || '')}</td>
          </tr>
        `).join('');

        html += `
          <div class="subsection-title" style="margin-top: 10px;">High-Fee Holdings</div>
          <table>
            <thead><tr><th>Holding</th><th style="text-align: right;">Ongoing Charge</th><th style="text-align: right;">Annual Cost</th><th>Recommendation</th></tr></thead>
            <tbody>${holdingRows}</tbody>
          </table>
        `;
      }

      return html;
    },

    buildTaxWrappersHtml(wrappers) {
      if (!wrappers) return '';

      const rows = [];
      if (wrappers.has_isa) {
        rows.push(['ISA', `Allowance: ${this.fmtCurrency(wrappers.isa_allowance || 20000)} | Used: ${this.fmtCurrency(wrappers.isa_used_this_year || 0)} | Remaining: ${this.fmtCurrency(wrappers.isa_remaining || 0)}`]);
      }
      if (wrappers.has_gia) {
        rows.push(['General Investment Account', this.fmtCurrency(wrappers.gia_value || 0)]);
      }
      if (wrappers.has_onshore_bond) {
        rows.push(['Onshore Bond', 'Yes']);
      }
      if (wrappers.has_offshore_bond) {
        rows.push(['Offshore Bond', 'Yes']);
      }

      if (rows.length === 0) return '';

      return `
        <div class="subsection-title" style="margin-top: 16px;">Tax Wrapper Summary</div>
        <table>
          <tbody>
            ${rows.map(([label, value]) => `
              <tr>
                <td style="font-weight: 500;">${this.escapeHtml(label)}</td>
                <td style="text-align: right; font-weight: 600;">${this.escapeHtml(value)}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      `;
    },

    // ── Retirement Builders ───────────────────────────────────────────

    buildRetirementSummaryHtml(summary) {
      if (!summary) return '';

      const metrics = [];
      if (summary.target_retirement_age) metrics.push({ label: 'Target Retirement Age', value: String(summary.target_retirement_age) });
      if (summary.years_to_retirement !== undefined) metrics.push({ label: 'Years to Retirement', value: String(summary.years_to_retirement) });
      if (summary.projected_retirement_income) metrics.push({ label: 'Projected Retirement Income', value: this.fmtCurrency(summary.projected_retirement_income) + '/year' });
      if (summary.target_retirement_income) metrics.push({ label: 'Target Retirement Income', value: this.fmtCurrency(summary.target_retirement_income) + '/year' });
      if (summary.income_gap !== undefined) {
        const gap = summary.income_gap;
        const color = gap > 0 ? '#b91c1c' : '#15803d';
        const label = gap > 0 ? 'Income Gap' : 'Income Surplus';
        metrics.push({ label, value: this.fmtCurrency(Math.abs(gap)) + '/year', color });
      }
      if (summary.total_dc_value) metrics.push({ label: 'Total Defined Contribution Value', value: this.fmtCurrency(summary.total_dc_value) });
      if (summary.state_pension_income) metrics.push({ label: 'State Pension Income', value: this.fmtCurrency(summary.state_pension_income) + '/year' });

      if (metrics.length === 0) return '';

      const cardsHtml = metrics.map(m => `
        <div class="metric-card">
          <div class="metric-label">${this.escapeHtml(m.label)}</div>
          <div class="metric-value"${m.color ? ` style="color: ${m.color};"` : ''}>${this.escapeHtml(m.value)}</div>
        </div>
      `).join('');

      return `
        <div class="subsection-title">Retirement Overview</div>
        <div class="metric-grid">${cardsHtml}</div>
      `;
    },

    buildIncomeProjectionHtml(projection) {
      if (!projection) return '';

      let html = '<div class="subsection-title" style="margin-top: 16px;">Income Projection at Retirement</div>';

      if (projection.dc_projections && projection.dc_projections.length > 0) {
        const rows = projection.dc_projections.map(p => `
          <tr>
            <td style="font-weight: 500;">${this.escapeHtml(p.scheme_name || 'Unknown')}</td>
            <td style="text-align: right; font-weight: 600;">${this.fmtCurrency(p.projected_value || 0)}</td>
            <td style="text-align: right; font-size: 10px; color: #64748b;">${this.fmtPercentage(p.growth_rate_used || 0)} growth</td>
          </tr>
        `).join('');

        html += `
          <table>
            <thead><tr><th>Pension</th><th style="text-align: right;">Projected Value</th><th style="text-align: right;">Growth Rate</th></tr></thead>
            <tbody>${rows}</tbody>
          </table>
        `;
      }

      const incomeRows = [];
      if (projection.dc_annual_income) incomeRows.push(['Defined Contribution Income', this.fmtCurrency(projection.dc_annual_income) + '/year']);
      if (projection.db_annual_income) incomeRows.push(['Defined Benefit Income', this.fmtCurrency(projection.db_annual_income) + '/year']);
      if (projection.state_pension_income) incomeRows.push(['State Pension', this.fmtCurrency(projection.state_pension_income) + '/year']);
      if (projection.total_projected_income) incomeRows.push(['Total Projected Income', this.fmtCurrency(projection.total_projected_income) + '/year']);

      if (incomeRows.length > 0) {
        html += `
          <table style="margin-top: 8px;">
            <tbody>
              ${incomeRows.map(([label, value], i) => `
                <tr${i === incomeRows.length - 1 ? ' style="border-top: 2px solid #d1d5db;"' : ''}>
                  <td${i === incomeRows.length - 1 ? ' style="font-weight: 600;"' : ''}>${this.escapeHtml(label)}</td>
                  <td style="text-align: right; font-weight: ${i === incomeRows.length - 1 ? '700' : '500'};">${this.escapeHtml(value)}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        `;
      }

      return html;
    },

    buildAnnualAllowanceHtml(aa) {
      if (!aa) return '';

      const rows = [
        ['Tax Year', aa.tax_year || 'N/A'],
        ['Standard Allowance', this.fmtCurrency(aa.standard_allowance || 0)],
        ['Total Contributions', this.fmtCurrency(aa.total_contributions || 0)],
        ['Remaining Allowance', this.fmtCurrency(aa.remaining_allowance || 0)],
      ];

      if (aa.is_tapered) rows.push(['Tapered', 'Yes']);
      if (aa.carry_forward_available > 0) rows.push(['Carry Forward Available', this.fmtCurrency(aa.carry_forward_available)]);
      if (aa.has_excess) rows.push(['Excess Contributions', this.fmtCurrency(aa.excess_contributions || 0)]);

      return `
        <div class="subsection-title" style="margin-top: 16px;">Annual Allowance</div>
        <table>
          <tbody>
            ${rows.map(([label, value]) => `
              <tr>
                <td>${this.escapeHtml(String(label))}</td>
                <td style="text-align: right; font-weight: 600;">${this.escapeHtml(String(value))}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      `;
    },

    // ── Estate Plan Builders ──────────────────────────────────────────

    buildStructuredExecutiveSummaryHtml(summary, planType) {
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

      // Protection: Coverage Summary table
      if (summary.coverage_summary && summary.coverage_summary.length > 0) {
        const coverageRows = summary.coverage_summary.map(item => {
          const isAdequate = (item.status || '').toLowerCase() === 'adequate';
          const badgeClass = isAdequate ? 'badge-green' : 'badge-red';
          return `<tr>
            <td style="font-weight: 500;">${this.escapeHtml(item.name)}</td>
            <td style="text-align: right;">${this.fmtCurrency(item.need || 0)}</td>
            <td style="text-align: right;">${this.fmtCurrency(item.coverage || 0)}</td>
            <td style="text-align: right; font-weight: 600;">${this.fmtCurrency(item.gap || 0)}</td>
            <td style="text-align: center;"><span class="badge ${badgeClass}">${this.escapeHtml(item.status || 'Unknown')}</span></td>
          </tr>`;
        }).join('');

        html += `
          <div class="subsection-title">Coverage Summary</div>
          <table>
            <thead><tr><th>Name</th><th style="text-align: right;">Need</th><th style="text-align: right;">Coverage</th><th style="text-align: right;">Gap</th><th style="text-align: center;">Status</th></tr></thead>
            <tbody>${coverageRows}</tbody>
          </table>
        `;
      }

      // Investment/Retirement: Goals Summary table
      if (summary.goals_summary && summary.goals_summary.length > 0) {
        const goalsTitle = planType === 'retirement' ? 'Your Retirement Goals' : 'Your Investment Goals';
        const goalsRows = summary.goals_summary.map(item => {
          const isOnTrack = !!item.on_track;
          const badgeClass = isOnTrack ? 'badge-green' : 'badge-red';
          const statusLabel = isOnTrack ? 'On track' : 'Needs attention';
          const progressLabel = item.progress !== null && item.progress !== undefined ? Math.round(item.progress) + '%' : 'N/A';
          return `<tr>
            <td style="font-weight: 500;">${this.escapeHtml(item.name || 'Unnamed')}</td>
            <td style="text-align: right;">${this.fmtCurrency(item.target || 0)}</td>
            <td style="text-align: right;">${this.escapeHtml(progressLabel)}</td>
            <td style="text-align: center;"><span class="badge ${badgeClass}">${this.escapeHtml(statusLabel)}</span></td>
          </tr>`;
        }).join('');

        html += `
          <div class="subsection-title">${this.escapeHtml(goalsTitle)}</div>
          <table>
            <thead><tr><th>Goal</th><th style="text-align: right;">Target</th><th style="text-align: right;">Progress</th><th style="text-align: center;">Status</th></tr></thead>
            <tbody>${goalsRows}</tbody>
          </table>
        `;
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

    buildPersonalInformationHtml(info, planType) {
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

      // 4th card varies by plan type
      let fourthCardTitle = '';
      const fourthCardRows = [];

      if (planType === 'estate') {
        fourthCardTitle = 'Estate Profile';
        if (info.estimated_age_at_death) fourthCardRows.push(['Estimated Age at Death', info.estimated_age_at_death]);
        if (info.years_to_death) fourthCardRows.push(['Years to Planning Horizon', info.years_to_death]);
        if (info.marital_status_iht) {
          const ihtStatusMap = { married: 'Married / Civil Partnership', widowed: 'Widowed', single: 'Single', civil_partnership: 'Civil Partnership' };
          fourthCardRows.push(['Inheritance Tax Status', ihtStatusMap[info.marital_status_iht] || info.marital_status_iht]);
        }
        fourthCardRows.push(['Has Will', info.has_will ? 'Yes' : 'No']);
      } else if (planType === 'protection') {
        fourthCardTitle = 'Protection Profile';
        if (info.occupation) fourthCardRows.push(['Occupation', info.occupation]);
        if (info.smoker_status) fourthCardRows.push(['Smoker Status', info.smoker_status]);
        if (info.health_status) fourthCardRows.push(['Health Status', info.health_status]);
        if (info.retirement_age) fourthCardRows.push(['Planned Retirement Age', info.retirement_age]);
      } else if (planType === 'investment' || planType === 'retirement') {
        fourthCardTitle = 'Risk Profile';
        if (info.risk_level) fourthCardRows.push(['Risk Level', info.risk_level]);
      }

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
          ${fourthCardRows.length > 0 ? buildCard(fourthCardTitle, fourthCardRows) : ''}
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

    // ── Holistic Plan Print ──────────────────────────────────────────

    printHolisticPlan(plans) {
      if (!plans || Object.keys(plans).length === 0) return;
      this.generatingPdf = true;

      const printWindow = window.open('', '_blank', 'width=800,height=600');
      if (!printWindow) {
        alert('Please allow pop-ups to print the plan');
        this.generatingPdf = false;
        return;
      }

      const html = this.buildHolisticPlanHtml(plans);

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

    buildHolisticPlanHtml(plans) {
      const title = 'Holistic Financial Plan';
      const date = new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
      const firstPlan = plans.investment || plans.retirement || plans.protection || plans.estate;
      const userName = firstPlan?.metadata?.user_name || '';
      const personalInfo = firstPlan?.personal_information || {};
      const planTypes = Object.keys(plans);
      const planNames = {
        protection: 'Protection',
        investment: 'Investment & Savings',
        retirement: 'Retirement',
        estate: 'Estate Planning',
      };

      // Build module sections
      let currentSituationHtml = '';
      let actionsHtml = '';

      planTypes.forEach(type => {
        const plan = plans[type];
        if (!plan) return;
        const moduleName = planNames[type] || type;

        // Current Situation per module
        const situationContent = this.buildCurrentSituationByType(plan, type);
        if (situationContent) {
          currentSituationHtml += `
            <div class="section">
              <div class="subsection-title">${this.escapeHtml(moduleName)}</div>
              ${situationContent}
            </div>
          `;
        }

        // Actions per module
        const actionsContent = this.buildActionsByType(plan, type);
        const whatIfContent = this.buildWhatIfByType(plan, type);
        if (actionsContent || whatIfContent) {
          actionsHtml += `
            <div class="section">
              <div class="subsection-title">${this.escapeHtml(moduleName)}</div>
              ${actionsContent}
              ${whatIfContent}
            </div>
          `;
        }
      });

      // Priority Area
      const priorityHtml = this.buildHolisticPriorityAreaHtml(plans, personalInfo);

      // Aggregated Conclusion
      const conclusionHtml = this.buildHolisticConclusionHtml(plans);

      return `
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>${this.escapeHtml(title)}</title>
  <style>
    @page { size: A4; margin: 0; }
    @media print { html, body { margin: 0; padding: 0; } }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      font-size: 11px; line-height: 1.4; color: #1f2937;
      background: white; padding: 0;
      -webkit-print-color-adjust: exact; print-color-adjust: exact;
      position: relative; min-height: 100vh;
    }
    .page-header {
      position: fixed; top: 0; left: 0; right: 0;
      display: flex; align-items: center; justify-content: space-between;
      padding: 10mm 15mm 4px 15mm; border-bottom: 1px solid #e2e8f0;
      background: white; z-index: 999;
    }
    .page-header-logo { height: 26px; width: auto; }
    .page-header-text { font-size: 9px; color: #94a3b8; letter-spacing: 0.3px; }
    .plan-content { padding: 20mm 15mm 16mm 15mm; }
    .title-page {
      position: relative; padding: 10mm 15mm 0 15mm;
      page-break-after: always; break-after: page;
    }
    .title-page .logo { position: absolute; top: 10mm; right: 15mm; height: 110px; width: auto; }
    .header-content { text-align: center; padding-top: 280px; }
    .header-content h1 { font-size: 28px; font-weight: 700; color: #0f172a; margin-bottom: 8px; }
    .header-content .subtitle { font-size: 13px; color: #64748b; margin-bottom: 4px; }
    .header-content .date { font-size: 12px; color: #64748b; }
    .section { margin-bottom: 16px; page-break-inside: auto; }
    .section-title {
      font-size: 15px; font-weight: 700; color: #0f172a;
      padding-bottom: 6px; margin-bottom: 12px; border-bottom: 2px solid #e2e8f0;
      page-break-after: avoid; page-break-inside: avoid;
    }
    .section-subtitle {
      font-size: 10px; color: #64748b; margin-top: -8px; margin-bottom: 12px;
      page-break-after: avoid; page-break-inside: avoid;
    }
    .narrative { font-size: 11px; color: #374151; line-height: 1.6; white-space: pre-wrap; }
    .subsection-title {
      font-size: 12px; font-weight: 600; color: #374151;
      margin-bottom: 6px; margin-top: 14px; page-break-after: avoid;
    }
    .action-item { display: flex; align-items: flex-start; margin-bottom: 10px; break-inside: avoid; }
    .action-number {
      background: #f3f4f6; color: #374151; width: 18px; height: 18px;
      border-radius: 50%; display: flex; align-items: center; justify-content: center;
      font-size: 10px; font-weight: 700; margin-right: 8px; flex-shrink: 0;
    }
    .action-text { font-size: 11px; color: #374151; line-height: 1.4; }
    .action-detail { font-size: 10px; color: #64748b; margin-top: 2px; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 9px; font-weight: 600; }
    .badge-red { background: #fee2e2; color: #991b1b; }
    .badge-blue { background: #dbeafe; color: #1e40af; }
    .badge-gray { background: #f3f4f6; color: #374151; }
    .badge-green { background: #dcfce7; color: #166534; }
    .badge-purple { background: #f3e8ff; color: #6b21a8; }
    table { width: 100%; border-collapse: collapse; font-size: 11px; margin-top: 6px; margin-bottom: 8px; }
    th, td { border: 1px solid #e5e7eb; padding: 6px 10px; text-align: left; }
    th { background: #f9fafb; font-weight: 600; color: #374151; }
    .conclusion-box {
      background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 6px;
      padding: 12px; margin-top: 12px; font-size: 11px; line-height: 1.6; color: #374151;
    }
    .disabled-actions { margin-top: 12px; }
    .disabled-action {
      font-size: 10px; color: #6b7280; margin-bottom: 4px; padding-left: 12px; position: relative;
    }
    .disabled-action::before { content: '\\2014'; position: absolute; left: 0; }
    .footer {
      position: fixed; bottom: 0; left: 0; right: 0;
      display: flex; justify-content: space-between; align-items: center;
      font-size: 9px; color: #94a3b8; padding: 4px 15mm 8mm 15mm;
      border-top: 1px solid #e2e8f0; background: white; z-index: 1000;
    }
    .footer-left { text-align: left; }
    .footer-right { text-align: right; font-size: 10px; color: #64748b; }
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
    .priority-bar {
      width: 100%; height: 10px; background: #f3f4f6; border-radius: 5px; overflow: hidden; margin: 6px 0;
    }
    .priority-bar-fill { height: 100%; border-radius: 5px; }
    .priority-row {
      display: flex; align-items: center; justify-content: space-between;
      padding: 6px 8px; margin-bottom: 4px; border-radius: 4px;
      border: 1px solid #e5e7eb; font-size: 10px;
    }
    .priority-row-exceeded { background: #fef2f2; border-color: #fecaca; }
    .priority-row-disabled { opacity: 0.5; }
  </style>
</head>
<body>
  <div class="page-header">
    <img src="${this.logoUrl}" alt="Fynla" class="page-header-logo" />
    <span class="page-header-text">${this.escapeHtml(title)} &bull; ${this.escapeHtml(userName)}</span>
  </div>

  <div class="footer">
    <div class="footer-left">
      This document was generated by Fynla Financial Planning Software &bull; www.fynla.org &bull; This is not financial advice
    </div>
    <div class="footer-right">
      Prepared by ${this.escapeHtml(userName)}
    </div>
  </div>

  <div class="title-page">
    <img src="${this.logoUrl}" alt="Fynla" class="logo" />
    <div class="header-content">
      <h1>${this.escapeHtml(title)}</h1>
      <div class="subtitle">Prepared for ${this.escapeHtml(userName)}</div>
      <div class="date">${this.escapeHtml(date)}</div>
    </div>
  </div>

  <div class="plan-content">
    <!-- Executive Summary -->
    <div class="section">
      <div class="section-title">Executive Summary</div>
      <div class="section-subtitle">Your holistic financial plan overview</div>
      <div class="narrative">This holistic financial plan brings together your individual module plans into a single unified view, covering ${this.escapeHtml(planTypes.map(t => planNames[t] || t).join(', '))}.</div>
      <div style="margin-top: 8px; display: flex; gap: 6px; flex-wrap: wrap;">
        ${planTypes.map(t => `<span class="badge badge-blue">${this.escapeHtml(planNames[t] || t)}</span>`).join('')}
      </div>
    </div>

    ${personalInfo ? this.buildPersonalInformationHtml(personalInfo, 'investment') : ''}

    <!-- Current Situation -->
    <div class="section">
      <div class="section-title">Current Situation</div>
      <div class="section-subtitle">Overview across all financial areas</div>
      ${currentSituationHtml}
    </div>

    <!-- Recommended Actions -->
    <div class="section">
      <div class="section-title">Recommended Actions</div>
      <div class="section-subtitle">Actions grouped by financial area</div>
      ${actionsHtml}
    </div>

    <!-- Priority Area -->
    ${priorityHtml}

    <!-- Conclusion -->
    ${conclusionHtml}
  </div>
</body>
</html>`;
    },

    buildHolisticPriorityAreaHtml(plans, personalInfo) {
      const monthlyDisposable = personalInfo?.monthly_disposable || 0;
      const planNames = {
        protection: 'Protection',
        investment: 'Investment & Savings',
        retirement: 'Retirement',
        estate: 'Estate',
      };

      // Collect all enabled actions with costs
      const allActions = [];
      Object.keys(plans).forEach(type => {
        const plan = plans[type];
        if (!plan || !plan.actions) return;
        plan.actions.forEach(action => {
          if (!action.enabled) return;
          const monthlyCost = this.extractHolisticMonthlyCost(action);
          allActions.push({
            title: action.title,
            priority: action.priority || 'medium',
            sourceModule: type,
            monthlyCost,
          });
        });
      });

      // Sort: goals first, tax optimisation, then priority
      const priorityRank = { critical: 0, high: 1, medium: 2, low: 3 };
      allActions.sort((a, b) => (priorityRank[a.priority] ?? 2) - (priorityRank[b.priority] ?? 2));

      if (allActions.length === 0) return '';

      const totalAllocated = allActions.reduce((sum, a) => sum + a.monthlyCost, 0);
      const pct = monthlyDisposable > 0 ? Math.min(100, (totalAllocated / monthlyDisposable) * 100) : 0;
      const barColor = pct >= 100 ? '#ef4444' : pct >= 80 ? '#3b82f6' : '#22c55e';

      let cumulative = 0;
      const rows = allActions.map(a => {
        cumulative += a.monthlyCost;
        const exceeded = cumulative > monthlyDisposable && monthlyDisposable > 0;
        const badgeMap = { critical: 'badge-red', high: 'badge-blue', medium: 'badge-gray', low: 'badge-green' };
        const moduleBadge = { protection: 'badge-purple', investment: 'badge-blue', retirement: 'badge-green', estate: 'badge-gray' };

        return `
          <div class="priority-row ${exceeded ? 'priority-row-exceeded' : ''}">
            <div style="flex: 1;">
              <span class="badge ${badgeMap[a.priority] || 'badge-gray'}">${this.escapeHtml((a.priority || 'medium').charAt(0).toUpperCase() + (a.priority || 'medium').slice(1))}</span>
              <span class="badge ${moduleBadge[a.sourceModule] || 'badge-gray'}" style="margin-left: 4px;">${this.escapeHtml(planNames[a.sourceModule] || a.sourceModule)}</span>
              <span style="margin-left: 8px; font-weight: 500;">${this.escapeHtml(a.title)}</span>
            </div>
            <div style="text-align: right; white-space: nowrap;">
              ${this.fmtCurrency(a.monthlyCost)}/mo
              ${exceeded ? '<span style="color: #dc2626; font-size: 9px; margin-left: 4px;">Over budget</span>' : ''}
            </div>
          </div>
        `;
      }).join('');

      return `
      <div class="section">
        <div class="section-title">Priority Area</div>
        <div class="section-subtitle">All actions ranked and allocated against your shared monthly disposable income</div>
        <div style="display: flex; justify-content: space-between; font-size: 10px; margin-bottom: 4px;">
          <span>Monthly Disposable: <strong>${this.fmtCurrency(monthlyDisposable)}</strong></span>
          <span>Total Allocated: <strong style="color: ${totalAllocated > monthlyDisposable ? '#dc2626' : '#16a34a'}">${this.fmtCurrency(totalAllocated)}</strong></span>
        </div>
        <div class="priority-bar">
          <div class="priority-bar-fill" style="width: ${Math.round(pct)}%; background: ${barColor};"></div>
        </div>
        <div style="font-size: 9px; color: #64748b; margin-bottom: 10px;">${Math.round(pct)}% allocated</div>
        ${rows}
      </div>
      `;
    },

    buildHolisticConclusionHtml(plans) {
      const allActions = [];
      const planNames = {
        protection: 'Protection',
        investment: 'Investment & Savings',
        retirement: 'Retirement',
        estate: 'Estate',
      };

      Object.keys(plans).forEach(type => {
        const plan = plans[type];
        if (!plan || !plan.actions) return;
        plan.actions.filter(a => a.enabled).forEach(action => {
          allActions.push({
            title: action.title,
            priority: action.priority || 'medium',
            sourceModule: type,
          });
        });
      });

      const essential = allActions.filter(a => a.priority === 'critical' || a.priority === 'high');
      const optional = allActions.filter(a => a.priority !== 'critical' && a.priority !== 'high');

      let essentialHtml = '';
      if (essential.length) {
        essentialHtml = `
          <div style="margin-top: 10px;">
            <div style="font-size: 9px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">Priority Actions</div>
            ${essential.map((a, i) => `
              <div class="action-item">
                <div class="action-number" style="${a.priority === 'critical' ? 'background: #fee2e2; color: #991b1b;' : ''}">${i + 1}</div>
                <div class="action-text">${this.escapeHtml(a.title)} <span style="font-size: 9px; color: #6b7280;">(${this.escapeHtml(planNames[a.sourceModule] || a.sourceModule)})</span></div>
              </div>
            `).join('')}
          </div>
        `;
      }

      let optionalHtml = '';
      if (optional.length) {
        optionalHtml = `
          <div style="margin-top: 10px;">
            <div style="font-size: 9px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">Optional Improvements</div>
            ${optional.map(a => `
              <div class="disabled-action">${this.escapeHtml(a.title)} <span style="font-size: 9px; color: #6b7280;">(${this.escapeHtml(planNames[a.sourceModule] || a.sourceModule)})</span></div>
            `).join('')}
          </div>
        `;
      }

      return `
      <div class="section">
        <div class="section-title">Conclusion</div>
        <div class="section-subtitle">Aggregated summary across all plans</div>
        <div class="conclusion-box">
          This holistic plan brings together recommendations from ${Object.keys(plans).length} area${Object.keys(plans).length !== 1 ? 's' : ''} of your financial life.
        </div>
        ${essentialHtml}
        ${optionalHtml}
      </div>
      `;
    },

    extractHolisticMonthlyCost(action) {
      if (action.cascade_params?.additional_monthly) return action.cascade_params.additional_monthly;
      if (action.impact_parameters?.monthly_premium_estimate) return action.impact_parameters.monthly_premium_estimate;
      if (action.impact_parameters?.premium) return action.impact_parameters.premium;
      if (action.affordability?.monthly_premium_estimate) return action.affordability.monthly_premium_estimate;
      if (action.estimated_impact) return Math.abs(action.estimated_impact) / 12;
      return 0;
    },
  },
};
