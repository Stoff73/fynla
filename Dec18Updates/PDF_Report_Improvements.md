# PDF Report Download Improvements

## Overview
Implemented proper PDF generation for all three planning module reports using html2pdf.js library. Previously, downloads used browser print functionality which required users to adjust browser settings and produced inconsistent results.

---

## Changes Made

### New Dependencies
- Added `html2pdf.js` npm package for client-side PDF generation

### New Files Created
- `resources/js/components/Common/PrintHeader.vue` - Reusable PDF header component with logo

### Files Modified
- `resources/css/app.css` - Added print styles and PDF header visibility rules
- `resources/js/views/Protection/ComprehensiveProtectionPlan.vue` - PDF generation with logo and page breaks
- `resources/js/views/Estate/ComprehensiveEstatePlan.vue` - PDF generation with logo and page breaks
- `resources/js/views/Plans/InvestmentSavingsPlan.vue` - Added download button and PDF generation
- `resources/js/components/Plans/InvestmentSavingsPlanView.vue` - Added page break classes to sections

---

## Features

### 1. Proper PDF Generation
- Uses html2pdf.js library instead of browser print
- Generates actual PDF file that downloads automatically
- No browser settings required
- Consistent output across all browsers

### 2. PDF Header with Logo
- Fynla logo appears at top of first page in PDF
- Includes plan title and generation date
- Header is hidden on screen, only visible in downloaded PDF

### 3. Page Breaks Between Sections
- Each major section starts on a new page
- Prevents content from splitting awkwardly across pages
- Uses CSS class `pdf-page-break` to mark section boundaries

### 4. Clean PDF Output
- No navbar, footer, or download buttons in PDF
- Professional formatting with consistent margins (10mm)
- Preserves gradient backgrounds and colors

---

## Technical Implementation

### PDF Generation (`downloadPDF` method)
```javascript
downloadPDF() {
  const element = document.getElementById('plan-document');
  if (!element) return;

  // Show the PDF header before generating
  const pdfHeader = element.querySelector('.pdf-header');
  if (pdfHeader) pdfHeader.style.display = 'flex';

  const opt = {
    margin: [10, 10, 10, 10],
    filename: `Plan_${new Date().toISOString().split('T')[0]}.pdf`,
    image: { type: 'jpeg', quality: 0.98 },
    html2canvas: { scale: 2, useCORS: true, letterRendering: true },
    jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
    pagebreak: { mode: ['css'], before: '.pdf-page-break' },
  };

  html2pdf().set(opt).from(element).save().then(() => {
    // Hide the PDF header after generating
    if (pdfHeader) pdfHeader.style.display = 'none';
  });
}
```

### CSS for PDF Header Visibility
```css
/* PDF Header - hidden on screen, shown when generating PDF */
.pdf-header {
  display: none;
}
```

### Page Break Class
```html
<section class="mb-12 pdf-page-break">
  <!-- Section content -->
</section>
```

---

## PDF Output Structure

### Protection Plan
1. Logo Header + Executive Summary
2. Your Profile (new page)
3. Financial Summary (new page)
4. Current Protection Coverage (new page)
5. Coverage Gap Analysis (new page)
6. Optimised Protection Strategy (new page)
7. Recommendations (new page)

### Estate Plan
1. Logo Header + Executive Summary
2. Your Profile (new page)
3. Estate Summary (new page)
4. IHT Analysis (new page)
5. Mitigation Strategies (new page)
6. Timeline & Actions (new page)
7. Recommendations (new page)

### Investment & Savings Plan
1. Logo Header + Executive Summary
2. Investment Portfolio (new page)
3. Savings & Emergency Fund (new page)
4. Priority Action Plan (new page)

---

## File Output Names
- `Protection_Plan_YYYY-MM-DD.pdf`
- `Estate_Plan_YYYY-MM-DD.pdf`
- `Investment_Savings_Plan_YYYY-MM-DD.pdf`
