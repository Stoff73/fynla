<template>
    <div
        v-if="chips.length > 0"
        class="flex gap-2 overflow-x-auto py-2 px-1 scrollbar-hide"
    >
        <button
            v-for="chip in chips"
            :key="chip"
            class="flex-shrink-0 px-4 py-2 text-sm font-medium bg-white border border-light-gray rounded-full text-horizon-500 hover:bg-savannah-100 hover:border-savannah-300 transition-colors whitespace-nowrap"
            @click="$emit('select', chip)"
        >
            {{ chip }}
        </button>
    </div>
</template>

<script>
export default {
    name: 'QuickReplyChips',

    props: {
        chips: {
            type: Array,
            default: () => [],
            validator: (value) => value.every((item) => typeof item === 'string'),
        },
    },

    emits: ['select'],
};
</script>
