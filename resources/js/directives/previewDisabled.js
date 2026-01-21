/**
 * v-preview-disabled Directive
 *
 * Disables buttons/elements in preview mode and shows a custom tooltip.
 *
 * Usage:
 *   <button v-preview-disabled>Add Policy</button>
 *   <button v-preview-disabled="'add'">Add Account</button>
 *   <button v-preview-disabled="'edit'">Edit</button>
 *   <button v-preview-disabled="'upload'">Upload Document</button>
 *
 * The directive will:
 * - Add 'disabled' attribute in preview mode
 * - Show custom tooltip immediately on hover
 * - Add 'preview-disabled' CSS class
 * - Prevent click events
 */

import store from '../store';

const tooltips = {
    add: 'Register to add data',
    edit: 'Register to edit data',
    delete: 'Register to delete data',
    upload: 'Register to upload documents',
    save: 'Register to save data',
    default: 'Register to use this feature',
};

// Create and inject tooltip styles once
let stylesInjected = false;
function injectStyles() {
    if (stylesInjected) return;

    const style = document.createElement('style');
    style.textContent = `
        .preview-tooltip {
            position: fixed;
            background: #1f2937;
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            z-index: 99999;
            pointer-events: none;
            white-space: nowrap;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            opacity: 0;
            transition: opacity 0.15s ease;
        }
        .preview-tooltip.visible {
            opacity: 1;
        }
        .preview-tooltip::after {
            content: '';
            position: absolute;
            bottom: -6px;
            left: 50%;
            transform: translateX(-50%);
            border-width: 6px 6px 0;
            border-style: solid;
            border-color: #1f2937 transparent transparent transparent;
        }
    `;
    document.head.appendChild(style);
    stylesInjected = true;
}

// Shared tooltip element
let tooltipEl = null;

function getTooltipElement() {
    if (!tooltipEl) {
        injectStyles();
        tooltipEl = document.createElement('div');
        tooltipEl.className = 'preview-tooltip';
        document.body.appendChild(tooltipEl);
    }
    return tooltipEl;
}

function isPreviewMode() {
    return store?.getters['preview/isPreviewMode'] || false;
}

function getTooltip(actionType) {
    return tooltips[actionType] || tooltips.default;
}

function handleClick(e) {
    if (isPreviewMode()) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        return false;
    }
}

function showTooltip(el, actionType) {
    const tooltip = getTooltipElement();
    tooltip.textContent = getTooltip(actionType);

    // Position above the element
    const rect = el.getBoundingClientRect();
    const tooltipRect = tooltip.getBoundingClientRect();

    // Show tooltip first to get its dimensions
    tooltip.style.visibility = 'hidden';
    tooltip.classList.add('visible');

    // Calculate position
    const tooltipWidth = tooltip.offsetWidth;
    let left = rect.left + (rect.width / 2) - (tooltipWidth / 2);
    let top = rect.top - 40;

    // Keep within viewport
    if (left < 10) left = 10;
    if (left + tooltipWidth > window.innerWidth - 10) {
        left = window.innerWidth - tooltipWidth - 10;
    }
    if (top < 10) {
        // Show below if not enough space above
        top = rect.bottom + 10;
        tooltip.style.setProperty('--arrow-position', 'top');
    }

    tooltip.style.left = `${left}px`;
    tooltip.style.top = `${top}px`;
    tooltip.style.visibility = 'visible';
}

function hideTooltip() {
    if (tooltipEl) {
        tooltipEl.classList.remove('visible');
    }
}

function updateElement(el, binding) {
    const inPreviewMode = isPreviewMode();
    const actionType = binding.value || 'default';

    if (inPreviewMode) {
        el.setAttribute('disabled', 'disabled');
        el.classList.add('preview-disabled');
        el.style.cursor = 'not-allowed';
        el.style.opacity = '0.6';

        // Store original click handlers and add our blocker
        if (!el._previewClickHandler) {
            el._previewClickHandler = handleClick;
            el.addEventListener('click', handleClick, true);
        }

        // Add hover handlers for custom tooltip
        if (!el._previewMouseEnter) {
            el._previewMouseEnter = () => showTooltip(el, actionType);
            el._previewMouseLeave = hideTooltip;
            el.addEventListener('mouseenter', el._previewMouseEnter);
            el.addEventListener('mouseleave', el._previewMouseLeave);
        }
    } else {
        el.removeAttribute('disabled');
        el.classList.remove('preview-disabled');
        el.style.cursor = '';
        el.style.opacity = '';

        // Remove our click blocker
        if (el._previewClickHandler) {
            el.removeEventListener('click', el._previewClickHandler, true);
            el._previewClickHandler = null;
        }

        // Remove hover handlers
        if (el._previewMouseEnter) {
            el.removeEventListener('mouseenter', el._previewMouseEnter);
            el.removeEventListener('mouseleave', el._previewMouseLeave);
            el._previewMouseEnter = null;
            el._previewMouseLeave = null;
        }
    }
}

export const previewDisabled = {
    mounted(el, binding) {
        updateElement(el, binding);

        // Watch for store changes (when user logs in/out of preview)
        el._previewUnwatch = store.watch(
            (state, getters) => getters['preview/isPreviewMode'],
            () => updateElement(el, binding)
        );
    },

    updated(el, binding) {
        updateElement(el, binding);
    },

    unmounted(el) {
        // Cleanup
        if (el._previewClickHandler) {
            el.removeEventListener('click', el._previewClickHandler, true);
        }
        if (el._previewMouseEnter) {
            el.removeEventListener('mouseenter', el._previewMouseEnter);
            el.removeEventListener('mouseleave', el._previewMouseLeave);
        }
        if (el._previewUnwatch) {
            el._previewUnwatch();
        }
        hideTooltip();
    },
};

export default previewDisabled;
