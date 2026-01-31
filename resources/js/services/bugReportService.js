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
 * @returns {Promise<Object>} API response
 */
export async function submitBugReport(data) {
    const payload = {
        description: data.description,
        expected_behaviour: data.expectedBehaviour || null,
        console_logs: consoleCapture.getLogs(),
        page_url: window.location.href,
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
