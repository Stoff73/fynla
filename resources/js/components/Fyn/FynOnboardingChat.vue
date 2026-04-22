<script>
import { mapGetters } from 'vuex';
import AiChatPanel from '@/components/Shared/AiChatPanel.vue';

/**
 * Onboarding-specific chat wrapper. When the parent container owns the
 * width (docked: true in AppLayout) this component just fills available
 * space — AppLayout's asideWidthClass swings between 712px (wide) and
 * 356px (standard / profile-review pause). When rendered undocked it
 * mirrors those widths itself so free-form mounts stay aligned.
 *
 * The profile-review pause surface is the main `<slot/>` route — on
 * entry AppLayout.vue pushes the router to `/profile` so the real
 * `UserProfile.vue` page renders behind the shrunken chat. No in-chat
 * summary panel is rendered; the dashboard canvas is the review canvas.
 *
 * Mount target: AppLayout's docked aside uses this component for
 * onboarding routes; the post-onboarding chat uses AiChatPanel directly.
 */
export default {
    name: 'FynOnboardingChat',
    components: { AiChatPanel },
    props: {
        /**
         * When true, the parent container fixes the width (e.g. the 356px
         * docked <aside> in AppLayout). When false the component sizes
         * itself (712 wide / 356 standard).
         */
        docked: {
            type: Boolean,
            default: false,
        },
    },
    computed: {
        ...mapGetters('aiChat', ['onboardingLayout']),
        isStandardLayout() {
            return this.onboardingLayout === 'standard';
        },
        chatContainerClasses() {
            if (this.docked) return 'w-full h-full';
            return this.isStandardLayout
                ? 'w-[356px] max-w-full'
                : 'w-[712px] max-w-full';
        },
    },
};
</script>

<template>
    <div
        class="flex flex-col gap-4 transition-all duration-300"
        :class="[chatContainerClasses, docked ? 'overflow-hidden' : 'items-center mx-auto']"
    >
        <div :class="docked ? 'flex-1 min-h-0' : 'w-full'">
            <AiChatPanel v-bind="$attrs" v-on="$listeners" :docked="docked" />
        </div>
    </div>
</template>
