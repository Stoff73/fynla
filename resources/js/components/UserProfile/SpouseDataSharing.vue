<template>
  <div class="card p-6">
    <h3 class="text-h5 font-semibold text-horizon-500 mb-4">Spouse Data Sharing</h3>

    <!-- No Spouse -->
    <div v-if="!hasSpouse" class="text-body-base text-neutral-500">
      <p>You do not have a linked spouse. Add your spouse in the Family Members section to enable data sharing.</p>
    </div>

    <!-- Spouse Without Account Link -->
    <div v-else-if="requiresAccountLink" class="space-y-4">
      <div class="mb-6">
        <p class="text-body-sm text-neutral-500 mb-2">Spouse: <span class="font-medium text-horizon-500">{{ spouse?.name || 'N/A' }}</span></p>
        <p class="text-body-sm text-neutral-500">Status: <span class="font-medium text-horizon-500">Not linked</span></p>
      </div>

      <div class="bg-light-blue-100 border border-horizon-200 rounded-lg p-4">
        <div class="flex">
          <div class="flex-shrink-0">
            <svg class="h-5 w-5 text-horizon-500" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
            </svg>
          </div>
          <div class="ml-3 flex-1">
            <h4 class="text-body-sm font-medium text-horizon-600">Account Link Required</h4>
            <!--
              W-0349. The WORDING lives on the server, which is the only place
              that knows whether an invitation has gone out — the fallback here
              is a generic last resort, deliberately not a second copy of the
              sentence to drift from (Rule 20).
            -->
            <p class="mt-1 text-body-sm text-horizon-500">
              {{ permissionMessage || 'Your spouse needs an account before anything can be shared.' }}
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Has Spouse With Linked Account -->
    <div v-else>
      <!-- Spouse Info -->
      <div class="mb-6">
        <p class="text-body-sm text-neutral-500 mb-2">Spouse: <span class="font-medium text-horizon-500">{{ spouse?.name || 'N/A' }}</span></p>
        <p class="text-body-sm text-neutral-500">Email: <span class="font-medium text-horizon-500">{{ spouse?.email || 'N/A' }}</span></p>
      </div>

      <!-- No Permission Request -->
      <div v-if="!permission" class="space-y-4">
        <div class="bg-light-blue-100 border border-horizon-200 rounded-lg p-4">
          <div class="flex">
            <div class="flex-shrink-0">
              <svg class="h-5 w-5 text-horizon-500" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
              </svg>
            </div>
            <div class="ml-3 flex-1">
              <h4 class="text-body-sm font-medium text-horizon-600">Enable Joint Account View</h4>
              <!--
                W-0347 F3, second pass. This described only the benefit flowing to the
                person clicking, on the screen where they consent to their OWN records
                being disclosed to the other party. Art. 7(3) wants the right to
                withdraw stated before consent is given, not after.
              -->
              <p class="mt-1 text-body-sm text-horizon-500">
                Ask your spouse to share financial information with you. If you accept, you will each be able to see the other's assets, liabilities and income, and your accounts will be recorded as one household, with both of you recorded as married or in a civil partnership. You can stop sharing at any time; that turns off what each of you can see, and does not undo the household record.
              </p>
            </div>
          </div>
        </div>

        <button
          @click="handleRequestPermission"
          :disabled="loading"
          class="btn-primary"
        >
          <span v-if="!loading">Request Data Sharing Permission</span>
          <span v-else>Sending Request...</span>
        </button>
      </div>

      <!-- Permission Pending (sent by current user) -->
      <div v-else-if="isPending && permission.user_id === currentUserId" class="space-y-4">
        <div class="bg-violet-50 border border-violet-200 rounded-lg p-4">
          <div class="flex">
            <div class="flex-shrink-0">
              <svg class="h-5 w-5 text-violet-600" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
              </svg>
            </div>
            <div class="ml-3 flex-1">
              <h4 class="text-body-sm font-medium text-violet-800">Permission Request Pending</h4>
              <p class="mt-1 text-body-sm text-violet-700">
                Waiting for your spouse to accept the data sharing request.
              </p>
              <p class="mt-1 text-body-xs text-violet-600">
                Requested: {{ formatDate(permission.requested_at) }}
              </p>
            </div>
          </div>
        </div>

        <button
          @click="handleRevokePermission"
          :disabled="loading"
          class="btn-secondary"
        >
          <span v-if="!loading">Cancel Request</span>
          <span v-else>Cancelling...</span>
        </button>
      </div>

      <!-- Permission Pending (received by current user) -->
      <div v-else-if="isPending && permission.spouse_id === currentUserId" class="space-y-4">
        <div class="bg-light-blue-100 border border-horizon-200 rounded-lg p-4">
          <div class="flex">
            <div class="flex-shrink-0">
              <svg class="h-5 w-5 text-horizon-500" fill="currentColor" viewBox="0 0 20 20">
                <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
              </svg>
            </div>
            <div class="ml-3 flex-1">
              <h4 class="text-body-sm font-medium text-horizon-600">Permission Request Received</h4>
              <!--
                W-0347 F3. The old sentence described acceptance as one-way data
                viewing. It is neither: one accepted row makes the grant MUTUAL,
                and accepting also writes this account's own `spouse_id`,
                `marital_status` and `household_id`, none of which `revoke()`
                reverses. Both were disclosed after the decision or not at all.
                Same sentence as /m (Rule 20).
              -->
              <p class="mt-1 text-body-sm text-horizon-500">
                Your spouse has requested permission to view your financial data. If you accept, you will each be able to see the other's assets, liabilities and income, and your accounts will be recorded as one household, with both of you recorded as married or in a civil partnership. You can stop sharing at any time; that turns off what each of you can see, and does not undo the household record.
              </p>
              <p class="mt-1 text-body-xs text-horizon-500">
                Requested: {{ formatDate(permission.requested_at) }}
              </p>
            </div>
          </div>
        </div>

        <div class="flex gap-3">
          <button
            @click="handleAcceptPermission"
            :disabled="loading"
            class="btn-primary"
          >
            <span v-if="!loading">Accept Request</span>
            <span v-else>Accepting...</span>
          </button>

          <button
            @click="handleRejectPermission"
            :disabled="loading"
            class="btn-secondary"
          >
            <span v-if="!loading">Reject Request</span>
            <span v-else>Rejecting...</span>
          </button>
        </div>
      </div>

      <!-- Permission Accepted -->
      <div v-else-if="isAccepted" class="space-y-4">
        <div class="bg-spring-50 border border-spring-200 rounded-lg p-4">
          <div class="flex">
            <div class="flex-shrink-0">
              <svg class="h-5 w-5 text-spring-600" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
            </div>
            <div class="ml-3 flex-1">
              <h4 class="text-body-sm font-medium text-spring-800">Data Sharing Enabled</h4>
              <!-- W-0347 G11 — `/m` described this state as mutual and web as one-way.
                   It is mutual: one accepted row makes it true for both parties. -->
              <p class="mt-1 text-body-sm text-spring-700">
                You and your spouse can each see the other's assets, liabilities and income.
              </p>
              <!--
                W-0347 F2 — this printed "Accepted: <date>" over rows nobody had
                accepted, because the forging path set `responded_at` and nothing
                else. Those rows are now unanswered requests
                (`2026_08_24_130000_reask_spouse_permissions_nobody_granted`), and
                the guard keeps the screen from asserting an acceptance again if
                one ever arrives without a timestamp.
              -->
              <p v-if="permission.responded_at" class="mt-1 text-body-xs text-spring-600">
                Accepted: {{ formatDate(permission.responded_at) }}
              </p>
            </div>
          </div>
        </div>

        <button
          @click="handleRevokePermission"
          :disabled="loading"
          class="btn-secondary"
        >
          <span v-if="!loading">Revoke Permission</span>
          <span v-else>Revoking...</span>
        </button>
      </div>

      <!-- Permission Rejected -->
      <div v-else-if="isRejected" class="space-y-4">
        <div class="bg-raspberry-50 border border-raspberry-200 rounded-lg p-4">
          <div class="flex">
            <div class="flex-shrink-0">
              <svg class="h-5 w-5 text-raspberry-600" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
              </svg>
            </div>
            <div class="ml-3 flex-1">
              <!--
                Covers two cases now: a declined request, and sharing that was
                switched on and later withdrawn. Revoking marks the row
                `rejected` rather than deleting it, so a deleted record cannot
                read as "never asked" and quietly re-enable sharing. Wording
                stays true of both rather than asserting the spouse declined.
              -->
              <h4 class="text-body-sm font-medium text-raspberry-800">Data sharing is off</h4>
              <p class="mt-1 text-body-sm text-raspberry-700">
                Financial information is not being shared between these accounts.
              </p>
              <p v-if="permission.responded_at" class="mt-1 text-body-xs text-raspberry-600">
                Since: {{ formatDate(permission.responded_at) }}
              </p>
            </div>
          </div>
        </div>

        <!--
          W-0347 F4. Once sharing was off, neither party could turn it back on
          through any interface: `request()` refused while any row existed and
          `revoke()` leaves a `rejected` one behind. A withdrawal you cannot
          reverse is one people hesitate to make.
        -->
        <!--
          W-0347 F3 — this is the route by which someone who withdrew turns disclosure
          back ON, and it carried no notice at all. Same sentence as every other
          pre-consent screen (Rule 20).
        -->
        <p class="text-body-sm text-horizon-500">
          If you accept, you will each be able to see the other's assets, liabilities and income, and your accounts will be recorded as one household, with both of you recorded as married or in a civil partnership. You can stop sharing at any time; that turns off what each of you can see, and does not undo the household record.
        </p>

        <button
          @click="handleRequestPermission"
          :disabled="loading"
          class="btn-primary"
        >
          <span v-if="!loading">Ask to share again</span>
          <span v-else>Sending Request...</span>
        </button>
      </div>

      <!-- Error Message -->
      <div v-if="error" class="mt-4 bg-raspberry-50 border border-raspberry-200 rounded-lg p-4">
        <p class="text-body-sm text-raspberry-800">{{ error }}</p>
      </div>
    </div>
  </div>
