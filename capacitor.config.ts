import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'org.fynla.app',
  appName: 'Fynla',
  webDir: 'public/build',
  server: {
    url: process.env.CAPACITOR_DEV ? 'http://localhost:5173' : undefined,
    androidScheme: 'https',
  },
  plugins: {
    SplashScreen: {
      backgroundColor: '#F7F6F4',
      spinnerColor: '#E83E6D',
      launchAutoHide: true,
      launchShowDuration: 2000,
    },
    PushNotifications: {
      presentationOptions: ['badge', 'sound', 'alert'],
    },
    Keyboard: {
      resize: 'body',
      style: 'light',
    },
  },
};

export default config;
