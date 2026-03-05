<template>
  <div class="flex flex-wrap items-center gap-4 text-sm">
    <span class="text-neutral-500 font-medium">Events:</span>

    <!-- Goals -->
    <div
      v-for="category in goalCategories"
      :key="category.type"
      class="flex items-center gap-1.5"
    >
      <EventIcon
        :event="{ category: category.type, color: category.color, icon: category.icon }"
        :size="18"
      />
      <span class="text-neutral-500">{{ category.label }}</span>
    </div>

    <!-- Life Events -->
    <div
      v-for="category in lifeEventCategories"
      :key="category.type"
      class="flex items-center gap-1.5"
    >
      <EventIcon
        :event="{ category: category.type, color: category.color, icon: category.icon }"
        :size="18"
      />
      <span class="text-neutral-500">{{ category.label }}</span>
    </div>

    <!-- Summary indicators -->
    <div class="flex items-center gap-3 ml-auto text-xs">
      <!-- Completed indicator -->
      <span class="flex items-center gap-1">
        <span class="w-3 h-3 rounded-full bg-horizon-400 opacity-40"></span>
        <span class="text-neutral-500">Completed</span>
      </span>
      <span class="flex items-center gap-1">
        <span class="w-2 h-2 rounded-full bg-spring-500"></span>
        <span class="text-neutral-500">Income</span>
      </span>
      <span class="flex items-center gap-1">
        <span class="w-2 h-2 rounded-full bg-raspberry-500"></span>
        <span class="text-neutral-500">Expense</span>
      </span>
    </div>
  </div>
</template>

<script>
import { EVENT_ICONS } from '@/constants/eventIcons';
import EventIcon from './EventIcon.vue';

export default {
  name: 'EventIconLegend',

  components: {
    EventIcon,
  },

  props: {
    events: {
      type: Array,
      required: true,
    },
  },

  computed: {
    goalCategories() {
      return this.categorizeEvents('goal');
    },

    lifeEventCategories() {
      return this.categorizeEvents('life_event');
    },
  },

  methods: {
    categorizeEvents(type) {
      const filtered = this.events.filter(e => e.type === type);
      const grouped = {};

      filtered.forEach(event => {
        const category = event.category;
        if (!grouped[category]) {
          const iconConfig = EVENT_ICONS[category] || {};
          grouped[category] = {
            type: category,
            label: iconConfig.label || this.formatLabel(category),
            color: event.color || iconConfig.color || '#64748B',
            icon: iconConfig.icon || 'FlagIcon',
            count: 0,
          };
        }
        grouped[category].count++;
      });

      return Object.values(grouped).slice(0, 4); // Limit to 4 categories
    },

    formatLabel(type) {
      return type
        .split('_')
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
    },
  },
};
</script>
