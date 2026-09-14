/**
 * Refetch when Fyn sends the user to the screen they are already on.
 *
 * AiChatPanel.handleNavigation fires `fyn-screen-refresh` when a Fyn navigation
 * resolves to the current route — a verify edit that just landed, the next
 * Pension Check section on the same page, "No thanks" from the dashboard —
 * because nothing remounts and the page would keep showing the pre-write
 * figures. The /m screens watch store.screenRefreshTick for the same reason.
 * A page opts in by mixing this in and defining fynScreenRefresh().
 */
export const fynScreenRefreshMixin = {
  mounted() {
    this._onFynScreenRefresh = () => this.fynScreenRefresh();
    window.addEventListener('fyn-screen-refresh', this._onFynScreenRefresh);
  },
  beforeUnmount() {
    window.removeEventListener('fyn-screen-refresh', this._onFynScreenRefresh);
  },
};
