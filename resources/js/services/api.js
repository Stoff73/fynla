import axios from 'axios';
import store from '@/store';
import { getToken, removeToken } from '@/services/tokenStorage';

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
// For subdirectory deployments (e.g. csjones.co/fynla dev), we also append
// VITE_ROUTER_BASE so /api resolves under /fynla, not the domain root.
// Capacitor apps load at capacitor://localhost — detect this so it doesn't match local dev
const isCapacitor = typeof window !== 'undefined' && window.location.protocol === 'capacitor:';
const isLocal = !isCapacitor && typeof window !== 'undefined' && (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1');
const localHost = typeof window !== 'undefined' ? window.location.hostname : 'localhost';
// VITE_ROUTER_BASE is set by the build scripts to '/' (fynla.org root) or '/fynla/'
// (csjones.co/fynla dev subdirectory). Strip the trailing slash so the final
// concatenation `${origin}${routerBase}/api` produces correctly-formed URLs for both.
const routerBase = (import.meta.env.VITE_ROUTER_BASE || '/').replace(/\/$/, '');
const apiBaseURL = isCapacitor
  ? (import.meta.env.VITE_API_BASE_URL || 'https://fynla.org')
  : isLocal
    ? `http://${localHost}:${window.location?.port || '8000'}`
    : `${window.location?.origin || import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000'}${routerBase}`;
if (isCapacitor && import.meta.env.DEV) {
  console.log('[Capacitor] API service base URL:', `${apiBaseURL}/api`);
}
const api = axios.create({
  baseURL: `${apiBaseURL}/api`,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  // Capacitor uses Bearer tokens, not cookies — withCredentials causes WKWebView to block cross-origin requests
  withCredentials: !isCapacitor,
});

// Request interceptor to add auth token (async to support native storage in Phase 2)
api.interceptors.request.use(
  async (config) => {
    const token = await getToken();
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
  async (error) => {
    if (error.response) {
      // Handle 401 Unauthorized errors
      if (error.response.status === 401) {
        // Don't redirect if we're already on login/register endpoints (let component handle it)
        const isAuthEndpoint = error.config?.url?.includes('/auth/login') ||
          error.config?.url?.includes('/auth/register') ||
          error.config?.url?.includes('/auth/verify-code') ||
          error.config?.url?.includes('/preview/exit');

        // Check if we're in preview mode - don't redirect, just reject silently
        const isPreviewMode = store.getters['preview/isPreviewMode'];

        if (!isAuthEndpoint && !isPreviewMode) {
          console.error('[API] 401 Unauthorized - Token expired or invalid. Redirecting to login...');
          // Clear token via tokenStorage abstraction layer
          await removeToken();
          // On native (Capacitor), use SPA navigation to avoid page reload
          if (isCapacitor && window.__appRouter) {
            window.__appRouter.push('/m/login');
          } else {
            const basePath = window.location.pathname.includes('/fps/') ? '/fps' : '';
            window.location.href = `${basePath}/login`;
          }
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
    if (isCapacitor) {
      console.error('[API] Network error:', error.config?.method?.toUpperCase(), error.config?.url, error.message || 'unknown');
    }
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

export { apiBaseURL };
export default api;
