// Mobile bug-report diagnostics gatherer (SP3 scaffold).
//
// Collects browser/app/route context to attach to a bug report.

// Build-time version (set VITE_APP_VERSION in the build scripts). Falls back
// to a readable default so the field is never empty.
const BUILD_VERSION = import.meta.env.VITE_APP_VERSION || 'dev';

/**
 * Gather diagnostics for a bug report.
 *
 * @param {string} route - The current in-app route/path.
 * @returns {Promise<object>} platform, device_model, os_version, app_version, route
 */
export async function gatherDiagnostics(route) {
  return {
    route: route || (typeof window !== 'undefined' ? window.location.pathname : ''),
    platform: 'web',
    device_model: typeof navigator !== 'undefined' ? 'browser' : 'unknown',
    os_version: (typeof navigator !== 'undefined' && navigator.platform) || 'unknown',
    app_version: BUILD_VERSION,
  };
}

export default { gatherDiagnostics };
