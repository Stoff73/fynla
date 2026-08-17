const DESTINATION_PARAMETER_KEYS = new Set([
  'account_id',
  'pension_id',
  'pension_type',
  'policy_id',
  'policy_type',
  'goal_id',
  'property_id',
  'mortgage_id',
  'liability_id',
  'income_owner',
  'income_source',
]);

function identifierParameters(params = {}) {
  return Object.fromEntries(
    Object.entries(params).filter(([key]) => DESTINATION_PARAMETER_KEYS.has(key)),
  );
}

export function buildContextualConversationRequest({
  action,
  resourceType,
  resourceId = null,
  currentDestination,
  origin,
}) {
  const request = {
    action,
    resource_type: resourceType,
    current_destination: {
      screen: currentDestination.screen,
      params: identifierParameters(currentDestination.params),
      fallback: currentDestination.fallback,
    },
    origin: {
      kind: origin.kind,
      recommendation_id: origin.recommendationId ?? null,
    },
  };

  if (resourceId !== null && resourceId !== undefined) {
    request.resource_id = Number(resourceId);
  }

  return request;
}
