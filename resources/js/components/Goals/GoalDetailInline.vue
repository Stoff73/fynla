<template>
  <div class="detail-inline">
    <!-- Back Button -->
    <button @click="$emit('back')" class="detail-inline-back mb-4">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
      </svg>
      Back to Goals
    </button>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-raspberry-600"></div>
      <p class="mt-4 text-neutral-500">Loading goal details...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-savannah-100 rounded-card p-6 text-center border border-light-gray">
      <p class="text-raspberry-600">{{ error }}</p>
      <button
        @click="loadGoalDetail"
        class="mt-4 btn-danger"
      >
        Retry
      </button>
    </div>

    <!-- Goal Content -->
    <div v-else-if="goal" class="space-y-6">
      <!-- Header -->
      <div class="bg-white rounded-card border border-light-gray shadow-sm p-6">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
          <div>
            <div class="flex items-center gap-3 mb-2">
              <span class="text-2xl">{{ getGoalIcon(goal.goal_type) }}</span>
              <div>
                <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-horizon-500">{{ goal.goal_name }}</h1>
                <div class="flex flex-wrap items-center gap-2 mt-1">
                  <span class="text-xs px-2 py-0.5 rounded-full" :class="moduleTagClass">
                    {{ moduleLabel }}
                  </span>
                  <span v-if="goal.priority === 'critical' || goal.priority === 'high'"
                    class="text-xs px-2 py-0.5 rounded-full"
                    :class="priorityTagClass"
                  >
                    {{ goal.priority }}
                  </span>
                  <span
                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                    :class="statusBadgeClass"
                  >
                    <span class="w-1.5 h-1.5 rounded-full mr-1.5" :class="statusDotClass"></span>
                    {{ statusText }}
                  </span>
                </div>
              </div>
            </div>
            <p v-if="goal.description" class="text-sm text-neutral-500 mt-2">{{ goal.description }}</p>
          </div>
          <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto shrink-0">
            <button
              v-if="goal.status === 'active'"
              v-preview-disabled="'add'"
              @click="$emit('add-contribution', goal)"
              class="w-full sm:w-auto px-4 py-2 bg-success-600 text-white rounded-button font-medium hover:bg-success-700 transition-all duration-150 shadow-sm hover:shadow-md"
            >
              + Contribution
            </button>
            <button
              v-preview-disabled="'edit'"
              @click="$emit('edit', goal)"
              class="w-full sm:w-auto btn-primary"
            >
              Edit
            </button>
            <button
              v-preview-disabled="'delete'"
              @click="$emit('delete', goal)"
              class="w-full sm:w-auto btn-danger"
            >
              Delete
            </button>
          </div>
        </div>

        <!-- Key Metrics -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-6">
          <div class="bg-violet-50 rounded-lg p-4 border border-violet-200">
            <p class="text-sm text-neutral-500">Progress</p>
            <p class="text-2xl font-bold text-violet-600">{{ progressPercent }}%</p>
            <p class="text-xs text-neutral-500 mt-1">{{ formatCurrency(goal.current_amount) }} of {{ formatCurrency(goal.target_amount) }}</p>
          </div>
          <div class="bg-savannah-100 rounded-lg p-4">
            <p class="text-sm text-neutral-500">Time Remaining</p>
            <p class="text-2xl font-bold text-horizon-500">{{ timeRemaining }}</p>
            <p v-if="goal.target_date" class="text-xs text-neutral-500 mt-1">Target: {{ formatDate(goal.target_date) }}</p>
          </div>
          <div class="bg-savannah-100 rounded-lg p-4">
            <p class="text-sm text-neutral-500">Monthly Contribution</p>
            <p class="text-2xl font-bold" :class="goal.monthly_contribution > 0 ? 'text-spring-600' : 'text-horizon-500'">
              {{ goal.monthly_contribution ? formatCurrency(goal.monthly_contribution) : '\u2014' }}
            </p>
            <p v-if="goal.required_monthly_contribution" class="text-xs text-neutral-500 mt-1">
              Required: {{ formatCurrency(goal.required_monthly_contribution) }}
            </p>
          </div>
          <div class="bg-savannah-100 rounded-lg p-4">
            <p class="text-sm text-neutral-500">Amount Remaining</p>
            <p class="text-2xl font-bold text-horizon-500">{{ formatCurrency(goal.amount_remaining || 0) }}</p>
            <p v-if="contributionStreak > 0" class="text-xs text-violet-600 mt-1">
              {{ contributionStreak }} month streak
            </p>
          </div>
        </div>

        <!-- Progress Bar -->
        <div class="mt-4">
          <div class="w-full bg-horizon-200 rounded-full h-2">
            <div
              class="h-2 rounded-full transition-all duration-500"
              :class="progressBarClass"
              :style="{ width: Math.min(progressPercent, 100) + '%' }"
            ></div>
          </div>
          <div v-if="milestones && milestones.length > 0" class="flex justify-between mt-2">
            <template v-for="milestone in milestones" :key="milestone.percentage">
              <div
                class="text-xs text-center"
                :class="milestone.reached ? 'text-spring-600 font-medium' : 'text-horizon-400'"
              >
                {{ milestone.percentage }}%
              </div>
            </template>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="bg-white rounded-card border border-light-gray shadow-sm">
        <div class="border-b border-light-gray">
          <nav class="flex -mb-px overflow-x-auto">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              @click="activeTab = tab.id"
              class="px-6 py-3 border-b-2 font-medium text-sm transition-colors whitespace-nowrap flex-shrink-0"
              :class="
                activeTab === tab.id
                  ? 'border-raspberry-600 text-raspberry-600'
                  : 'border-transparent text-neutral-500 hover:text-neutral-500 hover:border-horizon-300'
              "
            >
              {{ tab.label }}
            </button>
          </nav>
        </div>

        <div class="p-6">
          <!-- Overview Tab -->
          <div v-show="activeTab === 'overview'" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- Goal Details -->
              <div>
                <h3 class="text-lg font-semibold text-horizon-500 mb-3">Goal Details</h3>
                <dl class="space-y-2">
                  <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Goal Type:</dt>
                    <dd class="text-sm font-medium text-horizon-500 sm:text-right">{{ goal.display_goal_type || formatGoalType(goal.goal_type) }}</dd>
                  </div>
                  <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Assigned Module:</dt>
                    <dd class="text-sm font-medium text-horizon-500 sm:text-right">{{ moduleLabel || '\u2014' }}</dd>
                  </div>
                  <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Priority:</dt>
                    <dd class="text-sm font-medium text-horizon-500 capitalize sm:text-right">{{ goal.priority || 'Medium' }}</dd>
                  </div>
                  <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Essential:</dt>
                    <dd class="text-sm font-medium text-horizon-500 sm:text-right">{{ goal.is_essential ? 'Yes' : 'No' }}</dd>
                  </div>
                  <div v-if="goal.start_date" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Start Date:</dt>
                    <dd class="text-sm font-medium text-horizon-500 sm:text-right">{{ formatDate(goal.start_date) }}</dd>
                  </div>
                  <div v-if="goal.target_date" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Target Date:</dt>
                    <dd class="text-sm font-medium text-horizon-500 sm:text-right">{{ formatDate(goal.target_date) }}</dd>
                  </div>
                  <div v-if="goal.created_at" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Created:</dt>
                    <dd class="text-sm font-medium text-horizon-500 sm:text-right">{{ formatDate(goal.created_at) }}</dd>
                  </div>
                </dl>
              </div>

              <!-- Financial Summary -->
              <div>
                <h3 class="text-lg font-semibold text-horizon-500 mb-3">Financial Summary</h3>
                <dl class="space-y-2">
                  <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Target Amount:</dt>
                    <dd class="text-sm font-semibold text-violet-600 sm:text-right">{{ formatCurrency(goal.target_amount) }}</dd>
                  </div>
                  <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Current Amount:</dt>
                    <dd class="text-sm font-medium text-horizon-500 sm:text-right">{{ formatCurrency(goal.current_amount) }}</dd>
                  </div>
                  <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Amount Remaining:</dt>
                    <dd class="text-sm font-medium text-horizon-500 sm:text-right">{{ formatCurrency(goal.amount_remaining || 0) }}</dd>
                  </div>
                  <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Monthly Contribution:</dt>
                    <dd class="text-sm font-medium text-horizon-500 sm:text-right">{{ goal.monthly_contribution ? formatCurrency(goal.monthly_contribution) : '\u2014' }}</dd>
                  </div>
                  <div v-if="goal.required_monthly_contribution" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Required Monthly:</dt>
                    <dd class="text-sm font-medium sm:text-right" :class="isContributionSufficient ? 'text-spring-600' : 'text-raspberry-600'">
                      {{ formatCurrency(goal.required_monthly_contribution) }}
                    </dd>
                  </div>
                  <div v-if="goal.contribution_frequency" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Contribution Frequency:</dt>
                    <dd class="text-sm font-medium text-horizon-500 capitalize sm:text-right">{{ goal.contribution_frequency }}</dd>
                  </div>
                </dl>
              </div>

              <!-- Property Details (if property goal) -->
              <div v-if="isPropertyGoal">
                <h3 class="text-lg font-semibold text-horizon-500 mb-3">Property Details</h3>
                <dl class="space-y-2">
                  <div v-if="goal.property_location" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Location:</dt>
                    <dd class="text-sm font-medium text-horizon-500 sm:text-right">{{ goal.property_location }}</dd>
                  </div>
                  <div v-if="goal.estimated_property_price" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Estimated Price:</dt>
                    <dd class="text-sm font-medium text-horizon-500 sm:text-right">{{ formatCurrency(goal.estimated_property_price) }}</dd>
                  </div>
                  <div v-if="goal.deposit_percentage" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Deposit Percentage:</dt>
                    <dd class="text-sm font-medium text-horizon-500 sm:text-right">{{ goal.deposit_percentage }}%</dd>
                  </div>
                  <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">First-Time Buyer:</dt>
                    <dd class="text-sm font-medium text-horizon-500 sm:text-right">{{ goal.is_first_time_buyer ? 'Yes' : 'No' }}</dd>
                  </div>
                </dl>
              </div>

              <!-- Streak & Milestones -->
              <div>
                <h3 class="text-lg font-semibold text-horizon-500 mb-3">Progress Tracking</h3>
                <dl class="space-y-2">
                  <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Current Streak:</dt>
                    <dd class="text-sm font-medium text-horizon-500 sm:text-right">
                      {{ contributionStreak > 0 ? contributionStreak + ' months' : '\u2014' }}
                    </dd>
                  </div>
                  <div v-if="goal.longest_streak" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Longest Streak:</dt>
                    <dd class="text-sm font-medium text-horizon-500 sm:text-right">{{ goal.longest_streak }} months</dd>
                  </div>
                  <div v-if="goal.last_contribution_date" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Last Contribution:</dt>
                    <dd class="text-sm font-medium text-horizon-500 sm:text-right">{{ formatDate(goal.last_contribution_date) }}</dd>
                  </div>
                  <div v-if="goal.current_milestone" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Current Milestone:</dt>
                    <dd class="text-sm font-medium text-spring-600 sm:text-right">{{ goal.current_milestone }}% reached</dd>
                  </div>
                  <div v-if="goal.next_milestone" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Next Milestone:</dt>
                    <dd class="text-sm font-medium text-violet-600 sm:text-right">{{ goal.next_milestone }}%</dd>
                  </div>
                </dl>
              </div>
            </div>
          </div>

          <!-- Affordability Tab -->
          <div v-show="activeTab === 'affordability'" class="space-y-6">
            <div v-if="!affordability" class="text-center py-8 text-neutral-500">
              <p>Affordability analysis is not available for this goal.</p>
            </div>
            <div v-else>
              <!-- Affordability Summary -->
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                <div class="bg-savannah-100 rounded-lg p-4 border" :class="affordability.can_afford ? 'border-spring-200' : 'border-raspberry-200'">
                  <p class="text-sm text-neutral-500">Affordability</p>
                  <p class="text-lg font-bold" :class="affordability.can_afford ? 'text-spring-600' : 'text-raspberry-600'">
                    {{ affordability.can_afford ? 'Achievable' : 'Needs Attention' }}
                  </p>
                </div>
                <div v-if="affordability.monthly_surplus !== undefined" class="bg-savannah-100 rounded-lg p-4">
                  <p class="text-sm text-neutral-500">Monthly Surplus</p>
                  <p class="text-lg font-bold" :class="affordability.monthly_surplus >= 0 ? 'text-spring-600' : 'text-raspberry-600'">
                    {{ formatCurrency(affordability.monthly_surplus) }}
                  </p>
                </div>
                <div v-if="affordability.months_to_goal !== undefined" class="bg-savannah-100 rounded-lg p-4">
                  <p class="text-sm text-neutral-500">Estimated Time to Goal</p>
                  <p class="text-lg font-bold text-horizon-500">
                    {{ formatMonthsToGoal(affordability.months_to_goal) }}
                  </p>
                </div>
              </div>

              <!-- Affordability Details -->
              <div v-if="affordability.breakdown" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <h3 class="text-lg font-semibold text-horizon-500 mb-3">Income & Expenses</h3>
                  <dl class="space-y-2">
                    <div v-if="affordability.breakdown.monthly_income" class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Monthly Income:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency(affordability.breakdown.monthly_income) }}</dd>
                    </div>
                    <div v-if="affordability.breakdown.monthly_expenses" class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Monthly Expenses:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency(affordability.breakdown.monthly_expenses) }}</dd>
                    </div>
                    <div v-if="affordability.breakdown.other_goal_contributions" class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Other Goal Contributions:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency(affordability.breakdown.other_goal_contributions) }}</dd>
                    </div>
                  </dl>
                </div>
                <div>
                  <h3 class="text-lg font-semibold text-horizon-500 mb-3">Recommendations</h3>
                  <ul v-if="affordability.recommendations && affordability.recommendations.length > 0" class="space-y-2">
                    <li v-for="(rec, i) in affordability.recommendations" :key="i" class="flex items-start gap-2 text-sm text-neutral-500">
                      <svg class="w-4 h-4 text-violet-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                      </svg>
                      {{ rec }}
                    </li>
                  </ul>
                  <p v-else class="text-sm text-neutral-500">No specific recommendations at this time.</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Projections Tab (for investment goals) -->
          <div v-show="activeTab === 'projections'" class="space-y-6">
            <div v-if="!projections" class="text-center py-8 text-neutral-500">
              <p>Projections are available for investment-linked goals.</p>
            </div>
            <div v-else>
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                <div v-if="projections.expected_value" class="bg-violet-50 rounded-lg p-4 border border-violet-200">
                  <p class="text-sm text-neutral-500">Expected Value at Target</p>
                  <p class="text-lg font-bold text-violet-600">{{ formatCurrency(projections.expected_value) }}</p>
                </div>
                <div v-if="projections.probability_of_success !== undefined" class="bg-savannah-100 rounded-lg p-4">
                  <p class="text-sm text-neutral-500">Probability of Success</p>
                  <p class="text-lg font-bold" :class="projections.probability_of_success >= 70 ? 'text-spring-600' : 'text-violet-600'">
                    {{ projections.probability_of_success }}%
                  </p>
                </div>
                <div v-if="projections.best_case" class="bg-savannah-100 rounded-lg p-4">
                  <p class="text-sm text-neutral-500">Best Case</p>
                  <p class="text-lg font-bold text-spring-600">{{ formatCurrency(projections.best_case) }}</p>
                </div>
              </div>
              <div v-if="projections.worst_case || projections.median_case" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div v-if="projections.median_case" class="bg-savannah-100 rounded-lg p-4">
                  <p class="text-sm text-neutral-500">Median Case</p>
                  <p class="text-lg font-bold text-horizon-500">{{ formatCurrency(projections.median_case) }}</p>
                </div>
                <div v-if="projections.worst_case" class="bg-savannah-100 rounded-lg p-4">
                  <p class="text-sm text-neutral-500">Worst Case</p>
                  <p class="text-lg font-bold text-horizon-500">{{ formatCurrency(projections.worst_case) }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Dependencies Tab -->
          <div v-show="activeTab === 'dependencies'" class="space-y-6">
            <div v-if="dependenciesLoading" class="text-center py-8">
              <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-raspberry-600"></div>
            </div>
            <div v-else-if="!hasDependencies" class="text-center py-8 text-neutral-500">
              <svg class="mx-auto h-12 w-12 text-horizon-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
              </svg>
              <p>No dependencies configured for this goal.</p>
              <p class="text-sm mt-1">Dependencies link goals together so one must complete before another can start.</p>
            </div>
            <div v-else>
              <!-- Depends On -->
              <div v-if="dependsOn.length > 0" class="mb-6">
                <h3 class="text-lg font-semibold text-horizon-500 mb-3">This Goal Depends On</h3>
                <div class="space-y-3">
                  <div
                    v-for="dep in dependsOn"
                    :key="dep.id"
                    class="flex items-center justify-between p-3 bg-savannah-100 rounded-lg border border-light-gray"
                  >
                    <div class="flex items-center gap-3">
                      <span class="text-lg">{{ getGoalIcon(dep.goal_type) }}</span>
                      <div>
                        <p class="text-sm font-medium text-horizon-500">{{ dep.goal_name }}</p>
                        <p class="text-xs text-neutral-500 capitalize">{{ dep.dependency_type }} - {{ dep.status }}</p>
                      </div>
                    </div>
                    <span
                      class="text-xs px-2 py-1 rounded-full"
                      :class="dep.status === 'completed' ? 'bg-spring-100 text-spring-700' : 'bg-violet-100 text-violet-700'"
                    >
                      {{ dep.status === 'completed' ? 'Complete' : 'In Progress' }}
                    </span>
                  </div>
                </div>
              </div>

              <!-- Depended On By -->
              <div v-if="dependedOnBy.length > 0">
                <h3 class="text-lg font-semibold text-horizon-500 mb-3">Goals That Depend On This</h3>
                <div class="space-y-3">
                  <div
                    v-for="dep in dependedOnBy"
                    :key="dep.id"
                    class="flex items-center justify-between p-3 bg-savannah-100 rounded-lg border border-light-gray"
                  >
                    <div class="flex items-center gap-3">
                      <span class="text-lg">{{ getGoalIcon(dep.goal_type) }}</span>
                      <div>
                        <p class="text-sm font-medium text-horizon-500">{{ dep.goal_name }}</p>
                        <p class="text-xs text-neutral-500 capitalize">{{ dep.dependency_type }}</p>
                      </div>
                    </div>
                    <span class="text-xs px-2 py-1 rounded-full bg-violet-50 text-violet-600">
                      Waiting
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import { previewModeMixin } from '@/mixins/previewModeMixin';
import goalsService from '@/services/goalsService';
import { getGoalIcon } from '@/constants/goalIcons';
import { formatDateLong } from '@/utils/dateFormatter';

import logger from '@/utils/logger';
export default {
  name: 'GoalDetailInline',
  mixins: [currencyMixin, previewModeMixin],

  props: {
    goalId: {
      type: Number,
      required: true,
    },
  },

  emits: ['back', 'edit', 'delete', 'add-contribution', 'updated'],

  data() {
    return {
      activeTab: 'overview',
      loading: false,
      error: null,
      goalDetail: null,
      milestones: null,
      streak: null,
      affordability: null,
      projections: null,
      dependenciesLoading: false,
      dependsOn: [],
      dependedOnBy: [],
    };
  },

  computed: {
    goal() {
      return this.goalDetail;
    },

    tabs() {
      const baseTabs = [
        { id: 'overview', label: 'Overview' },
        { id: 'affordability', label: 'Affordability' },
      ];

      if (this.goal?.assigned_module === 'investment') {
        baseTabs.push({ id: 'projections', label: 'Projections' });
      }

      baseTabs.push({ id: 'dependencies', label: 'Dependencies' });

      return baseTabs;
    },

    progressPercent() {
      if (!this.goal?.target_amount) return 0;
      const current = parseFloat(this.goal.current_amount) || 0;
      return Math.round((current / parseFloat(this.goal.target_amount)) * 100);
    },

    contributionStreak() {
      if (this.streak?.current_streak) return this.streak.current_streak;
      return this.goal?.contribution_streak || 0;
    },

    isContributionSufficient() {
      if (!this.goal?.monthly_contribution || !this.goal?.required_monthly_contribution) return true;
      return parseFloat(this.goal.monthly_contribution) >= parseFloat(this.goal.required_monthly_contribution);
    },

    isPropertyGoal() {
      return this.goal?.goal_type === 'property_purchase' || this.goal?.goal_type === 'home_deposit';
    },

    hasDependencies() {
      return this.dependsOn.length > 0 || this.dependedOnBy.length > 0;
    },

    progressBarClass() {
      if (this.progressPercent >= 100) return 'bg-spring-500';
      if (parseFloat(this.goal?.current_amount) <= 0) return 'bg-horizon-300';
      if (this.goal?.is_on_track) return 'bg-violet-500';
      return 'bg-violet-500';
    },

    moduleLabel() {
      const labels = {
        savings: 'Savings',
        investment: 'Investment',
        property: 'Property',
        retirement: 'Retirement',
      };
      return labels[this.goal?.assigned_module] || this.goal?.assigned_module || '';
    },

    moduleTagClass() {
      const classes = {
        savings: 'bg-emerald-100 text-emerald-700',
        investment: 'bg-violet-100 text-violet-700',
        property: 'bg-purple-100 text-purple-700',
        retirement: 'bg-violet-100 text-violet-700',
      };
      return classes[this.goal?.assigned_module] || 'bg-savannah-100 text-neutral-500';
    },

    priorityTagClass() {
      if (this.goal?.priority === 'critical') return 'bg-raspberry-100 text-raspberry-700';
      if (this.goal?.priority === 'high') return 'bg-violet-100 text-violet-700';
      return 'bg-savannah-100 text-neutral-500';
    },

    statusText() {
      if (this.goal?.status === 'completed') return 'Completed';
      if (this.goal?.status === 'paused') return 'Paused';
      if (this.progressPercent >= 100) return 'Goal Achieved';
      if (parseFloat(this.goal?.current_amount) <= 0) return 'Not Started';
      if (this.goal?.is_on_track) return 'On Track';
      return 'Behind Schedule';
    },

    statusBadgeClass() {
      if (this.goal?.status === 'completed' || this.progressPercent >= 100) return 'bg-spring-100 text-spring-800';
      if (this.goal?.status === 'paused') return 'bg-savannah-100 text-horizon-500';
      if (parseFloat(this.goal?.current_amount) <= 0) return 'bg-savannah-100 text-neutral-500';
      if (this.goal?.is_on_track) return 'bg-violet-100 text-violet-800';
      return 'bg-violet-100 text-violet-800';
    },

    statusDotClass() {
      if (this.goal?.status === 'completed' || this.progressPercent >= 100) return 'bg-spring-500';
      if (this.goal?.status === 'paused') return 'bg-savannah-1000';
      if (parseFloat(this.goal?.current_amount) <= 0) return 'bg-horizon-400';
      if (this.goal?.is_on_track) return 'bg-violet-500';
      return 'bg-violet-500';
    },

    timeRemaining() {
      const days = this.goal?.days_remaining;
      if (days === undefined || days === null) return '\u2014';
      if (days < 0) return 'Overdue';
      if (days === 0) return 'Today';
      if (days === 1) return '1 day';
      if (days < 30) return `${days} days`;
      if (days < 365) {
        const months = Math.floor(days / 30);
        return `${months} ${months === 1 ? 'month' : 'months'}`;
      }
      const years = Math.floor(days / 365);
      const months = Math.floor((days % 365) / 30);
      if (months === 0) return `${years} ${years === 1 ? 'year' : 'years'}`;
      return `${years}y ${months}m`;
    },
  },

  mounted() {
    this.loadGoalDetail();
  },

  methods: {
    async loadGoalDetail() {
      this.loading = true;
      this.error = null;

      try {
        const response = await goalsService.getGoal(this.goalId);
        if (response.success) {
          this.goalDetail = response.data.goal || response.data;
          this.milestones = response.data.milestones || null;
          this.streak = response.data.streak || null;
          this.affordability = response.data.affordability || null;
          this.projections = response.data.projections || null;
          this.loadDependencies();
        }
      } catch (err) {
        this.error = 'Failed to load goal details. Please try again.';
        logger.error('Failed to load goal detail:', err);
      } finally {
        this.loading = false;
      }
    },

    async loadDependencies() {
      this.dependenciesLoading = true;
      try {
        const response = await goalsService.getDependencies(this.goalId);
        if (response.success) {
          this.dependsOn = response.data.depends_on || [];
          this.dependedOnBy = response.data.depended_on_by || [];
        }
      } catch (err) {
        logger.error('Failed to load dependencies:', err);
      } finally {
        this.dependenciesLoading = false;
      }
    },

    formatDate(date) {
      return formatDateLong(date) || '\u2014';
    },

    formatGoalType(type) {
      if (!type) return '';
      return type
        .split('_')
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
    },

    formatMonthsToGoal(months) {
      if (!months || months <= 0) return '\u2014';
      if (months < 12) return `${months} months`;
      const years = Math.floor(months / 12);
      const remainingMonths = months % 12;
      if (remainingMonths === 0) return `${years} ${years === 1 ? 'year' : 'years'}`;
      return `${years}y ${remainingMonths}m`;
    },

    getGoalIcon,
  },
};
</script>
