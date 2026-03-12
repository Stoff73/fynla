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
      this.$router.push(`/m/module/${moduleId}`);
    },

    async handleLogout() {
      // Mobile logout clears local state but keeps the server token valid
      // so biometric (Face ID) credentials in the iOS Keychain still work
      await this.$store.dispatch('auth/mobileLogout');
      this.$router.push('/m/login');
    },
  },
};
</script>
