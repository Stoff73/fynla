// Mobile bug-report diagnostics gatherer (SP3 scaffold).
//
// Collects device/app/route context to attach to a bug report. Capacitor
// plugins are imported dynamically and guarded so the same bundle works on
// web (where Capacitor is absent) without throwing.

const isNative = typeof window !== 'undefined' && !!window.Capacitor?.isNativePlatform?.();

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
  const diagnostics = {
    route: route || (typeof window !== 'undefined' ? window.location.pathname : ''),
    platform: isNative ? 'ios' : 'web',
    device_model: 'unknown',
    os_version: 'unknown',
    app_version: BUILD_VERSION,
  };

  if (isNative) {
    try {
      const { Device } = await import('@capacitor/device');
      const info = await Device.getInfo();
      diagnostics.platform = info.platform || diagnostics.platform;
      diagnostics.device_model = info.model || 'unknown';
      diagnostics.os_version = `${info.operatingSystem || ''} ${info.osVersion || ''}`.trim() || 'unknown';
    } catch (e) {
      // Plugin unavailable — keep defaults.
    }

    try {
      const { App } = await import('@capacitor/app');
      const appInfo = await App.getInfo();
      if (appInfo?.version) {
        diagnostics.app_version = `${appInfo.version}${appInfo.build ? ` (${appInfo.build})` : ''}`;
      }
    } catch (e) {
      // Plugin unavailable — keep build-time version.
    }
  } else if (typeof navigator !== 'undefined') {
    diagnostics.device_model = 'browser';
    diagnostics.os_version = navigator.platform || 'unknown';
  }

  return diagnostics;
}

export default { gatherDiagnostics };
