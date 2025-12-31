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
 * Tax Year: 2025/26 (April 6, 2025 - April 5, 2026)
 *
 * @see app/Services/TaxConfigService.php - Backend authoritative source
 * @see database/seeders/TaxConfigurationSeeder.php - Database values
 */

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
export const MONEY_PURCHASE_ANNUAL_ALLOWANCE = 10000; // After accessing benefits
export const PENSION_LIFETIME_ALLOWANCE = null; // Abolished from 2024/25

/**
 * Income Tax Allowances
 */
export const PERSONAL_ALLOWANCE = 12570;
export const PERSONAL_ALLOWANCE_TAPER_THRESHOLD = 100000;
export const HIGHER_RATE_THRESHOLD = 50270;
export const ADDITIONAL_RATE_THRESHOLD = 125140;

/**
 * Capital Gains Tax
 */
export const CGT_ANNUAL_ALLOWANCE = 3000;

/**
 * Inheritance Tax
 */
export const IHT_NIL_RATE_BAND = 325000;
export const IHT_RESIDENCE_NIL_RATE_BAND = 175000;
export const IHT_RNRB_TAPER_THRESHOLD = 2000000;
export const IHT_STANDARD_RATE = 0.40;
export const IHT_REDUCED_RATE = 0.36; // When 10%+ left to charity

/**
 * Other Allowances
 */
export const DIVIDEND_ALLOWANCE = 500;
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
  // ISA
  ISA_ANNUAL_ALLOWANCE,
  LIFETIME_ISA_ALLOWANCE,
  JUNIOR_ISA_ALLOWANCE,

  // Income Tax
  PERSONAL_ALLOWANCE,
  PERSONAL_ALLOWANCE_TAPER_THRESHOLD,
  HIGHER_RATE_THRESHOLD,
  ADDITIONAL_RATE_THRESHOLD,

  // Pension
  PENSION_ANNUAL_ALLOWANCE,
  MONEY_PURCHASE_ANNUAL_ALLOWANCE,

  // CGT
  CGT_ALLOWANCE: CGT_ANNUAL_ALLOWANCE,
  CGT_ANNUAL_ALLOWANCE,

  // IHT
  IHT_NIL_RATE_BAND,
  IHT_RESIDENCE_NIL_RATE_BAND,
  IHT_RNRB_TAPER_THRESHOLD,
  IHT_STANDARD_RATE,
  IHT_REDUCED_RATE,

  // Other
  DIVIDEND_ALLOWANCE,
  SAVINGS_ALLOWANCE_BASIC,
  SAVINGS_ALLOWANCE_HIGHER,
  MARRIAGE_ALLOWANCE,
  ANNUAL_GIFT_EXEMPTION,
  SMALL_GIFT_EXEMPTION,
};

export default TAX_CONFIG;
