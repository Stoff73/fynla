/**
 * Analytics Service
 *
 * Thin wrapper around Plausible Cloud custom events.
 * All methods are safe to call even when Plausible is not loaded
 * (e.g. analytics disabled, ad-blocker active, local development).
 */

// Module-level map from route path prefixes to module names
const MODULE_PATH_MAP = {
    '/protection': 'protection',
    '/savings': 'savings',
    '/goals': 'goals',
    '/net-worth/investments': 'investment',
    '/net-worth/retirement': 'retirement',
    '/net-worth': 'net_worth',
    '/pension': 'retirement',
    '/estate': 'estate',
    '/trusts': 'estate',
    '/plans/investment': 'investment',
    '/plans/protection': 'protection',
    '/plans/retirement': 'retirement',
    '/plans/estate': 'estate',
    '/holistic-plan': 'holistic_plan',
    '/dashboard': 'dashboard',
};

let deviceInfoCaptured = false;

/**
 * Safely send a custom event to Plausible.
 * No-ops silently when Plausible is unavailable.
 */
function track(eventName, props = {}) {
    try {
        if (typeof window !== 'undefined' && typeof window.plausible === 'function') {
            window.plausible(eventName, { props });
        }
    } catch {
        // Silently ignore — analytics must never break the app
    }
}

/**
 * Determine device type from viewport width.
 * Breakpoints align with common Tailwind/responsive thresholds.
 */
function getDeviceType() {
    if (typeof window === 'undefined') return 'unknown';
    const width = window.innerWidth;
    if (width < 768) return 'mobile';
    if (width < 1024) return 'tablet';
    return 'desktop';
}

/**
 * Detect the module name from a route path.
 * Returns null if the path doesn't match a known module.
 */
function detectModule(path) {
    // Strip /preview prefix so preview routes match too
    const normalizedPath = path.replace(/^\/preview/, '');

    for (const [prefix, moduleName] of Object.entries(MODULE_PATH_MAP)) {
        if (normalizedPath.startsWith(prefix)) {
            return moduleName;
        }
    }
    return null;
}

const analyticsService = {
    /**
     * Capture device info (sent once per session with first pageview).
     * Sends: device_type, viewport_width, viewport_height
     */
    captureDeviceInfo() {
        if (deviceInfoCaptured) return;
        if (typeof window === 'undefined') return;

        track('device_info', {
            device_type: getDeviceType(),
            viewport_width: window.innerWidth,
            viewport_height: window.innerHeight,
        });

        deviceInfoCaptured = true;
    },

    /**
     * Track a page view on route change.
     * Also captures device info on the first call.
     *
     * @param {string} routeName  - Vue route name (e.g. 'Dashboard')
     * @param {string} routePath  - Vue route path (e.g. '/dashboard')
     */
    trackPageView(routeName, routePath) {
        // Capture device info on first pageview of the session
        this.captureDeviceInfo();

        track('pageview', {
            route_name: routeName || 'unknown',
            route_path: routePath || 'unknown',
            device_type: getDeviceType(),
        });

        // If this route corresponds to a module, track the module visit too
        const moduleName = detectModule(routePath || '');
        if (moduleName) {
            this.trackModuleVisit(moduleName);
        }
    },

    /**
     * Track a generic custom event.
     *
     * @param {string} eventName - Event identifier (e.g. 'cta_clicked')
     * @param {Object} props     - Additional properties
     */
    trackEvent(eventName, props = {}) {
        track(eventName, {
            ...props,
            device_type: getDeviceType(),
        });
    },

    /**
     * Track when the AI chat panel is opened.
     */
    trackChatOpened() {
        track('chat_opened', {
            device_type: getDeviceType(),
            page: typeof window !== 'undefined' ? window.location.pathname : 'unknown',
        });
    },

    /**
     * Track when the user sends a chat message.
     *
     * @param {number} messageLength - Character count of the message
     */
    trackChatMessageSent(messageLength = 0) {
        track('chat_message_sent', {
            message_length: messageLength,
            device_type: getDeviceType(),
        });
    },

    /**
     * Track a module visit.
     *
     * @param {string} moduleName - Module identifier (e.g. 'protection', 'savings')
     */
    trackModuleVisit(moduleName) {
        track('module_visit', {
            module: moduleName,
            device_type: getDeviceType(),
        });
    },
};

export default analyticsService;
