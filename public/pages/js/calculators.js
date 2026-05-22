/**
 * Fynla — Calculators page
 * Vanilla JS replacement for CalculatorsPage.vue.
 * All calculator logic ported verbatim from the Vue source.
 */

/* ===================================================================
   TAX CONFIG — loaded from /api/public/tax-config on boot
   =================================================================== */
var taxConfig = {};

function loadTaxConfig() {
  return fetch('/api/public/tax-config')
    .then(function (res) { return res.json(); })
    .then(function (data) {
      taxConfig = data;
      calculateIncomeTax();
    })
    .catch(function () {
      console.warn('[Fynla] Tax config unavailable — using static fallback values.');
      taxConfig = {
        personal_allowance: 12570,
        personal_allowance_taper_threshold: 100000,
        higher_rate_threshold: 50270,
        additional_rate_threshold: 125140,
        income_tax_basic_rate: 0.20,
        income_tax_higher_rate: 0.40,
        income_tax_additional_rate: 0.45,
        ni_primary_threshold: 12570,
        ni_upper_earnings_limit: 50270,
        ni_basic_rate: 0.08,
        ni_additional_rate: 0.02,
        pension_tax_free_cash_rate: 0.25,
        pension_tax_free_lump_sum_limit: 268275,
        student_loan_repayment_rate: 0.09,
        /* Stamp duty bands */
        sdlt_standard_bands: [
          { threshold: 125000, rate: 0 },
          { threshold: 250000, rate: 2 },
          { threshold: 925000, rate: 5 },
          { threshold: 1500000, rate: 10 },
          { threshold: Infinity, rate: 12 }
        ],
        sdlt_ftb_bands: [
          { threshold: 300000, rate: 0 },
          { threshold: 500000, rate: 5 },
          { threshold: 925000, rate: 5 },
          { threshold: 1500000, rate: 10 },
          { threshold: Infinity, rate: 12 }
        ],
        sdlt_ftb_max_price: 500000,
        sdlt_additional_surcharge: 5,
        sdlt_non_uk_surcharge: 2,
        lbtt_bands: [
          { threshold: 145000, rate: 0 },
          { threshold: 250000, rate: 2 },
          { threshold: 325000, rate: 5 },
          { threshold: 750000, rate: 10 },
          { threshold: Infinity, rate: 12 }
        ],
        lbtt_additional_surcharge: 6,
        lbtt_non_uk_surcharge: 2,
        ltt_bands: [
          { threshold: 225000, rate: 0 },
          { threshold: 400000, rate: 6 },
          { threshold: 750000, rate: 7.5 },
          { threshold: 1500000, rate: 10 },
          { threshold: Infinity, rate: 12 }
        ],
        ltt_additional_surcharge: 4,
        ltt_non_uk_surcharge: 2,
      };
      calculateIncomeTax();
    });
}

/* Helper: resolve taxConfig keys — the public API nests values differently from the Vue store getters */
function tc(path) {
  /* Supports dot-notation paths matching what the API returns:
     income_tax.personal_allowance  OR flat  personal_allowance */
  if (path.indexOf('.') === -1) {
    return taxConfig[path] !== undefined ? taxConfig[path] : null;
  }
  var parts = path.split('.');
  var obj = taxConfig;
  for (var i = 0; i < parts.length; i++) {
    if (obj == null) return null;
    obj = obj[parts[i]];
  }
  return obj !== undefined ? obj : null;
}

/* Convenience accessors matching Vue Vuex getter names */
function getPersonalAllowance()              { return tc('income_tax.personal_allowance') || tc('personal_allowance') || 12570; }
function getPersonalAllowanceTaper()         { return tc('income_tax.personal_allowance_taper_threshold') || tc('personal_allowance_taper_threshold') || 100000; }
function getHigherRateThreshold()            { return tc('income_tax.higher_rate_threshold') || tc('higher_rate_threshold') || 50270; }
function getAdditionalRateThreshold()        { return tc('income_tax.additional_rate_threshold') || tc('additional_rate_threshold') || 125140; }
function getBasicRate()                      { return tc('income_tax.basic_rate') || tc('income_tax_basic_rate') || 0.20; }
function getHigherRate()                     { return tc('income_tax.higher_rate') || tc('income_tax_higher_rate') || 0.40; }
function getAdditionalRate()                 { return tc('income_tax.additional_rate') || tc('income_tax_additional_rate') || 0.45; }
function getNIPrimaryThreshold()             { return tc('national_insurance.primary_threshold') || tc('ni_primary_threshold') || 12570; }
function getNIUpperEarningsLimit()           { return tc('national_insurance.upper_earnings_limit') || tc('ni_upper_earnings_limit') || 50270; }
function getNIBasicRate()                    { return tc('national_insurance.basic_rate') || tc('ni_basic_rate') || 0.08; }
function getNIAdditionalRate()               { return tc('national_insurance.additional_rate') || tc('ni_additional_rate') || 0.02; }
function getPensionTaxFreeCashRate()         { return tc('pension.tax_free_cash_rate') || tc('pension_tax_free_cash_rate') || 0.25; }
function getPensionTaxFreeLumpSumLimit()     { return tc('pension.tax_free_lump_sum_limit') || tc('pension_tax_free_lump_sum_limit') || 268275; }
function getStudentLoanRepaymentRate()       { return tc('student_loan_repayment_rate') || 0.09; }
function getSdltStandardBands()              { return tc('stamp_duty.england.standard_bands') || tc('sdlt_standard_bands') || []; }
function getSdltFtbBands()                   { return tc('stamp_duty.england.ftb_bands') || tc('sdlt_ftb_bands') || []; }
function getSdltFtbMaxPrice()                { return tc('stamp_duty.england.ftb_max_price') || tc('sdlt_ftb_max_price') || 500000; }
function getSdltAdditionalSurcharge()        { return tc('stamp_duty.england.additional_surcharge') || tc('sdlt_additional_surcharge') || 5; }
function getSdltNonUkSurcharge()             { return tc('stamp_duty.england.non_uk_surcharge') || tc('sdlt_non_uk_surcharge') || 2; }
function getLbttBands()                      { return tc('stamp_duty.scotland.standard_bands') || tc('lbtt_bands') || []; }
function getLbttAdditionalSurcharge()        { return tc('stamp_duty.scotland.additional_surcharge') || tc('lbtt_additional_surcharge') || 6; }
function getLbttNonUkSurcharge()             { return tc('stamp_duty.scotland.non_uk_surcharge') || tc('lbtt_non_uk_surcharge') || 2; }
function getLttBands()                       { return tc('stamp_duty.wales.standard_bands') || tc('ltt_bands') || []; }
function getLttAdditionalSurcharge()         { return tc('stamp_duty.wales.additional_surcharge') || tc('ltt_additional_surcharge') || 4; }
function getLttNonUkSurcharge()              { return tc('stamp_duty.wales.non_uk_surcharge') || tc('ltt_non_uk_surcharge') || 2; }

/* ===================================================================
   STATE — mirrors Vue data()
   =================================================================== */
var state = {
  activeCalculator: 'income-tax',
  activeStage: 'Planning Your Future',
  expandedStages: {
    'Starting Out': true,
    'Building Foundations': true,
    'Protecting and Growing': false,
    'Planning Your Future': true,
    'Enjoying Your Wealth': false,
  },
  incomeTax: { income: 50000, pension: 0, result: null },
  mortgage: { propertyValue: 300000, deposit: 30000, interestRate: 4.5, term: 25, repaymentType: 'repayment', result: null },
  loan: { amount: 10000, rate: 8.9, term: 36, result: null },
  emergencyFund: { monthlyExpenses: 2500, currentSavings: 5000, targetMonths: 6, result: null },
  pension: { currentValue: 50000, monthlyContribution: 500, currentAge: 35, retirementAge: 65, growthRate: 5.0, result: null },
  savingsGoal: { target: 10000, current: 1000, monthly: 200, rate: 4, result: null },
  mortgageAfford: { salary1: 45000, salary2: 0, monthlyDebts: 200, deposit: 25000, result: null },
  stampDuty: { price: 300000, buyerType: 'home-mover', country: 'england', result: null },
  compoundInterest: { lumpSum: 5000, monthly: 200, rate: 6, term: 20, frequency: 'monthly', result: null },
  personalLoan: { amount: 10000, rate: 6.9, term: 5, result: null },
  pensionWithdrawal: { pot: 250000, withdrawal: 50000, withdrawalType: 'lump', otherIncome: 12570, taxFreeTaken: false, result: null },
  pensionRelief: { contribution: 5000, taxBand: 'basic', pensionType: 'relief-at-source', result: null },
  studentLoan: { plan: 'plan2', balance: 45000, salary: 30000, salaryGrowth: 3, result: null },
};

var studentLoanPlans = {
  plan1: { label: 'Plan 1', threshold: 24990, rate: 6.25, writeOff: 25 },
  plan2: { label: 'Plan 2', threshold: 27295, rate: 7.3, writeOff: 30 },
  plan4: { label: 'Plan 4 (Scotland)', threshold: 31395, rate: 6.25, writeOff: 30 },
  plan5: { label: 'Plan 5', threshold: 25000, rate: 7.3, writeOff: 40 },
};

