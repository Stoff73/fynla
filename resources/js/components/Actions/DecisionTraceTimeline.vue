<template>
    <div>
        <div v-if="!steps || steps.length === 0">
            <div class="text-center py-8">
                <p class="text-body-sm text-neutral-500">No decision trace available for this action.</p>
            </div>
        </div>

        <div v-else>
            <!-- Timeline with left border -->
            <div class="relative border-l-2 border-light-gray ml-4">
                <div v-for="(step, index) in steps" :key="index" class="relative pl-8 pb-6 min-w-0">
                    <!-- Circle indicator -->
                    <div :class="['absolute -left-[9px] top-1 w-4 h-4 rounded-full border-2 border-white', step.passed ? 'bg-spring-500' : 'bg-raspberry-500']"></div>

                    <!-- Content -->
                    <p class="text-body-sm font-semibold text-horizon-500 break-words">{{ step.question }}</p>
                    <div class="mt-1 space-y-0.5 min-w-0">
                        <p class="text-caption text-neutral-500 break-all">
                            {{ step.data_field }}: <span class="font-mono text-horizon-500">{{ step.data_value }}</span>
                        </p>
                        <p v-if="step.threshold" class="text-caption text-neutral-500 break-words">
                            Target: <span class="font-mono text-horizon-500">{{ step.threshold }}</span>
                        </p>
                        <p v-if="step.explanation" class="text-caption text-neutral-500 italic mt-1">
                            {{ step.explanation }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Outcome bar -->
            <div v-if="outcome" class="ml-8 mt-2 bg-violet-50 border border-violet-200 rounded-lg p-4">
                <p class="text-body-sm font-semibold text-violet-700">{{ outcome.title }}</p>
                <p v-if="outcome.description" class="text-caption text-neutral-500 mt-1">{{ outcome.description }}</p>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'DecisionTraceTimeline',

    props: {
        steps: {
            type: Array,
            required: true,
        },
        outcome: {
            type: Object,
            required: true,
        },
    },
};
</script>
