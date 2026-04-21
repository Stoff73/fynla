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
    computed: {
        ...mapGetters('aiChat', ['onboardingLayout']),
        isStandardLayout() {
            return this.onboardingLayout === 'standard';
        },
        chatContainerClasses() {
            return this.isStandardLayout
                ? 'w-[525px] max-w-full'
                : 'w-full max-w-4xl';
        },
    },
};
</script>

<template>
    <div class="flex flex-col items-center gap-6 mx-auto transition-all duration-300" :class="chatContainerClasses">
        <!--
            Standard (pause) layout: chat on the left, profile review
            panel on the right when the viewport allows. On narrow
            viewports the panel stacks below the chat.
        -->
        <template v-if="isStandardLayout">
            <div class="flex flex-col lg:flex-row gap-6 w-full">
                <div class="flex-1 min-w-0">
                    <AiChatPanel />
                </div>
                <aside class="w-full lg:w-[300px] flex-shrink-0">
                    <ProfileReviewPanel />
                </aside>
            </div>
        </template>

        <!-- Wide layout: just the chat, dashboard blurred behind. -->
        <template v-else>
            <AiChatPanel />
        </template>
    </div>
</template>
