<template>
  <div class="max-w-5xl mx-auto">
    <!-- Main Welcome Card -->
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-6 mb-6">
      <h1 class="text-h2 font-display text-gray-900 mb-4">
        Welcome {{ userName }} to Fynla Demo
      </h1>
      <p class="text-body text-gray-700 mb-4">
        This demo tries to show the potential for a full financial planning app. Currently we have the following functionality:
      </p>
      <ul class="space-y-2 text-body-sm text-gray-600 mb-6">
        <li class="flex items-start">
          <svg class="w-5 h-5 text-primary-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
          </svg>
          <span>Onboarding / Factfind process</span>
        </li>
        <li class="flex items-start">
          <svg class="w-5 h-5 text-primary-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
          </svg>
          <span>Spouse account linking for jointly owned assets and liabilities</span>
        </li>
        <li class="flex items-start">
          <svg class="w-5 h-5 text-primary-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
          </svg>
          <span>Dashboard view</span>
        </li>
        <li class="flex items-start">
          <svg class="w-5 h-5 text-primary-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
          </svg>
          <span>Net Worth dashboard</span>
        </li>
        <li class="flex items-start">
          <svg class="w-5 h-5 text-primary-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
          </svg>
          <span>Estate Planning Dashboard</span>
        </li>
        <li class="flex items-start">
          <svg class="w-5 h-5 text-primary-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
          </svg>
          <span>Protection dashboard</span>
        </li>
        <li class="flex items-start">
          <svg class="w-5 h-5 text-primary-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
          </svg>
          <span>User profile section</span>
        </li>
        <li class="flex items-start">
          <svg class="w-5 h-5 text-primary-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
          </svg>
          <span>Balance sheet for single and linked accounts</span>
        </li>
        <li class="flex items-start">
          <svg class="w-5 h-5 text-primary-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
          </svg>
          <span>IHT calculator and liability indicator</span>
        </li>
        <li class="flex items-start">
          <svg class="w-5 h-5 text-primary-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
          </svg>
          <span>Comprehensive Estate Planning Plan</span>
        </li>
        <li class="flex items-start">
          <svg class="w-5 h-5 text-primary-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
          </svg>
          <span>Many other bits and bobs, so please click all buttons, tabs and check as much as you can.</span>
        </li>
      </ul>

      <!-- Security Note -->
      <div class="bg-gray-50 rounded-lg p-4 mb-6">
        <h3 class="text-body font-semibold text-gray-900 mb-2">Note on security:</h3>
        <p class="text-body-sm text-gray-700 mb-2">
          While this is a demo, I have built this with information security in mind, so the following is in place:
        </p>
        <ul class="space-y-1 text-body-sm text-gray-600">
          <li class="flex items-start">
            <svg class="w-4 h-4 text-green-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
            </svg>
            <span>Encrypted database</span>
          </li>
          <li class="flex items-start">
            <svg class="w-4 h-4 text-green-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
            </svg>
            <span>Secure log-in and password control</span>
          </li>
          <li class="flex items-start">
            <svg class="w-4 h-4 text-green-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
            </svg>
            <span>Account silo monitoring, so no leaking of info</span>
          </li>
        </ul>
      </div>

      <!-- Continue Button -->
      <div class="text-center">
        <button
          @click="selectFocusArea('estate')"
          :disabled="loading"
          class="inline-flex items-center px-8 py-3 border border-transparent text-body font-medium rounded-button text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        >
          <svg v-if="!loading" class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
          </svg>
          <svg v-else class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          {{ loading ? 'Loading...' : 'Continue to Onboarding' }}
        </button>
      </div>
    </div>

    <!-- Note from CSJ Card -->
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-6">
      <h2 class="text-h3 font-display text-gray-900 mb-4">Note from CSJ</h2>
      <div class="text-body-sm text-gray-700 space-y-4">
        <p>Dear {{ userName }},</p>
        <p>
          Thank you so much for giving your time to check out Fynla.
        </p>
        <p>
          It has taken 2 months to put this together, and the point of asking you to go through this app is to see if this is viable or not. The feedback form is pretty simple, or just email me, <a href="mailto:c.jones@csjones.co" class="text-primary-600 hover:text-primary-700 underline">c.jones@csjones.co</a> or just give me a call.
        </p>
        <p>
          Thanks again for going through the app, really appreciate it, and good luck.
        </p>
        <p class="font-semibold">CSJ</p>
        <p class="text-gray-500 italic">
          P.S. There are bugs, and issues, remember I am not a developer, so I run out of talent everyday, thank goodness for AI :)
        </p>
      </div>
    </div>

    <div v-if="error" class="mt-6 p-4 bg-error-50 border border-error-200 rounded-lg">
      <p class="text-body-sm text-error-700">{{ error }}</p>
    </div>
  </div>
</template>

<script>
import { ref, computed } from 'vue';
import { useStore } from 'vuex';

export default {
  name: 'FocusAreaSelection',

  emits: ['selected'],

  setup(props, { emit }) {
    const store = useStore();
    const loading = ref(false);
    const error = ref(null);

    // Nickname mapping for special users (Welcome screen only)
    const nicknameMap = {
      'Steven Cracknell': 'Captain',
      'Darren Veenhuis': 'Dazzler',
      'Gareth Brace': 'Big G',
      'Stephen Kavanagh': 'Oh Ah',
      'Tristan von Strein': 'TDawg',
      'Paul Smith': 'Smitty',
      'Brett Isenberg': 'Slippery',
    };

    const userName = computed(() => {
      const user = store.state.auth.user;
      if (user && user.name) {
        // Check if user has a nickname
        if (nicknameMap[user.name]) {
          return nicknameMap[user.name];
        }
        // Get first name only for other users
        return user.name.split(' ')[0];
      }
      return '';
    });

    const selectFocusArea = async (focusArea) => {
      loading.value = true;
      error.value = null;

      try {
        await store.dispatch('onboarding/setFocusArea', focusArea);
        emit('selected', focusArea);
      } catch (err) {
        error.value = err.message || 'Failed to set focus area. Please try again.';
      } finally {
        loading.value = false;
      }
    };

    return {
      loading,
      error,
      userName,
      selectFocusArea,
    };
  },
};
</script>
