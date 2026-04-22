<script>
import { mapGetters } from 'vuex';
import AiChatPanel from '@/components/Shared/AiChatPanel.vue';
import ProfileReviewPanel from '@/components/Onboarding/ProfileReviewPanel.vue';

/**
 * Onboarding-specific chat wrapper. When the parent container owns the
 * width (docked: true in AppLayout) this component just fills available
 * space — AppLayout's asideWidthClass swings between 712px (wide) and
 * 356px (standard / profile-review pause). When rendered undocked it
 * mirrors those widths itself so free-form mounts stay aligned.
 *
 * Renders ProfileReviewPanel alongside during profile-review pauses
 * (layout === 'standard'). Dashboard blur and the /profile route push
 * that accompany the pause are handled in AppLayout.vue by watching the
 * same `onboardingLayout` getter — this component only owns its sizing.
 *
 * Mount target: AppLayout's docked aside uses this component for
 * onboarding routes; the post-onboarding chat uses AiChatPanel directly.
 */
export default {
    name: 'FynOnboardingChat',
    components: { AiChatPanel, ProfileReviewPanel },
    props: {
        /**
         * When true, the parent container fixes the width (e.g. the 356px
         * docked <aside> in AppLayout). Wide/standard sizing collapses to
         * the available width; we stack ProfileReviewPanel below the chat
         * instead of beside it.
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
        <!-- Chat is always rendered; in docked mode it takes full height. -->
        <div :class="docked ? 'flex-1 min-h-0' : 'w-full'">
            <AiChatPanel v-bind="$attrs" v-on="$listeners" :docked="docked" />
        </div>

        <!--
            Standard (pause) layout: render the profile review panel.
            In docked mode it stacks below the chat; in wide free-form
            mode it sits beside the chat on large viewports.
        -->
        <aside
            v-if="isStandardLayout"
            :class="docked
                ? 'border-t border-light-gray bg-savannah-100 max-h-[40%] overflow-y-auto'
                : 'w-full lg:w-[300px] flex-shrink-0'"
        >
            <ProfileReviewPanel />
        </aside>
    </div>
</template>
