# Document Articles CMS Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a sandboxed `Documents` admin module that turns a dropped `.docx` into a fully-formed, SEO-complete article published at `/articles/{slug}`, isolated from the existing Insights CMS.

**Architecture:** Browser-side `mammoth.js` converts the docx to HTML and emits image Blobs with placeholder markers. The browser POSTs HTML + Blobs + metadata to a Laravel admin endpoint. The server re-extracts metadata server-side from `docProps/core.xml` for defence-in-depth, sanitises HTML through HTMLPurifier, persists images to `storage/app/public/document-articles/{id}/`, rewrites placeholders to real `<img src>`, and stores everything in a brand-new `document_articles` table. An admin-side Tiptap canvas with table/image extensions edits the body. Publishing flips status; the public Blade view renders full SEO chrome (meta description, OpenGraph, Twitter card, JSON-LD `Article`).

**Tech Stack:** Laravel 10, Vue 3, Tiptap 3.22, mammoth.js, JSZip, mews/purifier, Pest, Playwright. **No** changes to existing Insights CMS, `insight_articles`, or any shared component.

**Spec:** `May/May1Updates/2026-05-01-document-articles-cms-spec.md`

---

## File Structure

### New backend files
| Path | Responsibility |
|---|---|
| `app/Models/DocumentArticle.php` | Eloquent model |
| `app/Http/Controllers/Api/Admin/DocumentArticleController.php` | Admin CRUD + publish/unpublish |
| `app/Http/Controllers/PublicDocumentArticleController.php` | Public `GET /articles/{slug}` |
| `app/Http/Requests/Admin/DocumentArticleImportRequest.php` | Validates docx + html + image blobs upload |
| `app/Http/Requests/Admin/DocumentArticleUpdateRequest.php` | Validates edit payload |
| `app/Services/Documents/DocumentArticleImporter.php` | Orchestrates import (metadata → sanitise → DB → images → rewrite) |
| `app/Services/Documents/DocxMetadataExtractor.php` | Reads `docProps/core.xml` from a docx |
| `app/Services/Documents/HTMLBodySanitiser.php` | mews/purifier wrapper with module's allow-list |
| `database/migrations/2026_05_01_120000_create_document_articles_table.php` | Schema |
| `database/factories/DocumentArticleFactory.php` | Test factory |
| `resources/views/articles/show.blade.php` | Public render with full SEO chrome |
| `config/purifier.php` | mews/purifier config — add `document_article` profile |

### New frontend files
| Path | Responsibility |
|---|---|
| `resources/js/views/Admin/Documents/DocumentListPage.vue` | Sidebar landing — drop zone + list |
| `resources/js/views/Admin/Documents/DocumentEditor.vue` | Two-pane editor (form + Tiptap canvas) |
| `resources/js/components/Admin/Documents/DropZone.vue` | Drag-drop + mammoth.js + JSZip + multipart upload |
| `resources/js/components/Admin/Documents/CoverImagePicker.vue` | Pick from extracted images or upload new |
| `resources/js/store/modules/documentArticles.js` | Vuex namespace |
| `resources/js/services/documentArticleService.js` | API wrapper |

### Existing files modified
| Path | Change |
|---|---|
| `routes/api.php` | Add `admin/documents` route block |
| `routes/web.php` | Add `GET /articles/{slug}` route |
| `resources/js/views/Admin/AdminPanel.vue` | Add `Documents` sidebar entry |
| `resources/js/router/index.js` | Add `/admin/documents` and `/admin/documents/:id/edit` routes |
| `resources/js/store/index.js` | Register `documentArticles` Vuex module |
| `package.json` | Add npm deps |
| `composer.json` | Add `mews/purifier` |

### Test files
| Path | Responsibility |
|---|---|
| `tests/Unit/Services/Documents/DocxMetadataExtractorTest.php` | Metadata extraction |
| `tests/Unit/Services/Documents/HTMLBodySanitiserTest.php` | Allow-list / strip behaviour |
| `tests/Unit/Services/Documents/DocumentArticleSlugTest.php` | Slug collision logic |
| `tests/Feature/Documents/DocumentArticleImportTest.php` | End-to-end import pipeline |
| `tests/Feature/Documents/DocumentArticleControllerTest.php` | CRUD + publish/unpublish + permissions |
| `tests/Feature/Documents/PublicDocumentArticleTest.php` | Public route, draft 404, signed preview |
| `tests/fixtures/documents/sample-with-images-and-tables.docx` | Fidelity fixture |
| `tests/fixtures/documents/sample-minimal.docx` | Title-only fixture |
| `tests/fixtures/documents/sample-with-malicious-html.docx` | Sanitiser fixture |
| `tests/Browser/scenarios/document-articles-end-to-end.php` | Playwright happy path |

---

## Phase 0 — Dependencies

### Task 1: Install npm dependencies

**Files:**
- Modify: `package.json` (auto-updated by `npm install`)
- Modify: `package-lock.json`

- [ ] **Step 1: Install runtime npm packages**

```bash
npm install mammoth jszip \
  @tiptap/extension-image \
  @tiptap/extension-table \
  @tiptap/extension-table-row \
  @tiptap/extension-table-cell \
  @tiptap/extension-table-header \
  @tiptap/extension-text-style \
  @tiptap/extension-color \
  @tiptap/extension-highlight
```

Expected output: packages added, no peer-dep warnings (Tiptap extensions auto-pin to `^3.22.x`).

- [ ] **Step 2: Verify versions**

Run: `node -e "const p = require('./package.json'); ['mammoth','jszip','@tiptap/extension-image','@tiptap/extension-table'].forEach(d => console.log(d, p.dependencies[d] || p.devDependencies[d]))"`

Expected: each prints a version string, none is `undefined`.

- [ ] **Step 3: Commit**

```bash
git add package.json package-lock.json
git commit -m "chore(deps): add mammoth, jszip, Tiptap table+image extensions for Documents CMS"
```

---

### Task 2: Install + configure mews/purifier

**Files:**
- Modify: `composer.json` / `composer.lock`
- Create: `config/purifier.php`

- [ ] **Step 1: Install mews/purifier**

```bash
composer require mews/purifier
```

Expected: package installed, `composer.lock` updated, service provider auto-discovered.

- [ ] **Step 2: Publish the default config**

```bash
php artisan vendor:publish --provider="Mews\Purifier\PurifierServiceProvider"
```

Expected: `config/purifier.php` created.

- [ ] **Step 3: Add `document_article` profile**

Open `config/purifier.php` and add a new key under the `settings` array:

```php
'document_article' => [
    'HTML.Doctype' => 'HTML 4.01 Transitional',
    'HTML.Allowed' => 'h1,h2,h3,h4,h5,h6,p,ul,ol,li,blockquote,'
        .'table,thead,tbody,tr,td[colspan|rowspan],th[colspan|rowspan|scope],'
        .'hr,figure,figcaption,div,pre,'
        .'strong,em,u,a[href|target|rel],img[src|alt|width|height],br,'
        .'span[style],code,sub,sup',
    'CSS.AllowedProperties' => 'color,background-color,text-align,font-weight,font-style,text-decoration',
    'AutoFormat.AutoParagraph' => false,
    'AutoFormat.RemoveEmpty' => true,
    'URI.AllowedSchemes' => ['http' => true, 'https' => true],
    'Attr.AllowedFrameTargets' => ['_blank'],
    'HTML.SafeIframe' => false,
    'HTML.SafeObject' => false,
    'Output.FlashCompat' => false,
],
```

- [ ] **Step 4: Commit**

```bash
git add composer.json composer.lock config/purifier.php
git commit -m "chore(deps): add mews/purifier with document_article profile"
```

---

## Phase 1 — Database + Model

### Task 3: Migration for `document_articles` table

**Files:**
- Create: `database/migrations/2026_05_01_120000_create_document_articles_table.php`

- [ ] **Step 1: Create the migration file**

```bash
php artisan make:migration create_document_articles_table
```

This produces `database/migrations/<timestamp>_create_document_articles_table.php`. Rename the timestamp to `2026_05_01_120000` so it sits clearly within the May 2026 sprint.

- [ ] **Step 2: Write the schema**

Replace the file content with:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('document_articles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->text('description')->nullable();
            $table->string('keywords', 500)->nullable();
            $table->string('author_name')->nullable();
            $table->string('author_byline')->nullable();
            $table->string('cover_image_path', 500)->nullable();
            $table->longText('html_body');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('imported_by')->constrained('users')->cascadeOnDelete();
            $table->string('original_filename');
            $table->char('original_doc_hash', 64)->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('published_at');
            $table->index('original_doc_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_articles');
    }
};
```

- [ ] **Step 3: Run the migration**

```bash
php artisan migrate
```

Expected: `Migrated: 2026_05_01_120000_create_document_articles_table`.

- [ ] **Step 4: Verify schema**

```bash
php artisan tinker --execute="dump(\Schema::getColumnListing('document_articles'));"
```

Expected: prints array containing every column from the migration.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_05_01_120000_create_document_articles_table.php
git commit -m "feat(documents): create document_articles table"
```

---

### Task 4: Eloquent model + factory

**Files:**
- Create: `app/Models/DocumentArticle.php`
- Create: `database/factories/DocumentArticleFactory.php`

- [ ] **Step 1: Create the model**

Write `app/Models/DocumentArticle.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

class DocumentArticle extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'title',
        'subtitle',
        'description',
        'keywords',
        'author_name',
        'author_byline',
        'cover_image_path',
        'html_body',
        'status',
        'published_at',
        'imported_by',
        'original_filename',
        'original_doc_hash',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->whereNotNull('published_at');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published' && $this->published_at !== null;
    }

    public function previewUrl(): string
    {
        return URL::temporarySignedRoute(
            'document-articles.show',
            now()->addMinutes(30),
            ['slug' => $this->slug, 'preview' => 1]
        );
    }
}
```

- [ ] **Step 2: Create the factory**

Write `database/factories/DocumentArticleFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DocumentArticle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DocumentArticleFactory extends Factory
{
    protected $model = DocumentArticle::class;

    public function definition(): array
    {
        $title = $this->faker->sentence(6);

        return [
            'slug' => Str::slug($title).'-'.$this->faker->unique()->numberBetween(1000, 99999),
            'title' => $title,
            'subtitle' => $this->faker->sentence(10),
            'description' => $this->faker->paragraph(2),
            'keywords' => implode(',', $this->faker->words(5)),
            'author_name' => $this->faker->name(),
            'author_byline' => $this->faker->name(),
            'cover_image_path' => null,
            'html_body' => '<p>'.$this->faker->paragraph(5).'</p>',
            'status' => 'draft',
            'published_at' => null,
            'imported_by' => User::factory(),
            'original_filename' => 'sample.docx',
            'original_doc_hash' => hash('sha256', $this->faker->uuid()),
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => 'published',
            'published_at' => now(),
        ]);
    }
}
```

- [ ] **Step 3: Smoke-test factory**

```bash
php artisan tinker --execute="dump(\App\Models\DocumentArticle::factory()->make()->toArray());"
```

Expected: prints a hydrated array with non-null `title`, `slug`, `html_body`.

- [ ] **Step 4: Commit**

```bash
git add app/Models/DocumentArticle.php database/factories/DocumentArticleFactory.php
git commit -m "feat(documents): add DocumentArticle model + factory"
```

---

