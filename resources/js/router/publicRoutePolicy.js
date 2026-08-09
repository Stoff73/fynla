const authenticatedPublicUtilityPaths = new Set([
  '/help',
  '/privacy',
  '/terms',
]);

export function isAuthenticatedPublicUtilityPath(path) {
  return authenticatedPublicUtilityPaths.has(path);
}
