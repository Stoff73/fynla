<template>
  <div
    :class="[
      'relative rounded-xl p-4 cursor-pointer border min-w-[180px] max-w-[220px] transition-all duration-150',
      'hover:shadow-md hover:-translate-y-px',
      disabled ? 'opacity-45' : '',
      selected ? 'ring-2 ring-violet-500' : '',
      nodeTypeClasses,
    ]"
    @click="$emit('click')"
  >
    <!-- Priority badge -->
    <span
      v-if="priority && !disabled"
      :class="[
        'absolute -top-2 -right-2 text-[10px] font-bold px-2 py-0.5 rounded-full text-white',
        priorityBadgeClass,
      ]"
    >
      {{ priorityLabel }}
    </span>
    <span
      v-if="disabled"
      class="absolute -top-2 -right-2 text-[10px] font-bold px-2 py-0.5 rounded-full text-white bg-neutral-500"
    >
      OFF
    </span>

    <!-- Label -->
    <div class="text-sm font-bold text-horizon-500 mb-1 truncate" :title="label">
      {{ label }}
    </div>

    <!-- Description -->
    <div class="text-xs text-neutral-500 leading-relaxed line-clamp-2" :title="description">
      {{ description }}
    </div>
  </div>
</template>

<script>
export default {
  name: 'DecisionNode',

  props: {
    type: {
      type: String,
      required: true,
      validator: (v) => ['data', 'trigger', 'logic', 'outcome'].includes(v),
    },
    label: {
      type: String,
      default: '',
    },
    description: {
      type: String,
      default: '',
    },
    priority: {
      type: String,
      default: null,
    },
    disabled: {
      type: Boolean,
      default: false,
    },
    selected: {
      type: Boolean,
      default: false,
    },
  },

  emits: ['click'],

  computed: {
    nodeTypeClasses() {
      if (this.disabled) {
        return 'bg-white border-light-gray';
      }
      const classes = {
        data: 'bg-light-blue-100 border-light-blue-500',
        trigger: 'bg-violet-50 border-violet-200',
        logic: 'bg-spring-50 border-spring-200',
        outcome: 'bg-raspberry-50 border-raspberry-200',
      };
      return classes[this.type] || 'bg-white border-light-gray';
    },

    priorityBadgeClass() {
      const classes = {
        critical: 'bg-raspberry-700',
        high: 'bg-raspberry-500',
        medium: 'bg-violet-500',
        low: 'bg-spring-500',
      };
      return classes[this.priority] || 'bg-neutral-500';
    },

    priorityLabel() {
      const labels = {
        critical: 'CRIT',
        high: 'HIGH',
        medium: 'MED',
        low: 'LOW',
      };
      return labels[this.priority] || '';
    },
  },
};
</script>
