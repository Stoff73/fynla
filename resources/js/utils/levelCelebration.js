/**
 * The level-up celebration rule, in one place.
 *
 * The full-screen takeover was removed on 2026-09-17: level-ups are banked
 * server-side as a range and spent only on the dashboard hero circle, where
 * the number climbs one level at a time with a confetti burst per increment.
 *
 * `/m` and native iOS each carry their own copy of this rule — separate Vite
 * bundles cannot import each other and native is Swift — so the three are
 * kept honest by the same test vectors, not by a shared import. If you change
 * behaviour here, change it in `resources/mobile/navigation/levelCelebration.js`
 * and `ios-native/Fynla/Features/Gamification/LevelCelebrationSequence.swift`
 * in the same commit.
 */

/** Pause between level steps, in milliseconds. Three levels lands under 3s. */
export const STEP_MS = 900;

/**
 * Every level owed, ascending. The ladder starts at 1, so a missing `from`
 * means "never celebrated anything".
 *
 * @param {number|null|undefined} from
 * @param {number|null|undefined} to
 * @returns {number[]}
 */
export function levelsOwed(from, to) {
  const start = Number.isFinite(from) ? Number(from) : 1;
  const end = Number.isFinite(to) ? Number(to) : 1;
  if (end <= start) return [];
  return Array.from({ length: end - start }, (_, i) => start + i + 1);
}

/**
 * The dashboard is what the user is actually looking at: it is the active
 * screen, Fyn is not over it, and the tab is in front. A backgrounded tab
 * matters — the climb would otherwise be spent and acked unseen.
 */
export function dashboardIsBeingViewed({ onDashboard, fynOpen, hidden }) {
  return Boolean(onDashboard) && !fynOpen && !hidden;
}

/** The viewer's operating-system "reduce motion" accessibility setting. */
export function prefersReducedMotion() {
  try {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  } catch {
    return false;
  }
}

const defaultWait = (ms) => new Promise((resolve) => { setTimeout(resolve, ms); });

/**
 * Walk the owed levels, calling `onLevel(level, { burst })` at each.
 *
 * Under reduced motion the number simply lands on the final level with no
 * burst — but the sequence STILL resolves with a level to ack, or the user
 * accumulates a queue they can never spend.
 *
 * @returns {Promise<number|null>} the level to acknowledge, or null if nothing was owed
 */
export async function runLevelSequence({
  from,
  to,
  onLevel,
  reducedMotion = false,
  stepMs = STEP_MS,
  wait = defaultWait,
}) {
  const levels = levelsOwed(from, to);
  if (levels.length === 0) return null;

  const last = levels[levels.length - 1];

  if (reducedMotion) {
    onLevel(last, { burst: false });
    return last;
  }

  for (const level of levels) {
    onLevel(level, { burst: true });
    // eslint-disable-next-line no-await-in-loop -- the pause between levels IS the animation
    await wait(stepMs);
  }

  return last;
}
