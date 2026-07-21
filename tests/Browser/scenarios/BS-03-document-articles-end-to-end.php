<?php

declare(strict_types=1);

/**
 * BS-03 — Document Articles end-to-end.
 *
 * GREEN when:
 *   1. Admin logs in (/login → email code → /dashboard).
 *   2. Admin clicks "Documents" in the sidebar → URL becomes /admin/documents.
 *   3. Page shows "Drop a Word document here" copy.
 *   4. Drag tests/fixtures/documents/sample-with-images-and-tables.docx onto the drop zone.
 *   5. URL changes to /admin/documents/{id}/edit; form fields populated:
 *        - title input value contains "Rich Sample Title"
 *        - author byline input value contains "Sam Author"
 *        - meta description contains "A rich fixture"
 *        - body canvas shows "Big Heading" and a 2-column table with "Left" and "Right".
 *   6. Click "Publish" → success message "Published."
 *   7. Open new tab to /insights/rich-sample-title.
 *   8. Page renders inside PublicLayout — top nav, hero header, and footer all present.
 *   9. Page <title> contains "Rich Sample Title".
 *  10. <meta name="description"> present with the description text.
 *  11. og:image meta is set.
 *  12. JSON-LD <script type="application/ld+json"> present and parses to an Article object.
 *  13. Page body contains "Big Heading" and the 2-column table inside .article-html-body.
 *  14. The article surfaces in the /insights hub list (page 1, no category filter).
 *  15. view-source shows NO <script> outside the SPA bundle and JSON-LD blocks.
 *
 * Drop the malicious fixture in a second pass (sample-with-malicious-html.docx); after publish,
 * confirm "Hello" is rendered but "alert(1)" is NOT in the served HTML.
 */

return [
    'fixture_dir' => __DIR__.'/../../fixtures/documents',
    'admin_credentials' => ['email' => 'chris@fynla.org', 'password_env' => 'BROWSER_TEST_ADMIN_PASSWORD'],
    'happy_path_fixture' => 'sample-with-images-and-tables.docx',
    'security_fixture' => 'sample-with-malicious-html.docx',
    'expected_slug' => 'rich-sample-title',
];