/* Chart instances — destroyed and recreated on each calculate */
var mortgageChart = null;
var savingsGoalChart = null;
var compoundChart = null;

/* ===================================================================
   UTILITY HELPERS
   =================================================================== */
function formatCurrency(value) {
  return '£' + Math.round(value).toLocaleString('en-GB');
}

function formatInputDisplay(value) {
  if (value === null || value === undefined || value === '') return '';
  return Number(value).toLocaleString('en-GB');
}

function getCurrentTaxYear() {
  var now = new Date();
  var year = now.getFullYear();
  var month = now.getMonth() + 1; /* 1-based */
  var day = now.getDate();
  /* Tax year starts 6 April */
  var taxYearStart = (month > 4 || (month === 4 && day >= 6)) ? year : year - 1;
  return taxYearStart + '/' + String(taxYearStart + 1).slice(-2);
}

function el(id) { return document.getElementById(id); }
function setText(id, text) { var e = el(id); if (e) e.textContent = text; }
function show(id) { var e = el(id); if (e) e.removeAttribute('hidden'); }
function hide(id) { var e = el(id); if (e) e.setAttribute('hidden', ''); }

/* ===================================================================
   CALCULATOR SWITCHING
   =================================================================== */
function selectCalculator(id) {
  state.activeCalculator = id;
  /* Show/hide all calc-card panels */
  var cards = document.querySelectorAll('.calc-card');
  cards.forEach(function (card) {
    if (card.dataset.calc === id) {
      card.classList.add('is-active');
    } else {
      card.classList.remove('is-active');
    }
  });
  /* Update sidebar active state */
  var sidebarBtns = document.querySelectorAll('.calc-sidebar-btn');
  sidebarBtns.forEach(function (btn) {
    if (btn.dataset.calc === id) {
      btn.classList.add('is-active');
    } else {
      btn.classList.remove('is-active');
    }
  });
  /* Update mobile item pills */
  var itemPills = document.querySelectorAll('.calc-item-pill');
  itemPills.forEach(function (pill) {
    if (pill.dataset.calc === id) {
      pill.classList.add('is-active');
    } else {
      pill.classList.remove('is-active');
    }
  });
  /* Scroll to panel on mobile */
  if (window.innerWidth < 1024) {
    var panel = el('calculator-panel');
    if (panel) panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}

/* ===================================================================
   SIDEBAR STAGE TOGGLE
   =================================================================== */
function toggleStage(name) {
  state.expandedStages[name] = !state.expandedStages[name];
  var group = document.querySelector('.calc-stage-group[data-stage="' + name + '"]');
  if (!group) return;
  var items = group.querySelector('.calc-stage-items');
  var chevron = group.querySelector('.calc-stage-chevron');
  if (!items) return;
  if (state.expandedStages[name]) {
    items.removeAttribute('hidden');
    if (chevron) chevron.classList.add('is-open');
  } else {
    items.setAttribute('hidden', '');
    if (chevron) chevron.classList.remove('is-open');
  }
}

/* ===================================================================
   MOBILE STAGE PILL SELECTION
   =================================================================== */
function selectMobileStage(name) {
  state.activeStage = name;
  /* Update stage pill active classes */
  var stagePills = document.querySelectorAll('.calc-stage-pill');
  stagePills.forEach(function (pill) {
    if (pill.dataset.stage === name) {
      pill.classList.add('is-active');
    } else {
      pill.classList.remove('is-active');
    }
  });
  /* Show items for selected stage */
  var itemPillContainer = el('mobile-item-pills');
  if (!itemPillContainer) return;
  itemPillContainer.innerHTML = '';
  var calculatorStages = getCalculatorStages();
  var stage = calculatorStages.find(function (s) { return s.name === name; });
  if (!stage) return;
  stage.items.forEach(function (item) {
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'calc-item-pill' + (item.id === state.activeCalculator ? ' is-active' : '') + (item.type !== 'free' ? ' is-gated' : '');
    btn.dataset.calc = item.id;
    btn.setAttribute('aria-label', item.name);
    btn.textContent = item.name;
    if (item.type === 'free') {
      btn.addEventListener('click', function () { selectCalculator(item.id); });
    }
    itemPillContainer.appendChild(btn);
  });
}

/* ===================================================================
   CALCULATOR STAGES DATA
   =================================================================== */
function getCalculatorStages() {
  var taxYear = getCurrentTaxYear();
  return [
    {
      name: 'Starting Out',
      icon: 'M13 10V3L4 14h7v7l9-11h-7z',
      items: [
        { id: 'student-loan', name: 'Student Loan Repayment', type: 'free', menuIcon: 'M12 14l9-5-9-5-9 5 9 5z' },
        { id: 'savings-goal', name: 'Savings Goal', type: 'free', menuIcon: 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z' },
        { id: 'emergency-fund', name: 'Emergency Fund', type: 'free', menuIcon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z' },
      ],
    },
    {
      name: 'Building Foundations',
      icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
      items: [
        { id: 'mortgage', name: 'Mortgage Repayment', type: 'free', menuIcon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
        { id: 'mortgage-afford', name: 'Mortgage Affordability', type: 'free', menuIcon: 'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z' },
        { id: 'stamp-duty', name: 'Stamp Duty', type: 'free', menuIcon: 'M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z' },
        { id: 'personal-loan', name: 'Personal Loan', type: 'free', menuIcon: 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z' },
        { id: 'compound-interest', name: 'Compound Interest', type: 'free', menuIcon: 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6' },
      ],
    },
    {
      name: 'Protecting and Growing',
      icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
      items: [
        { id: 'life-insurance', name: 'Life Insurance Needs', type: 'gated-free', menuIcon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z' },
        { id: 'income-protection', name: 'Income Protection', type: 'gated-free', menuIcon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z' },
      ],
    },
    {
      name: 'Planning Your Future',
      icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
      items: [
        { id: 'income-tax', name: 'Income Tax', type: 'free', menuIcon: 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z' },
        { id: 'pension', name: 'Pension Growth', type: 'free', menuIcon: 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6' },
        { id: 'pension-relief', name: 'Pension Tax Relief', type: 'free', menuIcon: 'M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z' },
        { id: 'salary-sacrifice', name: 'Salary Sacrifice', type: 'gated-free', menuIcon: 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z' },
        { id: 'retirement-budget', name: 'Retirement Budget Planner', type: 'gated-paid', menuIcon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2' },
      ],
    },
    {
      name: 'Enjoying Your Wealth',
      icon: 'M12 3v1m4.22 1.78l-.71.71M20 12h1M4 12H3m3.34-5.66l-.71-.71M15.54 8.46A5.99 5.99 0 0112 7a5.99 5.99 0 00-3.54 1.46M12 14a2 2 0 100-4 2 2 0 000 4zm0 0v7',
      items: [
        { id: 'pension-withdrawal', name: 'Pension Withdrawal Tax', type: 'free', menuIcon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
        { id: 'iht-checker', name: 'Inheritance Tax Checker', type: 'gated-free', menuIcon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4' },
        { id: 'drawdown-runway', name: 'Pension Drawdown / Runway', type: 'gated-free', menuIcon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
        { id: 'annuity-vs-drawdown', name: 'Annuity vs Drawdown', type: 'gated-paid', menuIcon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' },
      ],
    },
  ];
}

/* ===================================================================
   CALCULATOR LOGIC — ported verbatim from Vue methods
   =================================================================== */

function calculateIncomeTax() {
  var income = state.incomeTax.income || 0;
  var pension = state.incomeTax.pension || 0;
  var taxable = income - pension;
  var pa = getPersonalAllowance();
  var taperThreshold = getPersonalAllowanceTaper();
  var higherThreshold = getHigherRateThreshold();
  var additionalThreshold = getAdditionalRateThreshold();
  var basicRate = getBasicRate();
  var higherRate = getHigherRate();
  var additionalRate = getAdditionalRate();

  var personalAllowance = pa;
  if (taxable > taperThreshold) {
    personalAllowance = Math.max(0, pa - Math.floor((taxable - taperThreshold) / 2));
  }

  var tax = 0;
  var remaining = Math.max(0, taxable - personalAllowance);

  var basicRateBand = Math.min(remaining, higherThreshold - personalAllowance);
  tax += basicRateBand * basicRate;
  remaining -= basicRateBand;

  if (remaining > 0) {
    var higherRateBand = Math.min(remaining, additionalThreshold - higherThreshold);
    tax += higherRateBand * higherRate;
    remaining -= higherRateBand;
  }

  if (remaining > 0) {
    tax += remaining * additionalRate;
  }

  var ni = 0;
  var niPrimaryThreshold = getNIPrimaryThreshold();
  var niUpperLimit = getNIUpperEarningsLimit();
  var niBasicRate = getNIBasicRate();
  var niAdditionalRate = getNIAdditionalRate();

  if (income > niPrimaryThreshold) {
    var niable = Math.min(income - niPrimaryThreshold, niUpperLimit - niPrimaryThreshold);
    ni = niable * niBasicRate;
    if (income > niUpperLimit) {
      ni += (income - niUpperLimit) * niAdditionalRate;
    }
  }

  var net = income - tax - ni;
  var effectiveRate = income > 0 ? ((tax + ni) / income * 100).toFixed(1) : '0.0';

  state.incomeTax.result = {
    gross: income, pension: pension, taxable: taxable,
    tax: Math.round(tax), ni: Math.round(ni),
    net: Math.round(net), effectiveRate: effectiveRate,
  };
  renderIncomeTaxResult();
}

function calculateMortgage() {
  var propertyValue = state.mortgage.propertyValue || 0;
  var deposit = state.mortgage.deposit || 0;
  var loanAmount = propertyValue - deposit;
  var annualRate = state.mortgage.interestRate / 100;
  var monthlyRate = annualRate / 12;
  var term = state.mortgage.term || 25;
  var numberOfPayments = term * 12;
  var isInterestOnly = state.mortgage.repaymentType === 'interest_only';

  if (loanAmount <= 0 || annualRate <= 0) return;

  var monthlyPayment, totalRepayment, totalInterest;

  if (isInterestOnly) {
    monthlyPayment = loanAmount * monthlyRate;
    totalRepayment = (monthlyPayment * numberOfPayments) + loanAmount;
    totalInterest = monthlyPayment * numberOfPayments;
  } else {
    monthlyPayment = loanAmount *
      (monthlyRate * Math.pow(1 + monthlyRate, numberOfPayments)) /
      (Math.pow(1 + monthlyRate, numberOfPayments) - 1);
    totalRepayment = monthlyPayment * numberOfPayments;
    totalInterest = totalRepayment - loanAmount;
  }

  var ltv = propertyValue > 0 ? Math.round((loanAmount / propertyValue) * 100) : 0;

  var amortisation = null;
  if (!isInterestOnly) {
    amortisation = [];
    var balance = loanAmount;
    for (var year = 1; year <= term; year++) {
      var yearCapital = 0;
      var yearInterest = 0;
      for (var m = 0; m < 12; m++) {
        var interestPortion = balance * monthlyRate;
        var capitalPortion = monthlyPayment - interestPortion;
        yearCapital += capitalPortion;
        yearInterest += interestPortion;
        balance -= capitalPortion;
      }
      amortisation.push({ year: year, capital: Math.round(yearCapital), interest: Math.round(yearInterest) });
    }
  }

  state.mortgage.result = {
    loanAmount: loanAmount, ltv: ltv,
    monthlyPayment: Math.round(monthlyPayment),
    totalInterest: Math.round(totalInterest),
    totalRepayment: Math.round(totalRepayment),
    amortisation: amortisation,
  };
  renderMortgageResult();
}

function calculateLoan() {
  var amount = state.loan.amount || 0;
  var monthlyRate = (state.loan.rate / 100) / 12;
  var term = state.loan.term || 1;
  if (amount <= 0 || monthlyRate <= 0 || term <= 0) return;

  var monthlyPayment = amount *
    (monthlyRate * Math.pow(1 + monthlyRate, term)) /
    (Math.pow(1 + monthlyRate, term) - 1);
  var totalRepayment = monthlyPayment * term;
  var totalInterest = totalRepayment - amount;

  state.loan.result = {
    amount: amount, rate: state.loan.rate, term: term,
    monthlyPayment: Math.round(monthlyPayment),
    totalInterest: Math.round(totalInterest),
    totalRepayment: Math.round(totalRepayment),
  };
  renderLoanResult();
}

function calculateEmergencyFund() {
  var monthlyExpenses = state.emergencyFund.monthlyExpenses || 0;
  var currentSavings = state.emergencyFund.currentSavings || 0;
  var targetMonths = state.emergencyFund.targetMonths;
  var targetAmount = monthlyExpenses * targetMonths;
  var shortfall = Math.max(0, targetAmount - currentSavings);
  var currentRunway = monthlyExpenses > 0 ? currentSavings / monthlyExpenses : 0;

  var adequacy = 'Low';
  var message = 'Build your emergency fund to cover unexpected expenses.';
  if (currentRunway >= 6) {
    adequacy = 'Good';
    message = 'Excellent! You have a strong emergency fund.';
  } else if (currentRunway >= 3) {
    adequacy = 'Adequate';
    message = 'Good progress. Consider building to 6 months for better security.';
  }

  state.emergencyFund.result = {
    monthlyExpenses: monthlyExpenses, targetMonths: targetMonths,
    targetAmount: targetAmount, currentSavings: currentSavings,
    shortfall: shortfall, currentRunway: currentRunway,
    adequacy: adequacy, message: message,
  };
  renderEmergencyFundResult();
}

function calculatePension() {
  var currentValue = state.pension.currentValue || 0;
  var monthlyContribution = state.pension.monthlyContribution || 0;
  var currentAge = state.pension.currentAge || 0;
  var retirementAge = state.pension.retirementAge || 65;
  var yearsToRetirement = retirementAge - currentAge;
  var growthRate = (state.pension.growthRate / 100) / 12;
  var months = yearsToRetirement * 12;
  if (months <= 0) return;

  var futureValueOfCurrent = currentValue * Math.pow(1 + growthRate, months);
  var futureValueOfContributions = growthRate > 0
    ? monthlyContribution * ((Math.pow(1 + growthRate, months) - 1) / growthRate)
    : monthlyContribution * months;

  var projectedValue = futureValueOfCurrent + futureValueOfContributions;
  var totalContributions = monthlyContribution * months;
  var investmentGrowth = projectedValue - currentValue - totalContributions;
  var annualIncome = projectedValue * 0.04;
  var monthlyIncome = annualIncome / 12;

  state.pension.result = {
    currentValue: currentValue, yearsToRetirement: yearsToRetirement,
    totalContributions: totalContributions,
    projectedValue: Math.round(projectedValue),
    investmentGrowth: Math.round(investmentGrowth),
    annualIncome: Math.round(annualIncome),
    monthlyIncome: Math.round(monthlyIncome),
  };
  renderPensionResult();
}

function calculateSavingsGoal() {
  var target = state.savingsGoal.target || 0;
  var current = state.savingsGoal.current || 0;
  var monthly = state.savingsGoal.monthly || 0;
  var monthlyRate = ((state.savingsGoal.rate || 0) / 100) / 12;

  if (target <= 0 || monthly <= 0) return;
  if (current >= target) {
    state.savingsGoal.result = { months: 0, yearsText: 'Already reached', totalContributions: 0, totalInterest: 0, chartData: [] };
    renderSavingsGoalResult();
    return;
  }

  var balance = current;
  var months = 0;
  var totalContributions = current;
  var chartData = [{ month: 0, balance: Math.round(current), contributions: Math.round(current) }];

  while (balance < target && months < 600) {
    balance += (balance * monthlyRate) + monthly;
    totalContributions += monthly;
    months++;
    if (months % 3 === 0 || balance >= target) {
      chartData.push({ month: months, balance: Math.round(Math.min(balance, target)), contributions: Math.round(totalContributions) });
    }
  }

  var years = Math.floor(months / 12);
  var remainingMonths = months % 12;
  var yearsText = '';
  if (years > 0 && remainingMonths > 0) yearsText = years + ' yr ' + remainingMonths + ' mo';
  else if (years > 0) yearsText = years + ' year' + (years > 1 ? 's' : '');
  else yearsText = remainingMonths + ' month' + (remainingMonths > 1 ? 's' : '');

  var totalInterest = Math.round(balance - totalContributions);

  state.savingsGoal.result = {
    months: months, yearsText: yearsText,
    totalContributions: Math.round(totalContributions - current),
    totalInterest: Math.max(0, totalInterest),
    chartData: chartData,
  };
  renderSavingsGoalResult();
}

function calculateStudentLoan() {
  var plan = studentLoanPlans[state.studentLoan.plan];
  var balance = state.studentLoan.balance || 0;
  var salary = state.studentLoan.salary || 0;
  var growthRate = (state.studentLoan.salaryGrowth || 0) / 100;
  var interestRate = plan.rate / 100;
  var threshold = plan.threshold;
  var writeOffYears = plan.writeOff;
  var repaymentRate = getStudentLoanRepaymentRate();

  if (balance <= 0 || salary <= 0) return;

  var initialAnnualRepayment = salary > threshold ? (salary - threshold) * repaymentRate : 0;
  var monthlyRepayment = Math.round(initialAnnualRepayment / 12);

  var totalRepaid = 0;
  var year = 0;
  var repaid = false;
  var runningBalance = balance;
  var currentSalary = salary;

  while (year < writeOffYears && runningBalance > 0) {
    runningBalance += runningBalance * interestRate;
    var annualRepayment = currentSalary > threshold ? (currentSalary - threshold) * repaymentRate : 0;

    if (annualRepayment >= runningBalance) {
      totalRepaid += runningBalance;
      runningBalance = 0;
      repaid = true;
      year++;
      break;
    }
    runningBalance -= annualRepayment;
    totalRepaid += annualRepayment;
    year++;
    currentSalary *= (1 + growthRate);
  }

  var writtenOff = !repaid && runningBalance > 0;
  var totalInterest = totalRepaid - balance + (writtenOff ? runningBalance : 0);
  var currentYear = new Date().getFullYear();

  state.studentLoan.result = {
    monthlyRepayment: monthlyRepayment,
    yearsToRepay: repaid ? (year + (year === 1 ? ' year' : ' years')) : ('Written off after ' + writeOffYears + ' years'),
    totalRepaid: Math.round(totalRepaid),
    totalInterest: Math.max(0, Math.round(totalInterest)),
    writeOffYear: String(currentYear + writeOffYears),
    writtenOff: writtenOff,
    remainingAtWriteOff: writtenOff ? Math.round(runningBalance) : 0,
  };
  renderStudentLoanResult();
}

function calculateMortgageAfford() {
  var salary1 = state.mortgageAfford.salary1 || 0;
  var salary2 = state.mortgageAfford.salary2 || 0;
  var monthlyDebts = state.mortgageAfford.monthlyDebts || 0;
  var totalIncome = salary1 + salary2;
  if (totalIncome <= 0) return;

  var debtAdjustment = monthlyDebts * 12 * 4;
  var max4x = Math.max(0, (totalIncome * 4) - debtAdjustment);
  var max45x = Math.max(0, (totalIncome * 4.5) - debtAdjustment);

  state.mortgageAfford.result = {
    totalIncome: totalIncome,
    max4x: Math.round(max4x),
    max45x: Math.round(max45x),
    property10: Math.round(max45x / 0.90),
    property15: Math.round(max45x / 0.85),
    property20: Math.round(max45x / 0.80),
  };
  renderMortgageAffordResult();
}

function calculateStampDuty() {
  var price = state.stampDuty.price || 0;
  if (price <= 0) return;

  var buyerType = state.stampDuty.buyerType;
  var country = state.stampDuty.country;
  var bands, taxName, surcharge = 0;

  if (country === 'scotland') {
    taxName = 'Land and Buildings Transaction Tax';
    bands = getLbttBands();
    if (buyerType === 'additional') surcharge = getLbttAdditionalSurcharge();
    if (buyerType === 'non-uk') surcharge = getLbttNonUkSurcharge();
  } else if (country === 'wales') {
    taxName = 'Land Transaction Tax';
    bands = getLttBands();
    if (buyerType === 'additional') surcharge = getLttAdditionalSurcharge();
    if (buyerType === 'non-uk') surcharge = getLttNonUkSurcharge();
  } else {
    taxName = 'Stamp Duty Land Tax';
    var ftbMaxPrice = getSdltFtbMaxPrice();
    if (buyerType === 'first-time' && price <= ftbMaxPrice) {
      bands = getSdltFtbBands();
    } else {
      bands = getSdltStandardBands();
    }
    if (buyerType === 'additional') surcharge = getSdltAdditionalSurcharge();
    if (buyerType === 'non-uk') surcharge = getSdltNonUkSurcharge();
  }

  var totalTax = 0;
  var prev = 0;
  var bandResults = [];
  for (var i = 0; i < bands.length; i++) {
    var band = bands[i];
    var taxable = Math.min(price, band.threshold) - prev;
    if (taxable <= 0) { prev = band.threshold; continue; }
    var effectiveRate = band.rate + surcharge;
    var tax = taxable * (effectiveRate / 100);
    totalTax += tax;
    var upperBound = Math.min(price, band.threshold);
    bandResults.push({
      label: '£' + prev.toLocaleString('en-GB') + ' - £' + upperBound.toLocaleString('en-GB') + ' at ' + effectiveRate + '%',
      tax: Math.round(tax),
    });
    prev = band.threshold;
    if (prev >= price) break;
  }

  var saving = 0;
  if (buyerType === 'first-time' && country === 'england' && price <= getSdltFtbMaxPrice()) {
    var standardBands = getSdltStandardBands();
    var standardTax = 0;
    var sPrev = 0;
    for (var j = 0; j < standardBands.length; j++) {
      var sb = standardBands[j];
      var t = Math.min(price, sb.threshold) - sPrev;
      if (t > 0) standardTax += t * (sb.rate / 100);
      sPrev = sb.threshold;
      if (sPrev >= price) break;
    }
    saving = Math.round(standardTax - totalTax);
  }

  state.stampDuty.result = {
    taxName: taxName,
    totalTax: Math.round(totalTax),
    effectiveRate: price > 0 ? ((totalTax / price) * 100).toFixed(1) : '0',
    bands: bandResults,
    saving: saving,
  };
  renderStampDutyResult();
}

function calculateCompoundInterest() {
  var lumpSum = state.compoundInterest.lumpSum || 0;
  var monthly = state.compoundInterest.monthly || 0;
  var annualRate = (state.compoundInterest.rate || 0) / 100;
  var term = state.compoundInterest.term || 1;
  var isMonthly = state.compoundInterest.frequency === 'monthly';
  if (lumpSum <= 0 && monthly <= 0) return;

  var chartData = [{ year: 0, withContributions: lumpSum, withoutContributions: lumpSum }];
  var balanceWith = lumpSum;
  var balanceWithout = lumpSum;
  var totalContributions = lumpSum;

  for (var y = 1; y <= term; y++) {
    if (isMonthly) {
      var mr = annualRate / 12;
      for (var m = 0; m < 12; m++) {
        balanceWith = balanceWith * (1 + mr) + monthly;
        balanceWithout = balanceWithout * (1 + mr);
      }
    } else {
      balanceWith = (balanceWith + monthly * 12) * (1 + annualRate);
      balanceWithout = balanceWithout * (1 + annualRate);
    }
    totalContributions += monthly * 12;
    chartData.push({ year: y, withContributions: Math.round(balanceWith), withoutContributions: Math.round(balanceWithout) });
  }

  state.compoundInterest.result = {
    finalValue: Math.round(balanceWith),
    totalContributions: Math.round(totalContributions),
    totalGrowth: Math.round(balanceWith - totalContributions),
    chartData: chartData,
  };
  renderCompoundInterestResult();
}

function calculatePersonalLoan() {
  var amount = state.personalLoan.amount || 0;
  var annualRate = (state.personalLoan.rate || 0) / 100;
  var term = state.personalLoan.term || 1;
  if (amount <= 0 || annualRate <= 0) return;

  var monthlyRate = annualRate / 12;
  var months = term * 12;
  var monthlyPayment = amount * (monthlyRate * Math.pow(1 + monthlyRate, months)) / (Math.pow(1 + monthlyRate, months) - 1);
  var totalRepaid = monthlyPayment * months;

  state.personalLoan.result = {
    monthlyPayment: Math.round(monthlyPayment),
    totalRepaid: Math.round(totalRepaid),
    totalInterest: Math.round(totalRepaid - amount),
  };
  renderPersonalLoanResult();
}

function _calculateIncomeTax2526(income) {
  var pa = getPersonalAllowance();
  var basicRateLimit = getHigherRateThreshold() - pa;
  var taperThreshold = getPersonalAllowanceTaper();
  var additionalThreshold = getAdditionalRateThreshold();
  var basicRate = getBasicRate();
  var higherRate = getHigherRate();
  var additionalRate = getAdditionalRate();

  var adjustedPA = pa;
  if (income > taperThreshold) {
    adjustedPA = Math.max(0, pa - Math.floor((income - taperThreshold) / 2));
  }

  var taxableIncome = Math.max(0, income - adjustedPA);
  var tax = 0;
  var basicBand = Math.min(taxableIncome, basicRateLimit);
  tax += basicBand * basicRate;
  var higherBand = Math.min(Math.max(0, taxableIncome - basicRateLimit), additionalThreshold - getHigherRateThreshold());
  tax += higherBand * higherRate;
  var additionalBand = Math.max(0, taxableIncome - (additionalThreshold - pa));
  tax += additionalBand * additionalRate;
  return tax;
}

function calculatePensionWithdrawal() {
  var pot = state.pensionWithdrawal.pot || 0;
  var withdrawal = Math.min(state.pensionWithdrawal.withdrawal || 0, pot);
  var otherIncome = state.pensionWithdrawal.otherIncome || 0;
  var taxFreeTaken = state.pensionWithdrawal.taxFreeTaken;
  if (withdrawal <= 0) return;

  var tfcRate = getPensionTaxFreeCashRate();
  var tfcLimit = getPensionTaxFreeLumpSumLimit();
  var taxFree = 0;
  if (!taxFreeTaken) {
    taxFree = Math.min(withdrawal * tfcRate, pot * tfcRate, tfcLimit);
  }

  var taxable = withdrawal - taxFree;
  var totalTaxableIncome = otherIncome + taxable;
  var taxOnTotal = _calculateIncomeTax2526(totalTaxableIncome);
  var taxOnOther = _calculateIncomeTax2526(otherIncome);
  var taxDue = Math.max(0, Math.round(taxOnTotal - taxOnOther));
  var netReceived = withdrawal - taxDue;
  var effectiveRate = withdrawal > 0 ? ((taxDue / withdrawal) * 100).toFixed(1) : '0';

  state.pensionWithdrawal.result = {
    taxFree: Math.round(taxFree),
    taxable: Math.round(taxable),
    taxDue: taxDue,
    netReceived: Math.round(netReceived),
    effectiveRate: effectiveRate,
  };
  renderPensionWithdrawalResult();
}

function calculatePensionRelief() {
  var contribution = state.pensionRelief.contribution || 0;
  if (contribution <= 0) return;

  var band = state.pensionRelief.taxBand;
  var isNetPay = state.pensionRelief.pensionType === 'net-pay';
  var basicRate = getBasicRate();
  var higherRate = getHigherRate();
  var additionalRate = getAdditionalRate();

  var gross = contribution;
  var basicRelief = Math.round(gross * basicRate);
  var additionalRelief = 0;
  var effectiveCost = gross;

  if (band === 'higher') {
    additionalRelief = Math.round(gross * (higherRate - basicRate));
    effectiveCost = Math.round(gross * (1 - higherRate));
  } else if (band === 'additional') {
    additionalRelief = Math.round(gross * (additionalRate - basicRate));
    effectiveCost = Math.round(gross * (1 - additionalRate));
  } else {
    effectiveCost = Math.round(gross * (1 - basicRate));
  }

  var totalRelief = basicRelief + additionalRelief;
  var netContribution = isNetPay ? gross : Math.round(gross * (1 - basicRate));

  state.pensionRelief.result = {
    gross: gross,
    netContribution: netContribution,
    basicRelief: basicRelief,
    additionalRelief: additionalRelief,
    totalRelief: totalRelief,
    effectiveCost: effectiveCost,
  };
  renderPensionReliefResult();
}

/* ===================================================================
   RENDER FUNCTIONS — update DOM with results
   =================================================================== */

function renderIncomeTaxResult() {
  var r = state.incomeTax.result;
  if (!r) { hide('income-tax-result'); show('income-tax-placeholder'); return; }
  show('income-tax-result'); hide('income-tax-placeholder');
  setText('it-gross', formatCurrency(r.gross));
  setText('it-pension', formatCurrency(r.pension));
  setText('it-taxable', formatCurrency(r.taxable));
  setText('it-tax', '-' + formatCurrency(r.tax));
  setText('it-ni', '-' + formatCurrency(r.ni));
  setText('it-net', formatCurrency(r.net));
  setText('it-rate', r.effectiveRate + '%');
}

function renderMortgageResult() {
  var r = state.mortgage.result;
  if (!r) { hide('mortgage-result'); show('mortgage-placeholder'); return; }
  show('mortgage-result'); hide('mortgage-placeholder');
  setText('m-monthly', formatCurrency(r.monthlyPayment));
  var subEl = el('m-repayment-sub');
  if (subEl) subEl.textContent = state.mortgage.repaymentType === 'interest_only' ? 'Interest only — capital not repaid' : 'Capital and interest';
  setText('m-loan', formatCurrency(r.loanAmount));
  setText('m-ltv', r.ltv + '%');
  setText('m-total', formatCurrency(r.totalRepayment));
  setText('m-interest', formatCurrency(r.totalInterest));

  var ltvWarn = el('m-ltv-warn');
  if (ltvWarn) { r.ltv > 90 ? ltvWarn.removeAttribute('hidden') : ltvWarn.setAttribute('hidden', ''); }
  var ioWarn = el('m-io-warn');
  if (ioWarn) {
    if (state.mortgage.repaymentType === 'interest_only') {
      ioWarn.removeAttribute('hidden');
      setText('m-io-loan-amount', formatCurrency(r.loanAmount));
    } else {
      ioWarn.setAttribute('hidden', '');
    }
  }

  var chartBox = el('m-chart-box');
  if (chartBox) {
    if (state.mortgage.repaymentType === 'repayment' && r.amortisation) {
      chartBox.removeAttribute('hidden');
      renderMortgageChart(r.amortisation);
    } else {
      chartBox.setAttribute('hidden', '');
    }
  }
}

function renderLoanResult() {
  var r = state.loan.result;
  if (!r) { hide('loan-result'); show('loan-placeholder'); return; }
  show('loan-result'); hide('loan-placeholder');
  setText('l-amount', formatCurrency(r.amount));
  setText('l-rate', r.rate + '% APR');
  setText('l-term', r.term + ' months');
  setText('l-monthly', formatCurrency(r.monthlyPayment));
  setText('l-interest', formatCurrency(r.totalInterest));
  setText('l-total', formatCurrency(r.totalRepayment));
}

function renderEmergencyFundResult() {
  var r = state.emergencyFund.result;
  if (!r) { hide('ef-result'); show('ef-placeholder'); return; }
  show('ef-result'); hide('ef-placeholder');
  setText('ef-expenses', formatCurrency(r.monthlyExpenses));
  setText('ef-target-months', r.targetMonths + ' months');
  setText('ef-target', formatCurrency(r.targetAmount));
  setText('ef-savings', formatCurrency(r.currentSavings));
  var shortfallEl = el('ef-shortfall');
  if (shortfallEl) {
    shortfallEl.textContent = r.shortfall > 0 ? formatCurrency(r.shortfall) : 'Fully funded!';
    shortfallEl.className = 'calc-result-row__value ' + (r.shortfall > 0 ? 'calc-result-row__value--raspberry' : 'calc-result-row__value--spring');
  }
  setText('ef-runway', r.currentRunway.toFixed(1) + ' months');

  var statusEl = el('ef-status');
  if (statusEl) {
    statusEl.className = 'calc-status-badge calc-status-badge--' + r.adequacy.toLowerCase();
    var titleEl = statusEl.querySelector('.calc-status-badge__title');
    var msgEl = statusEl.querySelector('.calc-status-badge__msg');
    if (titleEl) titleEl.textContent = 'Status: ' + r.adequacy;
    if (msgEl) msgEl.textContent = r.message;
  }
}

function renderPensionResult() {
  var r = state.pension.result;
  if (!r) { hide('pension-result'); show('pension-placeholder'); return; }
  show('pension-result'); hide('pension-placeholder');
  setText('p-years', r.yearsToRetirement + ' years');
  setText('p-current', formatCurrency(r.currentValue));
  setText('p-contributions', formatCurrency(r.totalContributions));
  var heroEl = el('p-projected-hero');
  if (heroEl) heroEl.textContent = formatCurrency(r.projectedValue);
  var heroLabel = el('p-projected-label');
  if (heroLabel) heroLabel.textContent = 'Projected Pot at ' + state.pension.retirementAge;
  setText('p-growth', formatCurrency(r.investmentGrowth));
  setText('p-annual', formatCurrency(r.annualIncome));
  setText('p-monthly', formatCurrency(r.monthlyIncome));
}

function renderSavingsGoalResult() {
  var r = state.savingsGoal.result;
  if (!r) { hide('sg-result'); show('sg-placeholder'); return; }
  show('sg-result'); hide('sg-placeholder');
  setText('sg-time', r.yearsText);
  setText('sg-target-label', 'to save ' + formatCurrency(state.savingsGoal.target));
  setText('sg-contributions', formatCurrency(r.totalContributions));
  setText('sg-interest', formatCurrency(r.totalInterest));
  if (r.chartData && r.chartData.length > 1) {
    renderSavingsGoalChart(r.chartData);
  }
}

function renderStudentLoanResult() {
  var r = state.studentLoan.result;
  if (!r) { hide('sl-result'); show('sl-placeholder'); return; }
  show('sl-result'); hide('sl-placeholder');
  setText('sl-monthly', formatCurrency(r.monthlyRepayment));
  setText('sl-years', r.yearsToRepay);
  setText('sl-repaid', formatCurrency(r.totalRepaid));
  setText('sl-interest', formatCurrency(r.totalInterest));
  setText('sl-writeoff', r.writeOffYear);

  var writtenOffEl = el('sl-written-off-warn');
  var repaidEl = el('sl-fully-repaid');
  if (writtenOffEl && repaidEl) {
    if (r.writtenOff) {
      writtenOffEl.removeAttribute('hidden');
      repaidEl.setAttribute('hidden', '');
      setText('sl-remaining', formatCurrency(r.remainingAtWriteOff));
      setText('sl-balance-orig', formatCurrency(state.studentLoan.balance));
      setText('sl-total-repaid-warn', formatCurrency(r.totalRepaid));
      var writeOffYrs = studentLoanPlans[state.studentLoan.plan].writeOff;
      setText('sl-writeoff-years', writeOffYrs);
    } else {
      writtenOffEl.setAttribute('hidden', '');
      repaidEl.removeAttribute('hidden');
      var plan = studentLoanPlans[state.studentLoan.plan];
      setText('sl-repaid-years', r.yearsToRepay);
      setText('sl-writeoff-period', plan.writeOff);
    }
  }
}

function renderMortgageAffordResult() {
  var r = state.mortgageAfford.result;
  if (!r) { hide('ma-result'); show('ma-placeholder'); return; }
  show('ma-result'); hide('ma-placeholder');
  setText('ma-max4x', formatCurrency(r.max4x));
  setText('ma-max45x', formatCurrency(r.max45x));
  setText('ma-p10', formatCurrency(r.property10));
  setText('ma-p15', formatCurrency(r.property15));
  setText('ma-p20', formatCurrency(r.property20));
}

function renderStampDutyResult() {
  var r = state.stampDuty.result;
  if (!r) { hide('sd-result'); show('sd-placeholder'); return; }
  show('sd-result'); hide('sd-placeholder');
  setText('sd-tax-name', r.taxName);
  setText('sd-total', formatCurrency(r.totalTax));
  setText('sd-rate', 'Effective rate: ' + r.effectiveRate + '%');

  var bandsEl = el('sd-bands');
  if (bandsEl) {
    bandsEl.innerHTML = '';
    r.bands.forEach(function (band) {
      var row = document.createElement('div');
      row.className = 'calc-band-row';
      row.innerHTML = '<span class="calc-band-row__label">' + band.label + '</span><span class="calc-band-row__value">' + formatCurrency(band.tax) + '</span>';
      bandsEl.appendChild(row);
    });
  }

  var savingEl = el('sd-saving');
  if (savingEl) {
    if (r.saving > 0) {
      savingEl.removeAttribute('hidden');
      setText('sd-saving-amount', formatCurrency(r.saving));
    } else {
      savingEl.setAttribute('hidden', '');
    }
  }

  var additionalEl = el('sd-additional-warn');
  if (additionalEl) {
    state.stampDuty.buyerType === 'additional' ? additionalEl.removeAttribute('hidden') : additionalEl.setAttribute('hidden', '');
  }
  var nonUkEl = el('sd-nonuk-warn');
  if (nonUkEl) {
    state.stampDuty.buyerType === 'non-uk' ? nonUkEl.removeAttribute('hidden') : nonUkEl.setAttribute('hidden', '');
  }
}

function renderCompoundInterestResult() {
  var r = state.compoundInterest.result;
  if (!r) { hide('ci-result'); show('ci-placeholder'); return; }
  show('ci-result'); hide('ci-placeholder');
  setText('ci-final', formatCurrency(r.finalValue));
  setText('ci-term-label', 'after ' + state.compoundInterest.term + ' years');
  setText('ci-contributions', formatCurrency(r.totalContributions));
  setText('ci-growth', formatCurrency(r.totalGrowth));
  if (r.chartData && r.chartData.length > 1) {
    renderCompoundChart(r.chartData);
  }
}

function renderPersonalLoanResult() {
  var r = state.personalLoan.result;
  if (!r) { hide('pl-result'); show('pl-placeholder'); return; }
  show('pl-result'); hide('pl-placeholder');
  setText('pl-monthly', formatCurrency(r.monthlyPayment));
  setText('pl-term-label', 'over ' + state.personalLoan.term + ' years');
  setText('pl-total', formatCurrency(r.totalRepaid));
  setText('pl-interest', formatCurrency(r.totalInterest));
}

function renderPensionWithdrawalResult() {
  var r = state.pensionWithdrawal.result;
  if (!r) { hide('pw-result'); show('pw-placeholder'); return; }
  show('pw-result'); hide('pw-placeholder');
  setText('pw-net', formatCurrency(r.netReceived));
  setText('pw-withdrawal-label', 'from a ' + formatCurrency(state.pensionWithdrawal.withdrawal) + ' withdrawal');
  setText('pw-taxfree', formatCurrency(r.taxFree));
  setText('pw-taxable', formatCurrency(r.taxable));
  setText('pw-taxdue', formatCurrency(r.taxDue));
  setText('pw-rate', r.effectiveRate + '%');

  var higherWarn = el('pw-higher-warn');
  if (higherWarn) {
    var threshold = getHigherRateThreshold() - (state.pensionWithdrawal.otherIncome || 0);
    r.taxable > threshold ? higherWarn.removeAttribute('hidden') : higherWarn.setAttribute('hidden', '');
  }
}

function renderPensionReliefResult() {
  var r = state.pensionRelief.result;
  if (!r) { hide('pr-result'); show('pr-placeholder'); return; }
  show('pr-result'); hide('pr-placeholder');
  setText('pr-gross', formatCurrency(r.gross));
  setText('pr-cost-label', 'from ' + formatCurrency(r.effectiveCost) + ' effective cost to you');
  setText('pr-net', formatCurrency(r.netContribution));
  setText('pr-basic-relief', formatCurrency(r.basicRelief));
  setText('pr-cost', formatCurrency(r.effectiveCost));
  setText('pr-total-relief', formatCurrency(r.totalRelief));

  var additionalReliefEl = el('pr-additional-relief');
  if (additionalReliefEl) {
    if (r.additionalRelief > 0) {
      additionalReliefEl.removeAttribute('hidden');
      setText('pr-additional-amount', formatCurrency(r.additionalRelief));
      var rasMsg = el('pr-ras-msg');
      var npMsg = el('pr-np-msg');
      var bandLabel = state.pensionRelief.taxBand === 'higher' ? 'higher' : 'additional';
      if (rasMsg) rasMsg.textContent = 'As a ' + bandLabel + ' rate taxpayer with a relief at source pension, you need to claim this ' + formatCurrency(r.additionalRelief) + ' via your self-assessment tax return.';
      if (state.pensionRelief.pensionType === 'relief-at-source') {
        if (rasMsg) rasMsg.removeAttribute('hidden');
        if (npMsg) npMsg.setAttribute('hidden', '');
      } else {
        if (rasMsg) rasMsg.setAttribute('hidden', '');
        if (npMsg) npMsg.removeAttribute('hidden');
      }
    } else {
      additionalReliefEl.setAttribute('hidden', '');
    }
  }
}

/* ===================================================================
   CHARTS — Chart.js
   =================================================================== */
function renderMortgageChart(amortisation) {
  var canvas = el('mortgage-chart');
  if (!canvas) return;
  if (mortgageChart) { mortgageChart.destroy(); mortgageChart = null; }
  mortgageChart = new Chart(canvas.getContext('2d'), {
    type: 'bar',
    data: {
      labels: amortisation.map(function (d) { return 'Yr ' + d.year; }),
      datasets: [
        { label: 'Capital', data: amortisation.map(function (d) { return d.capital; }), backgroundColor: '#5854E6' },
        { label: 'Interest', data: amortisation.map(function (d) { return d.interest; }), backgroundColor: '#E83E6D' },
      ],
    },
    options: {
      responsive: true,
      plugins: {
        legend: { position: 'top', labels: { font: { size: 11 } } },
        tooltip: { callbacks: { label: function (ctx) { return ctx.dataset.label + ': £' + Math.round(ctx.raw).toLocaleString('en-GB'); } } },
      },
      scales: {
        x: { stacked: true, ticks: { font: { size: 10 }, color: '#94A3B8' }, grid: { color: '#E2E8F0' } },
        y: { stacked: true, ticks: { font: { size: 10 }, color: '#94A3B8', callback: function (v) { return '£' + Math.round(v / 1000) + 'k'; } }, grid: { color: '#E2E8F0' } },
      },
    },
  });
}

function renderSavingsGoalChart(chartData) {
  var canvas = el('savings-goal-chart');
  if (!canvas) return;
  if (savingsGoalChart) { savingsGoalChart.destroy(); savingsGoalChart = null; }
  var labels = chartData.map(function (d) {
    if (d.month === 0) return 'Now';
    var y = Math.floor(d.month / 12);
    var m = d.month % 12;
    return y > 0 ? (y + 'y' + (m > 0 ? ' ' + m + 'm' : '')) : (m + 'm');
  });
  savingsGoalChart = new Chart(canvas.getContext('2d'), {
    type: 'line',
    data: {
      labels: labels,
      datasets: [
        { label: 'Total Balance', data: chartData.map(function (d) { return d.balance; }), borderColor: '#E83E6D', backgroundColor: 'rgba(232,62,109,0.1)', fill: true, tension: 0.4, borderWidth: 2, pointRadius: 0 },
        { label: 'Contributions Only', data: chartData.map(function (d) { return d.contributions; }), borderColor: '#94A3B8', backgroundColor: 'rgba(148,163,184,0.05)', fill: true, tension: 0.4, borderWidth: 2, pointRadius: 0 },
      ],
    },
    options: {
      responsive: true,
      plugins: {
        legend: { position: 'top', labels: { font: { size: 11 } } },
        tooltip: { callbacks: { label: function (ctx) { return ctx.dataset.label + ': £' + ctx.raw.toLocaleString('en-GB'); } } },
      },
      scales: {
        x: { ticks: { font: { size: 10 }, color: '#94A3B8' }, grid: { color: '#E2E8F0' } },
        y: { ticks: { font: { size: 10 }, color: '#94A3B8', callback: function (v) { return '£' + Math.round(v / 1000) + 'k'; } }, grid: { color: '#E2E8F0' } },
      },
    },
  });
}

function renderCompoundChart(chartData) {
  var canvas = el('compound-chart');
  if (!canvas) return;
  if (compoundChart) { compoundChart.destroy(); compoundChart = null; }
  compoundChart = new Chart(canvas.getContext('2d'), {
    type: 'line',
    data: {
      labels: chartData.map(function (d) { return d.year === 0 ? 'Now' : 'Yr ' + d.year; }),
      datasets: [
        { label: 'With Contributions', data: chartData.map(function (d) { return d.withContributions; }), borderColor: '#20B486', backgroundColor: 'rgba(32,180,134,0.1)', fill: true, tension: 0.4, borderWidth: 2, pointRadius: 0 },
        { label: 'Without Contributions', data: chartData.map(function (d) { return d.withoutContributions; }), borderColor: '#94A3B8', backgroundColor: 'rgba(148,163,184,0.05)', fill: true, tension: 0.4, borderWidth: 2, pointRadius: 0 },
      ],
    },
    options: {
      responsive: true,
      plugins: {
        legend: { position: 'top', labels: { font: { size: 11 } } },
        tooltip: { callbacks: { label: function (ctx) { return ctx.dataset.label + ': £' + Math.round(ctx.raw).toLocaleString('en-GB'); } } },
      },
      scales: {
        x: { ticks: { font: { size: 10 }, color: '#94A3B8' }, grid: { color: '#E2E8F0' } },
        y: { ticks: { font: { size: 10 }, color: '#94A3B8', callback: function (v) { return '£' + Math.round(v / 1000) + 'k'; } }, grid: { color: '#E2E8F0' } },
      },
    },
  });
}

/* ===================================================================
   TOGGLE BUTTON HELPERS
   =================================================================== */
function setActiveToggle(groupSelector, activeClass) {
  var group = document.querySelectorAll(groupSelector);
  group.forEach(function (btn) {
    if (btn.dataset.value === activeClass) {
      btn.classList.add('is-active');
    } else {
      btn.classList.remove('is-active');
    }
  });
}

/* ===================================================================
   INPUT WIRING — currency inputs with formatted display
   =================================================================== */
function wireCurrencyInput(inputId, stateObj, key) {
  var input = el(inputId);
  if (!input) return;
  /* Set initial display */
  input.value = formatInputDisplay(stateObj[key]);
  input.addEventListener('input', function (e) {
    var raw = e.target.value.replace(/[^0-9.]/g, '');
    var num = parseFloat(raw) || 0;
    stateObj[key] = num;
    e.target.value = num ? num.toLocaleString('en-GB') : '';
  });
  input.addEventListener('blur', function (e) {
    var num = stateObj[key] || 0;
    e.target.value = num ? num.toLocaleString('en-GB') : '';
  });
}

function wireNumberInput(inputId, stateObj, key) {
  var input = el(inputId);
  if (!input) return;
  input.value = stateObj[key];
  input.addEventListener('input', function (e) {
    var val = parseFloat(e.target.value) || 0;
    stateObj[key] = val;
  });
}

function wireRangeInput(rangeId, displayId, stateObj, key) {
  var range = el(rangeId);
  var display = el(displayId);
  if (!range) return;
  range.value = stateObj[key];
  if (display) display.textContent = stateObj[key];
  range.addEventListener('input', function () {
    stateObj[key] = parseFloat(range.value);
    if (display) display.textContent = range.value;
  });
}

/* ===================================================================
   DEPOSIT / LTV DISPLAY (mortgage)
   =================================================================== */
function updateMortgageHints() {
  var pv = state.mortgage.propertyValue;
  var dep = state.mortgage.deposit;
  var hintEl = el('m-deposit-hint');
  if (!hintEl) return;
  if (pv > 0 && dep > 0) {
    var pct = Math.round((dep / pv) * 100);
    var ltv = 100 - pct;
    hintEl.querySelector('#m-dep-pct').textContent = 'Deposit: ' + pct + '%';
    hintEl.querySelector('#m-dep-ltv').textContent = 'Loan to Value: ' + ltv + '%';
    hintEl.removeAttribute('hidden');
  } else {
    hintEl.setAttribute('hidden', '');
  }
}

/* ===================================================================
   STUDENT LOAN PLAN INFO UPDATE
   =================================================================== */
function updateStudentLoanPlanInfo() {
  var plan = studentLoanPlans[state.studentLoan.plan];
  var infoEl = el('sl-plan-info');
  if (infoEl && plan) {
    infoEl.innerHTML =
      '<span>Threshold: ' + formatCurrency(plan.threshold) + '/yr</span>' +
      '<span>Interest: ' + plan.rate + '%</span>' +
      '<span>Written off after: ' + plan.writeOff + ' years</span>';
  }
}

/* ===================================================================
   PENSION RELIEF TYPE INFO
   =================================================================== */
function updatePensionReliefInfo() {
  var isRas = state.pensionRelief.pensionType === 'relief-at-source';
  var infoEl = el('pr-type-info');
  if (!infoEl) return;
  infoEl.textContent = isRas
    ? 'Relief at source: you pay from net pay, provider claims 20% from HMRC. Higher/additional rate taxpayers claim the rest via self-assessment.'
    : 'Net pay: contributions taken before tax from your gross salary. Tax relief is automatic — nothing to claim.';
}

/* ===================================================================
   INIT — wire all controls
   =================================================================== */
document.addEventListener('DOMContentLoaded', function () {
  /* ------------------------------------------------------------------
     Read ?calc= query parameter
  ------------------------------------------------------------------ */
  var params = new URLSearchParams(window.location.search);
  var calcParam = params.get('calc');
  if (calcParam) {
    state.activeCalculator = calcParam;
    /* find which stage this calc belongs to */
    var stages = getCalculatorStages();
    for (var si = 0; si < stages.length; si++) {
      for (var ii = 0; ii < stages[si].items.length; ii++) {
        if (stages[si].items[ii].id === calcParam) {
          state.activeStage = stages[si].name;
          break;
        }
      }
    }
  }

  /* ------------------------------------------------------------------
     Init sidebar expand/collapse
  ------------------------------------------------------------------ */
  var stageGroups = document.querySelectorAll('.calc-stage-group');
  stageGroups.forEach(function (group) {
    var stageName = group.dataset.stage;
    var items = group.querySelector('.calc-stage-items');
    var chevron = group.querySelector('.calc-stage-chevron');
    if (!state.expandedStages[stageName] && items) {
      items.setAttribute('hidden', '');
      if (chevron) chevron.classList.remove('is-open');
    }
    var btn = group.querySelector('.calc-stage-btn');
    if (btn) {
      btn.addEventListener('click', function () { toggleStage(stageName); });
    }
  });

  /* ------------------------------------------------------------------
     Init sidebar calculator buttons
  ------------------------------------------------------------------ */
  var sidebarBtns = document.querySelectorAll('.calc-sidebar-btn');
  sidebarBtns.forEach(function (btn) {
    var calcId = btn.dataset.calc;
    if (btn.dataset.type !== 'free') return;
    btn.addEventListener('click', function () { selectCalculator(calcId); });
  });

  /* ------------------------------------------------------------------
     Init mobile stage pills
  ------------------------------------------------------------------ */
  var stagePills = document.querySelectorAll('.calc-stage-pill');
  stagePills.forEach(function (pill) {
    pill.addEventListener('click', function () { selectMobileStage(pill.dataset.stage); });
  });

  /* ------------------------------------------------------------------
     Set initial calculator active state
  ------------------------------------------------------------------ */
  selectCalculator(state.activeCalculator);
  selectMobileStage(state.activeStage);

  /* ------------------------------------------------------------------
     INCOME TAX
  ------------------------------------------------------------------ */
  wireCurrencyInput('it-income', state.incomeTax, 'income');
  wireCurrencyInput('it-pension', state.incomeTax, 'pension');
  var itBtn = el('it-calc-btn');
  if (itBtn) itBtn.addEventListener('click', calculateIncomeTax);

  /* ------------------------------------------------------------------
     MORTGAGE
  ------------------------------------------------------------------ */
  wireCurrencyInput('m-property', state.mortgage, 'propertyValue');
  wireCurrencyInput('m-deposit', state.mortgage, 'deposit');
  wireRangeInput('m-term-range', 'm-term-display', state.mortgage, 'term');
  wireNumberInput('m-rate', state.mortgage, 'interestRate');

  var mPropInput = el('m-property');
  var mDepInput = el('m-deposit');
  if (mPropInput) mPropInput.addEventListener('input', updateMortgageHints);
  if (mDepInput) mDepInput.addEventListener('input', updateMortgageHints);

  document.querySelectorAll('[data-mortgage-type]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      state.mortgage.repaymentType = btn.dataset.mortgageType;
      document.querySelectorAll('[data-mortgage-type]').forEach(function (b) {
        b.classList.toggle('is-active', b.dataset.mortgageType === state.mortgage.repaymentType);
      });
    });
  });

  var mBtn = el('m-calc-btn');
  if (mBtn) mBtn.addEventListener('click', calculateMortgage);

  /* ------------------------------------------------------------------
     LOAN (amortisation)
  ------------------------------------------------------------------ */
  wireCurrencyInput('l-amount', state.loan, 'amount');
  wireNumberInput('l-rate', state.loan, 'rate');
  wireNumberInput('l-term', state.loan, 'term');
  var lBtn = el('l-calc-btn');
  if (lBtn) lBtn.addEventListener('click', calculateLoan);

  /* ------------------------------------------------------------------
     EMERGENCY FUND
  ------------------------------------------------------------------ */
  wireCurrencyInput('ef-expenses', state.emergencyFund, 'monthlyExpenses');
  wireCurrencyInput('ef-savings', state.emergencyFund, 'currentSavings');
  var efSelect = el('ef-target-months');
  if (efSelect) {
    efSelect.value = state.emergencyFund.targetMonths;
    efSelect.addEventListener('change', function () { state.emergencyFund.targetMonths = parseInt(efSelect.value); });
  }
  var efBtn = el('ef-calc-btn');
  if (efBtn) efBtn.addEventListener('click', calculateEmergencyFund);

  /* ------------------------------------------------------------------
     PENSION GROWTH
  ------------------------------------------------------------------ */
  wireCurrencyInput('p-current', state.pension, 'currentValue');
  wireCurrencyInput('p-contribution', state.pension, 'monthlyContribution');
  wireNumberInput('p-age', state.pension, 'currentAge');
  wireNumberInput('p-retire-age', state.pension, 'retirementAge');
  wireNumberInput('p-rate', state.pension, 'growthRate');
  var pBtn = el('p-calc-btn');
  if (pBtn) pBtn.addEventListener('click', calculatePension);

  /* ------------------------------------------------------------------
     SAVINGS GOAL
  ------------------------------------------------------------------ */
  wireCurrencyInput('sg-target', state.savingsGoal, 'target');
  wireCurrencyInput('sg-current', state.savingsGoal, 'current');
  wireCurrencyInput('sg-monthly', state.savingsGoal, 'monthly');
  wireRangeInput('sg-rate-range', 'sg-rate-display', state.savingsGoal, 'rate');
  var sgBtn = el('sg-calc-btn');
  if (sgBtn) sgBtn.addEventListener('click', calculateSavingsGoal);

  /* ------------------------------------------------------------------
     STUDENT LOAN
  ------------------------------------------------------------------ */
  var slPlanSelect = el('sl-plan');
  if (slPlanSelect) {
    slPlanSelect.value = state.studentLoan.plan;
    slPlanSelect.addEventListener('change', function () {
      state.studentLoan.plan = slPlanSelect.value;
      updateStudentLoanPlanInfo();
    });
  }
  wireCurrencyInput('sl-balance', state.studentLoan, 'balance');
  wireCurrencyInput('sl-salary', state.studentLoan, 'salary');
  wireRangeInput('sl-growth-range', 'sl-growth-display', state.studentLoan, 'salaryGrowth');
  var slBtn = el('sl-calc-btn');
  if (slBtn) slBtn.addEventListener('click', calculateStudentLoan);
  updateStudentLoanPlanInfo();

  /* ------------------------------------------------------------------
     MORTGAGE AFFORDABILITY
  ------------------------------------------------------------------ */
  wireCurrencyInput('ma-salary1', state.mortgageAfford, 'salary1');
  wireCurrencyInput('ma-salary2', state.mortgageAfford, 'salary2');
  wireCurrencyInput('ma-debts', state.mortgageAfford, 'monthlyDebts');
  wireCurrencyInput('ma-deposit', state.mortgageAfford, 'deposit');
  var maBtn = el('ma-calc-btn');
  if (maBtn) maBtn.addEventListener('click', calculateMortgageAfford);

  /* ------------------------------------------------------------------
     STAMP DUTY
  ------------------------------------------------------------------ */
  wireCurrencyInput('sd-price', state.stampDuty, 'price');
  var sdBuyerSelect = el('sd-buyer');
  if (sdBuyerSelect) {
    sdBuyerSelect.value = state.stampDuty.buyerType;
    sdBuyerSelect.addEventListener('change', function () { state.stampDuty.buyerType = sdBuyerSelect.value; });
  }
  var sdCountrySelect = el('sd-country');
  if (sdCountrySelect) {
    sdCountrySelect.value = state.stampDuty.country;
    sdCountrySelect.addEventListener('change', function () { state.stampDuty.country = sdCountrySelect.value; });
  }
  var sdBtn = el('sd-calc-btn');
  if (sdBtn) sdBtn.addEventListener('click', calculateStampDuty);

  /* ------------------------------------------------------------------
     COMPOUND INTEREST
  ------------------------------------------------------------------ */
  wireCurrencyInput('ci-lump', state.compoundInterest, 'lumpSum');
  wireCurrencyInput('ci-monthly', state.compoundInterest, 'monthly');
  wireRangeInput('ci-rate-range', 'ci-rate-display', state.compoundInterest, 'rate');
  wireRangeInput('ci-term-range', 'ci-term-display', state.compoundInterest, 'term');

  document.querySelectorAll('[data-compound-freq]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      state.compoundInterest.frequency = btn.dataset.compoundFreq;
      document.querySelectorAll('[data-compound-freq]').forEach(function (b) {
        b.classList.toggle('is-active--spring', b.dataset.compoundFreq === state.compoundInterest.frequency);
        b.classList.toggle('is-active', b.dataset.compoundFreq === state.compoundInterest.frequency);
      });
    });
  });

  var ciBtn = el('ci-calc-btn');
  if (ciBtn) ciBtn.addEventListener('click', calculateCompoundInterest);

  /* ------------------------------------------------------------------
     PERSONAL LOAN
  ------------------------------------------------------------------ */
  wireCurrencyInput('pl-amount', state.personalLoan, 'amount');
  wireNumberInput('pl-rate', state.personalLoan, 'rate');
  wireRangeInput('pl-term-range', 'pl-term-display', state.personalLoan, 'term');
  var plBtn = el('pl-calc-btn');
  if (plBtn) plBtn.addEventListener('click', calculatePersonalLoan);

  /* ------------------------------------------------------------------
     PENSION WITHDRAWAL
  ------------------------------------------------------------------ */
  wireCurrencyInput('pw-pot', state.pensionWithdrawal, 'pot');
  wireCurrencyInput('pw-withdrawal', state.pensionWithdrawal, 'withdrawal');
  wireCurrencyInput('pw-other', state.pensionWithdrawal, 'otherIncome');

  document.querySelectorAll('[data-pw-type]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      state.pensionWithdrawal.withdrawalType = btn.dataset.pwType;
      document.querySelectorAll('[data-pw-type]').forEach(function (b) {
        b.classList.toggle('is-active--horizon', b.dataset.pwType === state.pensionWithdrawal.withdrawalType);
        b.classList.toggle('is-active', b.dataset.pwType === state.pensionWithdrawal.withdrawalType);
      });
    });
  });

  document.querySelectorAll('[data-pw-taxfree]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      state.pensionWithdrawal.taxFreeTaken = btn.dataset.pwTaxfree === 'true';
      document.querySelectorAll('[data-pw-taxfree]').forEach(function (b) {
        var match = (b.dataset.pwTaxfree === 'true') === state.pensionWithdrawal.taxFreeTaken;
        b.classList.toggle('is-active--horizon', match);
        b.classList.toggle('is-active', match);
      });
    });
  });

  var pwBtn = el('pw-calc-btn');
  if (pwBtn) pwBtn.addEventListener('click', calculatePensionWithdrawal);

  /* ------------------------------------------------------------------
     PENSION RELIEF
  ------------------------------------------------------------------ */
  wireCurrencyInput('pr-contribution', state.pensionRelief, 'contribution');
  var prBandSelect = el('pr-band');
  if (prBandSelect) {
    prBandSelect.value = state.pensionRelief.taxBand;
    prBandSelect.addEventListener('change', function () { state.pensionRelief.taxBand = prBandSelect.value; });
  }

  document.querySelectorAll('[data-pr-type]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      state.pensionRelief.pensionType = btn.dataset.prType;
      document.querySelectorAll('[data-pr-type]').forEach(function (b) {
        b.classList.toggle('is-active', b.dataset.prType === state.pensionRelief.pensionType);
      });
      updatePensionReliefInfo();
    });
  });

  var prBtn = el('pr-calc-btn');
  if (prBtn) prBtn.addEventListener('click', calculatePensionRelief);
  updatePensionReliefInfo();

  /* ------------------------------------------------------------------
     Load tax config then auto-calculate income tax
  ------------------------------------------------------------------ */
  loadTaxConfig();
});
