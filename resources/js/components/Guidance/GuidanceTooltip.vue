<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition ease-out duration-300"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-if="isVisible && currentStep"
                ref="tooltip"
                class="fixed z-50 bg-white rounded-xl shadow-2xl border border-light-gray max-w-xs"
                :style="tooltipStyle"
            >
                <!-- Arrow -->
                <div
                    class="absolute w-3 h-3 bg-white border-light-gray"
                    :class="arrowClasses"
                    :style="arrowStyle"
                />

                <!-- Content -->
                <div class="p-4">
                    <!-- Progress indicator -->
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-medium text-raspberry-500">
                            Step {{ progress.current }} of {{ progress.total }}
                        </span>
                        <div class="flex gap-1">
                            <div
                                v-for="i in progress.total"
                                :key="i"
                                class="w-1.5 h-1.5 rounded-full"
                                :class="i <= progress.handled ? 'bg-raspberry-500' : 'bg-horizon-200'"
                            />
                        </div>
                    </div>

                    <!-- Step icon and label -->
                    <div class="flex items-start gap-3 mb-3">
                        <div class="w-8 h-8 bg-raspberry-100 rounded-lg flex items-center justify-center flex-shrink-0">
                            <component :is="stepIcon" class="w-4 h-4 text-raspberry-500" />
                        </div>
                        <div>
                            <h4 class="font-medium text-horizon-500">{{ currentStep.label }}</h4>
                            <p class="text-sm text-neutral-500 mt-0.5">{{ currentStep.description }}</p>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-2">
                        <button
                            @click="handleComplete"
                            class="flex-1 bg-raspberry-500 text-white px-3 py-2 rounded-lg text-sm font-medium hover:bg-raspberry-600 transition-colors"
                        >
                            Done
                        </button>
                        <button
                            @click="handleSkip"
                            class="px-3 py-2 text-neutral-500 hover:text-neutral-500 text-sm font-medium transition-colors"
                        >
                            Skip
                        </button>
                        <button
                            @click="handleDismiss"
                            class="p-2 text-horizon-400 hover:text-neutral-500 transition-colors"
                            title="Dismiss guide"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script>
import { mapGetters, mapActions } from 'vuex';

// Icon components as render functions
const icons = {
    user: {
        render() {
            return (
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
            );
        },
    },
    users: {
        render() {
            return (
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
            );
        },
    },
    home: {
        render() {
            return (
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
            );
        },
    },
    'piggy-bank': {
        render() {
            return (
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
            );
        },
    },
    'trending-up': {
        render() {
            return (
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                </svg>
            );
        },
    },
    calendar: {
        render() {
            return (
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            );
        },
    },
    shield: {
        render() {
            return (
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
            );
        },
    },
    'pound-sign': {
        render() {
            return (
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            );
        },
    },
};