</template>

<script>
import { computed, onMounted } from 'vue';
import { useStore } from 'vuex';

import logger from '@/utils/logger';
export default {
  name: 'SpouseDataSharing',

  setup() {
    const store = useStore();

    const hasSpouse = computed(() => store.getters['spousePermission/hasSpouse']);
    const spouse = computed(() => store.getters['spousePermission/spouse']);
    const permission = computed(() => store.getters['spousePermission/permission']);
    const isPending = computed(() => store.getters['spousePermission/isPending']);
    const isAccepted = computed(() => store.getters['spousePermission/isAccepted']);
    const isRejected = computed(() => store.getters['spousePermission/isRejected']);
    const loading = computed(() => store.getters['spousePermission/loading']);
    const error = computed(() => store.getters['spousePermission/error']);
    const currentUserId = computed(() => store.getters['auth/currentUser']?.id);
    const requiresAccountLink = computed(() => store.state.spousePermission.requiresAccountLink || false);
    const permissionMessage = computed(() => store.state.spousePermission.message || '');

    const formatDate = (date) => {
      if (!date) return 'N/A';
      return new Date(date).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
      });
    };

    const handleRequestPermission = async () => {
      try {
        await store.dispatch('spousePermission/requestPermission');
      } catch (error) {
        logger.error('Failed to request permission:', error);
      }
    };

    const handleAcceptPermission = async () => {
      try {
        await store.dispatch('spousePermission/acceptPermission');
      } catch (error) {
        logger.error('Failed to accept permission:', error);
      }
    };

    const handleRejectPermission = async () => {
      try {
        await store.dispatch('spousePermission/rejectPermission');
      } catch (error) {
        logger.error('Failed to reject permission:', error);
      }
    };

    const handleRevokePermission = async () => {
      try {
        if (confirm('Are you sure you want to revoke data sharing permission?')) {
          await store.dispatch('spousePermission/revokePermission');
        }
      } catch (error) {
        logger.error('Failed to revoke permission:', error);
      }
    };

    onMounted(() => {
      store.dispatch('spousePermission/fetchPermissionStatus');
    });

    return {
      hasSpouse,
      spouse,
      permission,
      isPending,
      isAccepted,
      isRejected,
      loading,
      error,
      currentUserId,
      requiresAccountLink,
      permissionMessage,
      formatDate,
      handleRequestPermission,
      handleAcceptPermission,
      handleRejectPermission,
      handleRevokePermission,
    };
  },
};
</script>