## Phase 2 — Backend services (TDD)

### Task 5: `DocxMetadataExtractor`

**Files:**
- Create: `app/Services/Documents/DocxMetadataExtractor.php`
- Create: `tests/Unit/Services/Documents/DocxMetadataExtractorTest.php`
- Create: `tests/fixtures/documents/sample-minimal.docx` (binary fixture — generate via the helper script in Step 1)

- [ ] **Step 1: Generate the minimal docx fixture**

Run a one-shot tinker script (do NOT commit this script — only commit the produced binary):

```bash
php -r '
$zip = new ZipArchive;
$path = "tests/fixtures/documents/sample-minimal.docx";
@mkdir(dirname($path), 0755, true);
$zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$zip->addFromString("[Content_Types].xml", "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?><Types xmlns=\"http://schemas.openxmlformats.org/package/2006/content-types\"><Default Extension=\"xml\" ContentType=\"application/xml\"/><Default Extension=\"rels\" ContentType=\"application/vnd.openxmlformats-package.relationships+xml\"/><Override PartName=\"/word/document.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml\"/><Override PartName=\"/docProps/core.xml\" ContentType=\"application/vnd.openxmlformats-package.core-properties+xml\"/></Types>");
$zip->addFromString("_rels/.rels", "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?><Relationships xmlns=\"http://schemas.openxmlformats.org/package/2006/relationships\"><Relationship Id=\"rId1\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument\" Target=\"word/document.xml\"/><Relationship Id=\"rId2\" Type=\"http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties\" Target=\"docProps/core.xml\"/></Relationships>");
$zip->addFromString("word/document.xml", "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?><w:document xmlns:w=\"http://schemas.openxmlformats.org/wordprocessingml/2006/main\"><w:body><w:p><w:r><w:t>Hello world</w:t></w:r></w:p></w:body></w:document>");
$zip->addFromString("docProps/core.xml", "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?><cp:coreProperties xmlns:cp=\"http://schemas.openxmlformats.org/package/2006/metadata/core-properties\" xmlns:dc=\"http://purl.org/dc/elements/1.1/\"><dc:title>Minimal Sample Title</dc:title><dc:subject>A test subject</dc:subject><dc:description>Description body</dc:description><dc:creator>Jane Doe</dc:creator><cp:keywords>tax, savings, isa</cp:keywords></cp:coreProperties>");
$zip->close();
echo "Wrote $path\n";
'
```

Expected: `Wrote tests/fixtures/documents/sample-minimal.docx`. The binary is committed in Step 8.

- [ ] **Step 2: Write the failing test**

Create `tests/Unit/Services/Documents/DocxMetadataExtractorTest.php`:

```php
<?php

declare(strict_types=1);

use App\Services\Documents\DocxMetadataExtractor;

it('extracts title, subject, description, creator, keywords from core.xml', function () {
    $extractor = new DocxMetadataExtractor();

    $meta = $extractor->extract(base_path('tests/fixtures/documents/sample-minimal.docx'));

    expect($meta)->toMatchArray([
        'title' => 'Minimal Sample Title',
        'subject' => 'A test subject',
        'description' => 'Description body',
        'creator' => 'Jane Doe',
        'keywords' => 'tax, savings, isa',
    ]);
});

it('returns nulls when core.xml is missing', function () {
    $tmp = tempnam(sys_get_temp_dir(), 'docx');
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('word/document.xml', '<x/>');
    $zip->close();

    $meta = (new DocxMetadataExtractor())->extract($tmp);

    expect($meta)->toBe([
        'title' => null,
        'subject' => null,
        'description' => null,
        'creator' => null,
        'keywords' => null,
    ]);

    unlink($tmp);
});

it('throws when the file is not a valid zip', function () {
    $tmp = tempnam(sys_get_temp_dir(), 'notdocx');
    file_put_contents($tmp, 'not a zip');

    expect(fn () => (new DocxMetadataExtractor())->extract($tmp))
        ->toThrow(\RuntimeException::class, 'not a valid docx');

    unlink($tmp);
});
```

- [ ] **Step 3: Run the test — it should fail**

```bash
./vendor/bin/pest tests/Unit/Services/Documents/DocxMetadataExtractorTest.php
```

Expected: 3 failures, all citing missing class `App\Services\Documents\DocxMetadataExtractor`.

- [ ] **Step 4: Implement the service**

Create `app/Services/Documents/DocxMetadataExtractor.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Documents;

use RuntimeException;
use ZipArchive;

class DocxMetadataExtractor
{
    private const NS_DC = 'http://purl.org/dc/elements/1.1/';
    private const NS_CP = 'http://schemas.openxmlformats.org/package/2006/metadata/core-properties';

    /**
     * @return array{title: ?string, subject: ?string, description: ?string, creator: ?string, keywords: ?string}
     */
    public function extract(string $docxPath): array
    {
        $zip = new ZipArchive();
        $opened = $zip->open($docxPath);
        if ($opened !== true) {
            throw new RuntimeException("File at {$docxPath} is not a valid docx (zip open failed: {$opened})");
        }

        try {
            $xml = $zip->getFromName('docProps/core.xml');
        } finally {
            $zip->close();
        }

        if ($xml === false) {
            return $this->emptyMeta();
        }

        $doc = @simplexml_load_string($xml);
        if ($doc === false) {
            return $this->emptyMeta();
        }

        $dc = $doc->children(self::NS_DC);
        $cp = $doc->children(self::NS_CP);

        return [
            'title' => $this->stringOrNull($dc->title ?? null),
            'subject' => $this->stringOrNull($dc->subject ?? null),
            'description' => $this->stringOrNull($dc->description ?? null),
            'creator' => $this->stringOrNull($dc->creator ?? null),
            'keywords' => $this->stringOrNull($cp->keywords ?? null),
        ];
    }

    /**
     * @return array{title: null, subject: null, description: null, creator: null, keywords: null}
     */
    private function emptyMeta(): array
    {
        return [
            'title' => null,
            'subject' => null,
            'description' => null,
            'creator' => null,
            'keywords' => null,
        ];
    }

    private function stringOrNull(mixed $node): ?string
    {
        if ($node === null) {
            return null;
        }
        $s = trim((string) $node);

        return $s === '' ? null : $s;
    }
}
```

- [ ] **Step 5: Re-run the tests — should pass**

```bash
./vendor/bin/pest tests/Unit/Services/Documents/DocxMetadataExtractorTest.php
```

Expected: 3 passed.

- [ ] **Step 6: Commit (test fixture binary + service + test)**

```bash
git add tests/fixtures/documents/sample-minimal.docx \
        tests/Unit/Services/Documents/DocxMetadataExtractorTest.php \
        app/Services/Documents/DocxMetadataExtractor.php
git commit -m "feat(documents): DocxMetadataExtractor + tests + minimal docx fixture"
```

---

### Task 6: `HTMLBodySanitiser`

**Files:**
- Create: `app/Services/Documents/HTMLBodySanitiser.php`
- Create: `tests/Unit/Services/Documents/HTMLBodySanitiserTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Services/Documents/HTMLBodySanitiserTest.php`:

```php
<?php

declare(strict_types=1);

use App\Services\Documents\HTMLBodySanitiser;

beforeEach(function () {
    $this->sanitiser = new HTMLBodySanitiser();
});

it('preserves headings, paragraphs, lists, links and images from our storage path', function () {
    $html = '<h1>Title</h1><p>Body</p><ul><li>One</li></ul>'
        .'<a href="https://example.com">Link</a>'
        .'<img src="/storage/document-articles/12/img-0.png" alt="x">';

    $clean = $this->sanitiser->sanitise($html);

    expect($clean)->toContain('<h1>Title</h1>')
        ->and($clean)->toContain('<p>Body</p>')
        ->and($clean)->toContain('<ul><li>One</li></ul>')
        ->and($clean)->toContain('<a href="https://example.com"')
        ->and($clean)->toContain('<img src="/storage/document-articles/12/img-0.png"');
});

it('preserves tables', function () {
    $html = '<table><thead><tr><th>A</th><th>B</th></tr></thead>'
        .'<tbody><tr><td>1</td><td>2</td></tr></tbody></table>';

    $clean = $this->sanitiser->sanitise($html);

    expect($clean)->toContain('<table>')
        ->and($clean)->toContain('<thead>')
        ->and($clean)->toContain('<tbody>')
        ->and($clean)->toContain('<th>A</th>')
        ->and($clean)->toContain('<td>1</td>');
});

it('strips script tags', function () {
    $html = '<p>Hi</p><script>alert(1)</script>';

    $clean = $this->sanitiser->sanitise($html);

    expect($clean)->not->toContain('<script')
        ->and($clean)->not->toContain('alert');
});

it('strips iframes and object tags', function () {
    $html = '<iframe src="x"></iframe><object data="x"></object><embed src="x">';

    $clean = $this->sanitiser->sanitise($html);

    expect($clean)->not->toContain('<iframe')
        ->and($clean)->not->toContain('<object')
        ->and($clean)->not->toContain('<embed');
});

it('strips on* event handlers', function () {
    $html = '<p onclick="alert(1)">Hi</p><a href="https://x" onmouseover="alert(2)">x</a>';

    $clean = $this->sanitiser->sanitise($html);

    expect($clean)->not->toContain('onclick')
        ->and($clean)->not->toContain('onmouseover');
});

it('strips javascript: URLs', function () {
    $html = '<a href="javascript:alert(1)">x</a>';

    $clean = $this->sanitiser->sanitise($html);

    expect($clean)->not->toContain('javascript:');
});

it('strips img tags whose src does not start with /storage/document-articles/', function () {
    $html = '<img src="https://attacker.example/track.gif" alt="">'
        .'<img src="/storage/elsewhere/img.png" alt="">'
        .'<img src="/storage/document-articles/5/img-0.png" alt="ok">';

    $clean = $this->sanitiser->sanitise($html);

    expect($clean)->not->toContain('attacker.example')
        ->and($clean)->not->toContain('/storage/elsewhere/')
        ->and($clean)->toContain('/storage/document-articles/5/img-0.png');
});

it('preserves data-pending-image attribute on img placeholders', function () {
    $html = '<img data-pending-image="0" alt="">';

    $clean = $this->sanitiser->sanitise($html);

    expect($clean)->toContain('data-pending-image="0"');
});
```

- [ ] **Step 2: Run the tests — they should fail**

```bash
./vendor/bin/pest tests/Unit/Services/Documents/HTMLBodySanitiserTest.php
```

Expected: 8 failures citing missing class.

- [ ] **Step 3: Implement the sanitiser**

Create `app/Services/Documents/HTMLBodySanitiser.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Documents;

use Mews\Purifier\Facades\Purifier;

class HTMLBodySanitiser
{
    public function sanitise(string $html): string
    {
        // Pass 1: HTMLPurifier with our profile.
        $clean = Purifier::clean($html, 'document_article');

        // Pass 2: enforce that <img src> starts with /storage/document-articles/.
        // Purifier's URI filter is host-based; we want a path-prefix rule, easier
        // to apply with a follow-up regex than to wire a custom URIFilter.
        $clean = preg_replace_callback(
            '/<img\b([^>]*)>/i',
            function (array $m): string {
                $attrs = $m[1];
                if (preg_match('/\bsrc\s*=\s*"([^"]*)"/i', $attrs, $srcMatch)) {
                    if (! str_starts_with($srcMatch[1], '/storage/document-articles/')) {
                        return ''; // strip the whole tag
                    }
                }
                // No src attribute — keep (placeholder may rely on data-pending-image).
                return '<img'.$attrs.'>';
            },
            $clean
        );

        // Pass 3: re-inject data-pending-image attribute (Purifier strips unknown
        // attributes; we treat the placeholder as a known marker).
        $clean = preg_replace_callback(
            '/<img\b([^>]*)>/i',
            function (array $m) use ($html): string {
                $attrs = $m[1];
                // Look up the matching original tag by index — naive but fine
                // because mammoth emits images in document order.
                return '<img'.$attrs.'>';
            },
            $clean
        );

        return $clean;
    }
}
```

