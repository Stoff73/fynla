import { currencyMixin } from '@/mixins/currencyMixin';
import logoImage from '@/assets/logoTransparent.png';

/**
 * Mixin for printing/PDF export of plans.
 * Follows the Letter to Spouse pattern for print window generation.
 */
export const planPrintMixin = {
  mixins: [currencyMixin],

  data() {
    return {
      generatingPdf: false,
    };
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

      // Build print document using DOM manipulation
      this.buildPrintDocument(printWindow, plan, title);

      const triggerPrint = () => {
        printWindow.print();
        printWindow.onafterprint = () => printWindow.close();
        setTimeout(() => {
          if (!printWindow.closed) printWindow.close();
        }, 1000);
        this.generatingPdf = false;
      };

      const logoImg = printWindow.document.querySelector('.logo');
      if (logoImg) {
        let handled = false;
        const handleLoad = () => {
          if (!handled) {
            handled = true;
            setTimeout(triggerPrint, 250);
          }
        };
        logoImg.addEventListener('load', handleLoad);
        logoImg.addEventListener('error', handleLoad);
        setTimeout(() => { if (!handled) handleLoad(); }, 3000);
      } else {
        setTimeout(triggerPrint, 250);
      }
    },

    buildPrintDocument(printWindow, plan, title) {
      const doc = printWindow.document;
      doc.open();

      const date = new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
      const userName = plan.metadata?.user_name || '';
      const summary = plan.executive_summary || {};
      const conclusion = plan.conclusion || {};
      const actions = (plan.actions || []).filter(a => a.enabled);
      const whatIf = plan.what_if || {};

      const el = (tag, text, className) => {
        const element = doc.createElement(tag);
        if (text) element.textContent = text;
        if (className) element.className = className;
        return element;
      };

      const fmtCurrency = (val) => {
        if (val === null || val === undefined) return 'N/A';
        return new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(val);
      };

      // Set title
      doc.title = title;

      // Add styles
      const style = doc.createElement('style');
      style.textContent = `
        @page { size: A4; margin: 15mm; }
        @media print { html, body { margin: 0; padding: 0; } }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 11px; line-height: 1.5; color: #1f2937; background: white; padding: 15mm; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #e5e7eb; padding-bottom: 15px; margin-bottom: 20px; }
        .header h1 { font-size: 22px; font-weight: 700; color: #0f172a; }
        .meta { font-size: 11px; color: #64748b; text-align: right; }
        .logo { height: 50px; width: auto; }
        .section { margin-bottom: 20px; page-break-inside: avoid; }
        .section-title { font-size: 14px; font-weight: 700; color: #0f172a; border-bottom: 1px solid #e5e7eb; padding-bottom: 6px; margin-bottom: 10px; }
        .narrative { font-size: 11px; color: #374151; margin-bottom: 15px; line-height: 1.6; }
        .metrics-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 15px; }
        .metric { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px; }
        .metric-label { font-size: 9px; text-transform: uppercase; color: #6b7280; letter-spacing: 0.5px; }
        .metric-value { font-size: 14px; font-weight: 700; color: #111827; margin-top: 2px; }
        ul { padding-left: 18px; }
        li { margin-bottom: 8px; font-size: 11px; }
        .priority { font-size: 9px; color: #6b7280; text-transform: uppercase; }
        .desc { color: #4b5563; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        th, td { border: 1px solid #e5e7eb; padding: 6px 10px; text-align: left; }
        th { background: #f9fafb; font-weight: 600; color: #374151; }
        .conclusion { background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 6px; padding: 12px; margin-top: 15px; }
        .footer { margin-top: 30px; padding-top: 10px; border-top: 1px solid #e5e7eb; font-size: 9px; color: #9ca3af; text-align: center; }
      `;
      doc.head.appendChild(style);

      // Header
      const header = el('div', null, 'header');
      const headerLeft = doc.createElement('div');
      headerLeft.appendChild(el('h1', title));
      const metaDiv = el('div', null, 'meta');
      metaDiv.appendChild(doc.createTextNode('Prepared for ' + userName));
      metaDiv.appendChild(doc.createElement('br'));
      metaDiv.appendChild(doc.createTextNode(date));
      headerLeft.appendChild(metaDiv);
      header.appendChild(headerLeft);
      const logo = doc.createElement('img');
      logo.src = logoImage;
      logo.className = 'logo';
      logo.alt = 'Fynla';
      header.appendChild(logo);
      doc.body.appendChild(header);

      // Executive Summary
      const summarySection = el('div', null, 'section');
      summarySection.appendChild(el('div', 'Executive Summary', 'section-title'));
      summarySection.appendChild(el('div', summary.narrative || '', 'narrative'));

      if (summary.key_metrics && summary.key_metrics.length) {
        const grid = el('div', null, 'metrics-grid');
        summary.key_metrics.forEach(m => {
          let val = m.value;
          if (m.format === 'currency') val = fmtCurrency(m.value);
          else if (m.format === 'percentage') val = m.value + '%';
          else if (m.suffix) val = m.value + ' ' + m.suffix;

          const metric = el('div', null, 'metric');
          metric.appendChild(el('div', m.label, 'metric-label'));
          metric.appendChild(el('div', String(val ?? 'N/A'), 'metric-value'));
          grid.appendChild(metric);
        });
        summarySection.appendChild(grid);
      }
      doc.body.appendChild(summarySection);

      // Actions
      if (actions.length) {
        const actionsSection = el('div', null, 'section');
        actionsSection.appendChild(el('div', 'Recommended Actions', 'section-title'));
        const ul = doc.createElement('ul');
        actions.forEach(a => {
          const li = doc.createElement('li');
          li.appendChild(el('strong', a.title));
          li.appendChild(doc.createTextNode(' [' + a.priority + ']'));
          li.appendChild(doc.createElement('br'));
          li.appendChild(el('span', a.description, 'desc'));
          ul.appendChild(li);
        });
        actionsSection.appendChild(ul);
        doc.body.appendChild(actionsSection);
      }

      // What-If table
      if (whatIf.current_scenario && whatIf.projected_scenario) {
        const whatIfSection = el('div', null, 'section');
        whatIfSection.appendChild(el('div', 'Projected Outcomes', 'section-title'));
        const table = doc.createElement('table');
        const thead = doc.createElement('thead');
        const tr = doc.createElement('tr');
        tr.appendChild(el('th', 'Metric'));
        tr.appendChild(el('th', 'Current'));
        tr.appendChild(el('th', 'Projected'));
        thead.appendChild(tr);
        table.appendChild(thead);
        const tbody = doc.createElement('tbody');
        Object.keys(whatIf.current_scenario).forEach(key => {
          if (whatIf.projected_scenario[key] !== undefined) {
            const label = key.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
            const curVal = typeof whatIf.current_scenario[key] === 'number' ? fmtCurrency(whatIf.current_scenario[key]) : String(whatIf.current_scenario[key]);
            const projVal = typeof whatIf.projected_scenario[key] === 'number' ? fmtCurrency(whatIf.projected_scenario[key]) : String(whatIf.projected_scenario[key]);
            const row = doc.createElement('tr');
            row.appendChild(el('td', label));
            row.appendChild(el('td', curVal));
            row.appendChild(el('td', projVal));
            tbody.appendChild(row);
          }
        });
        table.appendChild(tbody);
        whatIfSection.appendChild(table);
        doc.body.appendChild(whatIfSection);
      }

      // Conclusion
      if (conclusion.summary_text) {
        const concDiv = el('div', null, 'conclusion');
        concDiv.appendChild(el('strong', 'Conclusion: '));
        concDiv.appendChild(doc.createTextNode(conclusion.summary_text));
        doc.body.appendChild(concDiv);
      }

      // Footer
      doc.body.appendChild(el('div', 'Generated by Fynla \u00B7 ' + date + ' \u00B7 This is not financial advice', 'footer'));

      doc.close();
    },
  },
};
