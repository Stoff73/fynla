<template>
  <nav class="mobile-tab-bar bg-white border-t border-light-gray flex items-start justify-around"
       style="box-shadow: 0 -1px 3px rgba(0,0,0,0.06);">
    <button
      v-for="tab in tabs"
      :key="tab.id"
      class="tab-button flex flex-col items-center pt-2 pb-1 px-3 min-w-0 flex-1 relative"
      :class="activeTab === tab.id ? 'text-raspberry-500' : 'text-neutral-500'"
      @click="$emit('tab', tab.id)"
    >
      <!-- Icon -->
      <div class="relative">
        <component :is="tab.icon" :active="activeTab === tab.id" class="w-6 h-6" />
        <!-- Badge -->
        <span
          v-if="tab.badge > 0"
          class="absolute -top-1 -right-2 min-w-[16px] h-4 px-1 rounded-full bg-raspberry-500
                 text-white text-[10px] font-bold flex items-center justify-center"
        >
          {{ tab.badge > 9 ? '9+' : tab.badge }}
        </span>
        <span
          v-else-if="tab.dot"
          class="absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full bg-raspberry-500"
        ></span>
      </div>
      <!-- Label -->
      <span class="text-[10px] font-semibold mt-1">{{ tab.label }}</span>
    </button>
  </nav>
</template>

<script>
import TabIconHome from '@/mobile/icons/TabIconHome.vue';
import TabIconFyn from '@/mobile/icons/TabIconFyn.vue';
import TabIconLearn from '@/mobile/icons/TabIconLearn.vue';
import TabIconGoals from '@/mobile/icons/TabIconGoals.vue';
import TabIconMore from '@/mobile/icons/TabIconMore.vue';

export default {
  name: 'MobileTabBar',

  components: {
    TabIconHome,
    TabIconFyn,
    TabIconLearn,
    TabIconGoals,
    TabIconMore,
  },

  props: {
    activeTab: { type: String, default: 'home' },
    alertCount: { type: Number, default: 0 },
    unreadCount: { type: Number, default: 0 },
    milestoneCount: { type: Number, default: 0 },
  },

  emits: ['tab'],

  computed: {
    tabs() {
      return [
        { id: 'home', label: 'Home', icon: 'TabIconHome', dot: this.alertCount > 0, badge: 0 },
        { id: 'fyn', label: 'Fyn', icon: 'TabIconFyn', dot: false, badge: this.unreadCount },
        { id: 'learn', label: 'Learn', icon: 'TabIconLearn', dot: false, badge: 0 },
        { id: 'goals', label: 'Goals', icon: 'TabIconGoals', dot: false, badge: this.milestoneCount },
        { id: 'more', label: 'More', icon: 'TabIconMore', dot: false, badge: 0 },
      ];
    },
  },
};
</script>

<style scoped>
.mobile-tab-bar {
  height: 83px;
  padding-bottom: env(safe-area-inset-bottom);
}

.tab-button {
  -webkit-tap-highlight-color: transparent;
}
</style>
