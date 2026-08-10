import { apiPost } from '../api.js';
import { store } from '../store.js';

function topWindow() {
  return window.top || window;
}

export async function issueWebHandoff(destination, target = topWindow()) {
  const { ok, data } = await apiPost(
    '/api/v1/mobile/web-handoffs',
    { destination },
    store.token,
  );
  const url = data?.data?.url;
  if (!ok || !url) throw new Error('handoff_unavailable');

  target.location.href = url;
}

export function publicWebUrl(path, basePath = import.meta.env.VITE_ROUTER_BASE || '/') {
  const base = basePath.replace(/\/?$/, '/');
  return `${base}${String(path).replace(/^\//, '')}`;
}

export function openPublicWebPath(
  path,
  target = topWindow(),
  basePath = import.meta.env.VITE_ROUTER_BASE || '/',
) {
  target.location.href = publicWebUrl(path, basePath);
}
