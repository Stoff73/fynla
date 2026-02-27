<template>
  <!-- External link -->
  <a
    v-if="external && href"
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
    <svg v-if="!collapsed" class="w-3 h-3 ml-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
    </svg>
  </a>

  <!-- Action button (e.g. Bug Report) -->
  <button
    v-else-if="!to && !href"
    class="group flex items-center w-full mx-2 rounded-md transition-colors"
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
        ? 'bg-primary-50 text-primary-700'
        : 'text-gray-500 hover:bg-gray-50 hover:text-gray-700',
      collapsed ? 'justify-center px-2 py-2.5' : 'px-3 py-2'
    ]"
    :title="collapsed ? label : ''"
    @click="$emit('navigate')"
  >
    <SideMenuIcon :name="icon" class="w-5 h-5 flex-shrink-0" :class="active ? 'text-primary-600' : ''" />
    <span v-if="!collapsed" class="ml-3 text-sm font-medium whitespace-nowrap">{{ label }}</span>
  </router-link>
</template>

<script>
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
      type: String,
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
  },

  emits: ['navigate', 'action'],

  computed: {
    itemClasses() {
      return 'text-gray-500 hover:bg-gray-50 hover:text-gray-700';
    },
  },
};
</script>
