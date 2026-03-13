<template>
  <div class="px-4 pt-4 pb-6">
    <MobileDetailSkeleton v-if="loading" :rows="3" />

    <template v-else-if="hasData">
      <MobileHeroCard title="Goals & Life Events" :value="`${completedGoals.length} of ${allGoals.length}`" subtitle="Goals completed">
        <p v-if="totalCurrentAmount > 0" class="text-xs text-neutral-400 mt-1">{{ formatCurrency(totalCurrentAmount) }} saved</p>
      </MobileHeroCard>
      <MobileFynCard :summary="fynSummary" />

      <!-- Active Goals -->
      <MobileAccordionSection
        title="Active goals"
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

    <MobileEmptyState v-else title="No goals yet" subtitle="Your financial goals and life events will appear here" />
  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import MobileAccordionSection from '@/mobile/components/MobileAccordionSection.vue';
import MobileGoalCard from '@/mobile/goals/MobileGoalCard.vue';
import MobileHeroCard from '@/mobile/components/MobileHeroCard.vue';
import MobileFynCard from '@/mobile/components/MobileFynCard.vue';
import MobileDetailSkeleton from '@/mobile/components/MobileDetailSkeleton.vue';
import MobileEmptyState from '@/mobile/components/MobileEmptyState.vue';

export default {
  name: 'GoalsDetail',

  components: { MobileAccordionSection, MobileGoalCard, MobileHeroCard, MobileFynCard, MobileDetailSkeleton, MobileEmptyState },

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
