<template>
  <transition name="celebrate-fade">
    <div v-if="visible" class="celebrate" role="dialog" aria-modal="true" :aria-label="`Level up: ${levelName}`" @click="dismiss">
      <span v-for="f in fireworks" :key="f.id" class="fw" :style="f.style">
        <span class="core"></span>
        <i v-for="n in 10" :key="n" :style="particleStyle(f, n)"></i>
      </span>
      <span v-for="c in confetti" :key="'c'+c.id" class="confetti" :style="c.style"></span>

      <div class="celebrate-body" @click.stop>
        <p class="kicker">LEVEL UP</p>
        <div class="ring-wrap">
          <svg width="140" height="140" class="ring">
            <circle cx="70" cy="70" r="62" class="ring-track" />
            <circle cx="70" cy="70" r="62" class="ring-fill" />
          </svg>
          <span class="lvl-num">{{ level }}</span>
        </div>
        <h2 class="title">Congratulations</h2>
        <p class="subtitle">You reached {{ levelName }}</p>
        <p v-if="nextActionsText" class="next">{{ nextActionsText }}</p>
        <button type="button" class="cta" @click.stop="dismiss">Keep going</button>
        <p class="hint">Tap anywhere to dismiss and continue</p>
      </div>
    </div>
  </transition>
</template>

<script>
export default {
  name: 'GamificationCelebration',
  props: {
    level: { type: Number, required: true },
    levelName: { type: String, required: true },
    nextActions: { type: Array, default: () => [] },
  },
  data() {
    return {
      visible: true,
      fireworks: this.buildFireworks(),
      confetti: this.buildConfetti(),
    };
  },
  computed: {
    nextActionsText() {
      if (!this.nextActions.length) return '';
      return `Next: ${this.nextActions.join(' and ')} to reach the next level.`;
    },
  },
  methods: {
    dismiss() {
      this.visible = false;
      this.$emit('dismiss');
    },
    buildFireworks() {
      const palette = ['#20B486', '#E83E6D', '#A78BFA', '#E6C9A8', '#6EE7B7'];
      const spots = [[24, 30], [72, 22], [50, 14], [16, 58], [84, 62]];
      return spots.map((s, i) => ({
        id: i,
        color: palette[i % palette.length],
        style: `left:${s[0]}%;top:${s[1]}%;animation-delay:${i * 0.4}s`,
      }));
    },
    particleStyle(f, n) {
      const angle = (360 / 10) * n;
      return `--r:${angle}deg;background:${f.color};animation-delay:${(f.id * 0.4) + 0.5}s`;
    },
    buildConfetti() {
      const palette = ['#E83E6D', '#20B486', '#E6C9A8', '#A78BFA', '#6EE7B7'];
      return Array.from({ length: 9 }, (_, i) => ({
        id: i,
        style: `left:${8 + i * 10}%;background:${palette[i % palette.length]};animation-delay:${(i * 0.3) % 2}s`,
      }));
    },
  },
};
</script>

<style scoped>
.celebrate { position: fixed; inset: 0; z-index: 60; display: flex; align-items: center; justify-content: center; padding: 28px; text-align: center; color: #fff; overflow: hidden; background: linear-gradient(165deg, #141a2e 0%, #1F2A44 35%, #2c2466 72%, #5854E6 100%); }
.kicker { letter-spacing: 3px; font-size: 13px; font-weight: 700; color: #A7F3D0; }
.ring-wrap { position: relative; width: 140px; height: 140px; margin: 14px auto 6px; display: flex; align-items: center; justify-content: center; animation: pop .7s cubic-bezier(.2,.9,.3,1.4) both; }
.ring { position: absolute; inset: 0; transform: rotate(-90deg); }
.ring-track { fill: none; stroke: rgba(255,255,255,.15); stroke-width: 9; }
.ring-fill { fill: none; stroke: #20B486; stroke-width: 9; stroke-linecap: round; stroke-dasharray: 389; stroke-dashoffset: 389; animation: ring 1.3s ease-out .4s both; }
.lvl-num { font-size: 50px; font-weight: 900; }
.title { font-size: 28px; font-weight: 900; margin-top: 12px; }
.subtitle { font-size: 20px; font-weight: 700; color: #6EE7B7; margin-top: 2px; }
.next { font-size: 14px; color: #CBD5E1; margin-top: 16px; line-height: 1.55; max-width: 260px; }
.cta { margin-top: 20px; padding: 15px 28px; border: none; border-radius: 14px; background: #E83E6D; color: #fff; font-weight: 700; font-size: 16px; cursor: pointer; }
.hint { font-size: 13px; color: #CBD5E1; margin-top: 12px; }
.confetti { position: absolute; top: -20px; width: 9px; height: 9px; border-radius: 2px; animation: fall 3s linear infinite; }
.fw { position: absolute; width: 6px; height: 6px; }
.fw .core { position: absolute; left: -6px; top: -6px; width: 18px; height: 18px; border-radius: 50%; background: radial-gradient(#fff, transparent 70%); animation: flash 1.6s ease-out infinite; }
.fw i { position: absolute; left: 0; top: 0; width: 6px; height: 6px; border-radius: 50%; animation: burst 1.6s ease-out infinite; }
@keyframes pop { 0% { transform: scale(.4); opacity: 0; } 60% { transform: scale(1.08); } 100% { transform: scale(1); opacity: 1; } }
@keyframes ring { to { stroke-dashoffset: 96; } }
@keyframes fall { 0% { transform: translateY(0) rotate(0); opacity: 0; } 10% { opacity: 1; } 100% { transform: translateY(640px) rotate(420deg); opacity: 0; } }
@keyframes flash { 0%,7% { transform: scale(0); opacity: 0; } 9% { transform: scale(1.6); opacity: 1; } 16% { transform: scale(0); opacity: 0; } 100% { opacity: 0; } }
@keyframes burst { 0% { transform: rotate(var(--r)) translateY(0); opacity: 0; } 8% { opacity: 1; } 100% { transform: rotate(var(--r)) translateY(-58px); opacity: 0; } }
.celebrate-fade-enter-active, .celebrate-fade-leave-active { transition: opacity .3s ease; }
.celebrate-fade-enter-from, .celebrate-fade-leave-to { opacity: 0; }
</style>
