/**
 * Logger Utility
 *
 * A centralized logging utility that:
 * - Only outputs logs in development environment
 * - Provides consistent log formatting
 * - Can be easily toggled for debugging specific modules
 *
 * Usage:
 *   import logger from '@/utils/logger';
 *   logger.info('MyComponent', 'Data loaded', { items: 5 });
 *   logger.error('MyComponent', 'Failed to load', error);
 *   logger.debug('MyComponent', 'State changed', newState);
 *
 * SECURITY: Prevents sensitive debugging information from appearing in production
 */

const isDev = import.meta.env.DEV;

// Enable/disable specific log categories
const enabledCategories = {
  info: true,
  warn: true,
  error: true, // Errors always show in dev
  debug: true,
  table: true,
};

/**
 * Format log message with timestamp and context
 */
const formatMessage = (context, message) => {
  const timestamp = new Date().toISOString().split('T')[1].slice(0, 12);
  return `[${timestamp}] [${context}] ${message}`;
};

/**
 * Generic log function
 */
const log = (level, context, message, ...args) => {
  if (!isDev || !enabledCategories[level]) {
    return;
  }

  const formattedMessage = formatMessage(context, message);

  switch (level) {
    case 'error':
      console.error(formattedMessage, ...args);
      break;
    case 'warn':
      console.warn(formattedMessage, ...args);
      break;
    case 'debug':
      console.debug(formattedMessage, ...args);
      break;
    case 'table':
      console.log(formattedMessage);
      console.table(...args);
      break;
    default:
      console.log(formattedMessage, ...args);
  }
};

const logger = {
  /**
   * Log informational message
   */
  info: (context, message, ...args) => log('info', context, message, ...args),

  /**
   * Log warning message
   */
  warn: (context, message, ...args) => log('warn', context, message, ...args),

  /**
   * Log error message (use for caught exceptions)
   */
  error: (context, message, ...args) => log('error', context, message, ...args),

  /**
   * Log debug message (more verbose than info)
   */
  debug: (context, message, ...args) => log('debug', context, message, ...args),

  /**
   * Log data as a table
   */
  table: (context, message, data) => log('table', context, message, data),

  /**
   * Log with custom styling (for visual emphasis in dev)
   */
  styled: (context, message, style = 'color: blue; font-weight: bold') => {
    if (!isDev) return;
    console.log(`%c${formatMessage(context, message)}`, style);
  },

  /**
   * Group related logs together
   */
  group: (context, label, callback) => {
    if (!isDev) {
      callback();
      return;
    }
    console.group(formatMessage(context, label));
    callback();
    console.groupEnd();
  },

  /**
   * Time an operation
   */
  time: (context, label, callback) => {
    if (!isDev) {
      return callback();
    }
    const timerLabel = formatMessage(context, label);
    console.time(timerLabel);
    const result = callback();
    console.timeEnd(timerLabel);
    return result;
  },

  /**
   * Async time an operation
   */
  timeAsync: async (context, label, callback) => {
    if (!isDev) {
      return await callback();
    }
    const timerLabel = formatMessage(context, label);
    console.time(timerLabel);
    const result = await callback();
    console.timeEnd(timerLabel);
    return result;
  },

  /**
   * Enable/disable a log category
   */
  setCategory: (category, enabled) => {
    if (category in enabledCategories) {
      enabledCategories[category] = enabled;
    }
  },

  /**
   * Check if currently in development mode
   */
  isDev: () => isDev,
};

export default logger;
