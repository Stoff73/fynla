/**
 * Date formatting utilities for the FPS application.
 *
 * Provides consistent date formatting across Vue components.
 */

/**
 * Format a date for HTML5 date input fields.
 *
 * HTML5 date inputs require yyyy-MM-dd format.
 *
 * @param {Date|string|null} date - The date to format
 * @returns {string} Formatted date string (yyyy-MM-dd) or empty string if invalid
 *
 * @example
 * formatDateForInput('2025-01-15T00:00:00Z')
 * // Returns: '2025-01-15'
 *
 * formatDateForInput(new Date(2025, 0, 15))
 * // Returns: '2025-01-15'
 */
export function formatDateForInput(date) {
  if (!date) return '';

  const d = date instanceof Date ? date : new Date(date);

  if (isNaN(d.getTime())) return '';

  const year = d.getFullYear();
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');

  return `${year}-${month}-${day}`;
}

/**
 * Format a date for display (UK format: dd/MM/yyyy).
 *
 * @param {Date|string|null} date - The date to format
 * @returns {string} Formatted date string or empty string if invalid
 *
 * @example
 * formatDateForDisplay('2025-01-15')
 * // Returns: '15/01/2025'
 */
export function formatDateForDisplay(date) {
  if (!date) return '';

  const d = date instanceof Date ? date : new Date(date);

  if (isNaN(d.getTime())) return '';

  const day = String(d.getDate()).padStart(2, '0');
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const year = d.getFullYear();

  return `${day}/${month}/${year}`;
}

/**
 * Format a date as a relative time string.
 *
 * @param {Date|string|null} date - The date to format
 * @returns {string} Relative time string (e.g., '2 days ago', 'in 3 months')
 */
export function formatRelativeDate(date) {
  if (!date) return '';

  const d = date instanceof Date ? date : new Date(date);

  if (isNaN(d.getTime())) return '';

  const now = new Date();
  const diffMs = d.getTime() - now.getTime();
  const diffDays = Math.round(diffMs / (1000 * 60 * 60 * 24));

  if (diffDays === 0) return 'Today';
  if (diffDays === 1) return 'Tomorrow';
  if (diffDays === -1) return 'Yesterday';

  if (diffDays > 0) {
    if (diffDays < 7) return `in ${diffDays} days`;
    if (diffDays < 30) return `in ${Math.round(diffDays / 7)} weeks`;
    if (diffDays < 365) return `in ${Math.round(diffDays / 30)} months`;
    return `in ${Math.round(diffDays / 365)} years`;
  }

  const absDays = Math.abs(diffDays);
  if (absDays < 7) return `${absDays} days ago`;
  if (absDays < 30) return `${Math.round(absDays / 7)} weeks ago`;
  if (absDays < 365) return `${Math.round(absDays / 30)} months ago`;
  return `${Math.round(absDays / 365)} years ago`;
}

/**
 * Parse a UK date string (dd/MM/yyyy) into a Date object.
 *
 * @param {string} dateString - The date string in UK format
 * @returns {Date|null} Parsed Date object or null if invalid
 */
export function parseUKDate(dateString) {
  if (!dateString) return null;

  const parts = dateString.split('/');
  if (parts.length !== 3) return null;

  const day = parseInt(parts[0], 10);
  const month = parseInt(parts[1], 10) - 1; // Months are 0-indexed
  const year = parseInt(parts[2], 10);

  const date = new Date(year, month, day);

  // Validate the date is valid
  if (date.getDate() !== day || date.getMonth() !== month || date.getFullYear() !== year) {
    return null;
  }

  return date;
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
