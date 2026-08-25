<template>
  <AppLayout>
    <div class="module-gradient py-8">
      <div class="mb-8">
        <h1 class="text-h2 font-display text-horizon-500">Settings</h1>
        <p class="mt-2 text-body-base text-neutral-500">
          Manage household members for shared protection, estate, and goal planning
        </p>
      </div>

      <SettingsTabBar />

      <!--
        Placed BEFORE the family-members card deliberately, and it must stay
        there. FamilyMemberFormModal is `fixed z-10` but lives INSIDE that card,
        which establishes its own stacking context — so the modal's z-10 is
        trapped at the card's level rather than floating above the page. Any
        sibling rendered AFTER the card therefore paints on top of the open
        modal and swallows clicks on its Add Family Member button: the form
        could be filled and never submitted. Raising z-index on this panel does
        not help (tried; the panel still wins), because the competition is
        between stacking contexts, not between the elements. Ordering is the
        fix. Caught in the browser, covered by FamilySettings.spec.js.

        Actionable first is also the right reading: an unanswered request to
        link belongs above the list of people already in the household.

        Why it is here at all: SpouseDataSharing was written complete — all five
        states, accept/decline included — and then never mounted anywhere, while
        the notification email linked to a route that did not exist. Nobody
        could grant consent, so the backend forged it (W-0347). Mounting this is
        half that fix.
      -->
      <div class="mb-6">
        <SpouseDataSharing />
      </div>

      <div class="card">
        <div v-if="loading" class="flex justify-center items-center py-12">
          <div class="text-center">
            <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin mx-auto"></div>
            <p class="mt-4 text-body-base text-neutral-500">Loading profile...</p>
          </div>
        </div>
        <div v-else-if="error" class="rounded-md bg-raspberry-50 p-4">
          <h3 class="text-body-sm font-medium text-raspberry-800">Error loading profile</h3>
          <p class="mt-2 text-body-sm text-raspberry-700">{{ error }}</p>
          <button @click="loadProfile" class="btn-secondary mt-4">Try Again</button>
        </div>
        <FamilyMembers v-else />
      </div>

    </div>
  </AppLayout>
</template>

<script>
import { computed, onMounted } from 'vue';
import { useStore } from 'vuex';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsTabBar from '@/components/Settings/SettingsTabBar.vue';
import FamilyMembers from '@/components/UserProfile/FamilyMembers.vue';
import SpouseDataSharing from '@/components/UserProfile/SpouseDataSharing.vue';

export default {
  name: 'FamilySettings',
  components: { AppLayout, SettingsTabBar, FamilyMembers, SpouseDataSharing },
  setup() {
    const store = useStore();
    const loading = computed(() => store.getters['userProfile/loading']);
    const error = computed(() => store.getters['userProfile/error']);
    const loadProfile = () => store.dispatch('userProfile/fetchProfile');

    // SpouseDataSharing fetches its own status on mount — no dispatch here.
    onMounted(loadProfile);
    return { loading, error, loadProfile };
  },
};
</script>
