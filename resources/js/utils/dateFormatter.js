/**
 * Date Formatter Utility
 * Provides functions for formatting and parsing dates in UK format (DD/MM/YYYY)
 */

/**
 * Format a date as DD/MM/YYYY
 * @param {Date|string} date - The date to format
 * @returns {string} Formatted date string
 */
export function formatDate(date) {
  // Handle null, undefined, or empty values
  if (!date) {
    return '';
  }

  // Convert to Date object if string
  const dateObj = typeof date === 'string' ? new Date(date) : date;

  // Check if valid date
  if (!(dateObj instanceof Date) || isNaN(dateObj.getTime())) {
    return '';
  }

  // Extract day, month, year
  const day = String(dateObj.getDate()).padStart(2, '0');
  const month = String(dateObj.getMonth() + 1).padStart(2, '0');
  const year = dateObj.getFullYear();

  return `${day}/${month}/${year}`;
}

/**
 * Format a date for HTML date input (YYYY-MM-DD)
 * @param {Date|string} date - The date to format
 * @returns {string} Formatted date string for input
 */
export function formatDateForInput(date) {
  // Handle null, undefined, or empty values
  if (!date) {
    return '';
  }

  // Convert to Date object if string
  const dateObj = typeof date === 'string' ? new Date(date) : date;

  // Check if valid date
  if (!(dateObj instanceof Date) || isNaN(dateObj.getTime())) {
    return '';
  }

  // Extract day, month, year
  const day = String(dateObj.getDate()).padStart(2, '0');
  const month = String(dateObj.getMonth() + 1).padStart(2, '0');
  const year = dateObj.getFullYear();

  return `${year}-${month}-${day}`;
}

/**
 * Parse a date string (DD/MM/YYYY or YYYY-MM-DD) to a Date object
 * @param {string} dateString - The date string to parse
 * @returns {Date|null} Parsed Date object or null if invalid
 */
export function parseDate(dateString) {
  // Handle null, undefined, or empty values
  if (!dateString || dateString === '') {
    return null;
  }

  // Check if ISO format (YYYY-MM-DD)
  if (/^\d{4}-\d{2}-\d{2}/.test(dateString)) {
    const date = new Date(dateString);
    return isNaN(date.getTime()) ? null : date;
  }

  // Check if UK format (DD/MM/YYYY)
  if (/^\d{2}\/\d{2}\/\d{4}/.test(dateString)) {
    const parts = dateString.split('/');
    if (parts.length !== 3) {
      return null;
    }

    const day = parseInt(parts[0], 10);
    const month = parseInt(parts[1], 10) - 1; // Month is 0-indexed
    const year = parseInt(parts[2], 10);

    const date = new Date(year, month, day);

    // Validate the date is correct (handles invalid dates like 31/02/2024)
    if (
      date.getDate() !== day ||
      date.getMonth() !== month ||
      date.getFullYear() !== year
    ) {
      return null;
    }

    return date;
  }

  // Try parsing as is
  const date = new Date(dateString);
  return isNaN(date.getTime()) ? null : date;
}

/**
 * Format a date with month name (e.g., "15 January 2024")
 * @param {Date|string} date - The date to format
 * @param {boolean} shortMonth - Use short month name (Jan vs January)
 * @returns {string} Formatted date string
 */
export function formatDateLong(date, shortMonth = false) {
  // Handle null, undefined, or empty values
  if (!date) {
    return '';
  }

  // Convert to Date object if string
  const dateObj = typeof date === 'string' ? new Date(date) : date;

  // Check if valid date
  if (!(dateObj instanceof Date) || isNaN(dateObj.getTime())) {
    return '';
  }

  const options = {
    day: 'numeric',
    month: shortMonth ? 'short' : 'long',
    year: 'numeric',
  };

  return dateObj.toLocaleDateString('en-GB', options);
}

/**
 * Calculate age from date of birth
 * @param {Date|string} dateOfBirth - The date of birth
 * @param {Date|string} asOfDate - The date to calculate age as of (default: today)
 * @returns {number} Age in years
 */
export function calculateAge(dateOfBirth, asOfDate = new Date()) {
  // Parse dates
  const dob = typeof dateOfBirth === 'string' ? parseDate(dateOfBirth) : dateOfBirth;
  const asOf = typeof asOfDate === 'string' ? parseDate(asOfDate) : asOfDate;

  // Validate dates
  if (!dob || !asOf) {
    return 0;
  }

  let age = asOf.getFullYear() - dob.getFullYear();
  const monthDiff = asOf.getMonth() - dob.getMonth();

  // Adjust age if birthday hasn't occurred yet this year
  if (monthDiff < 0 || (monthDiff === 0 && asOf.getDate() < dob.getDate())) {
    age--;
  }

  return age;
}

/**
 * Get relative time string (e.g., "2 days ago", "in 3 months")
 * @param {Date|string} date - The date to compare
 * @returns {string} Relative time string
 */
