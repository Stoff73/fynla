<template>
  <!-- Locked item (feature-gated) -->
  <div
    v-if="locked"
    class="flex items-center mx-2 rounded-md text-neutral-300 cursor-not-allowed"
    :class="collapsed ? 'justify-center px-2 py-2.5' : 'px-3 py-2'"
    @mouseenter="showLockedTooltip"
    @mouseleave="hideLockedTooltip"
  >
    <SideMenuIcon :name="icon" class="w-5 h-5 flex-shrink-0" />
    <span v-if="!collapsed" class="ml-3 text-sm font-medium whitespace-nowrap">{{ label }}</span>

    <!-- Tooltip (teleported to body, fixed position) -->
    <Teleport to="body">
      <div
        v-if="tooltipVisible"
        class="fixed z-[9999] pointer-events-auto"
        :style="{ top: tooltipTop + 'px', left: tooltipLeft + 'px' }"
        @mouseenter="tooltipHovered = true"
        @mouseleave="hideLockedTooltip"
      >
        <div class="bg-horizon-600 text-white text-xs rounded-lg px-3 py-2 whitespace-nowrap shadow-lg">
          <div>Available on <strong>{{ requiredPlan }}</strong> plan</div>
          <router-link
            to="/settings?tab=subscription"
            class="text-raspberry-300 hover:text-raspberry-200 underline text-[11px]"
            @click="hideLockedTooltip"
          >Upgrade now &rarr;</router-link>
        </div>
      </div>
    </Teleport>
  </div>

  <!-- External link -->
  <a
    v-else-if="external && href"
    :href="href"
    target="_blank"
    rel="noopener noreferrer"
    class="group flex items-center mx-2 rounded-md transition-colors"
    :class="[itemClasses, collapsed ? 'justify-center px-2 py-2.5' : 'px-3 py-2']"
    :title="collapsed ? label : ''"
    @click="$emit('navigate')"
  >
    <SideMenuIcon :name="icon" class="w-5 h-5 flex-shrink-0" />
    <span v-if="!collapsed" class="ml-3 text-sm font-medium whitespace-nowrap">{{ label }}</span>
    <svg v-if="!collapsed" class="w-3 h-3 ml-auto text-horizon-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
    </svg>
  </a>

  <!-- Action button (e.g. Bug Report) -->
  <button
    v-else-if="!to && !href"
    class="group flex items-center self-stretch mx-2 rounded-md transition-colors"
    :class="[itemClasses, collapsed ? 'justify-center px-2 py-2.5' : 'px-3 py-2']"
    :title="collapsed ? label : ''"
    @click="$emit('action')"
  >
    <SideMenuIcon :name="icon" class="w-5 h-5 flex-shrink-0" />
    <span v-if="!collapsed" class="ml-3 text-sm font-medium whitespace-nowrap">{{ label }}</span>
  </button>

  <!-- Router link -->
  <router-link
    v-else
    :to="to"
    class="group flex items-center mx-2 rounded-md transition-colors"
    :class="[
      active
        ? activeBgClass
        : (muted ? 'text-neutral-500 opacity-70 hover:opacity-100 hover:bg-savannah-100 hover:text-horizon-500' : 'text-neutral-500 hover:bg-savannah-100 hover:text-horizon-500'),
      collapsed ? 'justify-center px-2 py-2.5' : 'px-3 py-2'
    ]"
    :title="collapsed ? label : ''"
    @click="$emit('navigate')"
  >
    <SideMenuIcon :name="icon" class="w-5 h-5 flex-shrink-0" :class="active ? activeIconClass : ''" />
    <span v-if="!collapsed" class="ml-3 text-sm font-medium whitespace-nowrap">{{ label }}</span>
  </router-link>
</template>

<script>
import { ref } from 'vue';
import SideMenuIcon from './SideMenuIcon.vue';

export default {
  name: 'SideMenuItem',

  components: {
    SideMenuIcon,
  },

  props: {
    icon: {
      type: String,
      required: true,
    },
    label: {
      type: String,
      required: true,
    },
    to: {
      type: [String, Object],
      default: '',
    },
    href: {
      type: String,
      default: '',
    },
    collapsed: {
      type: Boolean,
      default: false,
    },
    active: {
      type: Boolean,
      default: false,
    },
    external: {
      type: Boolean,
      default: false,
    },
    activeColour: {
      type: String,
      default: '', // e.g. 'violet', 'spring', 'raspberry', 'light-blue', 'horizon'
    },
    muted: {
      type: Boolean,
      default: false,
    },
    locked: {
      type: Boolean,
      default: false,
    },
    requiredPlan: {
      type: String,
      default: '',
    },
  },

  emits: ['navigate', 'action'],

  setup() {
    const tooltipVisible = ref(false);
    const tooltipHovered = ref(false);
    const tooltipTop = ref(0);
    const tooltipLeft = ref(0);

    const showLockedTooltip = (event) => {
      const rect = event.currentTarget.getBoundingClientRect();
      tooltipTop.value = rect.top + rect.height / 2 - 20;
      tooltipLeft.value = rect.right + 8;
      tooltipVisible.value = true;
    };

    const hideLockedTooltip = () => {
      setTimeout(() => {
        if (!tooltipHovered.value) {
          tooltipVisible.value = false;
        }
        tooltipHovered.value = false;
      }, 100);
    };

    return {
      tooltipVisible,
      tooltipHovered,
      tooltipTop,
      tooltipLeft,
      showLockedTooltip,
      hideLockedTooltip,
    };
  },

  computed: {
    itemClasses() {
      return 'text-neutral-500 hover:bg-savannah-100 hover:text-horizon-500';
    },
    activeBgClass() {
      return 'bg-horizon-500 text-white';
    },
    activeIconClass() {
      return 'text-white';
    },
  },
};
</script>
