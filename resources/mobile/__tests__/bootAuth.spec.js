import { describe, expect, it, vi } from 'vitest';
import { rotateBootToken } from '../bootAuth.js';

describe('rotateBootToken', () => {
  it('waits for a delayed successful rotation before exposing the token to the app', async () => {
    vi.useFakeTimers();
    const setToken = vi.fn();
    const refresh = vi.fn(() => new Promise((resolve) => {
      setTimeout(() => resolve({
        ok: true,
        status: 200,
        data: { data: { token: 'rotated-token' } },
      }), 4_500);
    }));

    const rotation = rotateBootToken({
      token: 'original-token',
      refresh,
      setToken,
      clearToken: vi.fn(),
      timeoutMs: 10_000,
    });

    await vi.advanceTimersByTimeAsync(2_000);
    expect(setToken).not.toHaveBeenCalled();

    await vi.advanceTimersByTimeAsync(2_500);
    await expect(rotation).resolves.toBe('rotated');
    expect(setToken).toHaveBeenCalledWith('rotated-token');
    vi.useRealTimers();
  });

  it('clears the bearer if rotation times out because the old token may still be revoked', async () => {
    vi.useFakeTimers();
    const clearToken = vi.fn();
    const rotation = rotateBootToken({
      token: 'original-token',
      refresh: vi.fn(() => new Promise(() => {})),
      setToken: vi.fn(),
      clearToken,
      timeoutMs: 10_000,
    });

    await vi.advanceTimersByTimeAsync(10_000);
    await expect(rotation).resolves.toBe('timed-out');
    expect(clearToken).toHaveBeenCalledOnce();
    vi.useRealTimers();
  });
});
