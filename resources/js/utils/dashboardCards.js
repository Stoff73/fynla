/**
 * Reads one dashboard card as the server sent it.
 *
 * Every figure, ring and caption on the five dashboard cards is built once on
 * the server (`App\Services\Mobile\DashboardCards`, the `cards` block of
 * `GET /api/v1/mobile/dashboard`) and rendered as sent by web, `/m` and iOS
 * (CSJ 2026-10-01: one figure, every surface). This only maps that block onto
 * the card fields both dashboards render; it never computes a figure. Labels,
 * routes and colours stay in each component.
 *
 * @param {object} payload - the dashboard endpoint's data block.
 * @param {string} key - net_worth, protection, savings, retirement or investment.
 * @returns {{value: number, valueIsIncome: boolean, caption: string, viz: string,
 *   progress: number, vizNum: string, vizCap: string, barFill: number,
 *   barValue: string, barUnit: string}}
 */
export function dashboardCard(payload, key) {
  const card = ((payload || {}).cards || {})[key] || {};
  const visual = card.visual || {};

  return {
    value: Number(card.value) || 0,
    valueIsIncome: card.value_is_income === true,
    caption: card.caption || '',
    viz: visual.type || 'bar',
    progress: Number(visual.progress) || 0,
    vizNum: visual.number || '',
    vizCap: visual.label || '',
    barFill: Number(visual.progress) || 0,
    barValue: visual.number || '',
    barUnit: visual.label || '',
  };
}