export default {
    name: 'GuidanceTooltip',

    data() {
        return {
            tooltipPosition: { top: 0, left: 0 },
            placement: 'bottom', // 'top', 'bottom', 'left', 'right'
            targetElement: null,
            resizeObserver: null,
        };
    },

    computed: {
        ...mapGetters('guidance', ['isActive', 'currentStep', 'progress']),

        isVisible() {
            return this.isActive && this.currentStep;
        },

        stepIcon() {
            const iconName = this.currentStep?.icon || 'user';
            return icons[iconName] || icons.user;
        },

        tooltipStyle() {
            return {
                top: `${this.tooltipPosition.top}px`,
                left: `${this.tooltipPosition.left}px`,
            };
        },

        arrowClasses() {
            const placements = {
                top: 'border-b border-r -bottom-1.5 rotate-45',
                bottom: 'border-t border-l -top-1.5 -rotate-135',
                left: 'border-t border-r -right-1.5 rotate-45',
                right: 'border-b border-l -left-1.5 -rotate-135',
            };
            return placements[this.placement] || placements.bottom;
        },

        arrowStyle() {
            // Center the arrow on the tooltip edge
            if (this.placement === 'top' || this.placement === 'bottom') {
                return { left: '50%', transform: 'translateX(-50%) rotate(45deg)' };
            }
            return { top: '50%', transform: 'translateY(-50%) rotate(45deg)' };
        },
    },

    watch: {
        currentStep: {
            immediate: true,
            handler(step) {
                if (step) {
                    this.$nextTick(() => this.positionTooltip());
                }
            },
        },

        isActive(active) {
            if (active) {
                this.setupResizeObserver();
            } else {
                this.cleanupResizeObserver();
            }
        },
    },

    mounted() {
        if (this.isActive) {
            this.setupResizeObserver();
        }
        window.addEventListener('resize', this.positionTooltip);
        window.addEventListener('scroll', this.positionTooltip, true);
    },

    beforeUnmount() {
        this.cleanupResizeObserver();
        window.removeEventListener('resize', this.positionTooltip);
        window.removeEventListener('scroll', this.positionTooltip, true);
    },

    methods: {
        ...mapActions('guidance', ['completeStep', 'skipStep', 'dismissGuidance']),

        positionTooltip() {
            if (!this.currentStep) return;

            const target = document.querySelector(this.currentStep.target);
            if (!target) {
                // Target not found, position in center of screen
                this.tooltipPosition = {
                    top: window.innerHeight / 2 - 100,
                    left: window.innerWidth / 2 - 160,
                };
                return;
            }

            this.targetElement = target;
            const targetRect = target.getBoundingClientRect();
            const tooltipWidth = 320; // max-w-xs
            const tooltipHeight = 200; // approximate
            const padding = 12;

            // Determine best placement
            const spaceAbove = targetRect.top;
            const spaceBelow = window.innerHeight - targetRect.bottom;
            const spaceLeft = targetRect.left;
            const spaceRight = window.innerWidth - targetRect.right;

            // Prefer bottom, then top, then right, then left
            if (spaceBelow >= tooltipHeight + padding) {
                this.placement = 'bottom';
                this.tooltipPosition = {
                    top: targetRect.bottom + padding,
                    left: Math.max(padding, Math.min(
                        targetRect.left + targetRect.width / 2 - tooltipWidth / 2,
                        window.innerWidth - tooltipWidth - padding
                    )),
                };
            } else if (spaceAbove >= tooltipHeight + padding) {
                this.placement = 'top';
                this.tooltipPosition = {
                    top: targetRect.top - tooltipHeight - padding,
                    left: Math.max(padding, Math.min(
                        targetRect.left + targetRect.width / 2 - tooltipWidth / 2,
                        window.innerWidth - tooltipWidth - padding
                    )),
                };
            } else if (spaceRight >= tooltipWidth + padding) {
                this.placement = 'right';
                this.tooltipPosition = {
                    top: Math.max(padding, Math.min(
                        targetRect.top + targetRect.height / 2 - tooltipHeight / 2,
                        window.innerHeight - tooltipHeight - padding
                    )),
                    left: targetRect.right + padding,
                };
            } else {
                this.placement = 'left';
                this.tooltipPosition = {
                    top: Math.max(padding, Math.min(
                        targetRect.top + targetRect.height / 2 - tooltipHeight / 2,
                        window.innerHeight - tooltipHeight - padding
                    )),
                    left: targetRect.left - tooltipWidth - padding,
                };
            }

            // Highlight target element
            this.highlightTarget(target);
        },

        highlightTarget(target) {
            // Remove previous highlight
            document.querySelectorAll('.guidance-highlight').forEach((el) => {
                el.classList.remove('guidance-highlight');
            });

            // Add highlight to current target
            if (target) {
                target.classList.add('guidance-highlight');
                // Scroll target into view if needed
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        },

        setupResizeObserver() {
            if (typeof ResizeObserver !== 'undefined') {
                this.resizeObserver = new ResizeObserver(() => {
                    this.positionTooltip();
                });

                // Observe body for layout changes
                this.resizeObserver.observe(document.body);
            }
        },

        cleanupResizeObserver() {
            if (this.resizeObserver) {
                this.resizeObserver.disconnect();
                this.resizeObserver = null;
            }

            // Remove highlight
            document.querySelectorAll('.guidance-highlight').forEach((el) => {
                el.classList.remove('guidance-highlight');
            });
        },

        async handleComplete() {
            if (this.currentStep) {
                await this.completeStep(this.currentStep.id);
            }
        },

        async handleSkip() {
            if (this.currentStep) {
                await this.skipStep(this.currentStep.id);
            }
        },

        async handleDismiss() {
            await this.dismissGuidance();
        },
    },
};
</script>

<style>
/* Guidance highlight effect */
.guidance-highlight {
    position: relative;
    z-index: 40;
    box-shadow: 0 0 0 4px rgba(88, 84, 230, 0.3), 0 0 0 8px rgba(88, 84, 230, 0.1);
    border-radius: 8px;
    animation: guidance-pulse 2s ease-in-out infinite;
}

/* Violet-500 RGBA (rgb 88,84,230 = #5854E6) — cannot use Tailwind utility inside @keyframes.
   CSS custom properties with opacity would require browser support for color-mix() which is
   not yet reliably available. Raw RGBA is intentional here for cross-browser compatibility. */
@keyframes guidance-pulse {
    0%, 100% {
        box-shadow: 0 0 0 4px rgba(88, 84, 230, 0.3), 0 0 0 8px rgba(88, 84, 230, 0.1);
    }
    50% {
        box-shadow: 0 0 0 6px rgba(88, 84, 230, 0.4), 0 0 0 12px rgba(88, 84, 230, 0.15);
    }
}
</style>