export function getRelativeTime(date) {
  // Handle null, undefined, or empty values
  if (!date) {
    return '';
  }

  // Convert to Date object if string
  const dateObj = typeof date === 'string' ? new Date(date) : date;

  // Check if valid date
  if (!(dateObj instanceof Date) || isNaN(dateObj.getTime())) {
    return '';
  }

  const now = new Date();
  const diffMs = dateObj.getTime() - now.getTime();
  const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

  if (diffDays === 0) {
    return 'today';
  } else if (diffDays === 1) {
    return 'tomorrow';
  } else if (diffDays === -1) {
    return 'yesterday';
  } else if (diffDays > 0 && diffDays < 7) {
    return `in ${diffDays} days`;
  } else if (diffDays < 0 && diffDays > -7) {
    return `${Math.abs(diffDays)} days ago`;
  } else if (diffDays > 0 && diffDays < 30) {
    const weeks = Math.floor(diffDays / 7);
    return `in ${weeks} week${weeks > 1 ? 's' : ''}`;
  } else if (diffDays < 0 && diffDays > -30) {
    const weeks = Math.floor(Math.abs(diffDays) / 7);
    return `${weeks} week${weeks > 1 ? 's' : ''} ago`;
  } else if (diffDays > 0 && diffDays < 365) {
    const months = Math.floor(diffDays / 30);
    return `in ${months} month${months > 1 ? 's' : ''}`;
  } else if (diffDays < 0 && diffDays > -365) {
    const months = Math.floor(Math.abs(diffDays) / 30);
    return `${months} month${months > 1 ? 's' : ''} ago`;
  } else if (diffDays > 0) {
    const years = Math.floor(diffDays / 365);
    return `in ${years} year${years > 1 ? 's' : ''}`;
  } else {
    const years = Math.floor(Math.abs(diffDays) / 365);
    return `${years} year${years > 1 ? 's' : ''} ago`;
  }
}

/**
 * Get the current tax year start date.
 *
 * UK tax year runs from 6 April to 5 April.
 *
 * @param {Date} [referenceDate] - Reference date (defaults to now)
 * @returns {Date} Start of the current tax year
 */
export function getTaxYearStart(referenceDate = new Date()) {
  const year = referenceDate.getFullYear();
  const month = referenceDate.getMonth();
  const day = referenceDate.getDate();

  // If before 6 April, tax year started previous year
  if (month < 3 || (month === 3 && day < 6)) {
    return new Date(year - 1, 3, 6); // 6 April previous year
  }

  return new Date(year, 3, 6); // 6 April this year
}

/**
 * Get the current tax year end date.
 *
 * @param {Date} [referenceDate] - Reference date (defaults to now)
 * @returns {Date} End of the current tax year
 */
export function getTaxYearEnd(referenceDate = new Date()) {
  const start = getTaxYearStart(referenceDate);
  return new Date(start.getFullYear() + 1, 3, 5); // 5 April next year
}

/**
 * Cached active tax year from the backend. Populated once on app load by
 * dispatching taxConfig/fetchActive (see store/modules/taxConfig.js).
 *
 * When set, getCurrentTaxYear() returns this value instead of the calendar
 * value — so if an admin has switched the active year in TaxSettings, every
 * component that displays "current tax year" updates together.
 *
 * When null, getCurrentTaxYear() falls back to pure calendar logic (used by
 * onboarding, public pages, and tests that have no logged-in session).
 */
let _activeTaxYearFromBackend = null;

/**
 * Set the active tax year fetched from the backend. Called from the
 * taxConfig Vuex store after GET /api/tax-year/current.
 *
 * Pass null to clear (e.g. on logout).
 */
export function setActiveTaxYear(taxYear) {
  _activeTaxYearFromBackend = taxYear || null;
}

/**
 * Compute the calendar-based UK tax year for a given date, ignoring any
 * backend override. Use this when you specifically need the calendar year
 * (e.g. to compare it to the admin-selected year).
 */
export function getCalendarTaxYear(referenceDate = new Date()) {
  const start = getTaxYearStart(referenceDate);
  const startYear = start.getFullYear();
  return `${startYear}/${String(startYear + 1).slice(-2)}`;
}

/**
 * Get the current UK tax year as a formatted string (e.g., "2026/27").
 *
 * When the backend has told us which tax year is active (via
 * setActiveTaxYear), that value is returned — this keeps the UI aligned
 * with whatever the admin has selected in TaxSettings, even on April 5
 * when the calendar and DB years disagree.
 *
 * If a referenceDate is passed explicitly, the calendar logic is used
 * instead (so historical/future date pickers still work).
 *
 * @param {Date} [referenceDate] - Reference date (defaults to now). Pass
 *   a date explicitly to bypass the backend override.
 * @returns {string} Tax year string like "2026/27"
 */
export function getCurrentTaxYear(referenceDate) {
  if (_activeTaxYearFromBackend && !referenceDate) {
    return _activeTaxYearFromBackend;
  }
  const start = getTaxYearStart(referenceDate || new Date());
  const startYear = start.getFullYear();
  return `${startYear}/${String(startYear + 1).slice(-2)}`;
}

export default {
  formatDate,
  formatDateForInput,
  parseDate,
  formatDateLong,
  calculateAge,
  getRelativeTime,
  getTaxYearStart,
  getTaxYearEnd,
  getCurrentTaxYear,
  getCalendarTaxYear,
  setActiveTaxYear,
};
