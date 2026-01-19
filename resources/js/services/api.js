import axios from 'axios';
import store from '@/store';

// Create axios instance with default config
// Use environment-specific base URL (production or local development)
// For local development, use the same hostname as the current page to avoid CORS issues
const isLocal = typeof window !== 'undefined' && (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1');
const localHost = typeof window !== 'undefined' ? window.location.hostname : 'localhost';
const apiBaseURL = isLocal ? `http://${localHost}:8000` : (import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000');
const api = axios.create({
  baseURL: `${apiBaseURL}/api`,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  withCredentials: true,
});

// Request interceptor to add auth token
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('auth_token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Response interceptor to handle errors and preview mode
api.interceptors.response.use(
  (response) => {
    // Check if this is a preview mode write operation
    if (response.data?.preview_mode === true) {
      console.info('[Preview Mode] Changes are session-only and will not be saved.');
      // Store the preview notice for components to display if needed
      response.data._preview_notice = response.data.preview_notice || 'Changes are session-only and will be lost on refresh.';
    }
    return response;
  },
  (error) => {
    if (error.response) {
      // Handle 401 Unauthorized errors
      if (error.response.status === 401) {
        // Don't redirect if we're already on login/register endpoints (let component handle it)
        const isAuthEndpoint = error.config?.url?.includes('/auth/login') ||
          error.config?.url?.includes('/auth/register');

        // Check if we're in preview mode - don't redirect, just reject silently
        const isPreviewMode = store.getters['preview/isPreviewMode'];

        if (!isAuthEndpoint && !isPreviewMode) {
          console.error('[API] 401 Unauthorized - Token expired or invalid. Redirecting to login...');
          // Clear token and redirect to login for protected routes
          localStorage.removeItem('auth_token');
          // Get the base path from the current location to handle subfolder deployments
          const basePath = window.location.pathname.includes('/fps/') ? '/fps' : '';
          window.location.href = `${basePath}/login`;
        } else {
          // For auth endpoints, return the error to be handled by the component
          return Promise.reject({
            message: error.response.data.message || 'Invalid credentials',
            errors: error.response.data.errors || null,
          });
        }
      }

      // Handle 422 Validation errors
      if (error.response.status === 422) {
        // Log as info, not error - these are expected validation responses
        console.info('[API] 422 Validation:', error.response.data.message || 'Validation failed');
        return Promise.reject({
          message: error.response.data.message || 'Validation failed',
          errors: error.response.data.errors || null,
          status: error.response.status,
          response: error.response,
        });
      }

      // Handle other errors
      return Promise.reject({
        message: error.response.data.message || 'An error occurred',
        errors: error.response.data.errors || null,
        status: error.response.status,
        response: error.response,
      });
    }

    // Network errors or other issues
    return Promise.reject({
      message: 'Network error. Please check your connection.',
      errors: null,
      status: null,
      response: null,
    });
  }
);

export default api;
