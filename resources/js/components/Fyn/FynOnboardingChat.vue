<script>
import { mapGetters } from 'vuex';
import AiChatPanel from '@/components/Shared/AiChatPanel.vue';
import ProfileReviewPanel from '@/components/Onboarding/ProfileReviewPanel.vue';

/**
 * Onboarding-specific chat wrapper. Renders the existing AiChatPanel
 * at wide (max-w-4xl) width by default, shrinks to w-[525px] at profile-
 * review pause states (layout=standard), and renders ProfileReviewPanel
 * alongside during those pauses.
 *
 * The blur on the dashboard behind the chat is handled in AppLayout.vue
 * by reading the same onboardingLayout getter — this component only
 * owns its own sizing.
 *
 * Mount target: the Onboarding view imports this instead of dropping in
 * AiChatPanel directly so the wide/standard layout switch is scoped to
 * the onboarding surface. Post-onboarding chat continues to use the
 * panel directly.
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
                ? 'w-[525px] max-w-full'
                : 'w-full max-w-4xl';
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
