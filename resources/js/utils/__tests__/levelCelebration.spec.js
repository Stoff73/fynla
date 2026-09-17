import { describe, it, expect, vi } from 'vitest';
import {
  levelsOwed,
  dashboardIsBeingViewed,
  runLevelSequence,
  STEP_MS,
} from '@/utils/levelCelebration';

describe('levelsOwed', () => {
  it('owes nothing when the range is empty', () => {
    expect(levelsOwed(4, 4)).toEqual([]);
  });

  it('owes every level above the one last celebrated', () => {
    expect(levelsOwed(3, 6)).toEqual([4, 5, 6]);
  });

  it('owes a single level for a single crossing', () => {
    expect(levelsOwed(1, 2)).toEqual([2]);
  });

  it('owes nothing when the server sends a range that runs backwards', () => {
    expect(levelsOwed(6, 3)).toEqual([]);
  });

  it('treats missing values as the bottom of the ladder', () => {
    expect(levelsOwed(undefined, 2)).toEqual([2]);
    expect(levelsOwed(null, null)).toEqual([]);
  });
});

describe('dashboardIsBeingViewed', () => {
  it('is true on the dashboard with Fyn closed and the tab visible', () => {
    expect(dashboardIsBeingViewed({ onDashboard: true, fynOpen: false, hidden: false })).toBe(true);
  });

  it('is false while Fyn is over the dashboard', () => {
    expect(dashboardIsBeingViewed({ onDashboard: true, fynOpen: true, hidden: false })).toBe(false);
  });

  it('is false away from the dashboard', () => {
    expect(dashboardIsBeingViewed({ onDashboard: false, fynOpen: false, hidden: false })).toBe(false);
  });

  it('is false when the tab is backgrounded, so the climb is never spent unseen', () => {
    expect(dashboardIsBeingViewed({ onDashboard: true, fynOpen: false, hidden: true })).toBe(false);
  });
});

describe('runLevelSequence', () => {
  it('does nothing and acks nothing when no level is owed', async () => {
    const onLevel = vi.fn();
    const acked = await runLevelSequence({ from: 5, to: 5, onLevel, wait: () => Promise.resolve() });

    expect(onLevel).not.toHaveBeenCalled();
    expect(acked).toBeNull();
  });

  it('steps through every owed level in order, bursting at each', async () => {
    const onLevel = vi.fn();
    const acked = await runLevelSequence({ from: 3, to: 6, onLevel, wait: () => Promise.resolve() });

    expect(onLevel.mock.calls.map((c) => c[0])).toEqual([4, 5, 6]);
    expect(onLevel.mock.calls.every((c) => c[1].burst === true)).toBe(true);
    expect(acked).toBe(6);
  });

  it('waits between levels so the climb is legible', async () => {
    const wait = vi.fn(() => Promise.resolve());
    await runLevelSequence({ from: 3, to: 5, onLevel: () => {}, wait });

    expect(wait).toHaveBeenCalledTimes(2);
    expect(wait).toHaveBeenCalledWith(STEP_MS);
  });

  it('under reduced motion lands on the final level with no burst, and still acks', async () => {
    const onLevel = vi.fn();
    const wait = vi.fn(() => Promise.resolve());
    const acked = await runLevelSequence({
      from: 3, to: 6, onLevel, reducedMotion: true, wait,
    });

    expect(onLevel).toHaveBeenCalledTimes(1);
    expect(onLevel).toHaveBeenCalledWith(6, { burst: false });
    expect(wait).not.toHaveBeenCalled();
    expect(acked).toBe(6);
  });
});
