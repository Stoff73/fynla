/**
 * Platform Detection Utility
 *
 * Detects whether the app is running as a native Capacitor app,
 * on iOS/Android, or in a standard web browser.
 */

export const platform = {
    isNative: () =>
        typeof window !== 'undefined' &&
        typeof window.Capacitor !== 'undefined' &&
        window.Capacitor.isNativePlatform(),

    isIOS: () => platform.isNative() && window.Capacitor.getPlatform() === 'ios',

    isAndroid: () => platform.isNative() && window.Capacitor.getPlatform() === 'android',

    isWeb: () => !platform.isNative(),

    isMobileViewport: () => typeof window !== 'undefined' && window.innerWidth < 768,
};
