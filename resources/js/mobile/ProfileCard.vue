<template>
  <div class="bg-white rounded-xl border border-light-gray p-4 flex items-center gap-4">
    <!-- Initials avatar -->
    <div
      class="w-12 h-12 rounded-full bg-horizon-500 flex items-center justify-center flex-shrink-0"
    >
      <span class="text-white font-bold text-base">{{ initials }}</span>
    </div>

    <!-- Info -->
    <div class="flex-1 min-w-0">
      <h3 class="text-base font-bold text-horizon-500 truncate">{{ displayName }}</h3>
      <p v-if="subscriptionTier" class="text-xs text-neutral-500 mt-0.5">{{ subscriptionTier }}</p>
      <p class="text-xs text-neutral-400 truncate mt-0.5">{{ email }}</p>
    </div>
  </div>
</template>

<script>
import { mapGetters } from 'vuex';

export default {
  name: 'ProfileCard',

  computed: {
    ...mapGetters('auth', ['currentUser']),

    displayName() {
      if (!this.currentUser) return '';
      return [this.currentUser.first_name, this.currentUser.last_name]
        .filter(Boolean)
        .join(' ') || 'User';
    },

    initials() {
      if (!this.currentUser) return '?';
      const first = (this.currentUser.first_name || '')[0] || '';
      const last = (this.currentUser.last_name || '')[0] || '';
      return (first + last).toUpperCase() || '?';
    },

    email() {
      return this.currentUser?.email || '';
    },

    subscriptionTier() {
      const tier = this.currentUser?.subscription_tier || this.currentUser?.subscription_plan;
      if (!tier) return '';
      return tier.charAt(0).toUpperCase() + tier.slice(1) + ' plan';
    },
  },
};
</script>
