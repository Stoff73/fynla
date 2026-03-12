/**
 * Platform Detection Utility
 *
 * Detects whether the app is running as a native Capacitor app,
 * on iOS/Android, or in a standard web browser.
 * Uses @capacitor/core for reliable detection.
 */

import { Capacitor } from '@capacitor/core';

export const platform = {
    isNative: () => Capacitor.isNativePlatform(),

    isIOS: () => Capacitor.getPlatform() === 'ios',

    isAndroid: () => Capacitor.getPlatform() === 'android',

    isWeb: () => Capacitor.getPlatform() === 'web',

    isMobileViewport: () => typeof window !== 'undefined' && window.innerWidth < 768,

    canUseBiometrics: () => Capacitor.isNativePlatform(),

    canUsePushNotifications: () => Capacitor.isNativePlatform(),

    canUseHaptics: () => Capacitor.isNativePlatform(),

    canUseVoiceInput: () => Capacitor.isNativePlatform(),
};
