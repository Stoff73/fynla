/**
 * Preview Mode Mixin
 *
 * Provides centralized preview mode detection and action blocking for Vue components.
 * In preview mode, all add/edit/delete/upload actions should be disabled.
 *
 * Usage:
 *   import { previewModeMixin } from '@/mixins/previewModeMixin';
 *   export default {
 *     mixins: [previewModeMixin],
 *     // Now use this.isPreviewMode, this.handlePreviewAction(), etc.
 *   }
 */

export const previewModeMixin = {
    computed: {
        /**
         * Check if currently in preview mode
         */
        isPreviewMode() {
            return this.$store?.getters['preview/isPreviewMode'] || false;
        },

        /**
         * Get the preview action tooltip text based on action type
         */
        previewTooltip() {
            return 'Register to add or edit information';
        },
    },

    methods: {
        /**
         * Get tooltip text for a specific action type
         * @param {string} action - 'add', 'edit', 'delete', 'upload'
         * @returns {string} Tooltip text
         */
        getPreviewTooltip(action = 'edit') {
            const tooltips = {
                add: 'Register to add information',
                edit: 'Register to edit information',
                delete: 'Register to delete information',
                upload: 'Register to upload documents',
                save: 'Register to save changes',
            };
            return tooltips[action] || 'Register to use this feature';
        },

        /**
         * Handle an action in preview mode - prevents execution and can show feedback
         * @param {Function} action - The action to execute if not in preview mode
         * @param {string} actionType - Type of action for tooltip ('add', 'edit', 'delete', 'upload')
         * @returns {boolean} Whether the action was allowed
         */
        handlePreviewAction(action, actionType = 'edit') {
            if (this.isPreviewMode) {
                // Action blocked in preview mode
                return false;
            }
            // Execute the action
            if (typeof action === 'function') {
                action();
            }
            return true;
        },

        /**
         * Wrapper for click handlers that should be blocked in preview mode
         * Use in templates: @click="previewGuard(() => openModal())"
         * @param {Function} action - The action to execute if not in preview mode
         * @returns {Function} Wrapped function
         */
        previewGuard(action) {
            if (this.isPreviewMode) {
                return; // Block action
            }
            return action();
        },

        /**
         * Get button props for preview mode (disabled state and title)
         * @param {string} actionType - Type of action for tooltip
         * @returns {Object} Props to spread on button element
         */
        getPreviewButtonProps(actionType = 'edit') {
            if (this.isPreviewMode) {
                return {
                    disabled: true,
                    title: this.getPreviewTooltip(actionType),
                    class: 'preview-disabled',
                };
            }
            return {};
        },

        /**
         * Check if a modal/form should be allowed to open
         * @returns {boolean} Whether the modal can open
         */
        canOpenModal() {
            return !this.isPreviewMode;
        },
    },
};

export default previewModeMixin;
