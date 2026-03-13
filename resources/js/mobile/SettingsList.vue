<template>
  <div>
    <h3 class="text-sm font-bold text-horizon-500 mb-2">Settings</h3>
    <div class="bg-white rounded-xl border border-light-gray divide-y divide-light-gray">
      <!-- Biometric toggle (native only) -->
      <div
        v-if="showBiometricToggle"
        class="w-full px-4 flex items-center gap-3"
        style="min-height: 48px;"
      >
        <svg class="w-5 h-5 text-neutral-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
        </svg>
        <span class="flex-1 text-sm text-horizon-500">{{ biometricName }}</span>
        <button
          :disabled="biometricLoading"
          class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
          :class="biometricEnabled ? 'bg-spring-500' : 'bg-neutral-300'"
          @click="toggleBiometric"
        >
          <span
            class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
            :class="biometricEnabled ? 'translate-x-5' : 'translate-x-0'"
          ></span>
        </button>
      </div>

      <!-- Standard settings items -->
      <button
        v-for="item in settingsItems"
        :key="item.id"
        class="w-full px-4 flex items-center gap-3 active:bg-savannah-100 transition-colors text-left"
        style="min-height: 48px;"
        @click="handleSettingTap(item)"
      >
        <svg class="w-5 h-5 text-neutral-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" :d="item.iconPath" />
        </svg>
        <span class="flex-1 text-sm text-horizon-500">{{ item.label }}</span>
        <svg class="w-4 h-4 text-neutral-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
        </svg>
      </button>
    </div>
  </div>
</template>

<script>
import { platform } from '@/utils/platform';
import { setItem, removeItem } from '@/services/tokenStorage';

export default {
  name: 'SettingsList',

  data() {
    return {
      showBiometricToggle: false,
      biometricEnabled: false,
      biometricLoading: false,
      biometricName: 'Face ID',
      settingsItems: [
        {
          id: 'account',
          label: 'Account',
          iconPath: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
          route: null,
          webPath: '/settings',
        },
        {
          id: 'security',
          label: 'Security',
          iconPath: 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z',
          route: null,
          webPath: '/settings/security',
        },
        {
          id: 'notifications',
          label: 'Notifications',
          iconPath: 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
          route: '/m/more/notifications',
          webPath: null,
        },
        {
          id: 'subscription',
          label: 'Subscription',
          iconPath: 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
          route: null,
          webPath: '/settings/subscription',
        },
        {
          id: 'privacy',
          label: 'Privacy',
          iconPath: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
          route: null,
          webPath: '/settings/privacy',
        },
        {
          id: 'help',
          label: 'Help',
          iconPath: 'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
          route: null,
          webPath: '/help',
        },
        {
          id: 'about',
          label: 'About',
          iconPath: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
          route: null,
          webPath: '/about',
        },
      ],
    };
  },

  async mounted() {
    await this.checkBiometricAvailability();
  },

  methods: {
    async checkBiometricAvailability() {
      if (!platform.canUseBiometrics()) return;

      try {
        const { NativeBiometric } = await import('@capgo/capacitor-native-biometric');
        const { isAvailable, biometryType } = await NativeBiometric.isAvailable();
        if (!isAvailable) return;

        this.biometricName = biometryType === 2 ? 'Face ID' : 'Touch ID';
        this.showBiometricToggle = true;

        // Check if credentials are currently stored
        try {
          const credentials = await NativeBiometric.getCredentials({ server: 'fynla.org' });
          this.biometricEnabled = !!(credentials?.password);
        } catch {
          this.biometricEnabled = false;
        }
      } catch {
        // Biometric not available on this device
      }
    },

    async toggleBiometric() {
      this.biometricLoading = true;
      try {
        const { NativeBiometric } = await import('@capgo/capacitor-native-biometric');

        if (this.biometricEnabled) {
          // Disabling — delete stored credentials and revoke the token
          await NativeBiometric.deleteCredentials({ server: 'fynla.org' });
          await removeItem('biometric_enabled');
          this.biometricEnabled = false;
        } else {
          // Enabling — verify biometric first, then store current credentials
          await NativeBiometric.verifyIdentity({
            reason: `Enable ${this.biometricName} for quick sign-in`,
            title: 'Fynla',
          });

          const token = this.$store.state.auth.token;
          const email = this.$store.state.auth.user?.email || '';

          if (token) {
            await NativeBiometric.setCredentials({
              username: email,
              password: token,
              server: 'fynla.org',
            });
            await setItem('biometric_enabled', 'true');
            this.biometricEnabled = true;
          }
        }
      } catch {
        // User cancelled biometric or it failed — don't change state
      } finally {
        this.biometricLoading = false;
      }
    },

    async handleSettingTap(item) {
      // In-app route
      if (item.route) {
        this.$router.push(item.route);
        return;
      }

      // External web path — open in browser on the production site
      if (item.webPath) {
        const baseUrl = import.meta.env.VITE_API_BASE_URL || 'https://fynla.org';
        const url = baseUrl + item.webPath;
        try {
          const { Browser } = await import('@capacitor/browser');
          await Browser.open({ url });
        } catch {
          window.open(url, '_blank', 'noopener,noreferrer');
        }
      }
    },
  },
};
</script>
