/**
 * UK Tax Configuration Fallback Constants
 *
 * IMPORTANT: These are FALLBACK values only. The authoritative source of tax
 * configuration is the backend TaxConfigService, which loads values from the
 * database. Components should:
 *
 * 1. PREFER fetching from API (e.g., /api/tax-settings/current)
 * 2. PREFER using Vuex store values (e.g., savings/isaAllowance)
 * 3. ONLY USE these constants as fallbacks when API data is unavailable
 *
 * These fallback values ensure the UI doesn't break if the API call fails,
 * but they should NOT be treated as the source of truth.
 *
 * Tax Year: 2026/27 (April 6, 2026 - April 5, 2027)
 *
 * @see app/Services/TaxConfigService.php - Backend authoritative source
 * @see database/seeders/TaxConfigurationSeeder.php - Database values
 */

/**
 * Active tax year (fallback reference).
 * The backend remains the source of truth — call /api/tax-settings/current
 * when a component needs to display or compute against the live tax year.
 */
export const TAX_YEAR = '2026/27';

/**
 * ISA (Individual Savings Account) Allowances
 *
 * Note: Total ISA contributions across all ISA types cannot exceed
 * ISA_ANNUAL_ALLOWANCE in a single tax year.
 */
export const ISA_ANNUAL_ALLOWANCE = 20000;
export const LIFETIME_ISA_ALLOWANCE = 4000; // Counts towards ISA_ANNUAL_ALLOWANCE
export const JUNIOR_ISA_ALLOWANCE = 9000;   // Separate from adult ISA allowance

/**
 * Pension Allowances
 */
export const PENSION_ANNUAL_ALLOWANCE = 60000;
export const ANNUAL_ALLOWANCE = 60000;
export const MONEY_PURCHASE_ANNUAL_ALLOWANCE = 10000; // After accessing benefits
export const PENSION_LIFETIME_ALLOWANCE = null; // Abolished from 2024/25

/**
 * State Pension (full new State Pension)
 */
export const STATE_PENSION_WEEKLY = 241.30;
export const STATE_PENSION_ANNUAL = 12547.60;

/**
 * Income Tax Allowances & Rates
 */
export const PERSONAL_ALLOWANCE = 12570;
export const PERSONAL_ALLOWANCE_TAPER_THRESHOLD = 100000;
export const HIGHER_RATE_THRESHOLD = 50270;
export const ADDITIONAL_RATE_THRESHOLD = 125140;
export const BASIC_RATE = 0.20;
export const HIGHER_RATE = 0.40;
export const ADDITIONAL_RATE = 0.45;

/**
 * National Insurance (Employee Class 1)
 */
export const NI_PRIMARY_THRESHOLD = 12570;
export const NI_UPPER_EARNINGS_LIMIT = 50270;
export const NI_BASIC_RATE = 0.08;     // Reduced from 12% to 8% from April 2024
export const NI_ADDITIONAL_RATE = 0.02;

/**
 * Stamp Duty Land Tax — England (from 1 April 2025)
 */
export const SDLT_STANDARD_BANDS = [
  { threshold: 125000, rate: 0 },
  { threshold: 250000, rate: 2 },
  { threshold: 925000, rate: 5 },
  { threshold: 1500000, rate: 10 },
  { threshold: Infinity, rate: 12 },
];
export const SDLT_FTB_BANDS = [
  { threshold: 300000, rate: 0 },
  { threshold: 500000, rate: 5 },
];
export const SDLT_FTB_MAX_PRICE = 500000;
export const SDLT_ADDITIONAL_SURCHARGE = 5;
export const SDLT_NON_UK_SURCHARGE = 2;

/**
 * Land and Buildings Transaction Tax — Scotland
 */
export const LBTT_BANDS = [
  { threshold: 145000, rate: 0 },
  { threshold: 250000, rate: 2 },
  { threshold: 325000, rate: 5 },
  { threshold: 750000, rate: 10 },
  { threshold: Infinity, rate: 12 },
];
export const LBTT_ADDITIONAL_SURCHARGE = 8; // Increased from 6% on 5 Dec 2024
export const LBTT_NON_UK_SURCHARGE = 2;

/**
 * Land Transaction Tax — Wales
 */
export const LTT_BANDS = [
  { threshold: 225000, rate: 0 },
  { threshold: 400000, rate: 6 },
  { threshold: 750000, rate: 7.5 },
  { threshold: 1500000, rate: 10 },
  { threshold: Infinity, rate: 12 },
];
export const LTT_ADDITIONAL_SURCHARGE = 5; // Increased from 4% on 11 Dec 2024
export const LTT_NON_UK_SURCHARGE = 2;

/**
 * Capital Gains Tax
 */
export const CGT_ANNUAL_ALLOWANCE = 3000;
export const CGT_BASIC_RATE = 0.18;
export const CGT_HIGHER_RATE = 0.24;
export const BADR_RATE = 0.18;              // Business Asset Disposal Relief (was 14% in 2025/26)
export const BADR_LIFETIME_LIMIT = 1000000;

/**
 * Inheritance Tax
 */
