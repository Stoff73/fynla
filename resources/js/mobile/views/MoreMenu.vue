<template>
  <div class="px-4 pt-4 pb-6 space-y-4">
    <!-- Profile card -->
    <ProfileCard />

    <!-- Settings list -->
    <SettingsList />

    <!-- Logout -->
    <button
      class="w-full py-3 text-sm font-medium text-raspberry-500"
      @click="handleLogout"
    >
      Sign Out
    </button>

    <!-- Version -->
    <p class="text-center text-xs text-neutral-400">
      Fynla v0.9.4
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

  methods: {
    async handleLogout() {
      // Mobile logout clears local state but keeps the server token valid
      // so biometric (Face ID) credentials in the iOS Keychain still work
      await this.$store.dispatch('auth/mobileLogout');
      this.$router.push('/m/login');
    },
  },
};
</script>
