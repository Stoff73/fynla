import axios from 'axios';
import store from '@/store';

// Retry configuration for transient failures
const RETRY_CONFIG = {
  maxRetries: 3,
  baseDelay: 1000, // 1 second
  maxDelay: 10000, // 10 seconds
  // Only retry on server errors (5xx) and network failures
  retryCondition: (error) => {
    // Network errors (no response)
    if (!error.response) return true;
    // Server errors (5xx)
    if (error.response.status >= 500 && error.response.status < 600) return true;
    // Rate limiting (429) - but with longer delay
    if (error.response.status === 429) return true;
    return false;
  },
  // Only retry idempotent requests by default (GET, HEAD, OPTIONS, PUT, DELETE)
  // POST is not retried unless explicitly marked safe
  isIdempotent: (config) => {
    const method = config.method?.toUpperCase();
    return ['GET', 'HEAD', 'OPTIONS', 'PUT', 'DELETE'].includes(method);
  },
};

/**
 * Calculate delay with exponential backoff and jitter
 */
function getRetryDelay(retryCount, is429 = false) {
  // For 429 rate limiting, use longer base delay
  const base = is429 ? RETRY_CONFIG.baseDelay * 2 : RETRY_CONFIG.baseDelay;
  // Exponential backoff: base * 2^retryCount
  const exponentialDelay = base * Math.pow(2, retryCount);
  // Add jitter (random 0-30% variation) to prevent thundering herd
  const jitter = exponentialDelay * Math.random() * 0.3;
  return Math.min(exponentialDelay + jitter, RETRY_CONFIG.maxDelay);
}

/**
 * Sleep utility for retry delays
 */
function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

// Create axios instance with default config
// Use environment-specific base URL (production or local development)
// For production, always use window.location.origin to avoid CORS issues when users
// access via www.fynla.org vs fynla.org (the hardcoded VITE_API_BASE_URL may not match)
const isLocal = typeof window !== 'undefined' && (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1');
const localHost = typeof window !== 'undefined' ? window.location.hostname : 'localhost';
const apiBaseURL = isLocal ? `http://${localHost}:8000` : (window.location?.origin || import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000');
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
    const token = sessionStorage.getItem('auth_token');
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
          // Clear token from both sessionStorage and localStorage, then redirect
          sessionStorage.removeItem('auth_token');
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

/**
 * Retry interceptor for transient failures (5xx, network errors, 429)
 * Uses exponential backoff with jitter
 */
api.interceptors.response.use(
  (response) => response,
  async (error) => {
    const config = error.config;

    // Skip retry if already retried max times or if retry not applicable
    if (!config || config.__retryCount >= RETRY_CONFIG.maxRetries) {
      return Promise.reject(error);
    }

    // Check if we should retry this error
    if (!RETRY_CONFIG.retryCondition(error)) {
      return Promise.reject(error);
    }

    // Only retry idempotent requests to avoid duplicate side effects
    if (!RETRY_CONFIG.isIdempotent(config)) {
      return Promise.reject(error);
    }

    // Initialize retry count
    config.__retryCount = config.__retryCount || 0;
    config.__retryCount++;

    // Calculate delay with exponential backoff
    const is429 = error.response?.status === 429;
    const delay = getRetryDelay(config.__retryCount - 1, is429);

    console.info(
      `[API Retry] Attempt ${config.__retryCount}/${RETRY_CONFIG.maxRetries} ` +
      `for ${config.method?.toUpperCase()} ${config.url} ` +
      `(status: ${error.response?.status || 'network error'}, delay: ${Math.round(delay)}ms)`
    );

    // Wait before retrying
    await sleep(delay);

    // Retry the request
    return api(config);
  }
);

export default api;
