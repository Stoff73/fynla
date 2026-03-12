<template>
  <div class="px-4 pt-4 pb-6">
    <div v-if="loading" class="space-y-3">
      <div class="bg-white rounded-xl p-6 animate-pulse">
        <div class="w-24 h-8 bg-savannah-100 rounded mx-auto"></div>
      </div>
      <div v-for="n in 3" :key="n" class="bg-white rounded-xl p-4 animate-pulse">
        <div class="w-40 h-4 bg-savannah-100 rounded"></div>
      </div>
    </div>

    <template v-else-if="hasData">
      <!-- Hero -->
      <div class="bg-white rounded-xl border border-light-gray p-6 text-center mb-4">
        <span class="text-3xl block mb-2">{{'🎯'}}</span>
        <h2 class="text-lg font-bold text-horizon-500">Goals & Life Events</h2>
        <p class="text-2xl font-black text-horizon-500 mt-3">{{ completedGoals.length }} of {{ allGoals.length }}</p>
        <p class="text-xs text-neutral-500 mt-1">Goals completed</p>
        <p v-if="totalCurrentAmount > 0" class="text-xs text-neutral-400 mt-1">{{ formatCurrency(totalCurrentAmount) }} saved</p>
      </div>

      <!-- Fyn -->
      <div class="bg-horizon-500 rounded-xl p-4 flex items-start gap-3 mb-4">
        <img src="/images/logos/favicon.png" alt="Fyn" class="w-8 h-8 rounded-full flex-shrink-0" />
        <p class="text-white text-sm leading-relaxed">{{ fynSummary }}</p>
      </div>

      <!-- Active Goals -->
      <MobileAccordionSection
        title="Active goals"
        icon="🏃"
        :badge="activeGoals.length || null"
        :default-open="true"
        class="mb-3"
      >
        <template v-if="activeGoals.length">
          <div class="divide-y divide-light-gray">
            <MobileGoalCard
              v-for="goal in activeGoals"
              :key="goal.id"
              :goal="goal"
              @click="navigateToGoal(goal.id)"
            />
          </div>
        </template>
        <p v-else class="px-4 py-6 text-sm text-neutral-500 text-center">No active goals</p>
      </MobileAccordionSection>

      <!-- Completed Goals -->
      <MobileAccordionSection
        v-if="completedGoals.length"
        title="Completed goals"
        icon="✅"
        :badge="completedGoals.length"
        class="mb-3"
      >
        <div class="divide-y divide-light-gray">
          <MobileGoalCard
            v-for="goal in completedGoals"
            :key="goal.id"
            :goal="goal"
            @click="navigateToGoal(goal.id)"
          />
        </div>
      </MobileAccordionSection>

      <!-- Life Events -->
      <MobileAccordionSection
        title="Life events"
        icon="📅"
        :badge="lifeEvents.length || null"
        class="mb-3"
      >
        <template v-if="lifeEvents.length">
          <div class="divide-y divide-light-gray">
            <div v-for="event in lifeEvents" :key="event.id" class="px-4 py-3">
              <div class="flex items-start justify-between">
                <div class="flex-1 min-w-0">
                  <h4 class="text-sm font-bold text-horizon-500 truncate">{{ event.name || event.type }}</h4>
                  <p v-if="event.date" class="text-xs text-neutral-500 mt-0.5">{{ formatEventDate(event.date) }}</p>
                  <p v-if="event.description" class="text-xs text-neutral-400 mt-0.5 line-clamp-2">{{ event.description }}</p>
                </div>
                <span
                  v-if="event.priority"
                  class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase ml-2"
                  :class="priorityClass(event.priority)"
                >
                  {{ event.priority }}
                </span>
              </div>
            </div>
          </div>
        </template>
        <p v-else class="px-4 py-6 text-sm text-neutral-500 text-center">No life events recorded</p>
      </MobileAccordionSection>
    </template>

    <div v-else class="text-center py-16">
      <span class="text-4xl block mb-3">{{'🎯'}}</span>
      <h3 class="text-base font-bold text-horizon-500 mb-1">No goals yet</h3>
      <p class="text-sm text-neutral-500">Your financial goals and life events will appear here</p>
    </div>
  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import MobileAccordionSection from '@/mobile/components/MobileAccordionSection.vue';
import MobileGoalCard from '@/mobile/goals/MobileGoalCard.vue';

export default {
  name: 'GoalsDetail',

  components: { MobileAccordionSection, MobileGoalCard },

  mixins: [currencyMixin],

  data() {
    return { loading: false };
  },

  computed: {
    ...mapGetters('goals', [
      'activeGoals',
      'completedGoals',
      'totalCurrentAmount',
      'totalTargetAmount',
      'hasGoals',
    ]),

    allGoals() {
      return [...(this.activeGoals || []), ...(this.completedGoals || [])];
    },

    lifeEvents() {
      return this.$store.state.goals.lifeEvents || [];
    },

    hasData() {
      return this.hasGoals || this.lifeEvents.length > 0;
    },

    fynSummary() {
      if (this.completedGoals?.length > 0) {
        return `Well done — you have completed ${this.completedGoals.length} of your ${this.allGoals.length} financial goals.`;
      }
      if (this.allGoals.length > 0) {
        return `You have ${this.allGoals.length} financial goal${this.allGoals.length > 1 ? 's' : ''} in progress.`;
      }
      return 'Setting financial goals gives direction and purpose to your planning.';
    },
  },

  async created() {
    this.loading = true;
    try {
      await Promise.all([
        this.$store.dispatch('goals/fetchGoals'),
        this.$store.dispatch('goals/fetchLifeEvents').catch(() => {}),
      ]);
    } catch {
      // Data unavailable
    } finally {
      this.loading = false;
    }
  },

  methods: {
    navigateToGoal(goalId) {
      this.$router.push(`/m/goals/${goalId}`);
    },

    formatEventDate(dateStr) {
      if (!dateStr) return '';
      const d = new Date(dateStr);
      return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
    },

    priorityClass(priority) {
      if (priority === 'high') return 'bg-raspberry-50 text-raspberry-500';
      if (priority === 'medium') return 'bg-violet-50 text-violet-500';
      return 'bg-savannah-100 text-horizon-500';
    },
  },
};
</script>
