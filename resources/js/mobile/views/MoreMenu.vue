<template>
  <div class="px-4 pt-4 pb-6 space-y-4">
    <!-- Profile card -->
    <ProfileCard />

    <!-- Module grid -->
    <div>
      <h3 class="text-sm font-bold text-horizon-500 mb-2">Modules</h3>
      <div class="grid grid-cols-2 gap-3">
        <button
          v-for="mod in modules"
          :key="mod.id"
          class="bg-white rounded-xl border border-light-gray p-4 text-left
                 active:bg-savannah-100 transition-colors"
          @click="navigateToModule(mod.id)"
        >
          <span class="text-lg block mb-1">{{ mod.icon }}</span>
          <span class="text-sm font-medium text-horizon-500">{{ mod.label }}</span>
        </button>
      </div>
    </div>

    <!-- Settings list -->
    <SettingsList />

    <!-- Open full web app -->
    <button
      class="w-full py-3 bg-white rounded-xl border border-light-gray text-sm font-medium
             text-horizon-500 flex items-center justify-center gap-2"
      @click="openWebApp"
    >
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
      </svg>
      Open full web app
    </button>

    <!-- Logout -->
    <button
      class="w-full py-3 text-sm font-medium text-raspberry-500"
      @click="handleLogout"
    >
      Log out
    </button>

    <!-- Version -->
    <p class="text-center text-xs text-neutral-400">
      Fynla v0.8.3
    </p>
  </div>
</template>

<script>
import ProfileCard from '@/mobile/ProfileCard.vue';
import SettingsList from '@/mobile/SettingsList.vue';

export default {
  name: 'MoreMenu',

  components: {
    ProfileCard,
    SettingsList,
  },

  data() {
    return {
      modules: [
        { id: 'protection', icon: '\uD83D\uDEE1\uFE0F', label: 'Protection' },
        { id: 'savings', icon: '\uD83D\uDCB0', label: 'Savings' },
        { id: 'investment', icon: '\uD83D\uDCC8', label: 'Investment' },
        { id: 'retirement', icon: '\uD83C\uDFE6', label: 'Retirement' },
        { id: 'estate', icon: '\uD83C\uDFE0', label: 'Estate' },
        { id: 'goals', icon: '\uD83C\uDFAF', label: 'Goals' },
        { id: 'coordination', icon: '\uD83D\uDD17', label: 'Coordination' },
      ],
    };
  },

  methods: {
    navigateToModule(moduleId) {
      this.$router.push(`/m/more/summary/${moduleId}`);
    },

    async openWebApp() {
      const baseUrl = import.meta.env.VITE_API_BASE_URL || 'https://fynla.org';
      const url = baseUrl + '/dashboard';
      try {
        const { Browser } = await import('@capacitor/browser');
        await Browser.open({ url });
      } catch {
        window.open(url, '_blank', 'noopener,noreferrer');
      }
    },

    async handleLogout() {
      await this.$store.dispatch('auth/logout');
      this.$router.push('/m/login');
    },
  },
};
</script>