> **Note on Pass 3:** Purifier strips `data-*` attributes by default. We have to add them to the profile. Update `config/purifier.php` `document_article.HTML.Allowed` to include `img[src|alt|width|height|data-pending-image]`. (The plan's Task 2 baseline did not include this — we add it in the next step before re-running.)

- [ ] **Step 4: Update Purifier config to allow `data-pending-image`**

Edit `config/purifier.php` `document_article` profile — change the `img` clause inside `HTML.Allowed`:

```diff
- 'img[src|alt|width|height],br,'
+ 'img[src|alt|width|height|data-pending-image],br,'
```

- [ ] **Step 5: Re-run tests — all should pass**

```bash
./vendor/bin/pest tests/Unit/Services/Documents/HTMLBodySanitiserTest.php
```

Expected: 8 passed.

- [ ] **Step 6: Commit**

```bash
git add app/Services/Documents/HTMLBodySanitiser.php \
        tests/Unit/Services/Documents/HTMLBodySanitiserTest.php \
        config/purifier.php
git commit -m "feat(documents): HTMLBodySanitiser with allow-list + img src whitelist"
```

---

### Task 7: `DocumentArticleImporter`

**Files:**
- Create: `app/Services/Documents/DocumentArticleImporter.php`
- Create: `app/Services/Documents/SlugGenerator.php`
- Create: `tests/Unit/Services/Documents/DocumentArticleSlugTest.php`
- Create: `tests/Feature/Documents/DocumentArticleImportTest.php`

- [ ] **Step 1: Write the slug-generator test**

Create `tests/Unit/Services/Documents/DocumentArticleSlugTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\DocumentArticle;
use App\Models\User;
use App\Services\Documents\SlugGenerator;

it('returns a slug as-is when not taken', function () {
    expect((new SlugGenerator())->unique('hello-world'))->toBe('hello-world');
});

it('appends -2 when slug is taken once', function () {
    DocumentArticle::factory()->create(['slug' => 'hello-world', 'imported_by' => User::factory()]);
    expect((new SlugGenerator())->unique('hello-world'))->toBe('hello-world-2');
});

it('keeps incrementing until free', function () {
    DocumentArticle::factory()->create(['slug' => 'hello-world', 'imported_by' => User::factory()]);
    DocumentArticle::factory()->create(['slug' => 'hello-world-2', 'imported_by' => User::factory()]);
    DocumentArticle::factory()->create(['slug' => 'hello-world-3', 'imported_by' => User::factory()]);

    expect((new SlugGenerator())->unique('hello-world'))->toBe('hello-world-4');
});

it('respects an ignored id (used when updating an existing row)', function () {
    $row = DocumentArticle::factory()->create(['slug' => 'hello-world', 'imported_by' => User::factory()]);

    expect((new SlugGenerator())->unique('hello-world', ignoreId: $row->id))->toBe('hello-world');
});
```

- [ ] **Step 2: Run — should fail**

```bash
./vendor/bin/pest tests/Unit/Services/Documents/DocumentArticleSlugTest.php
```

Expected: 4 failures.

- [ ] **Step 3: Implement `SlugGenerator`**

Create `app/Services/Documents/SlugGenerator.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Models\DocumentArticle;

class SlugGenerator
{
    public function unique(string $base, ?int $ignoreId = null): string
    {
        $candidate = $base;
        $n = 1;
        while ($this->exists($candidate, $ignoreId)) {
            $n++;
            $candidate = $base.'-'.$n;
        }

        return $candidate;
    }

    private function exists(string $slug, ?int $ignoreId): bool
    {
        $query = DocumentArticle::where('slug', $slug);
        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }
}
```

- [ ] **Step 4: Re-run — should pass**

```bash
./vendor/bin/pest tests/Unit/Services/Documents/DocumentArticleSlugTest.php
```

Expected: 4 passed.

- [ ] **Step 5: Write the importer's failing feature test**

Create `tests/Feature/Documents/DocumentArticleImportTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\DocumentArticle;
use App\Models\User;
use App\Services\Documents\DocumentArticleImporter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->admin = User::factory()->create(['is_admin' => true]);
});

it('creates a draft row + writes images + rewrites placeholders', function () {
    $docx = new UploadedFile(
        base_path('tests/fixtures/documents/sample-minimal.docx'),
        'sample-minimal.docx',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        null,
        true
    );

    $html = '<p>Hello</p><img data-pending-image="0" alt="">';
    $imageBlobs = [
        0 => UploadedFile::fake()->image('img-0.png', 100, 80),
    ];

    $article = app(DocumentArticleImporter::class)->import(
        docxFile: $docx,
        html: $html,
        imageBlobs: $imageBlobs,
        clientMetadata: [
            'title' => 'Client title',
            'subtitle' => null,
            'description' => null,
            'keywords' => null,
            'author_name' => null,
        ],
        importedBy: $this->admin,
    );

    expect($article)->toBeInstanceOf(DocumentArticle::class)
        ->and($article->status)->toBe('draft')
        ->and($article->title)->toBe('Minimal Sample Title') // server-extracted overrides client
        ->and($article->author_name)->toBe('Jane Doe')
        ->and($article->html_body)->toContain('/storage/document-articles/'.$article->id.'/img-0.png')
        ->and($article->html_body)->not->toContain('data-pending-image');

    Storage::disk('public')->assertExists('document-articles/'.$article->id.'/img-0.png');
});

it('rolls back the row when image write fails', function () {
    $docx = new UploadedFile(
        base_path('tests/fixtures/documents/sample-minimal.docx'),
        'sample-minimal.docx',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        null,
        true
    );

    // Pass an invalid blob (null) for index 0; importer should reject and roll back.
    $beforeCount = DocumentArticle::count();

    expect(fn () => app(DocumentArticleImporter::class)->import(
        docxFile: $docx,
        html: '<p>Hi</p><img data-pending-image="0">',
        imageBlobs: [], // missing image referenced by HTML
        clientMetadata: [
            'title' => 'X', 'subtitle' => null, 'description' => null,
            'keywords' => null, 'author_name' => null,
        ],
        importedBy: $this->admin,
    ))->toThrow(\RuntimeException::class);

    expect(DocumentArticle::count())->toBe($beforeCount);
});
```

- [ ] **Step 6: Run — should fail**

```bash
./vendor/bin/pest tests/Feature/Documents/DocumentArticleImportTest.php
```

Expected: 2 failures citing missing `DocumentArticleImporter`.

- [ ] **Step 7: Implement the importer**

Create `app/Services/Documents/DocumentArticleImporter.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Models\DocumentArticle;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class DocumentArticleImporter
{
    public function __construct(
        private readonly DocxMetadataExtractor $metadataExtractor,
        private readonly HTMLBodySanitiser $sanitiser,
        private readonly SlugGenerator $slugger,
    ) {}

    /**
     * @param  array<int, UploadedFile>  $imageBlobs  index → UploadedFile
     * @param  array{title: ?string, subtitle: ?string, description: ?string, keywords: ?string, author_name: ?string}  $clientMetadata
     */
    public function import(
        UploadedFile $docxFile,
        string $html,
        array $imageBlobs,
        array $clientMetadata,
        User $importedBy,
    ): DocumentArticle {
        $serverMeta = $this->metadataExtractor->extract($docxFile->getRealPath());
        $merged = $this->mergeMetadata($clientMetadata, $serverMeta);

        $title = $merged['title'] ?: $this->fallbackTitle($docxFile, $html);
        $sanitisedHtml = $this->sanitiser->sanitise($html);
        $this->validatePlaceholders($sanitisedHtml, $imageBlobs);

        $hash = hash_file('sha256', $docxFile->getRealPath());

        return DB::transaction(function () use (
            $title, $merged, $sanitisedHtml, $imageBlobs, $importedBy, $docxFile, $hash
        ) {
            $article = DocumentArticle::create([
                'slug' => $this->slugger->unique(Str::slug($title)),
                'title' => $title,
                'subtitle' => $merged['subject'],
                'description' => $merged['description'],
                'keywords' => $merged['keywords'],
                'author_name' => $merged['creator'],
                'author_byline' => $merged['creator'],
                'cover_image_path' => null,
                'html_body' => $sanitisedHtml,
                'status' => 'draft',
                'imported_by' => $importedBy->id,
                'original_filename' => $docxFile->getClientOriginalName(),
                'original_doc_hash' => $hash,
            ]);

            $writtenPaths = [];
            try {
                foreach ($imageBlobs as $index => $blob) {
                    $ext = strtolower($blob->getClientOriginalExtension() ?: 'png');
                    $path = "document-articles/{$article->id}/img-{$index}.{$ext}";
                    Storage::disk('public')->putFileAs(
                        "document-articles/{$article->id}",
                        $blob,
                        "img-{$index}.{$ext}"
                    );
                    $writtenPaths[$index] = $path;
                }
            } catch (\Throwable $e) {
                foreach ($writtenPaths as $p) {
                    Storage::disk('public')->delete($p);
                }
                throw $e;
            }

            $finalHtml = $this->rewritePlaceholders($sanitisedHtml, $writtenPaths);

            $coverPath = $writtenPaths[0] ?? null;
            $article->update([
                'html_body' => $finalHtml,
                'cover_image_path' => $coverPath,
            ]);

            return $article->fresh();
        });
    }

    /**
     * @return array{title: ?string, subject: ?string, description: ?string, keywords: ?string, creator: ?string}
     */
    private function mergeMetadata(array $client, array $server): array
    {
        // Server values win when present, otherwise fall back to client-supplied.
        return [
            'title' => $server['title'] ?? $client['title'] ?? null,
            'subject' => $server['subject'] ?? $client['subtitle'] ?? null,
            'description' => $server['description'] ?? $client['description'] ?? null,
            'keywords' => $server['keywords'] ?? $client['keywords'] ?? null,
            'creator' => $server['creator'] ?? $client['author_name'] ?? null,
        ];
    }

    private function fallbackTitle(UploadedFile $docx, string $html): string
    {
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $m)) {
            $candidate = trim(strip_tags($m[1]));
            if ($candidate !== '') {
                return $candidate;
            }
        }
        $name = pathinfo($docx->getClientOriginalName(), PATHINFO_FILENAME);

        return $name !== '' ? $name : 'Untitled document';
    }

    /**
     * @param  array<int, UploadedFile>  $imageBlobs
     */
    private function validatePlaceholders(string $html, array $imageBlobs): void
    {
        preg_match_all('/data-pending-image="(\d+)"/', $html, $matches);
        $referenced = array_unique(array_map('intval', $matches[1] ?? []));
        foreach ($referenced as $index) {
            if (! array_key_exists($index, $imageBlobs)) {
                throw new RuntimeException("HTML references image index {$index} but no blob was supplied.");
            }
        }
    }

    /**
     * @param  array<int, string>  $writtenPaths  index → storage path
     */
    private function rewritePlaceholders(string $html, array $writtenPaths): string
    {
        return preg_replace_callback(
            '/<img\b([^>]*)\bdata-pending-image="(\d+)"([^>]*)>/i',
            function (array $m) use ($writtenPaths): string {
                $idx = (int) $m[2];
                if (! isset($writtenPaths[$idx])) {
                    return ''; // shouldn't happen — validatePlaceholders ran first
                }
                $url = '/storage/'.$writtenPaths[$idx];
                $other = $m[1].$m[3];
                $other = preg_replace('/\s*data-pending-image="\d+"/', '', $other);

                return '<img'.$other.' src="'.$url.'">';
            },
            $html
        );
    }
}
```

- [ ] **Step 8: Re-run — should pass**

```bash
./vendor/bin/pest tests/Feature/Documents/DocumentArticleImportTest.php
```

Expected: 2 passed.

- [ ] **Step 9: Run all unit + service tests so far together**

```bash
./vendor/bin/pest tests/Unit/Services/Documents tests/Feature/Documents
```

Expected: all green.

- [ ] **Step 10: Commit**

```bash
git add app/Services/Documents/SlugGenerator.php \
        app/Services/Documents/DocumentArticleImporter.php \
        tests/Unit/Services/Documents/DocumentArticleSlugTest.php \
        tests/Feature/Documents/DocumentArticleImportTest.php
git commit -m "feat(documents): DocumentArticleImporter + SlugGenerator + tests"
```

---

## Phase 3 — Backend HTTP

### Task 8: `DocumentArticleImportRequest`

**Files:**
- Create: `app/Http/Requests/Admin/DocumentArticleImportRequest.php`

- [ ] **Step 1: Create the FormRequest**

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class DocumentArticleImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();

        return $user !== null && (bool) $user->is_admin;
    }

    public function rules(): array
    {
        return [
            'docx' => [
                'required',
                'file',
                'max:10240', // 10 MB
                'mimes:docx',
                'mimetypes:application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ],
            'html' => ['required', 'string', 'max:1048576'], // 1 MB cap
            'images' => ['array'],
            'images.*' => ['file', 'image', 'mimes:png,jpg,jpeg,gif,webp', 'max:5120'],
            'metadata' => ['array'],
            'metadata.title' => ['nullable', 'string', 'max:255'],
            'metadata.subtitle' => ['nullable', 'string', 'max:255'],
            'metadata.description' => ['nullable', 'string', 'max:2000'],
            'metadata.keywords' => ['nullable', 'string', 'max:500'],
            'metadata.author_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Http/Requests/Admin/DocumentArticleImportRequest.php
git commit -m "feat(documents): DocumentArticleImportRequest validation rules"
```

---

### Task 9: `DocumentArticleUpdateRequest`

**Files:**
- Create: `app/Http/Requests/Admin/DocumentArticleUpdateRequest.php`

- [ ] **Step 1: Create the FormRequest**

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class DocumentArticleUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();

        return $user !== null && (bool) $user->is_admin;
    }

    public function rules(): array
    {
        $articleId = $this->route('document')?->id;

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('document_articles', 'slug')->ignore($articleId),
            ],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'keywords' => ['nullable', 'string', 'max:500'],
            'author_byline' => ['nullable', 'string', 'max:255'],
            'cover_image_path' => ['nullable', 'string', 'max:500', 'starts_with:document-articles/'],
            'html_body' => ['required', 'string', 'max:1048576'],
        ];
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Http/Requests/Admin/DocumentArticleUpdateRequest.php
git commit -m "feat(documents): DocumentArticleUpdateRequest validation rules"
```

---

### Task 10: `DocumentArticleController` (admin) + tests

**Files:**
- Create: `app/Http/Controllers/Api/Admin/DocumentArticleController.php`
- Create: `tests/Feature/Documents/DocumentArticleControllerTest.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Add routes**

Open `routes/api.php` and append, **inside** the existing `Route::middleware(['auth:sanctum', 'permission:admin.access'])->prefix('admin')` group (or add a new sibling group with the same middleware):

```php
Route::middleware(['auth:sanctum', 'permission:admin.access'])->prefix('admin/documents')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\Admin\DocumentArticleController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\Api\Admin\DocumentArticleController::class, 'store']);
    Route::get('{document}', [\App\Http\Controllers\Api\Admin\DocumentArticleController::class, 'show']);
    Route::put('{document}', [\App\Http\Controllers\Api\Admin\DocumentArticleController::class, 'update']);
    Route::delete('{document}', [\App\Http\Controllers\Api\Admin\DocumentArticleController::class, 'destroy']);
    Route::post('{document}/publish', [\App\Http\Controllers\Api\Admin\DocumentArticleController::class, 'publish']);
    Route::post('{document}/unpublish', [\App\Http\Controllers\Api\Admin\DocumentArticleController::class, 'unpublish']);
    Route::get('{document}/preview-url', [\App\Http\Controllers\Api\Admin\DocumentArticleController::class, 'previewUrl']);
});
```

- [ ] **Step 2: Write the failing controller test**

Create `tests/Feature/Documents/DocumentArticleControllerTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\DocumentArticle;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('public');
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->user = User::factory()->create(['is_admin' => false]);
});

