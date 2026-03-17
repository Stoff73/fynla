<template>
  <div class="bg-white rounded-lg border border-light-gray shadow-sm p-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold text-horizon-500">Life Timeline</h3>
      <div class="flex items-center gap-3">
        <button
          class="text-xs font-medium text-neutral-500 hover:text-horizon-500 transition-colors"
          @click="$router.push('/goals?view=projection')"
        >
          What if &rarr;
        </button>
        <button
          class="text-sm font-semibold text-raspberry-500 hover:text-raspberry-600 transition-colors"
          @click="$router.push('/goals?addEvent=true')"
        >
          + Add event
        </button>
      </div>
    </div>

    <!-- Timeline -->
    <div v-if="timelineEvents.length > 0" class="relative">
      <!-- Vertical timeline line -->
      <div class="absolute left-3 top-3 bottom-3 w-px bg-light-gray"></div>

      <div class="space-y-4">
        <div
          v-for="(event, index) in timelineEvents"
          :key="event.id || index"
          class="relative flex items-start gap-4 pl-0"
        >
          <!-- Timeline dot -->
          <div class="relative z-10 flex-shrink-0">
            <!-- Past event: spring-500 solid dot -->
            <div
              v-if="event.timeState === 'past'"
              class="w-6 h-6 rounded-full bg-spring-500 flex items-center justify-center"
            >
              <svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
              </svg>
            </div>

            <!-- Imminent/current event: stage-colour dot with glow -->
            <div
              v-else-if="event.timeState === 'imminent'"
              class="w-6 h-6 rounded-full flex items-center justify-center"
              :class="'bg-' + stageColour + '-500'"
              :style="{ boxShadow: '0 0 0 4px ' + stageColourRgba }"
            >
              <div class="w-2 h-2 rounded-full bg-white"></div>
            </div>

            <!-- Future event: light-gray dot -->
            <div
              v-else
              class="w-6 h-6 rounded-full bg-light-gray border-2 border-white flex items-center justify-center"
            >
              <div class="w-2 h-2 rounded-full bg-neutral-500"></div>
            </div>
          </div>

          <!-- Event content -->
          <div class="flex-1 min-w-0 pt-0.5">
            <div class="flex items-center justify-between">
              <p
                class="text-sm font-medium truncate mr-2"
                :class="event.timeState === 'past' ? 'text-neutral-500' : 'text-horizon-500'"
              >
                {{ event.event_name || event.title || event.name }}
              </p>
              <span class="text-xs text-neutral-500 whitespace-nowrap">
                {{ formatEventDate(event) }}
              </span>
            </div>

            <!-- Imminent events get a call-to-action link -->
            <button
              v-if="event.timeState === 'imminent'"
              class="text-xs font-medium mt-1 hover:underline"
              :class="'text-' + stageColour + '-500'"
              @click="$router.push('/goals?event=' + event.id)"
            >
              See how this affects your plan &rarr;
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else class="text-center py-4">
      <div class="mx-auto w-12 h-12 rounded-full bg-violet-100 flex items-center justify-center mb-3">
        <svg class="w-6 h-6 text-violet-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
      </div>
      <h4 class="text-sm font-semibold text-horizon-500 mb-1">No Life Events Yet</h4>
      <p class="text-xs text-neutral-500">
        Add life events to see how they affect your financial plan
      </p>
    </div>
  </div>
</template>

<script>
import { mapGetters, mapState } from 'vuex';

export default {
  name: 'LifeTimelineCard',

  computed: {
    ...mapState('goals', ['lifeEvents']),
    ...mapGetters('goals', ['activeLifeEvents']),
    ...mapGetters('lifeStage', { stageColour: 'stageColour' }),

    stageColourRgba() {
      // Map stage colours to RGBA for the glow effect
      const colourMap = {
        violet: 'rgba(88, 84, 230, 0.25)',
        spring: 'rgba(32, 180, 134, 0.25)',
        raspberry: 'rgba(232, 62, 109, 0.25)',
        'light-blue': 'rgba(108, 131, 188, 0.25)',
        horizon: 'rgba(31, 42, 68, 0.25)',
      };
      return colourMap[this.stageColour] || 'rgba(232, 62, 109, 0.25)';
    },

    timelineEvents() {
      const events = [...(this.lifeEvents || [])];
      const now = new Date();
      const threeMonthsFromNow = new Date(now);
      threeMonthsFromNow.setMonth(threeMonthsFromNow.getMonth() + 3);

      return events
        .map(event => {
          const eventDate = event.expected_date || event.event_date || event.date;
          const date = eventDate ? new Date(eventDate) : null;
          let timeState = 'future';

          if (date) {
            if (date < now) {
              timeState = 'past';
            } else if (date <= threeMonthsFromNow) {
              timeState = 'imminent';
            }
          }

          // If the event has a status indicating completion, mark as past
          if (event.status === 'completed' || event.status === 'occurred') {
            timeState = 'past';
          }

          return { ...event, timeState };
        })
        .sort((a, b) => {
          const dateA = new Date(a.expected_date || a.event_date || a.date || 0);
          const dateB = new Date(b.expected_date || b.event_date || b.date || 0);
          return dateA - dateB;
        })
        .slice(0, 6); // Show at most 6 events
    },
  },

  methods: {
    formatEventDate(event) {
      const dateStr = event.expected_date || event.event_date || event.date;
      if (!dateStr) return '';
      const date = new Date(dateStr);
      const now = new Date();

      if (event.timeState === 'past') {
        // Show relative time for past events
        const diffDays = Math.floor((now - date) / (1000 * 60 * 60 * 24));
        if (diffDays < 30) return `${diffDays} days ago`;
        if (diffDays < 365) {
          const months = Math.round(diffDays / 30);
          return `${months} month${months !== 1 ? 's' : ''} ago`;
        }
        const years = Math.round(diffDays / 365);
        return `${years} year${years !== 1 ? 's' : ''} ago`;
      }

      // Future events: show month/year
      const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
      return `${months[date.getMonth()]} ${date.getFullYear()}`;
    },
  },
};
</script>
