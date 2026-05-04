<?php

declare(strict_types=1);

/**
 * BS — Document Articles end-to-end.
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
 *   7. Open new tab to /articles/rich-sample-title.
 *   8. Page <title> contains "Rich Sample Title — Fynla".
 *   9. <meta name="description"> present with the description text.
 *  10. og:image meta is set.
 *  11. JSON-LD <script type="application/ld+json"> present and parses to an Article object.
 *  12. Page body contains "Big Heading" and the 2-column table.
 *  13. view-source shows NO <script> outside the JSON-LD block.
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
