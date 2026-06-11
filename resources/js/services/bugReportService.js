/**
 * Bug Report Service
 *
 * Handles submission of bug reports to the API.
 */

import api from './api';
import consoleCapture from './consoleCapture';

/**
 * Submit a bug report
 *
 * @param {Object} data - Bug report data
 * @param {string} data.description - Description of the bug (required)
 * @param {string} [data.expectedBehaviour] - Expected behaviour (optional)
 * @param {string} [data.category] - Module/area the bug relates to (optional)
 * @param {string} [data.severity] - How serious the bug is (optional)
 * @returns {Promise<Object>} API response
 */
export async function submitBugReport(data) {
    const payload = {
        description: data.description,
        expected_behaviour: data.expectedBehaviour || null,
        category: data.category || 'General',
        severity: data.severity || 'Medium',
        console_logs: consoleCapture.getLogs(),
        page_url: window.location.href,
        route: window.location.pathname,
        platform: 'web',
        app_version: import.meta.env.VITE_APP_VERSION || 'web',
        user_agent: navigator.userAgent,
        screen_size: `${window.screen.width}x${window.screen.height}`,
        viewport_size: `${window.innerWidth}x${window.innerHeight}`,
        client_timestamp: new Date().toISOString(),
    };

    const response = await api.post('/bug-report', payload);
    return response.data;
}

export default {
    submitBugReport,
};
