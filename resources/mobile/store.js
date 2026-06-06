import { reactive } from 'vue';

const KEY = 'm_scaffold_token';

export const store = reactive({
  token: localStorage.getItem(KEY) || null,
  user: null,
  setToken(t) {
    this.token = t;
    if (t) localStorage.setItem(KEY, t);
    else localStorage.removeItem(KEY);
  },
  logout() {
    this.setToken(null);
    this.user = null;
  },
});
