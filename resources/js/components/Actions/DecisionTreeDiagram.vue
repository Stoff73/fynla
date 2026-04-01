<template>
    <div>
        <div v-if="!steps || steps.length === 0">
            <div class="text-center py-8">
                <p class="text-body-sm text-neutral-500">No decision trace available for this action.</p>
            </div>
        </div>

        <div v-else>
            <div v-for="(step, index) in steps" :key="index" class="mx-auto">
                <!-- Node -->
                <div :class="['border-2 rounded-lg p-4 overflow-hidden', step.passed ? 'border-spring-500 bg-spring-50' : 'border-raspberry-500 bg-raspberry-50']">
                    <div class="flex items-start gap-2 min-w-0">
                        <!-- Pass icon -->
                        <svg v-if="step.passed" class="w-5 h-5 text-spring-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <!-- Fail icon -->
                        <svg v-else class="w-5 h-5 text-raspberry-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        <div class="min-w-0">
                            <p class="text-body-sm font-semibold text-horizon-500 break-words">{{ step.question }}</p>
                            <p class="text-caption mt-1 break-all">
                                <span class="text-neutral-500">{{ step.data_field }}: </span>
                                <span class="font-mono text-horizon-500">{{ step.data_value }}</span>
                            </p>
                            <p v-if="step.threshold" class="text-caption text-neutral-500 mt-0.5 break-words">
                                Target: <span class="font-mono">{{ step.threshold }}</span>
                            </p>
                        </div>
                    </div>
                </div>
                <!-- Connector line -->
                <div v-if="index < steps.length - 1 || outcome" class="w-0.5 h-6 bg-light-gray mx-auto"></div>
            </div>

            <!-- Outcome node -->
            <div class="mx-auto" v-if="outcome">
                <div class="border-2 border-violet-500 bg-violet-50 rounded-lg p-4">
                    <p class="text-body-sm font-semibold text-violet-700">{{ outcome.title }}</p>
                    <p class="text-caption text-neutral-500 mt-1">{{ outcome.description }}</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'DecisionTreeDiagram',

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
