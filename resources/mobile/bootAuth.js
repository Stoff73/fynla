const TIMED_OUT = Symbol('timed-out');

/**
 * Resolve boot-time bearer rotation before data views start loading.
 *
 * A timed-out refresh is not safe to ignore: the server may still complete
 * the request and revoke the bearer the client is about to use. Clear it so
 * the app mounts signed out instead of issuing a burst of doomed API calls.
 */
export async function rotateBootToken({
  token,
  refresh,
  setToken,
  clearToken,
  timeoutMs = 10_000,
}) {
  if (!token) return 'no-token';

  let timeout;
  try {
    const result = await Promise.race([
      refresh(token),
      new Promise((resolve) => {
        timeout = setTimeout(() => resolve(TIMED_OUT), timeoutMs);
      }),
    ]);

    if (result === TIMED_OUT) {
      clearToken();
      return 'timed-out';
    }

    const rotatedToken = result?.data?.data?.token;
    if (result?.ok && typeof rotatedToken === 'string' && rotatedToken.length > 0) {
      setToken(rotatedToken);
      return 'rotated';
    }

    if (result?.status === 401) {
      clearToken();
      return 'invalid';
    }

    return 'unchanged';
  } catch {
    return 'failed';
  } finally {
    clearTimeout(timeout);
  }
}
