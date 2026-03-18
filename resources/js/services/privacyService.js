import api from './api';

const privacyService = {
  /**
   * Get user consent preferences
   * @returns {Promise}
   */
  async getConsents() {
    const response = await api.get('/auth/gdpr/consents');
    return response.data;
  },

  /**
   * Update a consent preference
   * @param {string} consentType - Type of consent (e.g. 'marketing')
   * @param {boolean} granted - Whether consent is granted
   * @returns {Promise}
   */
  async updateConsent(consentType, granted) {
    const response = await api.put('/auth/gdpr/consents', {
      consent_type: consentType,
      granted,
    });
    return response.data;
  },

  /**
   * Check status of data exports
   * @returns {Promise}
   */
  async getExportStatus() {
    const response = await api.get('/auth/gdpr/export/status');
    return response.data;
  },

  /**
   * Request a data export
   * @param {string} format - Export format ('json' or 'csv')
   * @returns {Promise}
   */
  async requestExport(format) {
    const response = await api.post('/auth/gdpr/export', { format });
    return response.data;
  },

  /**
   * Download a completed data export
   * @param {number} exportId - Export ID
   * @returns {Promise} Blob response
   */
  async downloadExport(exportId) {
    const response = await api.get(`/auth/gdpr/export/${exportId}/download`, {
      responseType: 'blob',
    });
    return response;
  },

  /**
   * Initiate account/data deletion process
   * @param {string} type - Deletion type ('account' or 'data')
   * @returns {Promise}
   */
  async initiateErasure(type) {
    const response = await api.post('/auth/gdpr/erasure/initiate', { type });
    return response.data;
  },

  /**
   * Verify identity for deletion (submit verification code)
   * @param {string} sessionToken - Deletion session token
   * @param {string} code - Verification code
   * @returns {Promise}
   */
  async verifyErasure(sessionToken, code) {
    const response = await api.post('/auth/gdpr/erasure/verify', {
      session_token: sessionToken,
      code,
    });
    return response.data;
  },

  /**
   * Resend verification code for deletion
   * @param {string} sessionToken - Deletion session token
   * @returns {Promise}
   */
  async resendErasureCode(sessionToken) {
    const response = await api.post('/auth/gdpr/erasure/resend-code', {
      session_token: sessionToken,
    });
    return response.data;
  },

  /**
   * Execute the deletion (final step)
   * @param {string} sessionToken - Deletion session token
   * @param {string} confirmation - Confirmation text
   * @returns {Promise}
   */
  async executeErasure(sessionToken, confirmation) {
    const response = await api.post('/auth/gdpr/erasure/execute', {
      session_token: sessionToken,
      confirmation,
    });
    return response.data;
  },
};

export default privacyService;
