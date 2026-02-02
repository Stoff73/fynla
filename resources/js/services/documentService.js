import api from './api';

/**
 * Document Upload & AI Extraction Service
 */
const documentService = {
  /**
   * Get all documents for the user
   */
  async getDocuments(page = 1) {
    const response = await api.get('/documents', { params: { page } });
    return response.data;
  },

  /**
   * Get available document types
   */
  async getTypes() {
    const response = await api.get('/documents/types');
    return response.data;
  },

  /**
   * Upload and process a document
   * @param {File} file - The file to upload
   * @param {string|null} documentType - Optional expected document type
   * @param {Function} onProgress - Progress callback
   */
  async upload(file, documentType = null, onProgress = null) {
    const formData = new FormData();
    formData.append('document', file);
    if (documentType) {
      formData.append('document_type', documentType);
    }

    const config = {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    };

    if (onProgress) {
      config.onUploadProgress = (progressEvent) => {
        const percentCompleted = Math.round(
          (progressEvent.loaded * 100) / progressEvent.total
        );
        onProgress(percentCompleted);
      };
    }

    const response = await api.post('/documents/upload', formData, config);
    return response.data;
  },

  /**
   * Upload a document without processing
   * @param {File} file - The file to upload
   * @param {string|null} documentType - Optional expected document type
   */
  async uploadOnly(file, documentType = null) {
    const formData = new FormData();
    formData.append('document', file);
    if (documentType) {
      formData.append('document_type', documentType);
    }

    const response = await api.post('/documents/upload-only', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
    return response.data;
  },

  /**
   * Get document details
   * @param {number} id - Document ID
   */
  async getDocument(id) {
    const response = await api.get(`/documents/${id}`);
    return response.data;
  },

  /**
   * Get extraction results for a document
   * @param {number} id - Document ID
   */
  async getExtraction(id) {
    const response = await api.get(`/documents/${id}/extraction`);
    return response.data;
  },

  /**
   * Confirm extraction and save to database
   * @param {number} id - Document ID
   * @param {Object} data - Confirmed data
   */
  async confirm(id, data) {
    const response = await api.post(`/documents/${id}/confirm`, { data });
    return response.data;
  },

  /**
   * Re-process a document
   * @param {number} id - Document ID
   */
  async reprocess(id) {
    const response = await api.post(`/documents/${id}/reprocess`);
    return response.data;
  },

  /**
   * Delete a document
   * @param {number} id - Document ID
   */
  async deleteDocument(id) {
    const response = await api.delete(`/documents/${id}`);
    return response.data;
  },
};

export default documentService;
