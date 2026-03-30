<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-lg font-bold text-horizon-500">AI Provider</h2>
        <p class="text-sm text-neutral-500 mt-1">Switch between AI providers for the Fyn assistant and document extraction</p>
      </div>
    </div>

    <div v-if="loading" class="flex justify-center py-8">
      <div class="w-8 h-8 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
    </div>

    <div v-else class="space-y-4">
      <!-- Provider Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div
          v-for="provider in providers"
          :key="provider.id"
          @click="provider.configured && selectProvider(provider.id)"
          :class="[
            'relative border-2 rounded-lg p-5 transition-all',
            activeProvider === provider.id
              ? 'border-raspberry-500 bg-raspberry-50'
              : provider.configured
                ? 'border-light-gray hover:border-horizon-300 cursor-pointer'
                : 'border-light-gray opacity-50 cursor-not-allowed',
          ]"
        >
          <!-- Active indicator -->
          <div v-if="activeProvider === provider.id" class="absolute top-3 right-3">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-spring-100 text-spring-700">
              Active
            </span>
          </div>

          <div class="flex items-start space-x-3">
            <div :class="[
              'flex-shrink-0 w-10 h-10 rounded-lg flex items-center justify-center text-white font-bold text-sm',
              provider.id === 'xai' ? 'bg-horizon-500' : 'bg-violet-500'
            ]">
              {{ provider.id === 'xai' ? 'X' : 'A' }}
            </div>
            <div class="flex-1 min-w-0">
              <h3 class="text-sm font-semibold text-horizon-500">{{ provider.name }}</h3>
              <p class="text-xs text-neutral-500 mt-1">Model: {{ provider.model }}</p>
              <p v-if="!provider.configured" class="text-xs text-raspberry-500 mt-1">
                API key not configured
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Status -->
      <div v-if="successMessage" class="flex items-center space-x-2 p-3 bg-spring-50 border border-spring-200 rounded-lg">
        <svg class="w-5 h-5 text-spring-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
        </svg>
        <p class="text-sm text-spring-700">{{ successMessage }}</p>
      </div>

      <div v-if="errorMessage" class="flex items-center space-x-2 p-3 bg-raspberry-50 border border-raspberry-200 rounded-lg">
        <svg class="w-5 h-5 text-raspberry-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
        </svg>
        <p class="text-sm text-raspberry-700">{{ errorMessage }}</p>
      </div>

      <!-- Info -->
      <div class="bg-savannah-100 border border-savannah-200 rounded-lg p-4">
        <div class="flex items-start space-x-2">
          <svg class="w-5 h-5 text-horizon-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
          </svg>
          <div class="text-sm text-horizon-600">
            <p class="font-medium">Switching providers</p>
            <p class="mt-1">Changes take effect immediately for all new AI conversations. Existing conversations will continue using the provider they started with. Both providers are always available for instant rollback.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import api from '../../services/api';

export default {
  name: 'AiSettings',

  data() {
    return {
      loading: true,
      activeProvider: 'anthropic',
      providers: [],
      successMessage: '',
      errorMessage: '',
      switching: false,
      _successTimer: null,
      _errorTimer: null,
    };
  },

  beforeUnmount() {
    if (this._successTimer) clearTimeout(this._successTimer);
    if (this._errorTimer) clearTimeout(this._errorTimer);
  },

  async mounted() {
    await this.loadSettings();
  },

  methods: {
    async loadSettings() {
      this.loading = true;
      try {
        const response = await api.get('/admin/ai-provider');
        if (response.data.success) {
          this.activeProvider = response.data.data.provider;
          this.providers = response.data.data.available_providers;
        }
      } catch (error) {
        this.errorMessage = 'Failed to load AI provider settings';
      } finally {
        this.loading = false;
      }
    },

    async selectProvider(providerId) {
      if (providerId === this.activeProvider || this.switching) return;

      this.switching = true;
      this.successMessage = '';
      this.errorMessage = '';

      try {
        const response = await api.post('/admin/ai-provider', { provider: providerId });
        if (response.data.success) {
          this.activeProvider = providerId;
          this.successMessage = response.data.message;
          this._successTimer = setTimeout(() => { this.successMessage = ''; }, 5000);
        }
      } catch (error) {
        this.errorMessage = error.response?.data?.message || 'Failed to switch provider';
        this._errorTimer = setTimeout(() => { this.errorMessage = ''; }, 5000);
      } finally {
        this.switching = false;
      }
    },
  },
};
</script>