export const IHT_NIL_RATE_BAND = 325000;
export const IHT_RESIDENCE_NIL_RATE_BAND = 175000;
export const IHT_RNRB_TAPER_THRESHOLD = 2000000;
export const IHT_STANDARD_RATE = 0.40;
export const IHT_REDUCED_RATE = 0.36; // When 10%+ left to charity

/**
 * Dividend Tax (2026/27: basic and higher rates each +2pp)
 */
export const DIVIDEND_ALLOWANCE = 500;
export const DIVIDEND_BASIC_RATE = 0.1075;      // Was 8.75% in 2025/26
export const DIVIDEND_HIGHER_RATE = 0.3575;     // Was 33.75% in 2025/26
export const DIVIDEND_ADDITIONAL_RATE = 0.3935;

/**
 * Pension Tax-Free Cash
 */
export const PENSION_TAX_FREE_RATE = 0.25;           // 25% tax-free portion
export const PENSION_TAX_FREE_LUMP_SUM_LIMIT = 268275; // Lifetime limit

/**
 * Student Loan Repayment
 */
export const STUDENT_LOAN_REPAYMENT_RATE = 0.09; // 9% of income above threshold (all plans)

/**
 * Statutory Sick Pay
 */
export const SSP_WEEKLY_RATE = 123.25; // 2026/27

/**
 * High Income Child Benefit Charge
 */
export const HICBC_THRESHOLD = 60000;

/**
 * Pension Annual Allowance Taper
 */
export const PENSION_TAPER_THRESHOLD_INCOME = 200000;
export const PENSION_TAPER_ADJUSTED_INCOME = 260000;

/**
 * Other Allowances
 */
export const SAVINGS_ALLOWANCE_BASIC = 1000;
export const SAVINGS_ALLOWANCE_HIGHER = 500;
export const MARRIAGE_ALLOWANCE = 1260;

/**
 * Gifting Exemptions
 */
export const ANNUAL_GIFT_EXEMPTION = 3000;
export const SMALL_GIFT_EXEMPTION = 250;

/**
 * Legacy export for backwards compatibility
 * @deprecated Use individual named exports instead
 */
export const TAX_CONFIG = {
  // Tax year
  TAX_YEAR,

  // ISA
  ISA_ANNUAL_ALLOWANCE,
  LIFETIME_ISA_ALLOWANCE,
  JUNIOR_ISA_ALLOWANCE,

  // Income Tax
  PERSONAL_ALLOWANCE,
  PERSONAL_ALLOWANCE_TAPER_THRESHOLD,
  HIGHER_RATE_THRESHOLD,
  ADDITIONAL_RATE_THRESHOLD,
  BASIC_RATE,
  HIGHER_RATE,
  ADDITIONAL_RATE,

  // National Insurance
  NI_PRIMARY_THRESHOLD,
  NI_UPPER_EARNINGS_LIMIT,
  NI_BASIC_RATE,
  NI_ADDITIONAL_RATE,

  // SDLT
  SDLT_STANDARD_BANDS,
  SDLT_FTB_BANDS,
  SDLT_FTB_MAX_PRICE,
  SDLT_ADDITIONAL_SURCHARGE,
  SDLT_NON_UK_SURCHARGE,

  // LBTT
  LBTT_BANDS,
  LBTT_ADDITIONAL_SURCHARGE,
  LBTT_NON_UK_SURCHARGE,

  // LTT
  LTT_BANDS,
  LTT_ADDITIONAL_SURCHARGE,
  LTT_NON_UK_SURCHARGE,

  // Pension
  PENSION_ANNUAL_ALLOWANCE,
  MONEY_PURCHASE_ANNUAL_ALLOWANCE,

  // CGT
  CGT_ALLOWANCE: CGT_ANNUAL_ALLOWANCE,
  CGT_ANNUAL_ALLOWANCE,
  CGT_BASIC_RATE,
  CGT_HIGHER_RATE,
  BADR_RATE,
  BADR_LIFETIME_LIMIT,

  // IHT
  IHT_NIL_RATE_BAND,
  IHT_RESIDENCE_NIL_RATE_BAND,
  IHT_RNRB_TAPER_THRESHOLD,
  IHT_STANDARD_RATE,
  IHT_REDUCED_RATE,

  // Dividends
  DIVIDEND_ALLOWANCE,
  DIVIDEND_BASIC_RATE,
  DIVIDEND_HIGHER_RATE,
  DIVIDEND_ADDITIONAL_RATE,

  // Other
  SAVINGS_ALLOWANCE_BASIC,
  SAVINGS_ALLOWANCE_HIGHER,
  MARRIAGE_ALLOWANCE,
  ANNUAL_GIFT_EXEMPTION,
  SMALL_GIFT_EXEMPTION,

  // State Pension
  STATE_PENSION_WEEKLY,
  STATE_PENSION_ANNUAL,

  // Statutory Sick Pay
  SSP_WEEKLY_RATE,

  // HICBC
  HICBC_THRESHOLD,

  // Pension Taper
  PENSION_TAPER_THRESHOLD_INCOME,
  PENSION_TAPER_ADJUSTED_INCOME,

  // Pension Tax-Free Cash
  PENSION_TAX_FREE_RATE,
  PENSION_TAX_FREE_LUMP_SUM_LIMIT,

  // Student Loan
  STUDENT_LOAN_REPAYMENT_RATE,
};

export default TAX_CONFIG;
