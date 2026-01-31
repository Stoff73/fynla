/**
 * Console Capture Service
 *
 * Intercepts console methods to capture logs for bug reports.
 * Maintains a circular buffer of the last 100 entries.
 * Also captures unhandled errors and promise rejections.
 */

const MAX_LOG_ENTRIES = 100;
const MAX_ENTRY_LENGTH = 2000;

// Circular buffer for log entries
let logBuffer = [];
let isCapturing = false;

// Store original console methods
const originalConsole = {
    log: console.log,
    error: console.error,
    warn: console.warn,
    info: console.info,
    debug: console.debug,
};

/**
 * Format a log entry with timestamp and level
 */
function formatEntry(level, args) {
    const timestamp = new Date().toISOString();
    let message;

    try {
        message = args.map(arg => {
            if (arg === null) return 'null';
            if (arg === undefined) return 'undefined';
            if (typeof arg === 'object') {
                try {
                    return JSON.stringify(arg, null, 2);
                } catch (e) {
                    return String(arg);
                }
            }
            return String(arg);
        }).join(' ');
    } catch (e) {
        message = '[Failed to serialize log arguments]';
    }

    // Truncate very long messages
    if (message.length > MAX_ENTRY_LENGTH) {
        message = message.substring(0, MAX_ENTRY_LENGTH) + '... [truncated]';
    }

    return `[${timestamp}] [${level.toUpperCase()}] ${message}`;
}

/**
 * Add an entry to the circular buffer
 */
function addEntry(level, args) {
    const entry = formatEntry(level, args);
    logBuffer.push(entry);

    // Maintain circular buffer size
    if (logBuffer.length > MAX_LOG_ENTRIES) {
        logBuffer.shift();
    }
}

/**
 * Create a wrapped console method
 */
function createWrapper(level) {
    return function (...args) {
        // Always call original method
        originalConsole[level].apply(console, args);

        // Capture if enabled
        if (isCapturing) {
            addEntry(level, args);
        }
    };
}

/**
 * Handle unhandled errors
 */
function handleError(event) {
    if (!isCapturing) return;

    const { message, filename, lineno, colno, error } = event;
    const stack = error?.stack || 'No stack trace available';

    addEntry('error', [
        `Unhandled Error: ${message}`,
        `at ${filename}:${lineno}:${colno}`,
        stack
    ]);
}

/**
 * Handle unhandled promise rejections
 */
function handleRejection(event) {
    if (!isCapturing) return;

    const reason = event.reason;
    let message;

    if (reason instanceof Error) {
        message = `Unhandled Promise Rejection: ${reason.message}\n${reason.stack || ''}`;
    } else {
        try {
            message = `Unhandled Promise Rejection: ${JSON.stringify(reason)}`;
        } catch (e) {
            message = `Unhandled Promise Rejection: ${String(reason)}`;
        }
    }

    addEntry('error', [message]);
}

/**
 * Start capturing console output
 */
function startCapture() {
    if (isCapturing) return;

    isCapturing = true;

    // Wrap console methods
    console.log = createWrapper('log');
    console.error = createWrapper('error');
    console.warn = createWrapper('warn');
    console.info = createWrapper('info');
    console.debug = createWrapper('debug');

    // Listen for unhandled errors
    window.addEventListener('error', handleError);
    window.addEventListener('unhandledrejection', handleRejection);

    // Add startup marker
    addEntry('info', ['Console capture started']);
}

/**
 * Stop capturing console output
 */
function stopCapture() {
    if (!isCapturing) return;

    isCapturing = false;

    // Restore original console methods
    console.log = originalConsole.log;
    console.error = originalConsole.error;
    console.warn = originalConsole.warn;
    console.info = originalConsole.info;
    console.debug = originalConsole.debug;

    // Remove error listeners
    window.removeEventListener('error', handleError);
    window.removeEventListener('unhandledrejection', handleRejection);
}

/**
 * Get all captured logs as a string
 */
function getLogs() {
    return logBuffer.join('\n');
}

/**
 * Clear the log buffer
 */
function clearLogs() {
    logBuffer = [];
}

/**
 * Get the number of captured entries
 */
function getLogCount() {
    return logBuffer.length;
}

export default {
    startCapture,
    stopCapture,
    getLogs,
    clearLogs,
    getLogCount,
};
