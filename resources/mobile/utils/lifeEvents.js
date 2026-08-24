/**
 * Life event totals — the one home shared by the web SPA and the /m pathway.
 *
 * W-0207: four surfaces each summed life events their own way and not one of
 * them excluded events that had already happened. A confirmed inheritance from
 * 2020 was presented as money still to come on every single one, including
 * under a heading reading "Future occurrences".
 *
 * The predicate itself lives on the model — LifeEvent::hasOccurred(), served on
 * every event row as `has_occurred` — so nothing here re-derives it from dates.
 * This module is only the arithmetic on top, kept in one place so the web events
 * tab and the /m goals screen cannot drift apart again.
 *
 * Lives under resources/mobile/utils/ because that is where the one existing
 * shared helper lives (fynText.js, imported by resources/js/components/Fyn/
 * FynQuickReplies.vue). One convention, not a second one.
 */

/** Shown wherever a recorded event's date has already passed. */
export const OCCURRED_LABEL = 'Already happened';

export function hasOccurred(event) {
  return Boolean(event && event.has_occurred);
}

export function upcomingEvents(events) {
  return (events || []).filter((event) => !hasOccurred(event));
}

/**
 * Expected income, expected expenditure and the net of the two, counting only
 * what is still to come. Mirrors LifeEventService::summariseUpcoming().
 */
export function summariseUpcoming(events) {
  const upcoming = upcomingEvents(events);
  const byImpact = (impact) => upcoming.filter((event) => event.impact_type === impact);

  const income = byImpact('income');
  const expense = byImpact('expense');
  const total = (list) => list.reduce((sum, event) => sum + (Number(event.amount) || 0), 0);

  const expectedIncome = total(income);
  const expectedExpense = total(expense);

  return {
    expected_income: expectedIncome,
    expected_expense: expectedExpense,
    net_impact: expectedIncome - expectedExpense,
    income_count: income.length,
    expense_count: expense.length,
  };
}