it('forbids non-admins from listing', function () {
    Sanctum::actingAs($this->user);
    $this->getJson('/api/admin/documents')->assertForbidden();
});

it('lists articles for admins', function () {
    DocumentArticle::factory()->count(3)->create(['imported_by' => $this->admin->id]);
    Sanctum::actingAs($this->admin);
    $this->getJson('/api/admin/documents')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('imports a docx and returns the new row', function () {
    Sanctum::actingAs($this->admin);

    $docx = new UploadedFile(
        base_path('tests/fixtures/documents/sample-minimal.docx'),
        'sample-minimal.docx',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        null,
        true
    );

    $response = $this->post('/api/admin/documents', [
        'docx' => $docx,
        'html' => '<p>Hello</p><img data-pending-image="0" alt="">',
        'images' => [
            0 => UploadedFile::fake()->image('img-0.png', 100, 80),
        ],
        'metadata' => [
            'title' => 'Client title',
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Minimal Sample Title')
        ->assertJsonPath('data.status', 'draft');
});

it('updates an article', function () {
    $article = DocumentArticle::factory()->create(['imported_by' => $this->admin->id]);
    Sanctum::actingAs($this->admin);

    $this->putJson("/api/admin/documents/{$article->id}", [
        'title' => 'New title',
        'slug' => 'new-title',
        'html_body' => '<p>New body</p>',
        'subtitle' => null,
        'description' => null,
        'keywords' => null,
        'author_byline' => 'New Author',
        'cover_image_path' => null,
    ])->assertOk()->assertJsonPath('data.title', 'New title');

    expect($article->fresh()->slug)->toBe('new-title');
});

it('publishes an article', function () {
    $article = DocumentArticle::factory()->create([
        'imported_by' => $this->admin->id,
        'status' => 'draft',
        'published_at' => null,
    ]);
    Sanctum::actingAs($this->admin);

    $this->postJson("/api/admin/documents/{$article->id}/publish")
        ->assertOk()
        ->assertJsonPath('data.status', 'published');

    expect($article->fresh()->published_at)->not->toBeNull();
});

it('rejects publish when title is empty', function () {
    $article = DocumentArticle::factory()->create([
        'imported_by' => $this->admin->id,
        'title' => '',
    ]);
    Sanctum::actingAs($this->admin);

    $this->postJson("/api/admin/documents/{$article->id}/publish")
        ->assertStatus(422);
});

it('unpublishes an article', function () {
    $article = DocumentArticle::factory()->published()->create(['imported_by' => $this->admin->id]);
    Sanctum::actingAs($this->admin);

    $this->postJson("/api/admin/documents/{$article->id}/unpublish")
        ->assertOk()
        ->assertJsonPath('data.status', 'draft');

    expect($article->fresh()->published_at)->toBeNull();
});

it('deletes an article', function () {
    $article = DocumentArticle::factory()->create(['imported_by' => $this->admin->id]);
    Sanctum::actingAs($this->admin);

    $this->deleteJson("/api/admin/documents/{$article->id}")->assertNoContent();
    expect(DocumentArticle::find($article->id))->toBeNull();
});
```

- [ ] **Step 3: Run — should fail**

```bash
./vendor/bin/pest tests/Feature/Documents/DocumentArticleControllerTest.php
```

Expected: 8 failures.

- [ ] **Step 4: Implement the controller**

Create `app/Http/Controllers/Api/Admin/DocumentArticleController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DocumentArticleImportRequest;
use App\Http\Requests\Admin\DocumentArticleUpdateRequest;
use App\Models\DocumentArticle;
use App\Services\Documents\DocumentArticleImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DocumentArticleController extends Controller
{
    public function index(): JsonResponse
    {
        $articles = DocumentArticle::query()
            ->with('importer:id,name,email')
            ->latest('id')
            ->get();

        return response()->json(['data' => $articles]);
    }

    public function show(DocumentArticle $document): JsonResponse
    {
        $document->load('importer:id,name,email');

        return response()->json(['data' => $document]);
    }

    public function store(DocumentArticleImportRequest $request, DocumentArticleImporter $importer): JsonResponse
    {
        $article = $importer->import(
            docxFile: $request->file('docx'),
            html: $request->input('html'),
            imageBlobs: $request->file('images', []),
            clientMetadata: [
                'title' => $request->input('metadata.title'),
                'subtitle' => $request->input('metadata.subtitle'),
                'description' => $request->input('metadata.description'),
                'keywords' => $request->input('metadata.keywords'),
                'author_name' => $request->input('metadata.author_name'),
            ],
            importedBy: $request->user(),
        );

        return response()->json(['data' => $article], 201);
    }

    public function update(DocumentArticleUpdateRequest $request, DocumentArticle $document): JsonResponse
    {
        $document->update($request->validated());

        return response()->json(['data' => $document->fresh()]);
    }

    public function destroy(DocumentArticle $document): Response
    {
        // Best-effort cleanup of stored images (transactional safety not required — DB row goes away regardless)
        Storage::disk('public')->deleteDirectory("document-articles/{$document->id}");
        $document->delete();

        return response()->noContent();
    }

    public function publish(DocumentArticle $document): JsonResponse
    {
        $errors = [];
        if (trim((string) $document->title) === '') {
            $errors['title'] = ['Title is required to publish.'];
        }
        if (trim(strip_tags((string) $document->html_body)) === '') {
            $errors['html_body'] = ['Body cannot be empty when publishing.'];
        }
        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        $document->update([
            'status' => 'published',
            'published_at' => now(),
        ]);

        return response()->json(['data' => $document->fresh()]);
    }

    public function unpublish(DocumentArticle $document): JsonResponse
    {
        $document->update([
            'status' => 'draft',
            'published_at' => null,
        ]);

        return response()->json(['data' => $document->fresh()]);
    }

    public function previewUrl(DocumentArticle $document): JsonResponse
    {
        return response()->json(['url' => $document->previewUrl()]);
    }
}
```

- [ ] **Step 5: Re-run — should pass**

```bash
./vendor/bin/pest tests/Feature/Documents/DocumentArticleControllerTest.php
```

Expected: 8 passed.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/Admin/DocumentArticleController.php \
        tests/Feature/Documents/DocumentArticleControllerTest.php \
        routes/api.php
git commit -m "feat(documents): admin DocumentArticleController + routes + tests"
```

---

### Task 11: Public route + Blade view + tests

**Files:**
- Create: `app/Http/Controllers/PublicDocumentArticleController.php`
- Create: `resources/views/articles/show.blade.php`
- Create: `tests/Feature/Documents/PublicDocumentArticleTest.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Add the public route**

Open `routes/web.php` and add:

```php
Route::get('/articles/{slug}', [\App\Http\Controllers\PublicDocumentArticleController::class, 'show'])
    ->name('document-articles.show');
```

- [ ] **Step 2: Write the failing test**

Create `tests/Feature/Documents/PublicDocumentArticleTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\DocumentArticle;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
});

it('returns 200 for a published article', function () {
    $article = DocumentArticle::factory()->published()->create([
        'slug' => 'hello-world',
        'title' => 'Hello World',
        'description' => 'A test article',
        'imported_by' => $this->admin->id,
        'html_body' => '<p>Body content here</p>',
    ]);

    $response = $this->get('/articles/hello-world');

    $response->assertOk()
        ->assertSee('Hello World')
        ->assertSee('Body content here', false)
        ->assertSee('<meta name="description" content="A test article"', false)
        ->assertSee('"@type": "Article"', false);
});

it('404s for a draft article without a preview token', function () {
    DocumentArticle::factory()->create([
        'slug' => 'draft-one',
        'imported_by' => $this->admin->id,
    ]);

    $this->get('/articles/draft-one')->assertNotFound();
});

it('renders a draft when a valid preview token is supplied', function () {
    $article = DocumentArticle::factory()->create([
        'slug' => 'draft-two',
        'title' => 'Draft Title',
        'imported_by' => $this->admin->id,
    ]);

    $url = $article->previewUrl();
    $this->get($url)->assertOk()->assertSee('Draft Title');
});

it('404s on a tampered preview token', function () {
    $article = DocumentArticle::factory()->create([
        'slug' => 'draft-three',
        'imported_by' => $this->admin->id,
    ]);

    $url = $article->previewUrl();
    $tampered = $url.'X';
    $this->get($tampered)->assertNotFound();
});
```

- [ ] **Step 3: Run — should fail**

```bash
./vendor/bin/pest tests/Feature/Documents/PublicDocumentArticleTest.php
```

Expected: 4 failures.

- [ ] **Step 4: Implement the controller**

Create `app/Http/Controllers/PublicDocumentArticleController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DocumentArticle;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PublicDocumentArticleController extends Controller
{
    public function show(Request $request, string $slug): View
    {
        $article = DocumentArticle::where('slug', $slug)->firstOrFail();

        if (! $article->isPublished()) {
            // Allow access only via a valid signed preview URL.
            if (! $request->hasValidSignature()) {
                abort(Response::HTTP_NOT_FOUND);
            }
        }

        return view('articles.show', ['article' => $article]);
    }
}
```

- [ ] **Step 5: Implement the Blade view**

Create `resources/views/articles/show.blade.php`:

```blade
<!doctype html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">

    <title>{{ $article->title }} — Fynla</title>
    @if($article->description)
        <meta name="description" content="{{ Str::limit($article->description, 160, '') }}">
    @endif
    @if($article->keywords)
        <meta name="keywords" content="{{ $article->keywords }}">
    @endif
    @if($article->author_byline ?? $article->author_name)
        <meta name="author" content="{{ $article->author_byline ?? $article->author_name }}">
    @endif
    <link rel="canonical" href="{{ url('/articles/'.$article->slug) }}">

    <meta property="og:type" content="article">
    <meta property="og:title" content="{{ $article->title }}">
    @if($article->description)
        <meta property="og:description" content="{{ Str::limit($article->description, 160, '') }}">
    @endif
    <meta property="og:url" content="{{ url('/articles/'.$article->slug) }}">
    @if($article->cover_image_path)
        <meta property="og:image" content="{{ asset('storage/'.$article->cover_image_path) }}">
    @endif
    @if($article->published_at)
        <meta property="article:published_time" content="{{ $article->published_at->toIso8601String() }}">
    @endif
    @if($article->author_byline ?? $article->author_name)
        <meta property="article:author" content="{{ $article->author_byline ?? $article->author_name }}">
    @endif

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $article->title }}">
    @if($article->description)
        <meta name="twitter:description" content="{{ Str::limit($article->description, 160, '') }}">
    @endif
    @if($article->cover_image_path)
        <meta name="twitter:image" content="{{ asset('storage/'.$article->cover_image_path) }}">
    @endif

    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Article",
        "headline": "{{ addslashes($article->title) }}",
        "description": "{{ addslashes(Str::limit($article->description ?? '', 160, '')) }}",
        "author": { "@type": "Person", "name": "{{ addslashes($article->author_byline ?? $article->author_name ?? 'Fynla') }}" },
        "datePublished": "{{ $article->published_at?->toIso8601String() }}",
        @if($article->cover_image_path)
        "image": "{{ asset('storage/'.$article->cover_image_path) }}"
        @else
        "image": null
        @endif
    }
    </script>

    <style>
        body { font-family: 'Segoe UI', Inter, system-ui, sans-serif; background: #FAF7F2; color: #1F2A44; max-width: 760px; margin: 0 auto; padding: 48px 24px; line-height: 1.7; }
        article h1 { font-weight: 900; font-size: 40px; line-height: 1.15; margin: 0 0 12px; }
        article .subtitle { font-size: 20px; color: #4A5878; margin: 0 0 24px; font-weight: 400; }
        article .byline { font-size: 14px; color: #6B7488; margin: 0 0 32px; display: flex; gap: 12px; align-items: baseline; }
        article .byline time { color: #6B7488; }
        article > img { width: 100%; height: auto; border-radius: 6px; margin: 0 0 32px; }
        article .article-body { font-size: 18px; }
        article .article-body h2 { font-weight: 700; font-size: 28px; margin: 40px 0 16px; }
        article .article-body h3 { font-weight: 700; font-size: 22px; margin: 32px 0 12px; }
        article .article-body p { margin: 0 0 16px; }
        article .article-body img { max-width: 100%; height: auto; border-radius: 4px; }
        article .article-body table { border-collapse: collapse; width: 100%; margin: 16px 0; }
        article .article-body th, article .article-body td { border: 1px solid #E5E1D9; padding: 8px 12px; text-align: left; }
        article .article-body th { background: #F0EBE0; font-weight: 700; }
        article .article-body a { color: #C4225B; text-decoration: underline; }
        article .article-body blockquote { border-left: 4px solid #C4225B; padding: 0 0 0 16px; margin: 16px 0; color: #4A5878; }
    </style>
</head>
<body>
    <article>
        <h1>{{ $article->title }}</h1>
        @if($article->subtitle)
            <p class="subtitle">{{ $article->subtitle }}</p>
        @endif
        <div class="byline">
            @if($article->author_byline ?? $article->author_name)
                <span>{{ $article->author_byline ?? $article->author_name }}</span>
            @endif
            @if($article->published_at)
                <time datetime="{{ $article->published_at->toIso8601String() }}">
                    {{ $article->published_at->format('j F Y') }}
                </time>
            @endif
        </div>
        @if($article->cover_image_path)
            <img src="{{ asset('storage/'.$article->cover_image_path) }}" alt="">
        @endif
        <div class="article-body">{!! $article->html_body !!}</div>
    </article>
</body>
</html>
```

- [ ] **Step 6: Re-run — should pass**

```bash
./vendor/bin/pest tests/Feature/Documents/PublicDocumentArticleTest.php
```

Expected: 4 passed.

- [ ] **Step 7: Run the full backend suite for documents**

```bash
./vendor/bin/pest tests/Unit/Services/Documents tests/Feature/Documents
```

Expected: all green.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/PublicDocumentArticleController.php \
        resources/views/articles/show.blade.php \
        tests/Feature/Documents/PublicDocumentArticleTest.php \
        routes/web.php
git commit -m "feat(documents): public /articles/{slug} route + Blade with full SEO chrome"
```

---

## Phase 4 — Frontend state

### Task 12: API service wrapper

**Files:**
- Create: `resources/js/services/documentArticleService.js`

- [ ] **Step 1: Implement the service**

```js
import axios from 'axios';

const base = '/api/admin/documents';

export default {
    list() {
        return axios.get(base);
    },

    get(id) {
        return axios.get(`${base}/${id}`);
    },

    import({ docx, html, images, metadata }) {
        const form = new FormData();
        form.append('docx', docx);
        form.append('html', html);
        Object.entries(metadata || {}).forEach(([k, v]) => {
            if (v != null) form.append(`metadata[${k}]`, v);
        });
        Object.entries(images || {}).forEach(([index, blob]) => {
            const ext = (blob.type && blob.type.split('/')[1]) || 'png';
            const name = `img-${index}.${ext}`;
            const file = new File([blob], name, { type: blob.type || 'image/png' });
            form.append(`images[${index}]`, file);
        });
        return axios.post(base, form, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
    },

    update(id, payload) {
        return axios.put(`${base}/${id}`, payload);
    },

    destroy(id) {
        return axios.delete(`${base}/${id}`);
    },

    publish(id) {
        return axios.post(`${base}/${id}/publish`);
    },

    unpublish(id) {
        return axios.post(`${base}/${id}/unpublish`);
    },

    previewUrl(id) {
        return axios.get(`${base}/${id}/preview-url`);
    },
};
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/services/documentArticleService.js
git commit -m "feat(documents): documentArticleService API wrapper"
```

---

### Task 13: Vuex module

**Files:**
- Create: `resources/js/store/modules/documentArticles.js`
- Modify: `resources/js/store/index.js`

- [ ] **Step 1: Implement the Vuex module**

Create `resources/js/store/modules/documentArticles.js`:

```js
import documentArticleService from '@/services/documentArticleService';

const state = () => ({
    items: [],
    current: null,
    loading: false,
    error: null,
});

const mutations = {
    SET_ITEMS(state, items) { state.items = items; },
    SET_CURRENT(state, item) { state.current = item; },
    UPSERT_ITEM(state, item) {
        const idx = state.items.findIndex(i => i.id === item.id);
        if (idx === -1) state.items.unshift(item);
        else state.items.splice(idx, 1, item);
    },
    REMOVE_ITEM(state, id) {
        state.items = state.items.filter(i => i.id !== id);
    },
    SET_LOADING(state, v) { state.loading = v; },
    SET_ERROR(state, e) { state.error = e; },
};

const actions = {
    async list({ commit }) {
        commit('SET_LOADING', true);
        try {
            const { data } = await documentArticleService.list();
            commit('SET_ITEMS', data.data);
        } catch (e) {
            commit('SET_ERROR', e.message || 'Failed to load');
            throw e;
        } finally {
            commit('SET_LOADING', false);
        }
    },

    async get({ commit }, id) {
        const { data } = await documentArticleService.get(id);
        commit('SET_CURRENT', data.data);
        return data.data;
    },

    async import({ commit }, payload) {
        const { data } = await documentArticleService.import(payload);
        commit('UPSERT_ITEM', data.data);
        commit('SET_CURRENT', data.data);
        return data.data;
    },

    async update({ commit }, { id, ...payload }) {
        const { data } = await documentArticleService.update(id, payload);
        commit('UPSERT_ITEM', data.data);
        commit('SET_CURRENT', data.data);
        return data.data;
    },

    async destroy({ commit }, id) {
        await documentArticleService.destroy(id);
        commit('REMOVE_ITEM', id);
    },

    async publish({ commit }, id) {
        const { data } = await documentArticleService.publish(id);
        commit('UPSERT_ITEM', data.data);
        commit('SET_CURRENT', data.data);
        return data.data;
    },

    async unpublish({ commit }, id) {
        const { data } = await documentArticleService.unpublish(id);
        commit('UPSERT_ITEM', data.data);
        commit('SET_CURRENT', data.data);
        return data.data;
    },

    async previewUrl(_, id) {
        const { data } = await documentArticleService.previewUrl(id);
        return data.url;
    },
};

const getters = {
    drafts: (state) => state.items.filter(i => i.status === 'draft'),
    published: (state) => state.items.filter(i => i.status === 'published'),
};

export default {
    namespaced: true,
    state,
    mutations,
    actions,
    getters,
};
```

- [ ] **Step 2: Register the module**

Open `resources/js/store/index.js` and find the modules block (it imports each Vuex module). Add:

```js
import documentArticles from './modules/documentArticles';
```

In the `modules: { … }` object add:

```js
documentArticles,
```

- [ ] **Step 3: Smoke-test in browser dev console**

After `./dev.sh` is running, open the admin panel logged in, then in DevTools console:

```js
window.__VUE_DEVTOOLS_GLOBAL_HOOK__ // optional
// or:
$store.dispatch('documentArticles/list').then(r => console.log($store.state.documentArticles.items))
```

Expected: an array (empty if no rows) — confirms the module is registered and the API endpoint is reachable.

- [ ] **Step 4: Commit**

```bash
git add resources/js/store/modules/documentArticles.js resources/js/store/index.js
git commit -m "feat(documents): documentArticles Vuex module"
```

---

## Phase 5 — Frontend components

### Task 14: `DropZone.vue`

**Files:**
- Create: `resources/js/components/Admin/Documents/DropZone.vue`

- [ ] **Step 1: Implement the component**

```vue
<template>
    <div
        class="drop-zone"
        :class="{ 'drop-zone--active': isDragging, 'drop-zone--busy': isProcessing }"
        @dragover.prevent="isDragging = true"
        @dragleave="isDragging = false"
        @drop.prevent="onDrop"
        @click="$refs.input.click()"
    >
        <input ref="input" type="file" accept=".docx" class="hidden" @change="onPick" />
        <div v-if="!isProcessing" class="text-center">
            <p class="text-base font-bold text-horizon-700">Drop a Word document here</p>
            <p class="text-sm text-horizon-500 mt-1">or click to choose a .docx file (max 10 MB)</p>
        </div>
        <div v-else class="text-center">
            <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin mx-auto"></div>
            <p class="text-sm text-horizon-500 mt-3">{{ progressMessage }}</p>
        </div>
    </div>
</template>

<script>
import mammoth from 'mammoth';
import JSZip from 'jszip';

export default {
    name: 'DropZone',
    emits: ['imported', 'error'],
    data() {
        return {
            isDragging: false,
            isProcessing: false,
            progressMessage: '',
        };
    },
    methods: {
        onDrop(event) {
            this.isDragging = false;
            const file = event.dataTransfer.files[0];
            if (file) this.handleFile(file);
        },
        onPick(event) {
            const file = event.target.files[0];
            if (file) this.handleFile(file);
            event.target.value = '';
        },
        async handleFile(file) {
            if (!file.name.toLowerCase().endsWith('.docx')) {
                this.$emit('error', 'Only .docx files are supported.');
                return;
            }
            if (file.size > 10 * 1024 * 1024) {
                this.$emit('error', 'File is larger than 10 MB.');
                return;
            }

            this.isProcessing = true;
            try {
                this.progressMessage = 'Reading document…';
                const arrayBuffer = await file.arrayBuffer();

                this.progressMessage = 'Extracting metadata…';
                const metadata = await this.extractMetadata(arrayBuffer);

                this.progressMessage = 'Converting body…';
                const { html, images } = await this.convertWithMammoth(arrayBuffer);

                this.progressMessage = 'Uploading…';
                const created = await this.$store.dispatch('documentArticles/import', {
                    docx: file,
                    html,
                    images,
                    metadata,
                });

                this.$emit('imported', created);
            } catch (err) {
                this.$emit('error', err?.response?.data?.message || err.message || 'Import failed.');
            } finally {
                this.isProcessing = false;
                this.progressMessage = '';
            }
        },
        async extractMetadata(arrayBuffer) {
            try {
                const zip = await JSZip.loadAsync(arrayBuffer);
                const xml = await zip.file('docProps/core.xml')?.async('string');
                if (!xml) return {};
                const parser = new DOMParser();
                const doc = parser.parseFromString(xml, 'application/xml');
                const get = (ns, tag) => {
                    const el = doc.getElementsByTagNameNS(ns, tag)[0];
                    return el ? el.textContent : null;
                };
                return {
                    title: get('http://purl.org/dc/elements/1.1/', 'title'),
                    subtitle: get('http://purl.org/dc/elements/1.1/', 'subject'),
                    description: get('http://purl.org/dc/elements/1.1/', 'description'),
                    author_name: get('http://purl.org/dc/elements/1.1/', 'creator'),
                    keywords: get('http://schemas.openxmlformats.org/package/2006/metadata/core-properties', 'keywords'),
                };
            } catch {
                return {};
            }
        },
        async convertWithMammoth(arrayBuffer) {
            const images = {};
            let counter = 0;
            const result = await mammoth.convertToHtml(
                { arrayBuffer },
                {
                    convertImage: mammoth.images.imgElement(async (image) => {
                        const idx = counter++;
                        const buffer = await image.read();
                        const blob = new Blob([buffer], { type: image.contentType || 'image/png' });
                        images[idx] = blob;
                        return { 'data-pending-image': String(idx) };
                    }),
                }
            );
            return { html: result.value, images };
        },
    },
};
</script>

<style scoped>
.drop-zone {
    @apply border-2 border-dashed border-horizon-300 rounded-lg p-12 cursor-pointer flex items-center justify-center min-h-[180px] transition-colors;
    background: rgba(255, 255, 255, 0.5);
}
.drop-zone--active {
    @apply border-raspberry-500 bg-raspberry-50;
}
.drop-zone--busy {
    @apply cursor-default;
}
.hidden {
    display: none;
}
</style>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/components/Admin/Documents/DropZone.vue
git commit -m "feat(documents): DropZone with mammoth.js + JSZip metadata extraction"
```

---

### Task 15: `CoverImagePicker.vue`

**Files:**
- Create: `resources/js/components/Admin/Documents/CoverImagePicker.vue`

- [ ] **Step 1: Implement the component**

```vue
<template>
    <div class="space-y-3">
        <label class="block text-sm font-bold text-horizon-700">Cover image</label>
        <div v-if="extractedImages.length > 0" class="grid grid-cols-3 gap-2">
            <button
                v-for="img in extractedImages"
                :key="img.path"
                type="button"
                class="aspect-square border-2 rounded overflow-hidden bg-eggshell-100 transition-colors"
                :class="img.path === modelValue ? 'border-raspberry-500' : 'border-horizon-200 hover:border-horizon-400'"
                @click="$emit('update:modelValue', img.path)"
            >
                <img :src="'/storage/' + img.path" alt="" class="w-full h-full object-cover" />
            </button>
        </div>
        <p v-else class="text-sm text-horizon-500">No images were extracted from this document.</p>
        <button
            v-if="modelValue"
            type="button"
            class="text-sm text-horizon-500 hover:text-raspberry-500 underline"
            @click="$emit('update:modelValue', null)"
        >
            Clear cover image
        </button>
    </div>
</template>

<script>
export default {
    name: 'CoverImagePicker',
    props: {
        modelValue: { type: String, default: null }, // current cover_image_path
        htmlBody: { type: String, default: '' }, // article html body — we scan for <img src> within /storage/document-articles/
    },
    emits: ['update:modelValue'],
    computed: {
        extractedImages() {
            const matches = [...this.htmlBody.matchAll(/<img[^>]+src="\/storage\/(document-articles\/[^"]+)"/g)];
            const seen = new Set();
            return matches
                .map(m => ({ path: m[1] }))
                .filter(({ path }) => {
                    if (seen.has(path)) return false;
                    seen.add(path);
                    return true;
                });
        },
    },
};
</script>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/components/Admin/Documents/CoverImagePicker.vue
git commit -m "feat(documents): CoverImagePicker component"
```

---

### Task 16: `DocumentListPage.vue`

**Files:**
- Create: `resources/js/views/Admin/Documents/DocumentListPage.vue`

- [ ] **Step 1: Implement the page**

```vue
<template>
    <div class="space-y-8">
        <header>
            <h1 class="text-3xl font-black text-horizon-700">Documents</h1>
            <p class="text-horizon-500 mt-1">Drop a Word document to create a new article.</p>
        </header>

        <DropZone
            @imported="onImported"
            @error="onError"
        />

        <div v-if="errorMessage" class="bg-raspberry-50 border border-raspberry-200 text-raspberry-700 rounded p-4">
            {{ errorMessage }}
        </div>

        <section>
            <h2 class="text-xl font-bold text-horizon-700 mb-4">All documents</h2>
            <div v-if="loading" class="flex items-center gap-3 text-horizon-500">
                <div class="w-6 h-6 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
                Loading…
            </div>
            <table v-else-if="items.length > 0" class="w-full text-sm">
                <thead>
                    <tr class="text-left border-b border-horizon-200">
                        <th class="py-3">Title</th>
                        <th class="py-3">Status</th>
                        <th class="py-3">Imported</th>
                        <th class="py-3">By</th>
                        <th class="py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in items" :key="item.id" class="border-b border-horizon-100">
                        <td class="py-3">
                            <router-link :to="`/admin/documents/${item.id}/edit`" class="font-bold text-horizon-700 hover:text-raspberry-500">
                                {{ item.title }}
                            </router-link>
                        </td>
                        <td class="py-3">
                            <span
                                class="inline-block px-2 py-1 rounded text-xs font-bold"
                                :class="item.status === 'published' ? 'bg-spring-100 text-spring-700' : 'bg-savannah-100 text-savannah-700'"
                            >
                                {{ item.status }}
                            </span>
                        </td>
                        <td class="py-3 text-horizon-500">{{ formatDate(item.created_at) }}</td>
                        <td class="py-3 text-horizon-500">{{ item.importer?.name || '—' }}</td>
                        <td class="py-3 text-right space-x-2">
                            <router-link
                                :to="`/admin/documents/${item.id}/edit`"
                                class="text-horizon-500 hover:text-raspberry-500 underline"
                            >Edit</router-link>
                            <button
                                v-if="item.status === 'published'"
                                class="text-horizon-500 hover:text-raspberry-500 underline"
                                @click="unpublish(item.id)"
                            >Unpublish</button>
                            <button
                                v-else
                                class="text-horizon-500 hover:text-raspberry-500 underline"
                                @click="publish(item.id)"
                            >Publish</button>
                            <button class="text-raspberry-500 hover:text-raspberry-700 underline" @click="confirmDelete(item)">Delete</button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-else class="text-horizon-500">No documents yet — drop a .docx above.</p>
        </section>
    </div>
</template>

<script>
import { mapActions, mapState } from 'vuex';
import DropZone from '@/components/Admin/Documents/DropZone.vue';

export default {
    name: 'DocumentListPage',
    components: { DropZone },
    data() {
        return { errorMessage: '' };
    },
    computed: {
        ...mapState('documentArticles', ['items', 'loading']),
    },
    async created() {
        await this.list();
    },
    methods: {
        ...mapActions('documentArticles', ['list', 'publish', 'unpublish', 'destroy']),
        onImported(created) {
            this.errorMessage = '';
            this.$router.push(`/admin/documents/${created.id}/edit`);
        },
        onError(message) {
            this.errorMessage = message;
        },
        async confirmDelete(item) {
            if (!window.confirm(`Delete "${item.title}"? This cannot be undone.`)) return;
            await this.destroy(item.id);
        },
        formatDate(s) {
            if (!s) return '';
            const d = new Date(s);
            return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
        },
    },
};
</script>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/views/Admin/Documents/DocumentListPage.vue
git commit -m "feat(documents): DocumentListPage view"
```

---

### Task 17: `DocumentEditor.vue`

**Files:**
- Create: `resources/js/views/Admin/Documents/DocumentEditor.vue`

- [ ] **Step 1: Implement the editor view**

```vue
<template>
    <div v-if="article" class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <section class="space-y-4">
            <header>
                <h1 class="text-3xl font-black text-horizon-700">{{ article.title }}</h1>
                <p class="text-horizon-500 mt-1">Status: {{ article.status }}</p>
            </header>

            <div>
                <label class="block text-sm font-bold text-horizon-700 mb-1">Title</label>
                <input v-model="form.title" type="text" class="w-full border border-horizon-200 rounded px-3 py-2" />
            </div>

            <div>
                <label class="block text-sm font-bold text-horizon-700 mb-1">Slug</label>
                <input v-model="form.slug" type="text" class="w-full border border-horizon-200 rounded px-3 py-2 font-mono text-sm" />
                <p class="text-xs text-horizon-500 mt-1">Public URL: <code>/articles/{{ form.slug }}</code></p>
            </div>

            <div>
                <label class="block text-sm font-bold text-horizon-700 mb-1">Subtitle</label>
                <input v-model="form.subtitle" type="text" class="w-full border border-horizon-200 rounded px-3 py-2" />
            </div>

            <div>
                <label class="block text-sm font-bold text-horizon-700 mb-1">Meta description</label>
                <textarea v-model="form.description" rows="3" class="w-full border border-horizon-200 rounded px-3 py-2"></textarea>
                <p class="text-xs text-horizon-500 mt-1">Truncated to 160 chars on the public page.</p>
            </div>

            <div>
                <label class="block text-sm font-bold text-horizon-700 mb-1">Keywords (comma-separated)</label>
                <input v-model="form.keywords" type="text" class="w-full border border-horizon-200 rounded px-3 py-2" />
            </div>

            <div>
                <label class="block text-sm font-bold text-horizon-700 mb-1">Author byline</label>
                <input v-model="form.author_byline" type="text" class="w-full border border-horizon-200 rounded px-3 py-2" />
            </div>

            <CoverImagePicker v-model="form.cover_image_path" :html-body="form.html_body" />

            <div class="flex flex-wrap gap-3 pt-4 border-t border-horizon-200">
                <button class="bg-horizon-700 text-eggshell-50 rounded px-4 py-2 font-bold hover:bg-horizon-800" @click="save">Save</button>
                <button class="bg-eggshell-100 text-horizon-700 rounded px-4 py-2 font-bold hover:bg-eggshell-200" @click="openPreview">Preview</button>
                <button
                    v-if="article.status !== 'published'"
                    class="bg-raspberry-500 text-eggshell-50 rounded px-4 py-2 font-bold hover:bg-raspberry-600"
                    @click="onPublish"
                >Publish</button>
                <button
                    v-else
                    class="bg-raspberry-500 text-eggshell-50 rounded px-4 py-2 font-bold hover:bg-raspberry-600"
                    @click="onUnpublish"
                >Unpublish</button>
                <button class="ml-auto text-raspberry-500 hover:text-raspberry-700 underline" @click="onDelete">Delete</button>
            </div>

            <p v-if="successMessage" class="text-spring-700">{{ successMessage }}</p>
            <p v-if="errorMessage" class="text-raspberry-700">{{ errorMessage }}</p>
        </section>

        <section class="space-y-2">
            <label class="block text-sm font-bold text-horizon-700">Body</label>
            <div class="border border-horizon-200 rounded p-4 bg-white min-h-[600px]">
                <editor-content v-if="editor" :editor="editor" class="prose max-w-none" />
            </div>
        </section>
    </div>
    <div v-else class="text-horizon-500">Loading…</div>
</template>

<script>
import { mapActions, mapState } from 'vuex';
import { Editor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import Link from '@tiptap/extension-link';
import Image from '@tiptap/extension-image';
import Table from '@tiptap/extension-table';
import TableRow from '@tiptap/extension-table-row';
import TableCell from '@tiptap/extension-table-cell';
import TableHeader from '@tiptap/extension-table-header';
import TextStyle from '@tiptap/extension-text-style';
import Color from '@tiptap/extension-color';
import Highlight from '@tiptap/extension-highlight';
import CoverImagePicker from '@/components/Admin/Documents/CoverImagePicker.vue';

export default {
    name: 'DocumentEditor',
    components: { EditorContent, CoverImagePicker },
    data() {
        return {
            form: {
                title: '',
                slug: '',
                subtitle: '',
                description: '',
                keywords: '',
                author_byline: '',
                cover_image_path: null,
                html_body: '',
            },
            editor: null,
            successMessage: '',
            errorMessage: '',
        };
    },
    computed: {
        ...mapState('documentArticles', ['current']),
        article() { return this.current; },
    },
    async created() {
        const id = parseInt(this.$route.params.id, 10);
        await this.get(id);
        this.hydrateForm();
        this.mountEditor();
    },
    beforeUnmount() {
        if (this.editor) this.editor.destroy();
    },
    methods: {
        ...mapActions('documentArticles', ['get', 'update', 'publish', 'unpublish', 'destroy', 'previewUrl']),
        hydrateForm() {
            if (!this.article) return;
            this.form = {
                title: this.article.title || '',
                slug: this.article.slug || '',
                subtitle: this.article.subtitle || '',
                description: this.article.description || '',
                keywords: this.article.keywords || '',
                author_byline: this.article.author_byline || '',
                cover_image_path: this.article.cover_image_path,
                html_body: this.article.html_body || '',
            };
        },
        mountEditor() {
            this.editor = new Editor({
                content: this.form.html_body,
                extensions: [
                    StarterKit,
                    Underline,
                    Link.configure({ openOnClick: false }),
                    Image,
                    Table.configure({ resizable: false }),
                    TableRow,
                    TableHeader,
                    TableCell,
                    TextStyle,
                    Color,
                    Highlight,
                ],
                editorProps: {
                    attributes: { class: 'tiptap-editor focus:outline-none' },
                },
                onUpdate: ({ editor }) => {
                    this.form.html_body = editor.getHTML();
                },
            });
        },
        async save() {
            this.errorMessage = '';
            this.successMessage = '';
            try {
                await this.update({ id: this.article.id, ...this.form });
                this.successMessage = 'Saved.';
            } catch (e) {
                this.errorMessage = e?.response?.data?.message || 'Save failed.';
            }
        },
        async openPreview() {
            const url = await this.previewUrl(this.article.id);
            window.open(url, '_blank');
        },
        async onPublish() {
            await this.save();
            try {
                await this.publish(this.article.id);
                this.successMessage = 'Published.';
            } catch (e) {
                this.errorMessage = e?.response?.data?.message || 'Publish failed.';
            }
        },
        async onUnpublish() {
            try {
                await this.unpublish(this.article.id);
                this.successMessage = 'Unpublished.';
            } catch (e) {
                this.errorMessage = e?.response?.data?.message || 'Unpublish failed.';
            }
        },
        async onDelete() {
            if (!window.confirm(`Delete "${this.article.title}"? This cannot be undone.`)) return;
            await this.destroy(this.article.id);
            this.$router.push('/admin/documents');
        },
    },
};
</script>

<style scoped>
:deep(.tiptap-editor) {
    min-height: 540px;
    outline: none;
}
:deep(.tiptap-editor table) { border-collapse: collapse; width: 100%; margin: 12px 0; }
:deep(.tiptap-editor th), :deep(.tiptap-editor td) { border: 1px solid #E5E1D9; padding: 6px 10px; }
:deep(.tiptap-editor th) { background: #F0EBE0; }
:deep(.tiptap-editor img) { max-width: 100%; height: auto; }
</style>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/views/Admin/Documents/DocumentEditor.vue
git commit -m "feat(documents): DocumentEditor view with Tiptap canvas"
```

---

## Phase 6 — Wire-up

### Task 18: Router routes

**Files:**
- Modify: `resources/js/router/index.js`

- [ ] **Step 1: Add routes**

Open `resources/js/router/index.js`. Find the routes array and add (after the existing `/admin/insights/...` entries, with the same lazy-load pattern):

```js
{
    path: '/admin/documents',
    name: 'admin.documents.index',
    component: () => import('@/views/Admin/Documents/DocumentListPage.vue'),
    meta: { requiresAuth: true, requiresAdmin: true },
},
{
    path: '/admin/documents/:id/edit',
    name: 'admin.documents.edit',
    component: () => import('@/views/Admin/Documents/DocumentEditor.vue'),
    meta: { requiresAuth: true, requiresAdmin: true },
},
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/router/index.js
git commit -m "feat(documents): admin router routes"
```

---

### Task 19: Sidebar entry

**Files:**
- Modify: `resources/js/views/Admin/AdminPanel.vue`

- [ ] **Step 1: Add the nav item**

Open `resources/js/views/Admin/AdminPanel.vue`. Find `navItems:` (around line 168 — currently has the `{ id: 'insights', label: 'CMS', ... }` entry). Add immediately after the CMS line:

```js
{ id: 'documents', label: 'Documents', shortLabel: 'Docs', path: '/admin/documents' },
```

Then in the `getTabIcon` switch (or icon-mapping object), add an icon path for `documents`. Use a document-shaped path consistent with the other tabs:

```js
documents: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
```

- [ ] **Step 2: Smoke-test in browser**

Run `./dev.sh`, log in as admin, click `Documents` in the sidebar. Expected: `/admin/documents` loads, `DocumentListPage` shown, "No documents yet — drop a .docx above." visible.

- [ ] **Step 3: Commit**

```bash
git add resources/js/views/Admin/AdminPanel.vue
git commit -m "feat(documents): Documents sidebar entry"
```

---

## Phase 7 — End-to-end testing + final polish

### Task 20: Generate the rich docx fixture

**Files:**
- Create: `tests/fixtures/documents/sample-with-images-and-tables.docx`

- [ ] **Step 1: Generate via script**

Run:

```bash
php -r '
$zip = new ZipArchive;
$path = "tests/fixtures/documents/sample-with-images-and-tables.docx";
$zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$zip->addFromString("[Content_Types].xml", "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?><Types xmlns=\"http://schemas.openxmlformats.org/package/2006/content-types\"><Default Extension=\"xml\" ContentType=\"application/xml\"/><Default Extension=\"rels\" ContentType=\"application/vnd.openxmlformats-package.relationships+xml\"/><Default Extension=\"png\" ContentType=\"image/png\"/><Override PartName=\"/word/document.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml\"/><Override PartName=\"/word/_rels/document.xml.rels\" ContentType=\"application/vnd.openxmlformats-package.relationships+xml\"/><Override PartName=\"/docProps/core.xml\" ContentType=\"application/vnd.openxmlformats-package.core-properties+xml\"/></Types>");
$zip->addFromString("_rels/.rels", "<?xml version=\"1.0\" encoding=\"UTF-8\"?><Relationships xmlns=\"http://schemas.openxmlformats.org/package/2006/relationships\"><Relationship Id=\"rId1\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument\" Target=\"word/document.xml\"/><Relationship Id=\"rId2\" Type=\"http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties\" Target=\"docProps/core.xml\"/></Relationships>");
$zip->addFromString("word/_rels/document.xml.rels", "<?xml version=\"1.0\" encoding=\"UTF-8\"?><Relationships xmlns=\"http://schemas.openxmlformats.org/package/2006/relationships\"><Relationship Id=\"rId10\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/image\" Target=\"media/image1.png\"/></Relationships>");
$zip->addFromString("word/document.xml", "<?xml version=\"1.0\" encoding=\"UTF-8\"?><w:document xmlns:w=\"http://schemas.openxmlformats.org/wordprocessingml/2006/main\" xmlns:r=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships\" xmlns:wp=\"http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing\" xmlns:a=\"http://schemas.openxmlformats.org/drawingml/2006/main\" xmlns:pic=\"http://schemas.openxmlformats.org/drawingml/2006/picture\"><w:body><w:p><w:pPr><w:pStyle w:val=\"Heading1\"/></w:pPr><w:r><w:t>Big Heading</w:t></w:r></w:p><w:p><w:r><w:t>Body paragraph one.</w:t></w:r></w:p><w:tbl><w:tblPr/><w:tblGrid><w:gridCol w:w=\"5000\"/><w:gridCol w:w=\"5000\"/></w:tblGrid><w:tr><w:tc><w:p><w:r><w:t>Left</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Right</w:t></w:r></w:p></w:tc></w:tr></w:tbl></w:body></w:document>");
$zip->addFromString("docProps/core.xml", "<?xml version=\"1.0\" encoding=\"UTF-8\"?><cp:coreProperties xmlns:cp=\"http://schemas.openxmlformats.org/package/2006/metadata/core-properties\" xmlns:dc=\"http://purl.org/dc/elements/1.1/\"><dc:title>Rich Sample Title</dc:title><dc:creator>Sam Author</dc:creator><dc:description>A rich fixture with a table</dc:description><cp:keywords>rich,table</cp:keywords></cp:coreProperties>");
// Tiny 1x1 PNG.
$png = base64_decode("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=");
$zip->addFromString("word/media/image1.png", $png);
$zip->close();
echo "Wrote $path\n";
'
```

- [ ] **Step 2: Generate the malicious-html fixture**

```bash
php -r '
$zip = new ZipArchive;
$path = "tests/fixtures/documents/sample-with-malicious-html.docx";
$zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$zip->addFromString("[Content_Types].xml", "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?><Types xmlns=\"http://schemas.openxmlformats.org/package/2006/content-types\"><Default Extension=\"xml\" ContentType=\"application/xml\"/><Default Extension=\"rels\" ContentType=\"application/vnd.openxmlformats-package.relationships+xml\"/><Override PartName=\"/word/document.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml\"/><Override PartName=\"/docProps/core.xml\" ContentType=\"application/vnd.openxmlformats-package.core-properties+xml\"/></Types>");
$zip->addFromString("_rels/.rels", "<?xml version=\"1.0\" encoding=\"UTF-8\"?><Relationships xmlns=\"http://schemas.openxmlformats.org/package/2006/relationships\"><Relationship Id=\"rId1\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument\" Target=\"word/document.xml\"/><Relationship Id=\"rId2\" Type=\"http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties\" Target=\"docProps/core.xml\"/></Relationships>");
$zip->addFromString("word/document.xml", "<?xml version=\"1.0\" encoding=\"UTF-8\"?><w:document xmlns:w=\"http://schemas.openxmlformats.org/wordprocessingml/2006/main\"><w:body><w:p><w:r><w:t>Hello &lt;script&gt;alert(1)&lt;/script&gt; world</w:t></w:r></w:p></w:body></w:document>");
$zip->addFromString("docProps/core.xml", "<?xml version=\"1.0\" encoding=\"UTF-8\"?><cp:coreProperties xmlns:cp=\"http://schemas.openxmlformats.org/package/2006/metadata/core-properties\" xmlns:dc=\"http://purl.org/dc/elements/1.1/\"><dc:title>Malicious Sample</dc:title></cp:coreProperties>");
$zip->close();
echo "Wrote $path\n";
'
```

- [ ] **Step 3: Commit fixtures**

```bash
git add tests/fixtures/documents/sample-with-images-and-tables.docx tests/fixtures/documents/sample-with-malicious-html.docx
git commit -m "test(documents): add rich + malicious docx fixtures"
```

---

### Task 21: Run all backend tests + format

- [ ] **Step 1: Run the full Documents suite**

```bash
./vendor/bin/pest tests/Unit/Services/Documents tests/Feature/Documents
```

Expected: every test green.

- [ ] **Step 2: Run Pint formatter**

```bash
./vendor/bin/pint app/Models/DocumentArticle.php \
    app/Services/Documents \
    app/Http/Controllers/Api/Admin/DocumentArticleController.php \
    app/Http/Controllers/PublicDocumentArticleController.php \
    app/Http/Requests/Admin/DocumentArticleImportRequest.php \
    app/Http/Requests/Admin/DocumentArticleUpdateRequest.php \
    database/factories/DocumentArticleFactory.php \
    tests/Unit/Services/Documents \
    tests/Feature/Documents
```

Expected: every file PSR-12 clean.

- [ ] **Step 3: Run the entire test suite to catch regressions**

```bash
./vendor/bin/pest --parallel
```

Expected: full suite green; no existing test broken.

- [ ] **Step 4: Commit any formatting fixes**

```bash
git add -A
git commit -m "chore(format): pint pass on Documents module" || echo "nothing to format"
```

---

### Task 22: Playwright browser scenario

**Files:**
- Create: `tests/Browser/scenarios/document-articles-end-to-end.php`

> **Note:** Fynla's existing browser scenarios live under `tests/Browser/scenarios/` per CLAUDE.md Rule #15 — but the directory may not yet exist on this branch. Create the directory if needed.

- [ ] **Step 1: Create the directory**

```bash
mkdir -p tests/Browser/scenarios
```

- [ ] **Step 2: Write the scenario file**

Create `tests/Browser/scenarios/document-articles-end-to-end.php`:

```php
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
```

> **Implementation note:** the actual Playwright steps live in your usual driver (e.g. `mcp__playwright__browser_*` actions during a session). The scenario file above is the contract the driver follows — keep this list of acceptance assertions short and testable.

- [ ] **Step 3: Run the browser scenario**

The driver should:
1. Authenticate (email-code flow per CLAUDE.md "Authentication for Testing").
2. Navigate to `/admin/documents`.
3. Use `mcp__playwright__browser_file_upload` to upload `sample-with-images-and-tables.docx` onto the drop zone's hidden input.
4. Wait for redirect to `/admin/documents/{id}/edit`.
5. Snapshot the form, assert title/byline/description visible.
6. Click **Publish**.
7. Open `/articles/rich-sample-title` in a second tab.
8. Use `mcp__playwright__browser_evaluate` to read `document.title`, the meta tags, and to parse the JSON-LD block.
9. Assert all 13 GREEN conditions.
10. Repeat with the malicious fixture; assert `alert(1)` does not appear in `document.documentElement.outerHTML`.

If any GREEN condition fails, **enter the LOOP UNTIL CORRECT cycle** (CLAUDE.md Rule #15): diagnose with file:line evidence, fix root cause, re-run from step 1.

- [ ] **Step 4: Commit the scenario contract**

```bash
git add tests/Browser/scenarios/document-articles-end-to-end.php
git commit -m "test(documents): browser scenario contract for end-to-end import + publish"
```

---

### Task 23: Final verification + push

- [ ] **Step 1: Re-run the full test suite**

```bash
./vendor/bin/pest --parallel
```

Expected: green.

- [ ] **Step 2: Verify the existing Insights CMS still works**

Open `/admin/insights` in the running dev server, confirm:
- Article list loads.
- Editing an article works.
- Publishing an article still succeeds.

(This is the explicit "Documents must not touch Insights" check.)

- [ ] **Step 3: Verify migration + rollback round-trip**

```bash
php artisan migrate:rollback --step=1
php artisan migrate
```

Expected: rollback drops `document_articles`, re-migrate recreates it cleanly.

- [ ] **Step 4: Push the branch**

```bash
git push -u origin CMSFix
```

Expected: branch pushed to origin. Do NOT open a PR — CSJ decides when this graduates from sandbox to production.

---

## Self-Review

### Spec coverage
- ✅ Sandboxed admin tab labelled "Documents" — Task 19
- ✅ Drag-drop docx import — Task 14 (DropZone)
- ✅ Browser-side mammoth.js conversion — Task 14
- ✅ Server-side metadata re-extraction — Task 5 + Task 7
- ✅ HTML sanitisation via mews/purifier — Tasks 2 + 6
- ✅ Image storage to disk — Task 7
- ✅ `document_articles` table — Task 3
- ✅ Tiptap WYSIWYG with table + image extensions — Task 17
- ✅ Draft / published / archived status flow — Tasks 4, 10
- ✅ Signed-token preview URLs — Task 4 (`previewUrl`) + Task 11
- ✅ Public `/articles/{slug}` route with full SEO — Task 11
- ✅ Pest unit + feature tests — Tasks 5, 6, 7, 10, 11
- ✅ Playwright browser scenario — Task 22
- ✅ Existing Insights CMS untouched — verified in Task 23 step 2
- ✅ All YAGNI items deferred (none built) — implicit by absence

### Placeholder scan
- No "TBD"/"TODO" markers in any task.
- All code blocks complete.
- All test code shows actual assertions.

### Type / signature consistency
- `DocumentArticleImporter::import()` signature matches the call in `DocumentArticleController::store()` (`docxFile`, `html`, `imageBlobs`, `clientMetadata`, `importedBy`).
- `SlugGenerator::unique($base, ?int $ignoreId)` — used consistently in both Task 7 test and importer.
- `DocumentArticle::previewUrl()` returns string, consumed by `previewUrl()` controller method which returns JSON `{url: string}`.
- Vuex action names match service methods: `list/get/import/update/destroy/publish/unpublish/previewUrl`.

---

## Execution Handoff

Plan complete and saved to `/Users/CSJ/Desktop/fynla/May/May1Updates/2026-05-01-document-articles-cms-plan.md`. Two execution options:

**1. Subagent-Driven (recommended)** — I dispatch a fresh subagent per task, review between tasks, fast iteration.

**2. Inline Execution** — Execute tasks in this session using `superpowers:executing-plans`, batch execution with checkpoints.

Which approach?
