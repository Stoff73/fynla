# Admin Insights CMS Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Status:** Amended — 17 April 2026 — conflicts resolved against codebase audit

**Goal:** Ship an admin-facing CMS for authoring, scheduling, and publishing insight articles on fynla.org, with database-driven hub and landing-page hero rendering, while keeping the 8 existing bespoke Vue articles fully intact.

**Architecture:** 3 new tables (`insight_articles`, `insight_templates`, `insight_article_revisions`) drive a block-based rendering system. Backend services own the business logic (publish transitions, feature toggle, image resizing, SEO). Public rendering uses a new `InsightArticlePage` + `ArticleBlockRenderer` with 11 per-block components. Admin rendering uses a split-layout editor (`ArticleEditor`) with a block canvas and field form. A Laravel middleware server-renders SEO meta tags into Blade for social crawlers via a new `@stack('head')` placeholder in `app.blade.php`. Existing bespoke articles get DB rows flagged `is_bespoke=true` so hub/landing can treat them uniformly, but their Vue components still own rendering. A `VITE_INSIGHTS_CMS_ENABLED` feature flag gates the public frontend activation so backend phases can deploy independently.

**Tech Stack:** Laravel 10, PHP 8.2, Vue 3, Vuex, MySQL 8, Tailwind, Pest. New dependencies: `intervention/image` (composer — image resizing), `dompurify` (npm — frontend XSS defence). Backend HTML sanitisation uses PHP's built-in `strip_tags` with a restricted tag allowlist — no HTMLPurifier dependency.

**Spec:** `docs/superpowers/specs/2026-04-17-admin-insights-cms-design.md`

**Phases:**
1. Data layer — migrations, models, factories, seeder (Tasks 1-8)
2. Backend services — article, template, image, SEO, observer, scheduled job (Tasks 9-15)
3. API layer — form requests, resources, 4 controllers, SEO middleware (Tasks 16-22). Task 23 (sitemap) deferred — no existing `SitemapController` to extend.
4. Public frontend — service, Vuex module, article page, block renderer, 11 public block components, router, hub page refactor, landing page refactor (Tasks 24-33)
5. Admin frontend — list page, editor, block picker, bespoke notice, template list, 11 admin block components, image uploader, admin router, admin tab nav, feature flag (Tasks 34-41c)
6. Polish — architecture tests, browser testing, deploy notes (Tasks 42-44)

**Commits:** One atomic commit per task (shown at the end of each task). Never skip hooks.

**Critical Fynla rules to honour throughout:**
- All tax values via `TaxConfigService` (backend) / `@/constants/taxConfig` (frontend) — zero hardcoded tax years, allowances, thresholds.
- British spelling in user-facing text (Customise, Optimisation). American in code.
- Acronyms spelled out except ISA.
- Design system v1.4.0 only: `raspberry-*`, `horizon-*`, `spring-*`, `violet-*`, `savannah-*`, `eggshell-*`, `neutral-*`. No amber, orange, `primary-*`, `secondary-*`, or `gray-*` for general UI.
- **Reuse global CSS classes from `resources/css/app.css`**: `.card`, `.card-lg`, `.card-sm`, `.card-warning`, `.card-success`, `.modal-overlay`, `.modal`, `.modal-header`, `.modal-footer`, `.badge-warning`, `.badge-success`, `.badge-info`, `.badge-error`, `.btn-primary`, `.btn-secondary`. Never redefine these in component `<style scoped>`.
- **Admin auth convention**: use `permission:admin.access` middleware (not the `admin` alias) — matches the existing admin route group in `routes/api.php`. New admin controllers also add a constructor-level `$this->middleware('permission:admin.access')` defence-in-depth check, mirroring `AdminController`.
- Form modals emit `save`, parent owns the API call.
- `currencyMixin` for all currency formatting — never redefine `formatCurrency`.
- `declare(strict_types=1);` on every PHP file.

---

## Phase 1 — Data Layer

### Task 1: Migration — `insight_articles` table

**Files:**
- Create: `database/migrations/2026_04_17_090001_create_insight_articles_table.php`

- [ ] **Step 1: Create the migration file**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('insight_articles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->text('summary');
            $table->enum('category', [
                'tax-changes',
                'pensions',
                'savings-isa',
                'estate-planning',
                'platform-updates',
            ]);
            $table->json('tags')->nullable();
            $table->string('hero_image_path')->nullable();
            $table->string('hero_image_card_path')->nullable();
            $table->string('hero_image_thumb_path')->nullable();
            $table->json('body_blocks')->nullable();
            $table->foreignId('template_id')->nullable()->constrained('insight_templates')->nullOnDelete();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_bespoke')->default(false);
            $table->string('bespoke_component')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('canonical_url')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('is_featured');
            $table->index('published_at');
            $table->index('category');
            $table->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insight_articles');
    }
};
```

Note: `template_id` references `insight_templates` which is created in Task 2 — this migration's timestamp (090001) comes BEFORE Task 2's (090002), so `template_id` needs to be added later. Rewrite above without the `constrained()` call:

Replace the `template_id` line with: `$table->foreignId('template_id')->nullable();` and add a separate migration after `insight_templates` exists to add the foreign key constraint, OR change the migration timestamps so `insight_templates` (Task 2) runs first. Use the second approach: rename this file to `2026_04_17_090002_create_insight_articles_table.php` and Task 2's to `2026_04_17_090001_create_insight_templates_table.php`. Then `constrained('insight_templates')->nullOnDelete()` works.

- [ ] **Step 2: Commit**

```bash
git add database/migrations/2026_04_17_090002_create_insight_articles_table.php
git commit -m "feat(insights): add insight_articles table migration"
```

---

### Task 2: Migration — `insight_templates` table

**Files:**
- Create: `database/migrations/2026_04_17_090001_create_insight_templates_table.php`

- [ ] **Step 1: Create the migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('insight_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->json('body_blocks');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insight_templates');
    }
};
```

- [ ] **Step 2: Run both migrations to verify they apply cleanly**

```bash
php artisan migrate
```

Expected: `insight_templates` and `insight_articles` both created, no errors.

- [ ] **Step 3: Rollback and re-apply to verify reversibility**

```bash
php artisan migrate:rollback --step=2 && php artisan migrate
```

Expected: both down() and up() run cleanly.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_04_17_090001_create_insight_templates_table.php
git commit -m "feat(insights): add insight_templates table migration"
```

---

### Task 3: Migration — `insight_article_revisions` table

**Files:**
- Create: `database/migrations/2026_04_17_090003_create_insight_article_revisions_table.php`

- [ ] **Step 1: Create the migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('insight_article_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('insight_articles')->cascadeOnDelete();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->text('summary');
            $table->json('body_blocks')->nullable();
            $table->foreignId('saved_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('saved_at');

            $table->index('article_id');
            $table->index('saved_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insight_article_revisions');
    }
};
```

- [ ] **Step 2: Apply and verify**

```bash
php artisan migrate
php artisan migrate:status | grep insight_article_revisions
```

Expected: `Ran` status shown.

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_04_17_090003_create_insight_article_revisions_table.php
git commit -m "feat(insights): add insight_article_revisions table migration"
```

---

### Task 4: Model — `InsightArticle`

**Files:**
- Create: `app/Models/Insights/InsightArticle.php`

- [ ] **Step 1: Create the model**

```php
<?php

declare(strict_types=1);

namespace App\Models\Insights;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;

class InsightArticle extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Auditable;

    protected $fillable = [
        'slug',
        'title',
        'subtitle',
        'summary',
        'category',
        'tags',
        'hero_image_path',
        'hero_image_card_path',
        'hero_image_thumb_path',
        'body_blocks',
        'template_id',
        'status',
        'is_featured',
        'is_bespoke',
        'bespoke_component',
        'published_at',
        'scheduled_at',
        'author_id',
        'meta_title',
        'meta_description',
        'canonical_url',
    ];

    protected $casts = [
        'tags' => 'array',
        'body_blocks' => 'array',
        'is_featured' => 'boolean',
        'is_bespoke' => 'boolean',
        'published_at' => 'datetime',
        'scheduled_at' => 'datetime',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(InsightTemplate::class, 'template_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(InsightArticleRevision::class, 'article_id')->orderByDesc('saved_at');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeBespoke(Builder $query, bool $bespoke = true): Builder
    {
        return $query->where('is_bespoke', $bespoke);
    }

    public function scopeScheduledDue(Builder $query): Builder
    {
        return $query->where('status', 'draft')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now());
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Models/Insights/InsightArticle.php
git commit -m "feat(insights): add InsightArticle model"
```

---

### Task 5: Model — `InsightTemplate`

**Files:**
- Create: `app/Models/Insights/InsightTemplate.php`

- [ ] **Step 1: Create the model**

```php
<?php

declare(strict_types=1);

namespace App\Models\Insights;

use App\Models\User;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InsightTemplate extends Model
{
    use HasFactory;
    use Auditable;

    protected $fillable = [
        'name',
        'description',
        'body_blocks',
        'created_by',
    ];

    protected $casts = [
        'body_blocks' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(InsightArticle::class, 'template_id');
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Models/Insights/InsightTemplate.php
git commit -m "feat(insights): add InsightTemplate model"
```

---

### Task 6: Model — `InsightArticleRevision`

**Files:**
- Create: `app/Models/Insights/InsightArticleRevision.php`

- [ ] **Step 1: Create the model (append-only — no `update` or `delete`)**

```php
<?php

declare(strict_types=1);

namespace App\Models\Insights;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class InsightArticleRevision extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'article_id',
        'title',
        'subtitle',
        'summary',
        'body_blocks',
        'saved_by',
        'saved_at',
    ];

    protected $casts = [
        'body_blocks' => 'array',
        'saved_at' => 'datetime',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(InsightArticle::class, 'article_id');
    }

    public function savedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saved_by');
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException('InsightArticleRevision is append-only; updates are not permitted.');
        });

        static::deleting(function (): void {
            throw new RuntimeException('InsightArticleRevision is append-only; deletions are not permitted.');
        });
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Models/Insights/InsightArticleRevision.php
git commit -m "feat(insights): add InsightArticleRevision append-only model"
```

---

### Task 7: Factories for all three models

**Files:**
- Create: `database/factories/Insights/InsightArticleFactory.php`
- Create: `database/factories/Insights/InsightTemplateFactory.php`
- Create: `database/factories/Insights/InsightArticleRevisionFactory.php`

- [ ] **Step 1: `InsightArticleFactory`**

```php
<?php

declare(strict_types=1);

namespace Database\Factories\Insights;

use App\Models\Insights\InsightArticle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class InsightArticleFactory extends Factory
{
    protected $model = InsightArticle::class;

    public function definition(): array
    {
        $title = $this->faker->sentence(6);

        return [
            'slug' => Str::slug($title).'-'.$this->faker->unique()->randomNumber(4),
            'title' => $title,
            'subtitle' => $this->faker->sentence(10),
            'summary' => $this->faker->paragraph(2),
            'category' => $this->faker->randomElement([
                'tax-changes', 'pensions', 'savings-isa', 'estate-planning', 'platform-updates',
            ]),
            'tags' => [$this->faker->word(), $this->faker->word()],
            'body_blocks' => [
                ['type' => 'heading', 'level' => 2, 'text' => 'Overview'],
                ['type' => 'paragraph', 'html' => '<p>'.$this->faker->paragraph().'</p>'],
            ],
            'status' => 'draft',
            'is_featured' => false,
            'is_bespoke' => false,
            'author_id' => User::factory(),
        ];
    }

    public function published(): self
    {
        return $this->state(fn () => [
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function featured(): self
    {
        return $this->state(fn () => ['is_featured' => true]);
    }

    public function bespoke(string $component = 'StocksSharesIsaUkPage'): self
    {
        return $this->state(fn () => [
            'is_bespoke' => true,
            'bespoke_component' => $component,
            'body_blocks' => [],
        ]);
    }

    public function scheduled(\DateTimeInterface $at): self
    {
        return $this->state(fn () => [
            'status' => 'draft',
            'scheduled_at' => $at,
        ]);
    }
}
```

- [ ] **Step 2: `InsightTemplateFactory`**

```php
<?php

declare(strict_types=1);

namespace Database\Factories\Insights;

use App\Models\Insights\InsightTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InsightTemplateFactory extends Factory
{
    protected $model = InsightTemplate::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(3, true),
            'description' => $this->faker->sentence(),
            'body_blocks' => [
                ['type' => 'heading', 'level' => 2, 'text' => 'Section'],
                ['type' => 'paragraph', 'html' => '<p>Template paragraph</p>'],
            ],
            'created_by' => User::factory(),
        ];
    }
}
```

- [ ] **Step 3: `InsightArticleRevisionFactory`**

```php
<?php

declare(strict_types=1);

namespace Database\Factories\Insights;

use App\Models\Insights\InsightArticle;
use App\Models\Insights\InsightArticleRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InsightArticleRevisionFactory extends Factory
{
    protected $model = InsightArticleRevision::class;

    public function definition(): array
    {
        return [
            'article_id' => InsightArticle::factory(),
            'title' => $this->faker->sentence(6),
            'subtitle' => null,
            'summary' => $this->faker->paragraph(),
            'body_blocks' => [],
            'saved_by' => User::factory(),
            'saved_at' => now(),
        ];
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add database/factories/Insights/
git commit -m "feat(insights): add factories for article, template, revision models"
```

---

### Task 8: Seeder — `ExistingInsightsMetadataSeeder`

**Files:**
- Create: `database/seeders/ExistingInsightsMetadataSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php` — add the call.

- [ ] **Step 1: Create the seeder**

Pull the 8 articles from the hardcoded `articles` array currently in `resources/js/views/Public/insights/InsightsHubPage.vue` (lines 219-288). Mirror into the seeder. The component names map from `router/index.js` lines 44-51. Note: the hub page's array lists 9 entries, but one of them (`isa-allowance-2025-26`) maps to `IsaAllowance202526Page` which is the 8th bespoke file — codebase audit confirmed only 8 bespoke Vue files exist. Use the 8 component names listed in the seeder code below.

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Insights\InsightArticle;
use App\Models\User;
use Illuminate\Database\Seeder;

class ExistingInsightsMetadataSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::where('is_admin', true)->first() ?? User::first();
        if (!$author) {
            $this->command->warn('ExistingInsightsMetadataSeeder: no user found, skipping.');
            return;
        }

        $articles = [
            [
                'slug' => 'how-much-to-retire-uk',
                'title' => 'How Much Do I Need to Retire in the UK? A Realistic Guide',
                'summary' => 'Calculate your UK retirement number using 2026 PLSA living standards. Pension pot sizes needed and how to bridge the State Pension gap.',
                'category' => 'pensions',
                'tags' => ['Pensions'],
                'hero_image_card_path' => 'insights/legacy/how-much-to-retire-uk.jpg',
                'bespoke_component' => 'HowMuchToRetireUkPage',
                'published_at' => '2026-04-14 00:00:00',
            ],
            [
                'slug' => 'stocks-shares-isa-uk',
                'title' => 'What Is a Stocks and Shares ISA? How It Works, Benefits & Risks',
                'summary' => 'A complete guide to Stocks and Shares ISAs — how they work, what you can invest in, tax benefits, risks, fees, and how to choose a platform.',
                'category' => 'savings-isa',
                'tags' => ['Savings & ISA'],
                'hero_image_card_path' => 'insights/legacy/stocks-shares-isa.jpg',
                'bespoke_component' => 'StocksSharesIsaUkPage',
                'published_at' => '2026-04-13 00:00:00',
            ],
            [
                'slug' => 'isa-guide-uk',
                'title' => 'The Ultimate Guide to ISAs in the UK: Types, Rules & Best Options',
                'summary' => 'Everything you need to know about ISAs in 2026 — types, allowances, rules, and how to choose the right one for your goals.',
                'category' => 'savings-isa',
                'tags' => ['Savings & ISA'],
                'hero_image_card_path' => 'insights/legacy/isa-guide-uk.jpg',
                'bespoke_component' => 'IsaGuideUkPage',
                'published_at' => '2026-04-08 00:00:00',
            ],
            [
                'slug' => 'retirement-planning-uk',
                'title' => 'The Complete Guide to Retirement Planning in the UK',
                'summary' => 'Plan a retirement that lasts — pensions, State Pension, ISAs, drawdown strategies, tax and how to estimate what you will need.',
                'category' => 'pensions',
                'tags' => ['Pensions'],
                'hero_image_card_path' => 'insights/legacy/retirement-planning-uk.jpg',
                'bespoke_component' => 'RetirementPlanningUkPage',
                'published_at' => '2026-04-08 00:00:00',
            ],
            [
                'slug' => 'inheritance-tax-uk',
                'title' => 'Inheritance Tax Explained: Thresholds, Rules & How to Calculate IHT',
                'summary' => "Understand UK inheritance tax with our 2026 guide. Learn IHT thresholds, nil rate bands, calculation methods and strategies to reduce your estate's tax bill.",
                'category' => 'estate-planning',
                'tags' => ['Estate planning'],
                'hero_image_card_path' => 'insights/legacy/inheritance-tax-uk.jpg',
                'bespoke_component' => 'InheritanceTaxExplainedPage',
                'published_at' => '2026-04-01 00:00:00',
            ],
            [
                'slug' => 'pension-contribution-limits-uk',
                'title' => 'Pension Contribution Limits UK 2026/27: How Much Can You Pay In?',
                'summary' => 'Find out the 2026/27 pension contribution limits, annual allowance, tax relief rates and carry forward rules. Updated guide for UK savers.',
                'category' => 'pensions',
                'tags' => ['Pensions'],
                'hero_image_card_path' => 'insights/legacy/pension-contribution-limits.jpg',
                'bespoke_component' => 'PensionContributionLimitsPage',
                'published_at' => '2026-04-01 00:00:00',
            ],
            [
                'slug' => 'pension-iht-changes-2027',
                'title' => 'Pension Inheritance Tax Changes: April 2027',
                'summary' => "From April 2027, unused pension pots will be included in your estate for Inheritance Tax. Here's what's changing and what you can do.",
                'category' => 'pensions',
                'tags' => ['Pensions', 'Estate planning'],
                'hero_image_card_path' => 'insights/legacy/pension-iht-changes.jpg',
                'bespoke_component' => 'PensionIhtChanges2027Page',
                'published_at' => '2026-03-01 00:00:00',
            ],
            [
                'slug' => 'isa-allowance-2025-26',
                'title' => 'ISA Allowance: Make the Most of Your Tax-Free Allowance',
                'summary' => 'The ISA allowance remains at the annual limit. Types, deadlines, and strategies for maximising your tax-free savings.',
                'category' => 'savings-isa',
                'tags' => ['Savings & ISA'],
                'hero_image_card_path' => 'insights/legacy/isa-allowance.jpg',
                'bespoke_component' => 'IsaAllowance202526Page',
                'published_at' => '2025-04-01 00:00:00',
            ],
        ];

        foreach ($articles as $data) {
            InsightArticle::updateOrCreate(
                ['slug' => $data['slug']],
                array_merge($data, [
                    'status' => 'published',
                    'is_bespoke' => true,
                    'is_featured' => false,
                    'body_blocks' => [],
                    'author_id' => $author->id,
                ]),
            );
        }

        $this->command->info('ExistingInsightsMetadataSeeder: '.count($articles).' bespoke articles seeded.');
    }
}
```

- [ ] **Step 2: Register in `DatabaseSeeder::run()`**

Add the call in the same position as other content seeders (near existing content/reference seeders). Exact line depends on the current file — place it after `TaxProductReferenceSeeder::class` to keep content seeders grouped:

```php
$this->call([
    // ... existing seeders ...
    ExistingInsightsMetadataSeeder::class,
]);
```

- [ ] **Step 3: Run the seeder and verify 9 rows exist**

```bash
php artisan db:seed --class=ExistingInsightsMetadataSeeder
php artisan tinker --execute="echo \App\Models\Insights\InsightArticle::where('is_bespoke', true)->count();"
```

Expected: `9`

- [ ] **Step 4: Run again to verify idempotency**

```bash
php artisan db:seed --class=ExistingInsightsMetadataSeeder
php artisan tinker --execute="echo \App\Models\Insights\InsightArticle::where('is_bespoke', true)->count();"
```

Expected: still `9` (not 18).

- [ ] **Step 5: Commit**

```bash
git add database/seeders/ExistingInsightsMetadataSeeder.php database/seeders/DatabaseSeeder.php
git commit -m "feat(insights): seed metadata for 8 bespoke existing articles"
```

---

## Phase 2 — Backend Services

### Task 9: `InsightArticleService` + unit tests

**Files:**
- Create: `app/Services/Insights/InsightArticleService.php`
- Create: `tests/Unit/Services/Insights/InsightArticleServiceTest.php`

**Responsibilities:** CRUD, status transitions (publish, archive, unarchive), feature toggle (auto-unfeature previous), revision writing, re-sync from template, slug generation/uniqueness.

- [ ] **Step 1: Write the failing tests first**

```php
<?php

declare(strict_types=1);

use App\Models\Insights\InsightArticle;
use App\Models\Insights\InsightTemplate;
use App\Models\User;
use App\Services\Insights\InsightArticleService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->service = app(InsightArticleService::class);
});

it('creates an article as draft by default', function () {
    $article = $this->service->create([
        'title' => 'Test Article',
        'summary' => 'Short summary',
        'category' => 'pensions',
    ], $this->admin);

    expect($article->status)->toBe('draft')
        ->and($article->slug)->toBe('test-article')
        ->and($article->author_id)->toBe($this->admin->id);
});

it('generates a unique slug when one collides', function () {
    InsightArticle::factory()->create(['slug' => 'test-article']);

    $article = $this->service->create([
        'title' => 'Test Article',
        'summary' => 's',
        'category' => 'pensions',
    ], $this->admin);

    expect($article->slug)->toBe('test-article-2');
});

it('publishes an article and sets published_at', function () {
    $article = InsightArticle::factory()->create(['status' => 'draft']);

    $this->service->publish($article);

    expect($article->fresh()->status)->toBe('published')
        ->and($article->fresh()->published_at)->not->toBeNull();
});

it('auto-unfeatures previously featured article when featuring a new one', function () {
    $previous = InsightArticle::factory()->published()->featured()->create();
    $next = InsightArticle::factory()->published()->create();

    $this->service->setFeatured($next);

    expect($previous->fresh()->is_featured)->toBeFalse()
        ->and($next->fresh()->is_featured)->toBeTrue();
});

it('archives an article without deleting it', function () {
    $article = InsightArticle::factory()->published()->create();

    $this->service->archive($article);

    expect($article->fresh()->status)->toBe('archived')
        ->and($article->fresh()->deleted_at)->toBeNull();
});

it('writes a revision on every update', function () {
    $article = InsightArticle::factory()->create();

    $this->service->update($article, ['title' => 'New Title'], $this->admin);

    expect($article->fresh()->revisions()->count())->toBe(1)
        ->and($article->fresh()->revisions()->first()->title)->toBe('New Title');
});

it('resyncs blocks from the article template', function () {
    $template = InsightTemplate::factory()->create([
        'body_blocks' => [['type' => 'heading', 'level' => 2, 'text' => 'Fresh']],
    ]);
    $article = InsightArticle::factory()->create([
        'template_id' => $template->id,
        'body_blocks' => [['type' => 'heading', 'level' => 2, 'text' => 'Old']],
    ]);

    $this->service->resyncFromTemplate($article, $this->admin);

    expect($article->fresh()->body_blocks)->toBe([
        ['type' => 'heading', 'level' => 2, 'text' => 'Fresh'],
    ]);
});

it('returns null for featured article when nothing is featured', function () {
    InsightArticle::factory()->count(3)->published()->create();

    expect($this->service->getFeatured())->toBeNull();
});

it('returns the featured article when one is flagged', function () {
    InsightArticle::factory()->count(2)->published()->create();
    $featured = InsightArticle::factory()->published()->featured()->create();

    expect($this->service->getFeatured()->id)->toBe($featured->id);
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
./vendor/bin/pest tests/Unit/Services/Insights/InsightArticleServiceTest.php
```

Expected: all tests fail with `Class App\Services\Insights\InsightArticleService not found`.

- [ ] **Step 3: Implement the service**

```php
<?php

declare(strict_types=1);

namespace App\Services\Insights;

use App\Models\Insights\InsightArticle;
use App\Models\Insights\InsightArticleRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InsightArticleService
{
    public function create(array $data, User $author): InsightArticle
    {
        $data['slug'] = $this->uniqueSlug($data['slug'] ?? Str::slug($data['title']));
        $data['author_id'] = $author->id;
        $data['status'] = $data['status'] ?? 'draft';
        $data['body_blocks'] = $data['body_blocks'] ?? [];

        return InsightArticle::create($data);
    }

    public function update(InsightArticle $article, array $data, User $editor): InsightArticle
    {
        if (isset($data['slug']) && $data['slug'] !== $article->slug) {
            $data['slug'] = $this->uniqueSlug($data['slug'], $article->id);
        }

        // Make the editor available to the InsightArticleObserver, which owns the revision write.
        // This ensures any Eloquent save path (admin UI, tinker, future code) produces a revision.
        auth()->setUser($editor);
        $article->update($data);

        return $article->fresh();
    }

    public function publish(InsightArticle $article): InsightArticle
    {
        $article->update([
            'status' => 'published',
            'published_at' => $article->published_at ?? now(),
            'scheduled_at' => null,
        ]);

        return $article->fresh();
    }

    public function archive(InsightArticle $article): InsightArticle
    {
        $article->update(['status' => 'archived', 'is_featured' => false]);

        return $article->fresh();
    }

    public function unarchive(InsightArticle $article): InsightArticle
    {
        $article->update(['status' => $article->published_at ? 'published' : 'draft']);

        return $article->fresh();
    }

    public function setFeatured(InsightArticle $article): InsightArticle
    {
        return DB::transaction(function () use ($article) {
            InsightArticle::where('is_featured', true)
                ->where('id', '!=', $article->id)
                ->update(['is_featured' => false]);

            $article->update(['is_featured' => true]);

            return $article->fresh();
        });
    }

    public function unsetFeatured(InsightArticle $article): InsightArticle
    {
        $article->update(['is_featured' => false]);

        return $article->fresh();
    }

    public function resyncFromTemplate(InsightArticle $article, User $editor): InsightArticle
    {
        if (!$article->template) {
            return $article;
        }

        return $this->update($article, [
            'body_blocks' => $article->template->body_blocks,
        ], $editor);
    }

    public function getFeatured(): ?InsightArticle
    {
        return InsightArticle::published()->featured()->first();
    }

    public function restoreRevision(InsightArticle $article, InsightArticleRevision $revision, User $editor): InsightArticle
    {
        return $this->update($article, [
            'title' => $revision->title,
            'subtitle' => $revision->subtitle,
            'summary' => $revision->summary,
            'body_blocks' => $revision->body_blocks,
        ], $editor);
    }

    private function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = Str::slug($base);
        $candidate = $slug;
        $n = 2;

        while (InsightArticle::where('slug', $candidate)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $candidate = "{$slug}-{$n}";
            $n++;
        }

        return $candidate;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
./vendor/bin/pest tests/Unit/Services/Insights/InsightArticleServiceTest.php
```

Expected: all 9 tests pass.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Insights/InsightArticleService.php tests/Unit/Services/Insights/InsightArticleServiceTest.php
git commit -m "feat(insights): add InsightArticleService with publish, feature, revision, resync"
```

---

### Task 10: `InsightImageService` + unit tests

**Files:**
- Create: `app/Services/Insights/InsightImageService.php`
- Create: `tests/Unit/Services/Insights/InsightImageServiceTest.php`
- Modify: `composer.json`, `composer.lock` — add `intervention/image`.

- [ ] **Step 0: Install the Intervention Image dependency** (not currently in `composer.json`)

```bash
composer require intervention/image
```

Verify with:

```bash
composer show intervention/image | grep versions
```

Commit the lockfile change separately:

```bash
git add composer.json composer.lock
git commit -m "chore(insights): add intervention/image for image resizing"
```

- [ ] **Step 1: Write tests**

```php
<?php

declare(strict_types=1);

use App\Services\Insights\InsightImageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->service = app(InsightImageService::class);
});

it('uploads an image and returns three paths', function () {
    $file = UploadedFile::fake()->image('photo.jpg', 2000, 1200);

    $result = $this->service->upload($file, 'my-article');

    expect($result)->toHaveKeys(['path', 'card_path', 'thumb_path']);
    Storage::disk('public')->assertExists($result['path']);
    Storage::disk('public')->assertExists($result['card_path']);
    Storage::disk('public')->assertExists($result['thumb_path']);
});

it('resizes the card image to 800x450', function () {
    $file = UploadedFile::fake()->image('photo.jpg', 2000, 1200);

    $result = $this->service->upload($file, 'my-article');
    $image = getimagesize(Storage::disk('public')->path($result['card_path']));

    expect($image[0])->toBe(800)->and($image[1])->toBe(450);
});

it('resizes the thumbnail to 200x200', function () {
    $file = UploadedFile::fake()->image('photo.jpg', 2000, 1200);

    $result = $this->service->upload($file, 'my-article');
    $image = getimagesize(Storage::disk('public')->path($result['thumb_path']));

    expect($image[0])->toBe(200)->and($image[1])->toBe(200);
});

it('rejects unsupported formats', function () {
    $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

    $this->service->upload($file, 'my-article');
})->throws(\InvalidArgumentException::class);

it('stores files under the slug directory', function () {
    $file = UploadedFile::fake()->image('photo.jpg');

    $result = $this->service->upload($file, 'my-article');

    expect($result['path'])->toStartWith('insights/my-article/');
});
```

- [ ] **Step 2: Run tests to verify failure**

```bash
./vendor/bin/pest tests/Unit/Services/Insights/InsightImageServiceTest.php
```

Expected: all fail with class not found.

- [ ] **Step 3: Implement the service**

```php
<?php

declare(strict_types=1);

namespace App\Services\Insights;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class InsightImageService
{
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];
    private const MAX_BYTES = 10 * 1024 * 1024; // 10 MB

    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
    }

    public function upload(UploadedFile $file, string $slug): array
    {
        $this->validate($file);

        $directory = "insights/{$slug}";
        $timestamp = now()->format('YmdHis');
        $basename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = strtolower($file->getClientOriginalExtension());

        $originalPath = "{$directory}/{$timestamp}-{$basename}.{$extension}";
        Storage::disk('public')->put($originalPath, file_get_contents($file->getRealPath()));

        $cardPath = "{$directory}/{$timestamp}-{$basename}-card.webp";
        $thumbPath = "{$directory}/{$timestamp}-{$basename}-thumb.webp";

        $card = $this->manager->read($file->getRealPath())
            ->cover(800, 450)
            ->toWebp(85)
            ->toString();
        Storage::disk('public')->put($cardPath, $card);

        $thumb = $this->manager->read($file->getRealPath())
            ->cover(200, 200)
            ->toWebp(80)
            ->toString();
        Storage::disk('public')->put($thumbPath, $thumb);

        return [
            'path' => $originalPath,
            'card_path' => $cardPath,
            'thumb_path' => $thumbPath,
        ];
    }

    private function validate(UploadedFile $file): void
    {
        if (!in_array($file->getMimeType(), self::ALLOWED_MIMES, true)) {
            throw new InvalidArgumentException("Unsupported image format: {$file->getMimeType()}");
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw new InvalidArgumentException('Image exceeds 10 MB limit.');
        }
    }
}
```

- [ ] **Step 4: Run tests**

```bash
./vendor/bin/pest tests/Unit/Services/Insights/InsightImageServiceTest.php
```

Expected: all 5 pass.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Insights/InsightImageService.php tests/Unit/Services/Insights/InsightImageServiceTest.php
git commit -m "feat(insights): add InsightImageService with WebP resize pipeline"
```

---

### Task 11: `InsightTemplateService` + unit tests

**Files:**
- Create: `app/Services/Insights/InsightTemplateService.php`
- Create: `tests/Unit/Services/Insights/InsightTemplateServiceTest.php`

- [ ] **Step 1: Write tests**

```php
<?php

declare(strict_types=1);

use App\Models\Insights\InsightArticle;
use App\Models\Insights\InsightTemplate;
use App\Models\User;
use App\Services\Insights\InsightTemplateService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->service = app(InsightTemplateService::class);
});

it('saves a template from an existing article', function () {
    $article = InsightArticle::factory()->create([
        'body_blocks' => [
            ['type' => 'heading', 'level' => 2, 'text' => 'Hello'],
            ['type' => 'paragraph', 'html' => '<p>World</p>'],
        ],
    ]);

    $template = $this->service->saveFromArticle($article, 'My template', 'Description', $this->admin);

    expect($template->name)->toBe('My template')
        ->and($template->body_blocks)->toBe($article->body_blocks)
        ->and($template->created_by)->toBe($this->admin->id);
});

it('rejects duplicate template names', function () {
    $article = InsightArticle::factory()->create();
    InsightTemplate::factory()->create(['name' => 'Standard guide']);

    $this->service->saveFromArticle($article, 'Standard guide', null, $this->admin);
})->throws(\Illuminate\Database\QueryException::class);

it('deletes a template and nulls the reference on articles', function () {
    $template = InsightTemplate::factory()->create();
    $article = InsightArticle::factory()->create(['template_id' => $template->id]);

    $this->service->delete($template);

    expect($article->fresh()->template_id)->toBeNull();
});
```

- [ ] **Step 2: Implement**

```php
<?php

declare(strict_types=1);

namespace App\Services\Insights;

use App\Models\Insights\InsightArticle;
use App\Models\Insights\InsightTemplate;
use App\Models\User;

class InsightTemplateService
{
    public function saveFromArticle(
        InsightArticle $article,
        string $name,
        ?string $description,
        User $creator,
    ): InsightTemplate {
        return InsightTemplate::create([
            'name' => $name,
            'description' => $description,
            'body_blocks' => $article->body_blocks,
            'created_by' => $creator->id,
        ]);
    }

    public function rename(InsightTemplate $template, string $name): InsightTemplate
    {
        $template->update(['name' => $name]);
        return $template->fresh();
    }

    public function delete(InsightTemplate $template): void
    {
        // nullOnDelete on the FK handles article detachment.
        $template->delete();
    }
}
```

- [ ] **Step 3: Run tests**

```bash
./vendor/bin/pest tests/Unit/Services/Insights/InsightTemplateServiceTest.php
```

Expected: all 3 pass.

- [ ] **Step 4: Commit**

```bash
git add app/Services/Insights/InsightTemplateService.php tests/Unit/Services/Insights/InsightTemplateServiceTest.php
git commit -m "feat(insights): add InsightTemplateService"
```

---

### Task 12: `InsightSeoService` + unit tests

**Files:**
- Create: `app/Services/Insights/InsightSeoService.php`
- Create: `tests/Unit/Services/Insights/InsightSeoServiceTest.php`

**Responsibility:** given an `InsightArticle`, return meta tag array + JSON-LD payload for Blade injection.

- [ ] **Step 1: Write tests**

```php
<?php

declare(strict_types=1);

use App\Models\Insights\InsightArticle;
use App\Services\Insights\InsightSeoService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(InsightSeoService::class);
});

it('falls back to title and summary when overrides are absent', function () {
    $article = InsightArticle::factory()->published()->create([
        'title' => 'My Title',
        'summary' => 'My summary',
        'meta_title' => null,
        'meta_description' => null,
    ]);

    $meta = $this->service->metaTags($article);

    expect($meta['title'])->toBe('My Title')
        ->and($meta['description'])->toBe('My summary');
});

it('uses SEO overrides when provided', function () {
    $article = InsightArticle::factory()->published()->create([
        'meta_title' => 'SEO Title',
        'meta_description' => 'SEO Desc',
    ]);

    $meta = $this->service->metaTags($article);

    expect($meta['title'])->toBe('SEO Title')
        ->and($meta['description'])->toBe('SEO Desc');
});

it('includes open graph and twitter card tags', function () {
    $article = InsightArticle::factory()->published()->create([
        'hero_image_card_path' => 'insights/slug/card.webp',
    ]);

    $meta = $this->service->metaTags($article);

    expect($meta['og'])->toHaveKeys(['title', 'description', 'image', 'type', 'url'])
        ->and($meta['og']['type'])->toBe('article')
        ->and($meta['og']['image'])->toContain('/storage/insights/slug/card.webp')
        ->and($meta['twitter']['card'])->toBe('summary_large_image');
});

it('builds a schema.org Article JSON-LD payload', function () {
    $article = InsightArticle::factory()->published()->create([
        'title' => 'JSON-LD Test',
    ]);

    $jsonLd = $this->service->jsonLd($article);

    expect($jsonLd['@context'])->toBe('https://schema.org')
        ->and($jsonLd['@type'])->toBe('Article')
        ->and($jsonLd['headline'])->toBe('JSON-LD Test')
        ->and($jsonLd)->toHaveKeys(['datePublished', 'author', 'image', 'publisher']);
});
```

- [ ] **Step 2: Implement**

```php
<?php

declare(strict_types=1);

namespace App\Services\Insights;

use App\Models\Insights\InsightArticle;

class InsightSeoService
{
    public function metaTags(InsightArticle $article): array
    {
        $title = $article->meta_title ?? $article->title;
        $description = $article->meta_description ?? $article->summary;
        $url = $this->articleUrl($article);
        $image = $this->imageUrl($article->hero_image_card_path);

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $article->canonical_url ?? $url,
            'og' => [
                'title' => $title,
                'description' => $description,
                'image' => $image,
                'type' => 'article',
                'url' => $url,
            ],
            'twitter' => [
                'card' => 'summary_large_image',
                'title' => $title,
                'description' => $description,
                'image' => $image,
            ],
        ];
    }

    public function jsonLd(InsightArticle $article): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $article->title,
            'description' => $article->summary,
            'image' => $this->imageUrl($article->hero_image_card_path),
            'datePublished' => optional($article->published_at)->toIso8601String(),
            'dateModified' => $article->updated_at->toIso8601String(),
            'author' => [
                '@type' => 'Organization',
                'name' => 'Fynla',
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'Fynla',
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => config('app.url').'/images/fynla-logo.png',
                ],
            ],
            'mainEntityOfPage' => $this->articleUrl($article),
        ];
    }

    private function articleUrl(InsightArticle $article): string
    {
        return rtrim(config('app.url'), '/')."/insights/{$article->slug}";
    }

    private function imageUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }
        return rtrim(config('app.url'), '/').'/storage/'.$path;
    }
}
```

- [ ] **Step 3: Run tests, commit**

```bash
./vendor/bin/pest tests/Unit/Services/Insights/InsightSeoServiceTest.php
git add app/Services/Insights/InsightSeoService.php tests/Unit/Services/Insights/InsightSeoServiceTest.php
git commit -m "feat(insights): add InsightSeoService for meta tags and JSON-LD"
```

---

### Task 13: `InsightArticleObserver`

**Files:**
- Create: `app/Observers/InsightArticleObserver.php`
- Modify: `app/Providers/AppServiceProvider.php` — register the observer.

**Responsibility:** own revision writes (service does NOT write revisions — Task 9 is already updated). Bust insights caches on any article change. Runs on `created` and `updated` only; `saved` and `deleted` bust cache without writing a revision.

**Revision ownership is this task, not the service.** This means any Eloquent save path (admin UI, tinker, future code) produces a revision. The service sets `auth()->setUser($editor)` before saving so the observer reads the correct user via `auth()->id()`.

- [ ] **Step 1: Create the observer**

```php
<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Insights\InsightArticle;
use App\Models\Insights\InsightArticleRevision;
use Illuminate\Support\Facades\Cache;

class InsightArticleObserver
{
    public function updated(InsightArticle $article): void
    {
        $this->writeRevision($article);
        $this->bustCaches();
    }

    public function created(InsightArticle $article): void
    {
        $this->writeRevision($article);
    }

    public function saved(InsightArticle $article): void
    {
        $this->bustCaches();
    }

    public function deleted(InsightArticle $article): void
    {
        $this->bustCaches();
    }

    private function writeRevision(InsightArticle $article): void
    {
        if ($article->is_bespoke) {
            return;
        }

        InsightArticleRevision::create([
            'article_id' => $article->id,
            'title' => $article->title,
            'subtitle' => $article->subtitle,
            'summary' => $article->summary,
            'body_blocks' => $article->body_blocks,
            'saved_by' => auth()->id() ?? $article->author_id,
            'saved_at' => now(),
        ]);
    }

    private function bustCaches(): void
    {
        Cache::forget('insights.featured');
        // Generation counter: all list cache keys embed the current version,
        // so incrementing invalidates every paginated page at once.
        Cache::increment('insights.list_version');
    }
}
```

- [ ] **Step 3: Register in `AppServiceProvider::boot()`**

```php
use App\Models\Insights\InsightArticle;
use App\Observers\InsightArticleObserver;

// inside boot():
InsightArticle::observe(InsightArticleObserver::class);
```

- [ ] **Step 4: Run the service tests — they should still pass**

```bash
./vendor/bin/pest tests/Unit/Services/Insights/InsightArticleServiceTest.php
```

Expected: all 9 still green (revision written by observer now).

- [ ] **Step 5: Commit**

```bash
git add app/Observers/InsightArticleObserver.php app/Services/Insights/InsightArticleService.php app/Providers/AppServiceProvider.php
git commit -m "feat(insights): observer for revisions + featured cache busting"
```

---

### Task 14: `PublishScheduledInsightsJob` + scheduler + tests

**Files:**
- Create: `app/Jobs/PublishScheduledInsightsJob.php`
- Modify: `app/Console/Kernel.php` — schedule every 5 minutes.
- Create: `tests/Unit/Jobs/PublishScheduledInsightsJobTest.php`

- [ ] **Step 1: Write tests**

```php
<?php

declare(strict_types=1);

use App\Jobs\PublishScheduledInsightsJob;
use App\Models\Insights\InsightArticle;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('publishes drafts whose scheduled_at has passed', function () {
    $due = InsightArticle::factory()->scheduled(now()->subMinute())->create();
    $future = InsightArticle::factory()->scheduled(now()->addHour())->create();

    (new PublishScheduledInsightsJob())->handle();

    expect($due->fresh()->status)->toBe('published')
        ->and($due->fresh()->published_at)->not->toBeNull()
        ->and($future->fresh()->status)->toBe('draft');
});

it('does not touch already-published articles', function () {
    $published = InsightArticle::factory()->published()->create(['published_at' => now()->subDay()]);
    $originalPublishedAt = $published->published_at;

    (new PublishScheduledInsightsJob())->handle();

    expect($published->fresh()->published_at->timestamp)->toBe($originalPublishedAt->timestamp);
});
```

- [ ] **Step 2: Implement**

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Insights\InsightArticle;
use App\Services\Insights\InsightArticleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PublishScheduledInsightsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(): void
    {
        $service = app(InsightArticleService::class);

        InsightArticle::scheduledDue()->each(function (InsightArticle $article) use ($service): void {
            $service->publish($article);
        });
    }
}
```

- [ ] **Step 3: Register in `Kernel::schedule()`**

```php
$schedule->job(new \App\Jobs\PublishScheduledInsightsJob())->everyFiveMinutes();
```

- [ ] **Step 4: Run tests**

```bash
./vendor/bin/pest tests/Unit/Jobs/PublishScheduledInsightsJobTest.php
```

Expected: 2 pass.

- [ ] **Step 5: Commit**

```bash
git add app/Jobs/PublishScheduledInsightsJob.php app/Console/Kernel.php tests/Unit/Jobs/PublishScheduledInsightsJobTest.php
git commit -m "feat(insights): scheduled publish job running every 5 minutes"
```

---

### Task 15: Block validator (shared by form requests)

**Files:**
- Create: `app/Services/Insights/BlockValidator.php`
- Create: `tests/Unit/Services/Insights/BlockValidatorTest.php`

**Responsibility:** validate `body_blocks` JSON against the block schema. Returns array of errors (empty = valid). Used by both store and update form requests.

- [ ] **Step 1: Write tests**

```php
<?php

declare(strict_types=1);

use App\Services\Insights\BlockValidator;

beforeEach(fn () => $this->validator = new BlockValidator());

it('passes valid blocks', function () {
    $blocks = [
        ['type' => 'heading', 'level' => 2, 'text' => 'Hello'],
        ['type' => 'paragraph', 'html' => '<p>World</p>'],
        ['type' => 'callout', 'variant' => 'tip', 'html' => '<p>Tip</p>'],
    ];

    expect($this->validator->validate($blocks))->toBe([]);
});

it('rejects unknown block types', function () {
    $errors = $this->validator->validate([['type' => 'bogus']]);

    expect($errors[0])->toContain('Unknown block type: bogus');
});

it('rejects heading without required fields', function () {
    $errors = $this->validator->validate([['type' => 'heading']]);

    expect($errors)->not->toBeEmpty();
});

it('rejects callout with invalid variant', function () {
    $errors = $this->validator->validate([
        ['type' => 'callout', 'variant' => 'orange', 'html' => '<p>x</p>'],
    ]);

    expect($errors[0])->toContain('variant');
});

it('rejects non-array input', function () {
    $errors = $this->validator->validate('not an array');

    expect($errors)->not->toBeEmpty();
});
```

- [ ] **Step 2: Implement**

```php
<?php

declare(strict_types=1);

namespace App\Services\Insights;

class BlockValidator
{
    private const CALLOUT_VARIANTS = ['info', 'tip', 'warning', 'success'];
    private const HEADING_LEVELS = [2, 3, 4];
    private const IMAGE_ALIGNMENTS = ['full', 'left', 'right'];
    private const CTA_STYLES = ['primary', 'secondary'];

    public function validate(mixed $blocks): array
    {
        if (!is_array($blocks)) {
            return ['body_blocks must be an array'];
        }

        $errors = [];
        foreach ($blocks as $index => $block) {
            $prefix = "block {$index}: ";
            if (!isset($block['type'])) {
                $errors[] = $prefix.'missing type';
                continue;
            }

            $typeErrors = $this->validateBlock($block);
            foreach ($typeErrors as $e) {
                $errors[] = $prefix.$e;
            }
        }

        return $errors;
    }

    private function validateBlock(array $block): array
    {
        return match ($block['type']) {
            'heading' => $this->validateHeading($block),
            'paragraph' => $this->validateParagraph($block),
            'list' => $this->validateList($block),
            'image' => $this->validateImage($block),
            'pull_quote' => $this->validatePullQuote($block),
            'callout' => $this->validateCallout($block),
            'divider' => [],
            'cta_button' => $this->validateCtaButton($block),
            'tax_year_stat' => $this->validateTaxYearStat($block),
            'related_articles' => $this->validateRelatedArticles($block),
            'key_takeaways' => $this->validateKeyTakeaways($block),
            default => ["Unknown block type: {$block['type']}"],
        };
    }

    private function validateHeading(array $b): array
    {
        $errors = [];
        if (!isset($b['level']) || !in_array($b['level'], self::HEADING_LEVELS, true)) {
            $errors[] = 'heading level must be 2, 3, or 4';
        }
        if (!isset($b['text']) || !is_string($b['text']) || $b['text'] === '') {
            $errors[] = 'heading text is required';
        }
        return $errors;
    }

    private function validateParagraph(array $b): array
    {
        if (!isset($b['html']) || !is_string($b['html'])) {
            return ['paragraph html is required'];
        }
        return [];
    }

    private function validateList(array $b): array
    {
        $errors = [];
        if (!isset($b['ordered']) || !is_bool($b['ordered'])) {
            $errors[] = 'list ordered must be boolean';
        }
        if (!isset($b['items']) || !is_array($b['items']) || $b['items'] === []) {
            $errors[] = 'list items must be a non-empty array';
        }
        return $errors;
    }

    private function validateImage(array $b): array
    {
        $errors = [];
        foreach (['path', 'alt'] as $req) {
            if (!isset($b[$req]) || !is_string($b[$req])) {
                $errors[] = "image {$req} is required";
            }
        }
        if (isset($b['alignment']) && !in_array($b['alignment'], self::IMAGE_ALIGNMENTS, true)) {
            $errors[] = 'image alignment must be full, left, or right';
        }
        return $errors;
    }

    private function validatePullQuote(array $b): array
    {
        if (!isset($b['text']) || !is_string($b['text']) || $b['text'] === '') {
            return ['pull_quote text is required'];
        }
        return [];
    }

    private function validateCallout(array $b): array
    {
        $errors = [];
        if (!isset($b['variant']) || !in_array($b['variant'], self::CALLOUT_VARIANTS, true)) {
            $errors[] = 'callout variant must be info, tip, warning, or success';
        }
        if (!isset($b['html']) || !is_string($b['html'])) {
            $errors[] = 'callout html is required';
        }
        return $errors;
    }

    private function validateCtaButton(array $b): array
    {
        $errors = [];
        foreach (['label', 'href'] as $req) {
            if (!isset($b[$req]) || !is_string($b[$req])) {
                $errors[] = "cta_button {$req} is required";
            }
        }
        if (isset($b['style']) && !in_array($b['style'], self::CTA_STYLES, true)) {
            $errors[] = 'cta_button style must be primary or secondary';
        }
        return $errors;
    }

    private function validateTaxYearStat(array $b): array
    {
        $errors = [];
        foreach (['stat_key', 'label'] as $req) {
            if (!isset($b[$req]) || !is_string($b[$req]) || $b[$req] === '') {
                $errors[] = "tax_year_stat {$req} is required";
            }
        }
        return $errors;
    }

    private function validateRelatedArticles(array $b): array
    {
        if (!isset($b['article_ids']) || !is_array($b['article_ids']) || $b['article_ids'] === []) {
            return ['related_articles article_ids must be a non-empty array'];
        }
        if (count($b['article_ids']) > 4) {
            return ['related_articles allows at most 4 articles'];
        }
        return [];
    }

    private function validateKeyTakeaways(array $b): array
    {
        if (!isset($b['bullets']) || !is_array($b['bullets']) || $b['bullets'] === []) {
            return ['key_takeaways bullets must be a non-empty array'];
        }
        return [];
    }
}
```

- [ ] **Step 3: Run tests, commit**

```bash
./vendor/bin/pest tests/Unit/Services/Insights/BlockValidatorTest.php
git add app/Services/Insights/BlockValidator.php tests/Unit/Services/Insights/BlockValidatorTest.php
git commit -m "feat(insights): add BlockValidator covering all 11 block types"
```

---

## Phase 3 — API Layer

### Task 16: Form requests

**Files:**
- Create: `app/Http/Requests/Admin/Insights/StoreInsightArticleRequest.php`
- Create: `app/Http/Requests/Admin/Insights/UpdateInsightArticleRequest.php`
- Create: `app/Http/Requests/Admin/Insights/StoreInsightTemplateRequest.php`
- Create: `app/Http/Requests/Admin/Insights/UploadInsightImageRequest.php`

- [ ] **Step 1: `StoreInsightArticleRequest`**

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Insights;

use App\Services\Insights\BlockValidator;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreInsightArticleRequest extends FormRequest
{
    private const ALLOWED_INLINE_TAGS = '<strong><em><a><br>';
    private const ALLOWED_BLOCK_TAGS = '<p><strong><em><a><br><ul><ol><li>';

    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'summary' => ['required', 'string', 'max:2000'],
            'slug' => ['nullable', 'string', 'alpha_dash', 'max:255'],
            'category' => ['required', 'in:tax-changes,pensions,savings-isa,estate-planning,platform-updates'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'hero_image_path' => ['nullable', 'string'],
            'hero_image_card_path' => ['nullable', 'string'],
            'hero_image_thumb_path' => ['nullable', 'string'],
            'body_blocks' => ['nullable', 'array'],
            'template_id' => ['nullable', 'exists:insight_templates,id'],
            'status' => ['nullable', 'in:draft,published,archived'],
            'is_featured' => ['nullable', 'boolean'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'canonical_url' => ['nullable', 'url'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Sanitise paragraph/callout HTML before validation — restricts the accepted
        // tag set and strips disallowed attributes. DOMPurify on the frontend is a
        // second layer at render time.
        $blocks = $this->input('body_blocks');
        if (!is_array($blocks)) {
            return;
        }

        $cleaned = array_map(function ($block) {
            if (!is_array($block) || !isset($block['type'])) {
                return $block;
            }

            if ($block['type'] === 'paragraph' && isset($block['html']) && is_string($block['html'])) {
                $block['html'] = $this->sanitiseHtml($block['html'], self::ALLOWED_INLINE_TAGS);
            }
            if ($block['type'] === 'callout' && isset($block['html']) && is_string($block['html'])) {
                $block['html'] = $this->sanitiseHtml($block['html'], self::ALLOWED_BLOCK_TAGS);
            }

            return $block;
        }, $blocks);

        $this->merge(['body_blocks' => $cleaned]);
    }

    private function sanitiseHtml(string $html, string $allowedTags): string
    {
        // strip_tags keeps only the listed tags and removes all attributes EXCEPT href on <a>
        // after this additional sweep. We explicitly re-add target="_blank" rel="noopener"
        // on external <a> tags so admin-authored links open safely.
        $stripped = strip_tags($html, $allowedTags);

        // Remove on* event attributes and javascript: URLs.
        $stripped = preg_replace('/\s+on\w+\s*=\s*"[^"]*"/i', '', $stripped);
        $stripped = preg_replace('/\s+on\w+\s*=\s*\'[^\']*\'/i', '', $stripped);
        $stripped = preg_replace('/href\s*=\s*"javascript:[^"]*"/i', 'href="#"', $stripped);
        $stripped = preg_replace('/href\s*=\s*\'javascript:[^\']*\'/i', "href='#'", $stripped);

        return $stripped;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $blocks = $this->input('body_blocks', []);
            if (!is_array($blocks) || $blocks === []) {
                return;
            }
            foreach ((new BlockValidator())->validate($blocks) as $error) {
                $v->errors()->add('body_blocks', $error);
            }
        });
    }
}
```

- [ ] **Step 2: `UpdateInsightArticleRequest`** — same shape, all fields optional (sometimes). Extend `StoreInsightArticleRequest` and override `rules()` to wrap everything in `sometimes`.

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Insights;

class UpdateInsightArticleRequest extends StoreInsightArticleRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        foreach ($rules as $field => $constraints) {
            if (!in_array('nullable', $constraints, true) && !in_array('sometimes', $constraints, true)) {
                array_unshift($constraints, 'sometimes');
                $rules[$field] = $constraints;
            }
        }
        return $rules;
    }
}
```

- [ ] **Step 3: `StoreInsightTemplateRequest`**

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Insights;

use Illuminate\Foundation\Http\FormRequest;

class StoreInsightTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        return [
            'article_id' => ['required', 'exists:insight_articles,id'],
            'name' => ['required', 'string', 'max:255', 'unique:insight_templates,name'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
```

- [ ] **Step 4: `UploadInsightImageRequest`**

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Insights;

use Illuminate\Foundation\Http\FormRequest;

class UploadInsightImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        return [
            'image' => ['required', 'file', 'image', 'mimes:jpeg,png,webp', 'max:10240'],
            'slug' => ['required', 'string', 'alpha_dash', 'max:255'],
        ];
    }
}
```

- [ ] **Step 5: Commit**

```bash
git add app/Http/Requests/Admin/Insights/
git commit -m "feat(insights): add form requests for article/template/image endpoints"
```

---

### Task 16b: Update `SanitizeInput` middleware to preserve `body_blocks` HTML

Fynla's global `SanitizeInput` middleware applies `strip_tags` to every string field in every request, including nested JSON. Without this task, paragraph and callout HTML inside `body_blocks` would be stripped to plain text before reaching the form request's `prepareForValidation()` — a silent data-loss bug.

**Files:**
- Modify: `app/Http/Middleware/SanitizeInput.php` — add `body_blocks` to the allowed-HTML list (or whatever the middleware's equivalent escape is).
- Create: `tests/Feature/Middleware/SanitizeInputInsightsTest.php`

- [ ] **Step 1: Inspect the current middleware**

```bash
grep -n 'htmlAllowedFields\|body_blocks\|strip_tags' app/Http/Middleware/SanitizeInput.php
```

If `htmlAllowedFields` (or similar) exists, add `'body_blocks'` to it. If the middleware uses a different pattern (e.g. route-prefix excludes), add `/api/admin/insights/*` to the excluded routes.

- [ ] **Step 2: Write a feature test**

```php
<?php

declare(strict_types=1);

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('preserves HTML inside body_blocks paragraph for admin insights endpoint', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->postJson('/api/admin/insights/articles', [
            'title' => 'HTML test',
            'summary' => 'Summary',
            'category' => 'pensions',
            'body_blocks' => [
                ['type' => 'paragraph', 'html' => '<p>Hello <strong>world</strong></p>'],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.body_blocks.0.html', '<p>Hello <strong>world</strong></p>');
});
```

- [ ] **Step 3: Apply the middleware change, run test, commit**

```bash
./vendor/bin/pest tests/Feature/Middleware/SanitizeInputInsightsTest.php
git add app/Http/Middleware/SanitizeInput.php tests/Feature/Middleware/SanitizeInputInsightsTest.php
git commit -m "fix(insights): preserve HTML in body_blocks through SanitizeInput middleware"
```

---

### Task 17: API Resources

**Files:**
- Create: `app/Http/Resources/Insights/InsightArticleResource.php`
- Create: `app/Http/Resources/Insights/InsightArticleListResource.php`
- Create: `app/Http/Resources/Insights/InsightTemplateResource.php`

- [ ] **Step 1: `InsightArticleResource`** (full detail, used for the public show endpoint and admin edit load)

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\Insights;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InsightArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'summary' => $this->summary,
            'category' => $this->category,
            'tags' => $this->tags ?? [],
            'hero_image' => $this->heroImageUrls(),
            'body_blocks' => $this->body_blocks ?? [],
            'template_id' => $this->template_id,
            'status' => $this->status,
            'is_featured' => $this->is_featured,
            'is_bespoke' => $this->is_bespoke,
            'bespoke_component' => $this->bespoke_component,
            'published_at' => optional($this->published_at)->toIso8601String(),
            'scheduled_at' => optional($this->scheduled_at)->toIso8601String(),
            'author' => $this->whenLoaded('author', fn () => [
                'id' => $this->author->id,
                'name' => $this->author->name,
            ]),
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'canonical_url' => $this->canonical_url,
        ];
    }

    private function heroImageUrls(): array
    {
        $base = rtrim(config('app.url'), '/').'/storage/';

        return [
            'full' => $this->hero_image_path ? $base.$this->hero_image_path : null,
            'card' => $this->hero_image_card_path ? $base.$this->hero_image_card_path : null,
            'thumb' => $this->hero_image_thumb_path ? $base.$this->hero_image_thumb_path : null,
        ];
    }
}
```

- [ ] **Step 2: `InsightArticleListResource`** (lightweight for hub/landing lists)

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\Insights;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InsightArticleListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $base = rtrim(config('app.url'), '/').'/storage/';

        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'summary' => $this->summary,
            'category' => $this->category,
            'tags' => $this->tags ?? [],
            'image_card' => $this->hero_image_card_path ? $base.$this->hero_image_card_path : null,
            'published_at' => optional($this->published_at)->toIso8601String(),
            'is_featured' => $this->is_featured,
            'is_bespoke' => $this->is_bespoke,
        ];
    }
}
```

- [ ] **Step 3: `InsightTemplateResource`**

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\Insights;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InsightTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'body_blocks' => $this->body_blocks ?? [],
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add app/Http/Resources/Insights/
git commit -m "feat(insights): add API resources for article list/detail and template"
```

---

### Task 18: `Api\Admin\InsightArticleController` + routes + feature tests

**Files:**
- Create: `app/Http/Controllers/Api/Admin/InsightArticleController.php`
- Modify: `routes/api.php` — add admin routes.
- Create: `tests/Feature/Api/Admin/InsightArticleControllerTest.php`

- [ ] **Step 1: Write feature tests**

```php
<?php

declare(strict_types=1);

use App\Models\Insights\InsightArticle;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->user = User::factory()->create(['is_admin' => false]);
});

it('forbids non-admins from listing articles', function () {
    $this->actingAs($this->user)
        ->getJson('/api/admin/insights/articles')
        ->assertForbidden();
});

it('lists articles for admins', function () {
    InsightArticle::factory()->count(3)->create(['author_id' => $this->admin->id]);

    $this->actingAs($this->admin)
        ->getJson('/api/admin/insights/articles')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('creates an article', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/admin/insights/articles', [
            'title' => 'New Guide',
            'summary' => 'What this is about',
            'category' => 'pensions',
            'body_blocks' => [['type' => 'heading', 'level' => 2, 'text' => 'Intro']],
        ])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'new-guide');
});

it('rejects invalid block types', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/admin/insights/articles', [
            'title' => 'Bad',
            'summary' => 's',
            'category' => 'pensions',
            'body_blocks' => [['type' => 'invented']],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('body_blocks');
});

it('publishes an article', function () {
    $article = InsightArticle::factory()->create(['author_id' => $this->admin->id]);

    $this->actingAs($this->admin)
        ->postJson("/api/admin/insights/articles/{$article->id}/publish")
        ->assertOk()
        ->assertJsonPath('data.status', 'published');
});

it('setting featured auto-unfeatures the previous', function () {
    $previous = InsightArticle::factory()->published()->featured()->create(['author_id' => $this->admin->id]);
    $next = InsightArticle::factory()->published()->create(['author_id' => $this->admin->id]);

    $this->actingAs($this->admin)
        ->postJson("/api/admin/insights/articles/{$next->id}/feature")
        ->assertOk();

    expect($previous->fresh()->is_featured)->toBeFalse()
        ->and($next->fresh()->is_featured)->toBeTrue();
});

it('archives an article', function () {
    $article = InsightArticle::factory()->published()->create(['author_id' => $this->admin->id]);

    $this->actingAs($this->admin)
        ->postJson("/api/admin/insights/articles/{$article->id}/archive")
        ->assertOk();

    expect($article->fresh()->status)->toBe('archived');
});

it('lists revisions for an article', function () {
    $article = InsightArticle::factory()->create(['author_id' => $this->admin->id]);
    $this->actingAs($this->admin)
        ->putJson("/api/admin/insights/articles/{$article->id}", ['title' => 'Changed'])
        ->assertOk();

    $this->actingAs($this->admin)
        ->getJson("/api/admin/insights/articles/{$article->id}/revisions")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
```

- [ ] **Step 2: Implement the controller**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Insights\StoreInsightArticleRequest;
use App\Http\Requests\Admin\Insights\UpdateInsightArticleRequest;
use App\Http\Resources\Insights\InsightArticleListResource;
use App\Http\Resources\Insights\InsightArticleResource;
use App\Models\Insights\InsightArticle;
use App\Models\Insights\InsightArticleRevision;
use App\Services\Insights\InsightArticleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InsightArticleController extends Controller
{
    public function __construct(private readonly InsightArticleService $articles)
    {
        // Defence-in-depth: route middleware already applies permission:admin.access,
        // but matching AdminController's convention ensures the controller can never
        // be reached (e.g. by a future unprotected route) without admin permission.
        $this->middleware('permission:admin.access');
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = InsightArticle::query()
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('category'), fn ($q, $c) => $q->where('category', $c))
            ->when($request->boolean('featured'), fn ($q) => $q->featured())
            ->orderByDesc('updated_at');

        return InsightArticleListResource::collection($query->paginate(20));
    }

    public function store(StoreInsightArticleRequest $request): JsonResponse
    {
        $article = $this->articles->create($request->validated(), $request->user());

        return (new InsightArticleResource($article))
            ->response()
            ->setStatusCode(201);
    }

    public function show(InsightArticle $article): InsightArticleResource
    {
        return new InsightArticleResource($article->load('author'));
    }

    public function update(UpdateInsightArticleRequest $request, InsightArticle $article): InsightArticleResource
    {
        $updated = $this->articles->update($article, $request->validated(), $request->user());
        return new InsightArticleResource($updated);
    }

    public function destroy(InsightArticle $article): JsonResponse
    {
        $article->delete();
        return response()->json(['message' => 'Deleted'], 200);
    }

    public function publish(InsightArticle $article): InsightArticleResource
    {
        return new InsightArticleResource($this->articles->publish($article));
    }

    public function archive(InsightArticle $article): InsightArticleResource
    {
        return new InsightArticleResource($this->articles->archive($article));
    }

    public function unarchive(InsightArticle $article): InsightArticleResource
    {
        return new InsightArticleResource($this->articles->unarchive($article));
    }

    public function feature(InsightArticle $article): InsightArticleResource
    {
        return new InsightArticleResource($this->articles->setFeatured($article));
    }

    public function unfeature(InsightArticle $article): InsightArticleResource
    {
        return new InsightArticleResource($this->articles->unsetFeatured($article));
    }

    public function resyncFromTemplate(Request $request, InsightArticle $article): InsightArticleResource
    {
        $updated = $this->articles->resyncFromTemplate($article, $request->user());
        return new InsightArticleResource($updated);
    }

    public function revisions(InsightArticle $article): JsonResponse
    {
        return response()->json([
            'data' => $article->revisions()->with('savedBy:id,name')->get(),
        ]);
    }

    public function restoreRevision(Request $request, InsightArticle $article, InsightArticleRevision $revision): InsightArticleResource
    {
        abort_unless($revision->article_id === $article->id, 404);

        $updated = $this->articles->restoreRevision($article, $revision, $request->user());
        return new InsightArticleResource($updated);
    }
}
```

- [ ] **Step 3: Add routes**

In `routes/api.php`, inside the admin-protected group:

```php
// Nest inside the existing admin route group in routes/api.php (around line 1020)
// which already applies ['auth:sanctum', 'permission:admin.access']. Do NOT use the
// 'admin' alias — existing admin routes uniformly use the 'permission:admin.access' string.
Route::prefix('admin/insights')->middleware(['auth:sanctum', 'permission:admin.access'])->group(function () {
    Route::get('articles', [\App\Http\Controllers\Api\Admin\InsightArticleController::class, 'index']);
    Route::post('articles', [\App\Http\Controllers\Api\Admin\InsightArticleController::class, 'store']);
    Route::get('articles/{article}', [\App\Http\Controllers\Api\Admin\InsightArticleController::class, 'show']);
    Route::put('articles/{article}', [\App\Http\Controllers\Api\Admin\InsightArticleController::class, 'update']);
    Route::delete('articles/{article}', [\App\Http\Controllers\Api\Admin\InsightArticleController::class, 'destroy']);
    Route::post('articles/{article}/publish', [\App\Http\Controllers\Api\Admin\InsightArticleController::class, 'publish']);
    Route::post('articles/{article}/archive', [\App\Http\Controllers\Api\Admin\InsightArticleController::class, 'archive']);
    Route::post('articles/{article}/unarchive', [\App\Http\Controllers\Api\Admin\InsightArticleController::class, 'unarchive']);
    Route::post('articles/{article}/feature', [\App\Http\Controllers\Api\Admin\InsightArticleController::class, 'feature']);
    Route::post('articles/{article}/unfeature', [\App\Http\Controllers\Api\Admin\InsightArticleController::class, 'unfeature']);
    Route::post('articles/{article}/resync-template', [\App\Http\Controllers\Api\Admin\InsightArticleController::class, 'resyncFromTemplate']);
    Route::get('articles/{article}/revisions', [\App\Http\Controllers\Api\Admin\InsightArticleController::class, 'revisions']);
    Route::post('articles/{article}/revisions/{revision}/restore', [\App\Http\Controllers\Api\Admin\InsightArticleController::class, 'restoreRevision']);
});
```

- [ ] **Step 4: Run tests, commit**

```bash
./vendor/bin/pest tests/Feature/Api/Admin/InsightArticleControllerTest.php
git add app/Http/Controllers/Api/Admin/InsightArticleController.php routes/api.php tests/Feature/Api/Admin/InsightArticleControllerTest.php
git commit -m "feat(insights): admin article controller with publish/feature/archive/revisions"
```

---

### Task 19: `Api\Admin\InsightTemplateController` + routes + feature tests

**Files:**
- Create: `app/Http/Controllers/Api/Admin/InsightTemplateController.php`
- Modify: `routes/api.php`
- Create: `tests/Feature/Api/Admin/InsightTemplateControllerTest.php`

- [ ] **Step 1: Tests**

```php
<?php

declare(strict_types=1);

use App\Models\Insights\InsightArticle;
use App\Models\Insights\InsightTemplate;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
});

it('saves a template from an article', function () {
    $article = InsightArticle::factory()->create(['author_id' => $this->admin->id]);

    $this->actingAs($this->admin)
        ->postJson('/api/admin/insights/templates', [
            'article_id' => $article->id,
            'name' => 'My template',
            'description' => 'A description',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'My template');
});

it('lists templates', function () {
    InsightTemplate::factory()->count(3)->create();

    $this->actingAs($this->admin)
        ->getJson('/api/admin/insights/templates')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('renames a template', function () {
    $template = InsightTemplate::factory()->create(['name' => 'Old']);

    $this->actingAs($this->admin)
        ->putJson("/api/admin/insights/templates/{$template->id}", ['name' => 'New'])
        ->assertOk();

    expect($template->fresh()->name)->toBe('New');
});

it('deletes a template and nulls article references', function () {
    $template = InsightTemplate::factory()->create();
    $article = InsightArticle::factory()->create(['template_id' => $template->id]);

    $this->actingAs($this->admin)
        ->deleteJson("/api/admin/insights/templates/{$template->id}")
        ->assertOk();

    expect($article->fresh()->template_id)->toBeNull();
});
```

- [ ] **Step 2: Controller**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Insights\StoreInsightTemplateRequest;
use App\Http\Resources\Insights\InsightTemplateResource;
use App\Models\Insights\InsightArticle;
use App\Models\Insights\InsightTemplate;
use App\Services\Insights\InsightTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InsightTemplateController extends Controller
{
    public function __construct(private readonly InsightTemplateService $templates)
    {
        $this->middleware('permission:admin.access');
    }

    public function index(): AnonymousResourceCollection
    {
        return InsightTemplateResource::collection(
            InsightTemplate::orderBy('name')->get()
        );
    }

    public function store(StoreInsightTemplateRequest $request): JsonResponse
    {
        $article = InsightArticle::findOrFail($request->integer('article_id'));

        $template = $this->templates->saveFromArticle(
            $article,
            $request->string('name'),
            $request->input('description'),
            $request->user(),
        );

        return (new InsightTemplateResource($template))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, InsightTemplate $template): InsightTemplateResource
    {
        $request->validate(['name' => ['required', 'string', 'max:255', 'unique:insight_templates,name,'.$template->id]]);

        $updated = $this->templates->rename($template, $request->string('name'));
        return new InsightTemplateResource($updated);
    }

    public function destroy(InsightTemplate $template): JsonResponse
    {
        $this->templates->delete($template);
        return response()->json(['message' => 'Deleted'], 200);
    }
}
```

- [ ] **Step 3: Routes + commit**

Add inside the same admin group:

```php
Route::get('templates', [\App\Http\Controllers\Api\Admin\InsightTemplateController::class, 'index']);
Route::post('templates', [\App\Http\Controllers\Api\Admin\InsightTemplateController::class, 'store']);
Route::put('templates/{template}', [\App\Http\Controllers\Api\Admin\InsightTemplateController::class, 'update']);
Route::delete('templates/{template}', [\App\Http\Controllers\Api\Admin\InsightTemplateController::class, 'destroy']);
```

Run tests, commit:

```bash
./vendor/bin/pest tests/Feature/Api/Admin/InsightTemplateControllerTest.php
git add app/Http/Controllers/Api/Admin/InsightTemplateController.php routes/api.php tests/Feature/Api/Admin/InsightTemplateControllerTest.php
git commit -m "feat(insights): admin template controller (list, save-from-article, rename, delete)"
```

---

### Task 20: `Api\Admin\InsightImageController` + feature tests

**Files:**
- Create: `app/Http/Controllers/Api/Admin/InsightImageController.php`
- Modify: `routes/api.php`
- Create: `tests/Feature/Api/Admin/InsightImageControllerTest.php`

- [ ] **Step 1: Tests**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    $this->admin = User::factory()->create(['is_admin' => true]);
});

it('uploads an image and returns three paths', function () {
    $file = UploadedFile::fake()->image('photo.jpg', 2000, 1200);

    $response = $this->actingAs($this->admin)
        ->postJson('/api/admin/insights/images', [
            'image' => $file,
            'slug' => 'my-article',
        ]);

    $response->assertCreated()
        ->assertJsonStructure(['data' => ['path', 'card_path', 'thumb_path']]);
});

it('rejects files larger than 10 MB', function () {
    $file = UploadedFile::fake()->image('huge.jpg')->size(11 * 1024);

    $this->actingAs($this->admin)
        ->postJson('/api/admin/insights/images', ['image' => $file, 'slug' => 'a'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('image');
});

it('rejects non-image formats', function () {
    $file = UploadedFile::fake()->create('file.pdf', 100, 'application/pdf');

    $this->actingAs($this->admin)
        ->postJson('/api/admin/insights/images', ['image' => $file, 'slug' => 'a'])
        ->assertUnprocessable();
});

it('forbids non-admin users', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $file = UploadedFile::fake()->image('p.jpg');

    $this->actingAs($user)
        ->postJson('/api/admin/insights/images', ['image' => $file, 'slug' => 'a'])
        ->assertForbidden();
});
```

- [ ] **Step 2: Controller**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Insights\UploadInsightImageRequest;
use App\Services\Insights\InsightImageService;
use Illuminate\Http\JsonResponse;

class InsightImageController extends Controller
{
    public function __construct(private readonly InsightImageService $images)
    {
        $this->middleware('permission:admin.access');
    }

    public function store(UploadInsightImageRequest $request): JsonResponse
    {
        $paths = $this->images->upload(
            $request->file('image'),
            $request->string('slug'),
        );

        return response()->json(['data' => $paths], 201);
    }
}
```

- [ ] **Step 3: Route + commit**

Add: `Route::post('images', [\App\Http\Controllers\Api\Admin\InsightImageController::class, 'store']);` inside the admin group.

```bash
./vendor/bin/pest tests/Feature/Api/Admin/InsightImageControllerTest.php
git add app/Http/Controllers/Api/Admin/InsightImageController.php routes/api.php tests/Feature/Api/Admin/InsightImageControllerTest.php
git commit -m "feat(insights): admin image upload endpoint"
```

---

### Task 21: `Api\Public\InsightController` + feature tests

**Files:**
- Create: `app/Http/Controllers/Api/Public/InsightController.php`
- Modify: `routes/api.php`
- Create: `tests/Feature/Api/Public/InsightControllerTest.php`

- [ ] **Step 1: Tests**

```php
<?php

declare(strict_types=1);

use App\Models\Insights\InsightArticle;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('lists only published articles', function () {
    InsightArticle::factory()->published()->create();
    InsightArticle::factory()->create(['status' => 'draft']);
    InsightArticle::factory()->create(['status' => 'archived']);

    $this->getJson('/api/insights')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('returns featured article and two supporting articles', function () {
    InsightArticle::factory()->published()->featured()->create(['published_at' => now()->subDay()]);
    InsightArticle::factory()->published()->create(['published_at' => now()->subHour()]);
    InsightArticle::factory()->published()->create(['published_at' => now()->subMinutes(30)]);
    InsightArticle::factory()->published()->create(['published_at' => now()->subMinutes(15)]);

    $response = $this->getJson('/api/insights/featured')->assertOk();

    expect($response['data']['featured']['is_featured'])->toBeTrue()
        ->and($response['data']['supporting'])->toHaveCount(2);
});

it('falls back to most recent published when nothing featured', function () {
    $latest = InsightArticle::factory()->published()->create(['published_at' => now()->subHour()]);
    InsightArticle::factory()->published()->create(['published_at' => now()->subDay()]);

    $this->getJson('/api/insights/featured')
        ->assertOk()
        ->assertJsonPath('data.featured.slug', $latest->slug);
});

it('returns a published article by slug', function () {
    $article = InsightArticle::factory()->published()->create(['slug' => 'my-post']);

    $this->getJson('/api/insights/my-post')
        ->assertOk()
        ->assertJsonPath('data.slug', 'my-post');
});

it('returns 404 for drafts', function () {
    $article = InsightArticle::factory()->create(['slug' => 'draft', 'status' => 'draft']);

    $this->getJson('/api/insights/draft')
        ->assertNotFound();
});

it('returns 404 for archived articles', function () {
    InsightArticle::factory()->create(['slug' => 'old', 'status' => 'archived']);

    $this->getJson('/api/insights/old')
        ->assertNotFound();
});

it('admins with ?preview=true can see drafts', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    InsightArticle::factory()->create(['slug' => 'preview-me', 'status' => 'draft']);

    $this->actingAs($admin)
        ->getJson('/api/insights/preview-me?preview=true')
        ->assertOk();
});
```

- [ ] **Step 2: Controller**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Insights\InsightArticleListResource;
use App\Http\Resources\Insights\InsightArticleResource;
use App\Models\Insights\InsightArticle;
use App\Services\Insights\InsightArticleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InsightController extends Controller
{
    public function __construct(private readonly InsightArticleService $articles)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $category = $request->input('category');
        $page = max(1, (int) $request->input('page', 1));
        $version = (int) \Illuminate\Support\Facades\Cache::get('insights.list_version', 1);
        $cacheKey = "insights.list.v{$version}.cat-".($category ?: 'all').".page.{$page}";

        $articles = \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addMinutes(10), function () use ($category) {
            return InsightArticle::published()
                ->when($category, fn ($q) => $q->where('category', $category))
                ->orderByDesc('published_at')
                ->paginate(24);
        });

        return InsightArticleListResource::collection($articles);
    }

    public function featured(): JsonResponse
    {
        $featured = $this->articles->getFeatured()
            ?? InsightArticle::published()->orderByDesc('published_at')->first();

        if (!$featured) {
            return response()->json(['data' => ['featured' => null, 'supporting' => []]]);
        }

        $supporting = InsightArticle::published()
            ->where('id', '!=', $featured->id)
            ->orderByDesc('published_at')
            ->take(2)
            ->get();

        return response()->json([
            'data' => [
                'featured' => (new InsightArticleListResource($featured))->resolve(),
                'supporting' => InsightArticleListResource::collection($supporting)->resolve(),
            ],
        ]);
    }

    public function show(Request $request, string $slug): InsightArticleResource
    {
        $query = InsightArticle::where('slug', $slug);

        if ($request->boolean('preview') && $request->user()?->is_admin) {
            // admin preview — any status except deleted
        } else {
            $query->published();
        }

        $article = $query->firstOrFail();
        return new InsightArticleResource($article->load('author'));
    }
}
```

- [ ] **Step 3: Routes (public, no auth)**

```php
Route::prefix('insights')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\Public\InsightController::class, 'index']);
    Route::get('featured', [\App\Http\Controllers\Api\Public\InsightController::class, 'featured']);
    Route::get('{slug}', [\App\Http\Controllers\Api\Public\InsightController::class, 'show'])
        ->middleware('auth:sanctum')->withoutMiddleware(['auth:sanctum']); // allow anonymous but inject user when present
});
```

Note: For the `?preview=true` path we need optional auth. Use a middleware-less route with `auth()->user()` resolved lazily — Sanctum populates `auth()->user()` from the session/token if present. No middleware needed.

Simplify:

```php
Route::prefix('insights')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\Public\InsightController::class, 'index']);
    Route::get('featured', [\App\Http\Controllers\Api\Public\InsightController::class, 'featured']);
    Route::get('{slug}', [\App\Http\Controllers\Api\Public\InsightController::class, 'show']);
});
```

- [ ] **Step 4: Run tests, commit**

```bash
./vendor/bin/pest tests/Feature/Api/Public/InsightControllerTest.php
git add app/Http/Controllers/Api/Public/InsightController.php routes/api.php tests/Feature/Api/Public/InsightControllerTest.php
git commit -m "feat(insights): public API (list, featured, show-by-slug with admin preview)"
```

---

### Task 22: `InsightsSeoMetaInjector` middleware + tests

**Files:**
- Create: `app/Http/Middleware/InsightsSeoMetaInjector.php`
- Modify: `app/Http/Kernel.php` — register.
- Modify: `resources/views/app.blade.php` — render injected tags.
- Modify: `routes/web.php` — apply middleware on `/insights/{slug}`.
- Create: `tests/Feature/Middleware/InsightsSeoMetaInjectorTest.php`

- [ ] **Step 1: Tests**

```php
<?php

declare(strict_types=1);

use App\Models\Insights\InsightArticle;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('injects meta tags for a published article', function () {
    InsightArticle::factory()->published()->create([
        'slug' => 'test',
        'title' => 'Test Title',
        'summary' => 'A summary',
    ]);

    $response = $this->get('/insights/test')->assertOk();

    $response->assertSee('<title>Test Title</title>', false)
        ->assertSee('name="description" content="A summary"', false)
        ->assertSee('property="og:type" content="article"', false)
        ->assertSee('application/ld+json', false);
});

it('skips bespoke articles', function () {
    InsightArticle::factory()->published()->bespoke()->create([
        'slug' => 'legacy',
        'title' => 'Legacy',
    ]);

    $response = $this->get('/insights/legacy')->assertOk();

    // No server-injected title from the middleware — bespoke Vue component handles its own
    $response->assertDontSee('<meta property="og:type" content="article">', false);
});

it('no-ops when article not found', function () {
    $this->get('/insights/nonexistent')->assertOk(); // SPA catch-all still returns the app shell
});
```

- [ ] **Step 2: Middleware**

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Insights\InsightArticle;
use App\Services\Insights\InsightSeoService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class InsightsSeoMetaInjector
{
    public function __construct(private readonly InsightSeoService $seo)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('slug') ?? $this->extractSlug($request->path());

        if ($slug) {
            $article = InsightArticle::where('slug', $slug)->published()->first();
            if ($article && !$article->is_bespoke) {
                View::share('insightMeta', $this->seo->metaTags($article));
                View::share('insightJsonLd', $this->seo->jsonLd($article));
            }
        }

        return $next($request);
    }

    private function extractSlug(string $path): ?string
    {
        if (preg_match('#^insights/([a-z0-9-]+)$#', trim($path, '/'), $m)) {
            return $m[1];
        }
        return null;
    }
}
```

- [ ] **Step 3: Register in `app/Http/Kernel.php`**

Add to `$routeMiddleware`:

```php
'insights.seo' => \App\Http\Middleware\InsightsSeoMetaInjector::class,
```

- [ ] **Step 4: Apply to the SPA route in `routes/web.php`**

The SPA catch-all currently renders `app.blade.php` for all frontend routes. Add a dedicated route for `/insights/{slug}` that applies the middleware before falling through to the SPA:

```php
Route::get('/insights/{slug}', function () {
    return view('app');
})->middleware('insights.seo')->where('slug', '[a-z0-9-]+');
```

Make sure this route is declared before the SPA catch-all so it takes precedence.

- [ ] **Step 5: Add a `@stack('head')` placeholder to `resources/views/app.blade.php`**

`app.blade.php` currently has a fully static `<head>` with hardcoded Open Graph / Twitter tags. Do not replace those — they are the fallback for all non-insight pages. Instead, add a `@stack('head')` directive that the middleware can push into, which will render AFTER the static tags. Insight-specific tags then appear last and take precedence.

In `resources/views/app.blade.php` inside `<head>`, after the existing meta tags (and before `</head>`), add:

```blade
{{-- Insight articles push per-article SEO meta tags here (see InsightsSeoMetaInjector middleware) --}}
@stack('head')
```

- [ ] **Step 6: Update the middleware to push to the stack rather than share with the view**

Replace the middleware body:

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Insights\InsightArticle;
use App\Services\Insights\InsightSeoService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class InsightsSeoMetaInjector
{
    public function __construct(private readonly InsightSeoService $seo)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('slug') ?? $this->extractSlug($request->path());

        if (!$slug) {
            return $next($request);
        }

        $article = InsightArticle::where('slug', $slug)->published()->first();
        if (!$article || $article->is_bespoke) {
            return $next($request);
        }

        $meta = $this->seo->metaTags($article);
        $jsonLd = $this->seo->jsonLd($article);

        // Register a view composer that pushes tags to the `head` stack on every render.
        View::composer('app', function ($view) use ($meta, $jsonLd): void {
            $view->getFactory()->startPush('head', $this->renderMeta($meta, $jsonLd));
            $view->getFactory()->stopPush();
        });

        return $next($request);
    }

    private function renderMeta(array $meta, array $jsonLd): string
    {
        $html = '';
        $html .= '<title>'.e($meta['title']).'</title>'.PHP_EOL;
        $html .= '<meta name="description" content="'.e($meta['description']).'">'.PHP_EOL;
        $html .= '<link rel="canonical" href="'.e($meta['canonical']).'">'.PHP_EOL;
        foreach ($meta['og'] as $key => $value) {
            if ($value) {
                $html .= '<meta property="og:'.$key.'" content="'.e($value).'">'.PHP_EOL;
            }
        }
        foreach ($meta['twitter'] as $key => $value) {
            if ($value) {
                $html .= '<meta name="twitter:'.$key.'" content="'.e($value).'">'.PHP_EOL;
            }
        }
        $html .= '<script type="application/ld+json">'.json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).'</script>'.PHP_EOL;
        return $html;
    }

    private function extractSlug(string $path): ?string
    {
        if (preg_match('#^insights/([a-z0-9-]+)$#', trim($path, '/'), $m)) {
            return $m[1];
        }
        return null;
    }
}
```

This replaces the earlier middleware shown in Step 2. The view-composer push approach ensures insight meta tags render after (and therefore override) the static tags in `app.blade.php`.

- [ ] **Step 6: Run tests, commit**

```bash
./vendor/bin/pest tests/Feature/Middleware/InsightsSeoMetaInjectorTest.php
git add app/Http/Middleware/InsightsSeoMetaInjector.php app/Http/Kernel.php routes/web.php resources/views/app.blade.php tests/Feature/Middleware/InsightsSeoMetaInjectorTest.php
git commit -m "feat(insights): server-render SEO meta tags for DB-driven articles"
```

---

### Task 23: Sitemap integration — DEFERRED

**Not implemented in this feature.** Fynla has no `SitemapController` today — only a static `public/sitemap.xml` file. Adding Laravel-controlled sitemap generation (either an on-demand controller or an observer-driven static regeneration) is meaningful work in its own right and is out of scope for this CMS feature.

Insight articles will still be discoverable via:
- Internal links from the hub page (`/insights`)
- Internal links from the landing page's "Latest insights" hero
- Social-share backlinks populated by the OG/Twitter Card tags

When the sitemap work is scheduled as a follow-up, the logic is:

```php
$insightArticles = \App\Models\Insights\InsightArticle::published()->get();
foreach ($insightArticles as $article) {
    $urls[] = [
        'loc' => url("/insights/{$article->slug}"),
        'lastmod' => $article->updated_at->toAtomString(),
        'changefreq' => 'weekly',
        'priority' => '0.7',
    ];
}
```

No task commits in this feature. Skip directly to Task 24.

---

## Phase 4 — Public Frontend

### Task 24: `insightsService.js` API wrapper

**Files:**
- Create: `resources/js/services/insightsService.js`

- [ ] **Step 1: Implement**

```javascript
import api from './api';

const insightsService = {
  // Public
  async list({ category } = {}) {
    const params = {};
    if (category) params.category = category;
    return (await api.get('/insights', { params })).data;
  },
  async featured() {
    return (await api.get('/insights/featured')).data;
  },
  async getBySlug(slug, { preview = false } = {}) {
    const params = preview ? { preview: 'true' } : {};
    return (await api.get(`/insights/${slug}`, { params })).data;
  },

  // Admin — articles
  async adminList({ status, category, featured, page = 1 } = {}) {
    const params = { page };
    if (status) params.status = status;
    if (category) params.category = category;
    if (featured !== undefined) params.featured = featured ? 1 : 0;
    return (await api.get('/admin/insights/articles', { params })).data;
  },
  async adminGet(id) {
    return (await api.get(`/admin/insights/articles/${id}`)).data;
  },
  async create(data) {
    return (await api.post('/admin/insights/articles', data)).data;
  },
  async update(id, data) {
    return (await api.put(`/admin/insights/articles/${id}`, data)).data;
  },
  async remove(id) {
    return (await api.delete(`/admin/insights/articles/${id}`)).data;
  },
  async publish(id) {
    return (await api.post(`/admin/insights/articles/${id}/publish`)).data;
  },
  async archive(id) {
    return (await api.post(`/admin/insights/articles/${id}/archive`)).data;
  },
  async unarchive(id) {
    return (await api.post(`/admin/insights/articles/${id}/unarchive`)).data;
  },
  async feature(id) {
    return (await api.post(`/admin/insights/articles/${id}/feature`)).data;
  },
  async unfeature(id) {
    return (await api.post(`/admin/insights/articles/${id}/unfeature`)).data;
  },
  async resyncTemplate(id) {
    return (await api.post(`/admin/insights/articles/${id}/resync-template`)).data;
  },
  async revisions(id) {
    return (await api.get(`/admin/insights/articles/${id}/revisions`)).data;
  },
  async restoreRevision(articleId, revisionId) {
    return (await api.post(`/admin/insights/articles/${articleId}/revisions/${revisionId}/restore`)).data;
  },

  // Admin — templates
  async listTemplates() {
    return (await api.get('/admin/insights/templates')).data;
  },
  async saveTemplate({ articleId, name, description }) {
    return (await api.post('/admin/insights/templates', {
      article_id: articleId,
      name,
      description,
    })).data;
  },
  async renameTemplate(id, name) {
    return (await api.put(`/admin/insights/templates/${id}`, { name })).data;
  },
  async deleteTemplate(id) {
    return (await api.delete(`/admin/insights/templates/${id}`)).data;
  },

  // Admin — images
  async uploadImage(file, slug) {
    const form = new FormData();
    form.append('image', file);
    form.append('slug', slug);
    return (await api.post('/admin/insights/images', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })).data;
  },
};

export default insightsService;
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/services/insightsService.js
git commit -m "feat(insights): frontend API service wrapper"
```

---

### Task 25: Vuex `insights` module

**Files:**
- Create: `resources/js/store/modules/insights.js`
- Modify: `resources/js/store/index.js` — register module.

- [ ] **Step 1: Implement the module**

```javascript
import insightsService from '@/services/insightsService';

const state = () => ({
  // Public
  list: [],
  listLoading: false,
  featured: null,
  supporting: [],
  featuredLoading: false,
  current: null,
  currentLoading: false,
  error: null,

  // Admin
  adminList: [],
  adminPagination: null,
  templates: [],
});

const mutations = {
  setList(state, list) { state.list = list; },
  setListLoading(state, v) { state.listLoading = v; },
  setFeatured(state, { featured, supporting }) {
    state.featured = featured;
    state.supporting = supporting;
  },
  setFeaturedLoading(state, v) { state.featuredLoading = v; },
  setCurrent(state, article) { state.current = article; },
  setCurrentLoading(state, v) { state.currentLoading = v; },
  setError(state, e) { state.error = e; },
  setAdminList(state, { data, pagination }) {
    state.adminList = data;
    state.adminPagination = pagination;
  },
  setTemplates(state, list) { state.templates = list; },
  addTemplate(state, t) { state.templates.push(t); },
  updateTemplate(state, t) {
    const i = state.templates.findIndex(x => x.id === t.id);
    if (i !== -1) state.templates.splice(i, 1, t);
  },
  removeTemplate(state, id) {
    state.templates = state.templates.filter(t => t.id !== id);
  },
};

const actions = {
  async fetchList({ commit }, { category } = {}) {
    commit('setListLoading', true);
    try {
      const res = await insightsService.list({ category });
      commit('setList', res.data);
    } catch (e) {
      commit('setError', e.message);
      throw e;
    } finally {
      commit('setListLoading', false);
    }
  },

  async fetchFeatured({ commit }) {
    commit('setFeaturedLoading', true);
    try {
      const res = await insightsService.featured();
      commit('setFeatured', {
        featured: res.data.featured,
        supporting: res.data.supporting || [],
      });
    } catch (e) {
      commit('setError', e.message);
      throw e;
    } finally {
      commit('setFeaturedLoading', false);
    }
  },

  async fetchBySlug({ commit }, { slug, preview = false }) {
    commit('setCurrentLoading', true);
    try {
      const res = await insightsService.getBySlug(slug, { preview });
      commit('setCurrent', res.data);
      return res.data;
    } catch (e) {
      commit('setError', e.message);
      throw e;
    } finally {
      commit('setCurrentLoading', false);
    }
  },

  async fetchAdminList({ commit }, params = {}) {
    const res = await insightsService.adminList(params);
    commit('setAdminList', {
      data: res.data,
      pagination: {
        current_page: res.meta?.current_page,
        last_page: res.meta?.last_page,
        total: res.meta?.total,
      },
    });
  },

  async fetchTemplates({ commit }) {
    const res = await insightsService.listTemplates();
    commit('setTemplates', res.data);
  },

  async saveAsTemplate({ commit }, payload) {
    const res = await insightsService.saveTemplate(payload);
    commit('addTemplate', res.data);
    return res.data;
  },

  async renameTemplate({ commit }, { id, name }) {
    const res = await insightsService.renameTemplate(id, name);
    commit('updateTemplate', res.data);
    return res.data;
  },

  async deleteTemplate({ commit }, id) {
    await insightsService.deleteTemplate(id);
    commit('removeTemplate', id);
  },
};

const getters = {
  listItems: s => s.list,
  featured: s => s.featured,
  supporting: s => s.supporting,
  current: s => s.current,
  templates: s => s.templates,
};

export default {
  namespaced: true,
  state,
  mutations,
  actions,
  getters,
};
```

- [ ] **Step 2: Register in `store/index.js`**

```javascript
import insights from './modules/insights';

// inside modules: { ... }
insights,
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/store/modules/insights.js resources/js/store/index.js
git commit -m "feat(insights): Vuex module for public + admin state"
```

---

### Task 26: Public block components (11 files)

**Files:**
- Create: `resources/js/components/Insights/blocks/HeadingBlock.vue`
- Create: `resources/js/components/Insights/blocks/ParagraphBlock.vue`
- Create: `resources/js/components/Insights/blocks/ListBlock.vue`
- Create: `resources/js/components/Insights/blocks/ImageBlock.vue`
- Create: `resources/js/components/Insights/blocks/PullQuoteBlock.vue`
- Create: `resources/js/components/Insights/blocks/CalloutBlock.vue`
- Create: `resources/js/components/Insights/blocks/DividerBlock.vue`
- Create: `resources/js/components/Insights/blocks/CtaButtonBlock.vue`
- Create: `resources/js/components/Insights/blocks/TaxYearStatBlock.vue`
- Create: `resources/js/components/Insights/blocks/RelatedArticlesBlock.vue`
- Create: `resources/js/components/Insights/blocks/KeyTakeawaysBlock.vue`

Each component follows a single-purpose pattern: receives a `block` prop, renders the corresponding HTML using design-system classes only.

- [ ] **Step 1: `HeadingBlock.vue`**

```vue
<template>
  <component :is="`h${block.level}`" :class="headingClasses" class="font-bold text-horizon-500 mb-4" style="letter-spacing:-0.02em;">
    {{ block.text }}
  </component>
</template>

<script>
export default {
  name: 'HeadingBlock',
  props: { block: { type: Object, required: true } },
  computed: {
    headingClasses() {
      return {
        'text-3xl md:text-4xl mt-10': this.block.level === 2,
        'text-2xl md:text-3xl mt-8': this.block.level === 3,
        'text-xl md:text-2xl mt-6': this.block.level === 4,
      };
    },
  },
};
</script>
```

- [ ] **Step 2: `ParagraphBlock.vue`** (sanitised `v-html`)

```vue
<template>
  <div class="text-base leading-relaxed text-neutral-600 mb-5" v-html="sanitised"></div>
</template>

<script>
import DOMPurify from 'dompurify';

export default {
  name: 'ParagraphBlock',
  props: { block: { type: Object, required: true } },
  computed: {
    sanitised() {
      return DOMPurify.sanitize(this.block.html || '', {
        ALLOWED_TAGS: ['p', 'strong', 'em', 'a', 'br'],
        ALLOWED_ATTR: ['href', 'target', 'rel'],
      });
    },
  },
};
</script>
```

DOMPurify is NOT currently in `package.json` — it must be installed before this block component can be built:

```bash
npm install dompurify
```

Commit `package.json` and `package-lock.json` in a dedicated commit:

```bash
git add package.json package-lock.json
git commit -m "chore(insights): add dompurify for frontend HTML sanitisation"
```

- [ ] **Step 3: `ListBlock.vue`**

```vue
<template>
  <component :is="block.ordered ? 'ol' : 'ul'" :class="block.ordered ? 'list-decimal' : 'list-disc'" class="pl-6 mb-5 text-neutral-600 space-y-2">
    <li v-for="(item, i) in block.items" :key="i">{{ item }}</li>
  </component>
</template>

<script>
export default {
  name: 'ListBlock',
  props: { block: { type: Object, required: true } },
};
</script>
```

- [ ] **Step 4: `ImageBlock.vue`**

```vue
<template>
  <figure :class="alignmentClass" class="my-6">
    <img :src="imageUrl" :alt="block.alt" class="rounded-lg w-full h-auto" />
    <figcaption v-if="block.caption" class="text-xs text-neutral-500 mt-2 text-center">
      {{ block.caption }}
    </figcaption>
  </figure>
</template>

<script>
export default {
  name: 'ImageBlock',
  props: { block: { type: Object, required: true } },
  computed: {
    imageUrl() {
      if (this.block.path?.startsWith('http')) return this.block.path;
      return `/storage/${this.block.path}`;
    },
    alignmentClass() {
      return {
        'mx-auto': this.block.alignment === 'full' || !this.block.alignment,
        'float-left mr-6 max-w-sm': this.block.alignment === 'left',
        'float-right ml-6 max-w-sm': this.block.alignment === 'right',
      };
    },
  },
};
</script>
```

- [ ] **Step 5: `PullQuoteBlock.vue`**

```vue
<template>
  <blockquote class="my-10 px-8 py-6 border-l-4 border-raspberry-500 bg-light-pink-100 rounded-r-lg">
    <p class="text-xl md:text-2xl font-bold text-horizon-500 leading-snug italic" style="letter-spacing:-0.01em;">
      "{{ block.text }}"
    </p>
    <footer v-if="block.attribution" class="mt-3 text-sm text-neutral-500 not-italic">— {{ block.attribution }}</footer>
  </blockquote>
</template>

<script>
export default {
  name: 'PullQuoteBlock',
  props: { block: { type: Object, required: true } },
};
</script>
```

- [ ] **Step 6: `CalloutBlock.vue`** (variants map to design-system colours)

```vue
<template>
  <aside :class="variantClasses" class="my-6 p-5 rounded-lg border-l-4">
    <div class="flex items-start gap-3">
      <svg class="w-5 h-5 flex-shrink-0 mt-0.5" :class="iconColour" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" :d="iconPath" clip-rule="evenodd" />
      </svg>
      <div class="flex-1 text-sm leading-relaxed" v-html="sanitised"></div>
    </div>
  </aside>
</template>

<script>
import DOMPurify from 'dompurify';

const VARIANT_STYLES = {
  info: { bg: 'bg-horizon-50 border-horizon-500', icon: 'text-horizon-500' },
  tip: { bg: 'bg-spring-50 border-spring-500', icon: 'text-spring-500' },
  success: { bg: 'bg-spring-50 border-spring-500', icon: 'text-spring-500' },
  warning: { bg: 'bg-violet-50 border-violet-500', icon: 'text-violet-500' },
};

const ICONS = {
  info: 'M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z',
  tip: 'M11 3a1 1 0 10-2 0v1a1 1 0 102 0V3zM15.657 5.757a1 1 0 00-1.414-1.414l-.707.707a1 1 0 001.414 1.414l.707-.707zM18 10a1 1 0 01-1 1h-1a1 1 0 110-2h1a1 1 0 011 1zM5.05 6.464A1 1 0 106.464 5.05l-.707-.707a1 1 0 00-1.414 1.414l.707.707zM5 10a1 1 0 01-1 1H3a1 1 0 110-2h1a1 1 0 011 1zM8 16v-1h4v1a2 2 0 11-4 0zM12 14c.015-.34.208-.646.477-.859a4 4 0 10-4.954 0c.27.213.462.519.476.859h4.002z',
  success: 'M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z',
  warning: 'M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z',
};

export default {
  name: 'CalloutBlock',
  props: { block: { type: Object, required: true } },
  computed: {
    variantStyle() { return VARIANT_STYLES[this.block.variant] || VARIANT_STYLES.info; },
    variantClasses() { return this.variantStyle.bg; },
    iconColour() { return this.variantStyle.icon; },
    iconPath() { return ICONS[this.block.variant] || ICONS.info; },
    sanitised() {
      return DOMPurify.sanitize(this.block.html || '', {
        ALLOWED_TAGS: ['p', 'strong', 'em', 'a', 'br', 'ul', 'ol', 'li'],
        ALLOWED_ATTR: ['href', 'target', 'rel'],
      });
    },
  },
};
</script>
```

- [ ] **Step 7: `DividerBlock.vue`**

```vue
<template>
  <hr class="my-10 border-t border-light-gray" />
</template>

<script>
export default { name: 'DividerBlock' };
</script>
```

- [ ] **Step 8: `CtaButtonBlock.vue`**

```vue
<template>
  <div class="my-8 text-center">
    <a :href="block.href" :class="buttonClasses" class="inline-block px-6 py-3 rounded-lg text-sm font-semibold transition-colors">
      {{ block.label }}
    </a>
  </div>
</template>

<script>
export default {
  name: 'CtaButtonBlock',
  props: { block: { type: Object, required: true } },
  computed: {
    buttonClasses() {
      return this.block.style === 'secondary'
        ? 'border-2 border-horizon-500 text-horizon-500 hover:bg-horizon-500 hover:text-white'
        : 'bg-raspberry-500 text-white hover:bg-raspberry-600';
    },
  },
};
</script>
```

- [ ] **Step 9: `TaxYearStatBlock.vue`** (reads from `taxConfig.js`)

```vue
<template>
  <div class="my-8 p-8 bg-gradient-to-br from-horizon-500 to-raspberry-500 rounded-lg text-white text-center">
    <p class="text-sm font-semibold uppercase tracking-wider opacity-80 mb-2">{{ block.label }}</p>
    <p class="text-5xl md:text-6xl font-black mb-1">{{ formattedValue }}</p>
    <p class="text-xs opacity-70">Tax year {{ currentTaxYear }}</p>
  </div>
</template>

<script>
import { getCurrentTaxYear } from '@/utils/dateFormatter';
import * as taxConfig from '@/constants/taxConfig';
import { currencyMixin } from '@/mixins/currencyMixin';

const STAT_MAP = {
  isa_annual_allowance: { source: 'ISA_ANNUAL_ALLOWANCE', format: 'currency' },
  personal_allowance: { source: 'PERSONAL_ALLOWANCE', format: 'currency' },
  pension_annual_allowance: { source: 'PENSION_ANNUAL_ALLOWANCE', format: 'currency' },
  iht_nil_rate_band: { source: 'IHT_NIL_RATE_BAND', format: 'currency' },
  cgt_annual_allowance: { source: 'CGT_ANNUAL_ALLOWANCE', format: 'currency' },
  // Extend as needed; matches keys exported from constants/taxConfig.js
};

export default {
  name: 'TaxYearStatBlock',
  mixins: [currencyMixin],
  props: { block: { type: Object, required: true } },
  computed: {
    currentTaxYear() { return getCurrentTaxYear(); },
    formattedValue() {
      const mapping = STAT_MAP[this.block.stat_key];
      if (!mapping) return '—';
      const value = taxConfig[mapping.source];
      if (value == null) return '—';
      return mapping.format === 'currency' ? this.formatCurrency(value) : value;
    },
  },
};
</script>
```

- [ ] **Step 10: `RelatedArticlesBlock.vue`**

```vue
<template>
  <section class="my-10">
    <h4 class="text-sm font-bold text-horizon-500 uppercase tracking-wide mb-4">Related articles</h4>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <router-link
        v-for="article in articles"
        :key="article.slug"
        :to="`/insights/${article.slug}`"
        class="p-4 bg-white border border-light-gray rounded-lg hover:bg-savannah-100 transition-colors"
      >
        <p class="text-xs text-neutral-400 uppercase tracking-wide mb-1">{{ article.category }}</p>
        <p class="text-sm font-bold text-horizon-500 leading-snug">{{ article.title }}</p>
      </router-link>
    </div>
  </section>
</template>

<script>
import insightsService from '@/services/insightsService';

export default {
  name: 'RelatedArticlesBlock',
  props: { block: { type: Object, required: true } },
  data() { return { articles: [] }; },
  async mounted() {
    if (!this.block.article_ids?.length) return;
    // Fetch each by id — admin endpoint requires admin auth, so use the public list and filter
    const res = await insightsService.list();
    const ids = new Set(this.block.article_ids);
    this.articles = res.data.filter(a => ids.has(a.id)).slice(0, 4);
  },
};
</script>
```

Note: public list doesn't currently return `id` — adjust `InsightArticleListResource` to include `id`. Do this in the same task as a one-line change.

- [ ] **Step 11: `KeyTakeawaysBlock.vue`**

```vue
<template>
  <aside class="my-8 p-6 bg-light-pink-100 border-l-4 border-raspberry-500 rounded-r-lg">
    <h4 class="text-sm font-bold text-raspberry-700 uppercase tracking-wide mb-3">Key takeaways</h4>
    <ul class="space-y-2">
      <li v-for="(b, i) in block.bullets" :key="i" class="flex items-start gap-2 text-sm text-horizon-500">
        <svg class="w-4 h-4 text-raspberry-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
        </svg>
        <span>{{ b }}</span>
      </li>
    </ul>
  </aside>
</template>

<script>
export default {
  name: 'KeyTakeawaysBlock',
  props: { block: { type: Object, required: true } },
};
</script>
```

- [ ] **Step 12: Commit**

```bash
git add resources/js/components/Insights/blocks/ app/Http/Resources/Insights/InsightArticleListResource.php
git commit -m "feat(insights): 11 public block components (all design-system compliant)"
```

---

### Task 27: `ArticleBlockRenderer.vue`

**Files:**
- Create: `resources/js/components/Insights/ArticleBlockRenderer.vue`

- [ ] **Step 1: Implement**

```vue
<template>
  <div class="article-body">
    <component
      v-for="(block, index) in blocks"
      :key="index"
      :is="componentFor(block.type)"
      :block="block"
    />
  </div>
</template>

<script>
import HeadingBlock from './blocks/HeadingBlock.vue';
import ParagraphBlock from './blocks/ParagraphBlock.vue';
import ListBlock from './blocks/ListBlock.vue';
import ImageBlock from './blocks/ImageBlock.vue';
import PullQuoteBlock from './blocks/PullQuoteBlock.vue';
import CalloutBlock from './blocks/CalloutBlock.vue';
import DividerBlock from './blocks/DividerBlock.vue';
import CtaButtonBlock from './blocks/CtaButtonBlock.vue';
import TaxYearStatBlock from './blocks/TaxYearStatBlock.vue';
import RelatedArticlesBlock from './blocks/RelatedArticlesBlock.vue';
import KeyTakeawaysBlock from './blocks/KeyTakeawaysBlock.vue';

const TYPE_MAP = {
  heading: HeadingBlock,
  paragraph: ParagraphBlock,
  list: ListBlock,
  image: ImageBlock,
  pull_quote: PullQuoteBlock,
  callout: CalloutBlock,
  divider: DividerBlock,
  cta_button: CtaButtonBlock,
  tax_year_stat: TaxYearStatBlock,
  related_articles: RelatedArticlesBlock,
  key_takeaways: KeyTakeawaysBlock,
};

export default {
  name: 'ArticleBlockRenderer',
  props: {
    blocks: { type: Array, required: true },
  },
  methods: {
    componentFor(type) { return TYPE_MAP[type] || null; },
  },
};
</script>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/components/Insights/ArticleBlockRenderer.vue
git commit -m "feat(insights): block renderer dispatching to 11 per-block components"
```

---

### Task 28: `InsightArticlePage.vue`

**Files:**
- Create: `resources/js/views/Public/insights/InsightArticlePage.vue`

- [ ] **Step 1: Implement**

```vue
<template>
  <PublicLayout>
    <div v-if="loading" class="max-w-4xl mx-auto px-4 py-20 text-center">
      <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin mx-auto"></div>
    </div>

    <div v-else-if="article" class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10 md:py-14">
      <!-- Hero -->
      <header class="mb-10">
        <div class="flex items-center gap-2 mb-4">
          <span class="text-xs font-semibold px-2 py-1 rounded-md uppercase tracking-wide bg-raspberry-100 text-raspberry-700">
            {{ categoryLabel }}
          </span>
          <span v-for="tag in article.tags" :key="tag" class="text-xs font-semibold px-2 py-1 rounded-md uppercase tracking-wide bg-light-gray text-neutral-600">
            {{ tag }}
          </span>
        </div>
        <h1 class="text-4xl md:text-5xl font-black text-horizon-500 mb-4 leading-tight" style="letter-spacing:-0.02em;">
          {{ article.title }}
        </h1>
        <p v-if="article.subtitle" class="text-lg text-neutral-600 mb-3 leading-relaxed">
          {{ article.subtitle }}
        </p>
        <p class="text-sm text-neutral-400">{{ formattedDate }}</p>
      </header>

      <!-- Hero image -->
      <figure v-if="article.hero_image?.full" class="mb-10 rounded-lg overflow-hidden">
        <img :src="article.hero_image.full" :alt="article.title" class="w-full h-auto" />
      </figure>

      <!-- Body -->
      <ArticleBlockRenderer :blocks="article.body_blocks || []" />

      <!-- Summary footer -->
      <footer class="mt-16 pt-8 border-t border-light-gray text-sm text-neutral-500 italic">
        {{ article.summary }}
      </footer>
    </div>

    <div v-else class="max-w-4xl mx-auto px-4 py-20 text-center">
      <h1 class="text-3xl font-bold text-horizon-500 mb-4">Article not found</h1>
      <p class="text-neutral-500 mb-6">The article you're looking for doesn't exist or has been unpublished.</p>
      <router-link to="/insights" class="inline-block px-6 py-2.5 bg-raspberry-500 text-white rounded-lg font-semibold">
        Back to insights
      </router-link>
    </div>
  </PublicLayout>
</template>

<script>
import { mapActions, mapGetters } from 'vuex';
import PublicLayout from '@/layouts/PublicLayout.vue';
import ArticleBlockRenderer from '@/components/Insights/ArticleBlockRenderer.vue';
import { formatDateLong } from '@/utils/dateFormatter';

const CATEGORY_LABELS = {
  'tax-changes': 'Tax changes',
  'pensions': 'Pensions',
  'savings-isa': 'Savings & ISA',
  'estate-planning': 'Estate planning',
  'platform-updates': 'Platform updates',
};

export default {
  name: 'InsightArticlePage',
  components: { PublicLayout, ArticleBlockRenderer },
  data() { return { loading: true, article: null }; },
  computed: {
    categoryLabel() { return CATEGORY_LABELS[this.article?.category] || this.article?.category; },
    formattedDate() {
      return this.article?.published_at ? formatDateLong(this.article.published_at) : '';
    },
  },
  async mounted() {
    await this.load();
  },
  async beforeRouteUpdate(to) {
    this.article = null;
    this.loading = true;
    await this.load(to.params.slug);
  },
  methods: {
    ...mapActions('insights', ['fetchBySlug']),
    async load(slug = this.$route.params.slug) {
      try {
        const preview = this.$route.query.preview === 'true';
        const article = await this.fetchBySlug({ slug, preview });
        if (article.is_bespoke) {
          // Safety net — bespoke articles should be caught by named routes
          this.article = null;
        } else {
          this.article = article;
        }
      } catch (e) {
        this.article = null;
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/views/Public/insights/InsightArticlePage.vue
git commit -m "feat(insights): public article page with block rendering"
```

---

### Task 29: Router update — add catch-all for `/insights/:slug`

**Files:**
- Modify: `resources/js/router/index.js`

- [ ] **Step 1: Add the route AFTER the 9 named insight routes**

Locate the block around line 311-319. After the last named insight route (`InsightHowMuchToRetire`), add:

```javascript
const InsightArticlePage = () => import('@/views/Public/insights/InsightArticlePage.vue');

// Inside routes array, AFTER all named insight routes:
{ path: '/insights/:slug', name: 'InsightArticle', component: InsightArticlePage, meta: { public: true } },
```

Add a comment immediately above marking the ordering requirement:

```javascript
// IMPORTANT: /insights/:slug catch-all must come AFTER all named insight routes
// so bespoke Vue articles take precedence. See insights-cms architecture test.
{ path: '/insights/:slug', name: 'InsightArticle', component: InsightArticlePage, meta: { public: true } },
```

- [ ] **Step 2: Verify ordering manually**

```bash
grep -n 'Insight' resources/js/router/index.js
```

Confirm the `/insights/:slug` line appears below all named `Insight*` routes.

- [ ] **Step 3: Commit**

```bash
git add resources/js/router/index.js
git commit -m "feat(insights): add /insights/:slug catch-all route (after named routes)"
```

---

### Task 30: Refactor `InsightsHubPage.vue` to DB-driven

**Files:**
- Modify: `resources/js/views/Public/insights/InsightsHubPage.vue`

- [ ] **Step 1: Remove hardcoded `articles` array**

Replace the `data()` return `articles` field (lines ~219-288) with an empty array, add loading state, fetch from Vuex on mount.

```vue
<script>
import { mapActions, mapGetters } from 'vuex';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { formatDateLong } from '@/utils/dateFormatter';

const CATEGORY_LABELS = {
  'tax-changes': 'Tax changes',
  'pensions': 'Pensions',
  'savings-isa': 'Savings & ISA',
  'estate-planning': 'Estate planning',
  'platform-updates': 'Platform updates',
};

export default {
  name: 'InsightsHubPage',
  components: { PublicLayout },

  data() {
    return {
      activeCategory: 'All',
      loading: true,
    };
  },

  computed: {
    ...mapGetters('insights', ['listItems']),
    categories() {
      return ['All', 'Tax changes', 'Pensions', 'Savings & ISA', 'Estate planning', 'Platform updates'];
    },
    articles() {
      return (this.listItems || []).map(a => ({
        ...a,
        date: a.published_at ? formatDateLong(a.published_at) : '',
        categoryLabel: CATEGORY_LABELS[a.category] || a.category,
        image: a.image_card,
      }));
    },
    latestArticles() { return this.articles.slice(0, 3); },
    otherArticles() {
      const remaining = this.articles.slice(3);
      if (this.activeCategory === 'All') return remaining;
      return remaining.filter(a => a.categoryLabel === this.activeCategory);
    },
  },

  async mounted() {
    this.loading = true;
    try {
      await this.fetchList();
    } finally {
      this.loading = false;
    }
  },

  methods: {
    ...mapActions('insights', ['fetchList']),
    getImage(img) { return img || null; }, // image is now a full URL from the resource
    tagClass(tag) {
      // Existing colour mapping — keep as-is
      return 'bg-horizon-100 text-horizon-500';
    },
    categoryCount(cat) {
      if (cat === 'All') return this.articles.length;
      return this.articles.filter(a => a.categoryLabel === cat).length;
    },
    isTallCard(idx) { return idx % 5 === 0; },
  },
};
</script>
```

In the template, `getImage(article.image)` now receives the full URL returned by `InsightArticleListResource` — update accordingly. Also update the router-link `:to` to use `article.slug` (the slug is now just the slug, not `/insights/slug` — prepend in the template: `:to="'/insights/' + article.slug"`).

- [ ] **Step 2: Test manually**

```bash
./dev.sh
# Visit http://localhost:8000/insights
# Verify: 8 articles render, category filter works, clicking a card navigates to correct URL
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/views/Public/insights/InsightsHubPage.vue
git commit -m "refactor(insights): hub page now DB-driven via Vuex"
```

---

### Task 31: Refactor `LandingPage.vue` — new 2/3 + 1/3 hero layout

**Files:**
- Modify: `resources/js/views/Public/LandingPage.vue`

- [ ] **Step 1: Locate the existing "Latest insights" section (line 362) and replace**

Remove the hardcoded `latestInsights` array (line ~462) and the `getInsightImage` method (line ~511). Add Vuex integration:

```javascript
import { mapActions, mapGetters } from 'vuex';

// in data(): remove latestInsights array

computed: {
  ...mapGetters('insights', { insightsFeatured: 'featured', insightsSupporting: 'supporting' }),
},

async mounted() {
  // existing mounted logic...
  try { await this.fetchFeatured(); } catch (e) { /* non-fatal */ }
},

methods: {
  ...mapActions('insights', ['fetchFeatured']),
  // remove getInsightImage
},
```

- [ ] **Step 2: Replace the template section (lines 362-398)**

```vue
<!-- Latest insights (2/3 featured + 2 supporting) -->
<div class="bg-light-pink-100 pt-12 pb-28">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 text-center mb-6" style="letter-spacing:-0.02em;">
      Latest insights
    </h2>

    <div v-if="insightsFeatured" class="grid grid-cols-1 lg:grid-cols-3 gap-5 max-w-6xl mx-auto">
      <!-- Featured (2/3) -->
      <router-link
        :to="'/insights/' + insightsFeatured.slug"
        class="lg:col-span-2 group relative block rounded-3xl overflow-hidden bg-horizon-500 min-h-[320px] lg:min-h-[420px]"
      >
        <img
          v-if="insightsFeatured.image_card"
          :src="insightsFeatured.image_card"
          :alt="insightsFeatured.title"
          class="absolute inset-0 w-full h-full object-cover opacity-80 group-hover:opacity-90 group-hover:scale-105 transition-all duration-700"
        />
        <div class="absolute inset-0 bg-gradient-to-t from-horizon-700 via-horizon-500/50 to-transparent"></div>
        <div class="relative h-full flex flex-col justify-end p-6 md:p-8">
          <span class="text-[0.65rem] font-bold px-2 py-1 rounded-md uppercase tracking-wider bg-raspberry-500 text-white self-start mb-3">
            Featured
          </span>
          <h3 class="text-2xl md:text-3xl font-bold text-white mb-3 leading-tight group-hover:text-raspberry-300 transition-colors" style="letter-spacing:-0.02em;">
            {{ insightsFeatured.title }}
          </h3>
          <p class="text-sm md:text-base text-white/80 leading-relaxed line-clamp-2">
            {{ insightsFeatured.summary }}
          </p>
        </div>
      </router-link>

      <!-- Two supporting (1/3 stacked) -->
      <div class="grid grid-rows-2 gap-5">
        <router-link
          v-for="article in insightsSupporting"
          :key="article.slug"
          :to="'/insights/' + article.slug"
          class="group relative block rounded-3xl overflow-hidden bg-white hover:shadow-xl transition-all"
        >
          <div class="flex h-full min-h-[150px] lg:min-h-[200px]">
            <div class="w-2/5 relative overflow-hidden bg-horizon-100">
              <img
                v-if="article.image_card"
                :src="article.image_card"
                :alt="article.title"
                class="absolute inset-0 w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
              />
            </div>
            <div class="flex-1 p-4 flex flex-col justify-center">
              <h4 class="text-sm md:text-base font-bold text-horizon-500 group-hover:text-raspberry-500 transition-colors leading-tight">
                {{ article.title }}
              </h4>
            </div>
          </div>
        </router-link>
      </div>
    </div>

    <div class="text-center mt-6">
      <router-link to="/insights" class="text-sm font-semibold text-horizon-500 hover:text-raspberry-500">
        See all insights →
      </router-link>
    </div>
  </div>
</div>
```

- [ ] **Step 3: Test manually**

```bash
# With dev server running, visit http://localhost:8000
# Scroll to "Latest insights"
# Verify: featured article takes 2/3, two supporting stack on 1/3, clicking navigates correctly
```

- [ ] **Step 4: Commit**

```bash
git add resources/js/views/Public/LandingPage.vue
git commit -m "refactor(insights): landing page hero — 2/3 featured + 1/3 stacked supporting"
```

---

### Task 32: Add `id` to `InsightArticleListResource`

Required for `RelatedArticlesBlock` to filter by id.

**Files:**
- Modify: `app/Http/Resources/Insights/InsightArticleListResource.php`

- [ ] **Step 1: Add `'id' => $this->id,` as the first key in `toArray()`.**

- [ ] **Step 2: Commit**

```bash
git add app/Http/Resources/Insights/InsightArticleListResource.php
git commit -m "fix(insights): expose article id on list resource for related-articles block"
```

---

### Task 33: Public rendering integration test

**Files:**
- Create: `tests/Browser/InsightArticleRenderingTest.php` OR `tests/Feature/InsightArticleRenderingTest.php`

Because Fynla uses Pest with the HTTP client (not Dusk), a feature-level assertion is sufficient for the backend contract. Browser verification happens manually per the project's testing rules.

- [ ] **Step 1: Backend contract test**

```php
<?php

declare(strict_types=1);

use App\Models\Insights\InsightArticle;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('returns body_blocks unchanged through the public show endpoint', function () {
    $article = InsightArticle::factory()->published()->create([
        'slug' => 'block-test',
        'body_blocks' => [
            ['type' => 'heading', 'level' => 2, 'text' => 'Intro'],
            ['type' => 'paragraph', 'html' => '<p>Hello</p>'],
            ['type' => 'callout', 'variant' => 'tip', 'html' => '<p>Tip</p>'],
        ],
    ]);

    $this->getJson('/api/insights/block-test')
        ->assertOk()
        ->assertJsonPath('data.body_blocks.0.type', 'heading')
        ->assertJsonPath('data.body_blocks.2.variant', 'tip');
});
```

- [ ] **Step 2: Manual browser verification checklist**

After this task, run through these steps in Playwright (the project's browser-testing law):

1. Seed the database: `php artisan db:seed`.
2. Visit `http://localhost:8000/insights` — verify all 8 bespoke articles + any new ones render in the hub.
3. Click one of the 8 bespoke articles (e.g. "What Is a Stocks and Shares ISA") — verify the original bespoke Vue page renders, NOT the new generic renderer.
4. Visit `http://localhost:8000/` — scroll to "Latest insights" — verify 2/3 + 1/3 bento layout renders.
5. Create a test article via `php artisan tinker` with a few blocks, publish it, visit its slug — verify block rendering.

Record each step's outcome before claiming the task complete.

- [ ] **Step 3: Commit the backend test**

```bash
git add tests/Feature/InsightArticleRenderingTest.php
git commit -m "test(insights): backend contract — body_blocks round-trip"
```

---

## Phase 5 — Admin Frontend

### Task 33b: Tailwind safelist — `bg-horizon-50`

The `CalloutBlock.vue` component maps the `info` variant to `bg-horizon-50 border-horizon-500`. The existing `tailwind.config.js` safelist has `bg-horizon-100`, `bg-horizon-400`, `bg-horizon-500` but NOT `bg-horizon-50`. If the class is constructed dynamically from a variant name (as it is in `CalloutBlock.vue`'s `variantStyle` computed), Tailwind's content scanner may not see it and will purge it from the built CSS.

**Files:**
- Modify: `tailwind.config.js` — add `bg-horizon-50` to the `safelist` array.

- [ ] **Step 1: Add the token**

In `tailwind.config.js`, locate the `safelist` array and add:

```javascript
'bg-horizon-50',
```

- [ ] **Step 2: Rebuild and verify**

```bash
./dev.sh  # or restart Vite
```

Grep the built CSS for `bg-horizon-50` — confirm it's present:

```bash
grep 'bg-horizon-50' public/build/assets/*.css 2>/dev/null | head -3
```

- [ ] **Step 3: Commit**

```bash
git add tailwind.config.js
git commit -m "chore(insights): safelist bg-horizon-50 for CalloutBlock info variant"
```

---

### Task 34: `ArticleListPage.vue`

**Files:**
- Create: `resources/js/views/Admin/Insights/ArticleListPage.vue`

- [ ] **Step 1: Implement**

```vue
<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <header class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-3xl font-black text-horizon-500" style="letter-spacing:-0.02em;">Insights</h1>
          <p class="text-sm text-neutral-500 mt-1">Manage published and draft articles.</p>
        </div>
        <router-link to="/admin/insights/new" class="px-5 py-2.5 bg-raspberry-500 text-white rounded-lg text-sm font-semibold hover:bg-raspberry-600">
          + New article
        </router-link>
      </header>

      <!-- Filters -->
      <div class="flex flex-wrap gap-3 mb-6">
        <select v-model="filters.status" class="px-3 py-2 text-sm border border-light-gray rounded-lg">
          <option value="">All statuses</option>
          <option value="draft">Draft</option>
          <option value="published">Published</option>
          <option value="archived">Archived</option>
        </select>
        <select v-model="filters.category" class="px-3 py-2 text-sm border border-light-gray rounded-lg">
          <option value="">All categories</option>
          <option value="tax-changes">Tax changes</option>
          <option value="pensions">Pensions</option>
          <option value="savings-isa">Savings & ISA</option>
          <option value="estate-planning">Estate planning</option>
          <option value="platform-updates">Platform updates</option>
        </select>
        <label class="flex items-center gap-2 text-sm text-horizon-500">
          <input type="checkbox" v-model="filters.featured" class="rounded" />
          Featured only
        </label>
      </div>

      <!-- Table -->
      <div class="card overflow-hidden">
        <table class="w-full">
          <thead class="bg-savannah-100 text-xs text-horizon-500 uppercase tracking-wide">
            <tr>
              <th class="px-4 py-3 text-left">Title</th>
              <th class="px-4 py-3 text-left">Category</th>
              <th class="px-4 py-3 text-left">Status</th>
              <th class="px-4 py-3 text-left">Published</th>
              <th class="px-4 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="article in articles" :key="article.slug" class="border-t border-light-gray hover:bg-savannah-100">
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <span class="font-semibold text-horizon-500">{{ article.title }}</span>
                  <span v-if="article.is_bespoke" class="text-[0.6rem] font-semibold px-1.5 py-0.5 rounded uppercase bg-violet-100 text-violet-700">Bespoke</span>
                  <span v-if="article.is_featured" class="text-[0.6rem] font-semibold px-1.5 py-0.5 rounded uppercase bg-raspberry-100 text-raspberry-700">Featured</span>
                </div>
              </td>
              <td class="px-4 py-3 text-sm text-neutral-500">{{ article.category }}</td>
              <td class="px-4 py-3">
                <span :class="statusClass(article.status)" class="text-xs font-semibold px-2 py-1 rounded">
                  {{ article.status }}
                </span>
              </td>
              <td class="px-4 py-3 text-sm text-neutral-500">{{ formatDate(article.published_at) }}</td>
              <td class="px-4 py-3 text-right">
                <router-link :to="`/admin/insights/${article.id}/edit`" class="text-sm text-raspberry-500 hover:underline">Edit</router-link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { mapActions, mapState } from 'vuex';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/utils/dateFormatter';

export default {
  name: 'ArticleListPage',
  components: { AppLayout },
  data() {
    return {
      filters: { status: '', category: '', featured: false },
    };
  },
  computed: {
    ...mapState('insights', { articles: 'adminList' }),
  },
  watch: {
    filters: { deep: true, handler() { this.reload(); } },
  },
  async mounted() { await this.reload(); },
  methods: {
    ...mapActions('insights', ['fetchAdminList']),
    formatDate,
    async reload() {
      await this.fetchAdminList({
        status: this.filters.status || undefined,
        category: this.filters.category || undefined,
        featured: this.filters.featured || undefined,
      });
    },
    statusClass(status) {
      return {
        'bg-neutral-100 text-neutral-500': status === 'draft',
        'bg-spring-100 text-spring-700': status === 'published',
        'bg-light-gray text-neutral-500': status === 'archived',
      };
    },
  },
};
</script>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/views/Admin/Insights/ArticleListPage.vue
git commit -m "feat(insights): admin article list page"
```

---

### Task 35: Admin block edit components (11 files)

**Files:**
- Create: `resources/js/components/Admin/Insights/blocks/EditHeadingBlock.vue`
- Create: `resources/js/components/Admin/Insights/blocks/EditParagraphBlock.vue`
- Create: `resources/js/components/Admin/Insights/blocks/EditListBlock.vue`
- Create: `resources/js/components/Admin/Insights/blocks/EditImageBlock.vue`
- Create: `resources/js/components/Admin/Insights/blocks/EditPullQuoteBlock.vue`
- Create: `resources/js/components/Admin/Insights/blocks/EditCalloutBlock.vue`
- Create: `resources/js/components/Admin/Insights/blocks/EditDividerBlock.vue`
- Create: `resources/js/components/Admin/Insights/blocks/EditCtaButtonBlock.vue`
- Create: `resources/js/components/Admin/Insights/blocks/EditTaxYearStatBlock.vue`
- Create: `resources/js/components/Admin/Insights/blocks/EditRelatedArticlesBlock.vue`
- Create: `resources/js/components/Admin/Insights/blocks/EditKeyTakeawaysBlock.vue`

Each component receives `:block="block"` as a prop and emits `update` with the modified block.

- [ ] **Step 1: `EditHeadingBlock.vue`**

```vue
<template>
  <div class="space-y-2">
    <div class="flex items-center gap-2">
      <label class="text-xs font-semibold text-neutral-500 uppercase">Level:</label>
      <select :value="block.level" @input="update('level', Number($event.target.value))" class="text-sm border border-light-gray rounded px-2 py-1">
        <option :value="2">H2</option>
        <option :value="3">H3</option>
        <option :value="4">H4</option>
      </select>
    </div>
    <input
      :value="block.text"
      @input="update('text', $event.target.value)"
      placeholder="Heading text"
      class="w-full text-lg font-bold text-horizon-500 px-3 py-2 border border-light-gray rounded"
    />
  </div>
</template>

<script>
export default {
  name: 'EditHeadingBlock',
  props: { block: { type: Object, required: true } },
  emits: ['update'],
  methods: {
    update(field, value) { this.$emit('update', { ...this.block, [field]: value }); },
  },
};
</script>
```

- [ ] **Step 2: `EditParagraphBlock.vue`** (simple textarea — inline HTML sanitised backend-side; admin can add `<strong>`, `<em>`, `<a>`)

```vue
<template>
  <textarea
    :value="block.html"
    @input="$emit('update', { ...block, html: $event.target.value })"
    placeholder="Paragraph HTML (plain text, or with <strong>/<em>/<a>)"
    rows="4"
    class="w-full text-sm text-neutral-600 px-3 py-2 border border-light-gray rounded resize-y font-mono"
  />
</template>

<script>
export default {
  name: 'EditParagraphBlock',
  props: { block: { type: Object, required: true } },
  emits: ['update'],
};
</script>
```

- [ ] **Step 3: `EditListBlock.vue`**

```vue
<template>
  <div class="space-y-2">
    <label class="flex items-center gap-2 text-sm">
      <input type="checkbox" :checked="block.ordered" @change="update('ordered', $event.target.checked)" />
      Numbered list
    </label>
    <div v-for="(item, i) in block.items" :key="i" class="flex items-center gap-2">
      <input :value="item" @input="updateItem(i, $event.target.value)" class="flex-1 text-sm px-3 py-2 border border-light-gray rounded" />
      <button type="button" @click="removeItem(i)" class="text-sm text-raspberry-500">Remove</button>
    </div>
    <button type="button" @click="addItem" class="text-sm font-semibold text-raspberry-500">+ Add item</button>
  </div>
</template>

<script>
export default {
  name: 'EditListBlock',
  props: { block: { type: Object, required: true } },
  emits: ['update'],
  methods: {
    update(field, value) { this.$emit('update', { ...this.block, [field]: value }); },
    updateItem(i, value) {
      const items = [...(this.block.items || [])];
      items[i] = value;
      this.update('items', items);
    },
    addItem() { this.update('items', [...(this.block.items || []), '']); },
    removeItem(i) {
      const items = [...(this.block.items || [])];
      items.splice(i, 1);
      this.update('items', items);
    },
  },
};
</script>
```

- [ ] **Step 4: `EditImageBlock.vue`** (integrates image upload via `insightsService`)

```vue
<template>
  <div class="space-y-2">
    <div v-if="block.path" class="relative">
      <img :src="imageUrl" :alt="block.alt" class="w-full rounded max-h-60 object-cover" />
      <button type="button" @click="clearImage" class="absolute top-2 right-2 px-2 py-1 text-xs bg-raspberry-500 text-white rounded">Replace</button>
    </div>
    <input v-else type="file" accept="image/jpeg,image/png,image/webp" @change="handleUpload" />

    <input
      :value="block.alt"
      @input="update('alt', $event.target.value)"
      placeholder="Alt text (required)"
      class="w-full text-sm px-3 py-2 border border-light-gray rounded"
    />
    <input
      :value="block.caption"
      @input="update('caption', $event.target.value)"
      placeholder="Caption (optional)"
      class="w-full text-sm px-3 py-2 border border-light-gray rounded"
    />
    <select :value="block.alignment || 'full'" @change="update('alignment', $event.target.value)" class="text-sm border border-light-gray rounded px-2 py-1">
      <option value="full">Full width</option>
      <option value="left">Float left</option>
      <option value="right">Float right</option>
    </select>
  </div>
</template>

<script>
import insightsService from '@/services/insightsService';

export default {
  name: 'EditImageBlock',
  props: {
    block: { type: Object, required: true },
    articleSlug: { type: String, required: true },
  },
  emits: ['update'],
  computed: {
    imageUrl() {
      if (!this.block.path) return null;
      if (this.block.path.startsWith('http')) return this.block.path;
      return `/storage/${this.block.path}`;
    },
  },
  methods: {
    update(field, value) { this.$emit('update', { ...this.block, [field]: value }); },
    clearImage() { this.update('path', ''); },
    async handleUpload(event) {
      const file = event.target.files[0];
      if (!file) return;
      try {
        const res = await insightsService.uploadImage(file, this.articleSlug || 'draft');
        this.$emit('update', { ...this.block, path: res.data.path });
      } catch (e) {
        alert('Upload failed: ' + (e.response?.data?.message || e.message));
      }
    },
  },
};
</script>
```

- [ ] **Step 5: Remaining 7 admin edit components** — `EditPullQuoteBlock`, `EditCalloutBlock`, `EditDividerBlock`, `EditCtaButtonBlock`, `EditTaxYearStatBlock`, `EditRelatedArticlesBlock`, `EditKeyTakeawaysBlock`. Each follows the same pattern: props `{ block }`, emits `update`, renders appropriate form controls.

Shortened reference for the remaining — create one file each with this structure (full implementations follow the patterns above: text field for strings, select for enums, repeaters for arrays):

```vue
<!-- EditPullQuoteBlock.vue -->
<template>
  <div class="space-y-2">
    <textarea :value="block.text" @input="update('text', $event.target.value)" placeholder="Quote text" rows="3" class="w-full text-sm px-3 py-2 border border-light-gray rounded" />
    <input :value="block.attribution" @input="update('attribution', $event.target.value)" placeholder="Attribution (optional)" class="w-full text-sm px-3 py-2 border border-light-gray rounded" />
  </div>
</template>
<script>
export default { name: 'EditPullQuoteBlock', props: { block: { type: Object, required: true } }, emits: ['update'], methods: { update(f, v) { this.$emit('update', { ...this.block, [f]: v }); } } };
</script>
```

```vue
<!-- EditCalloutBlock.vue -->
<template>
  <div class="space-y-2">
    <select :value="block.variant" @change="update('variant', $event.target.value)" class="text-sm border border-light-gray rounded px-2 py-1">
      <option value="info">Info (horizon)</option>
      <option value="tip">Tip (spring)</option>
      <option value="success">Success (spring)</option>
      <option value="warning">Warning (violet)</option>
    </select>
    <textarea :value="block.html" @input="update('html', $event.target.value)" placeholder="Callout HTML" rows="3" class="w-full text-sm px-3 py-2 border border-light-gray rounded font-mono" />
  </div>
</template>
<script>
export default { name: 'EditCalloutBlock', props: { block: { type: Object, required: true } }, emits: ['update'], methods: { update(f, v) { this.$emit('update', { ...this.block, [f]: v }); } } };
</script>
```

```vue
<!-- EditDividerBlock.vue -->
<template><p class="text-xs text-neutral-400 italic">Horizontal divider — no options.</p></template>
<script>export default { name: 'EditDividerBlock', props: { block: { type: Object, required: true } } };</script>
```

```vue
<!-- EditCtaButtonBlock.vue -->
<template>
  <div class="space-y-2">
    <input :value="block.label" @input="update('label', $event.target.value)" placeholder="Button label" class="w-full text-sm px-3 py-2 border border-light-gray rounded" />
    <input :value="block.href" @input="update('href', $event.target.value)" placeholder="URL (https:// or /relative)" class="w-full text-sm px-3 py-2 border border-light-gray rounded" />
    <select :value="block.style || 'primary'" @change="update('style', $event.target.value)" class="text-sm border border-light-gray rounded px-2 py-1">
      <option value="primary">Primary (raspberry)</option>
      <option value="secondary">Secondary (horizon outline)</option>
    </select>
  </div>
</template>
<script>
export default { name: 'EditCtaButtonBlock', props: { block: { type: Object, required: true } }, emits: ['update'], methods: { update(f, v) { this.$emit('update', { ...this.block, [f]: v }); } } };
</script>
```

```vue
<!-- EditTaxYearStatBlock.vue -->
<template>
  <div class="space-y-2">
    <select :value="block.stat_key" @change="update('stat_key', $event.target.value)" class="text-sm border border-light-gray rounded px-2 py-1 w-full">
      <option value="">— select a stat —</option>
      <option value="isa_annual_allowance">ISA annual allowance</option>
      <option value="personal_allowance">Personal allowance</option>
      <option value="pension_annual_allowance">Pension annual allowance</option>
      <option value="iht_nil_rate_band">IHT nil rate band</option>
      <option value="cgt_annual_allowance">CGT annual allowance</option>
    </select>
    <input :value="block.label" @input="update('label', $event.target.value)" placeholder="Display label (e.g. 'This year's ISA allowance')" class="w-full text-sm px-3 py-2 border border-light-gray rounded" />
  </div>
</template>
<script>
export default { name: 'EditTaxYearStatBlock', props: { block: { type: Object, required: true } }, emits: ['update'], methods: { update(f, v) { this.$emit('update', { ...this.block, [f]: v }); } } };
</script>
```

```vue
<!-- EditRelatedArticlesBlock.vue -->
<template>
  <div class="space-y-2">
    <p class="text-xs text-neutral-500">Select up to 4 articles:</p>
    <div class="max-h-60 overflow-y-auto border border-light-gray rounded p-2 space-y-1">
      <label v-for="article in availableArticles" :key="article.id" class="flex items-center gap-2 text-sm">
        <input
          type="checkbox"
          :value="article.id"
          :checked="isSelected(article.id)"
          :disabled="!isSelected(article.id) && selectedCount >= 4"
          @change="toggle(article.id)"
        />
        {{ article.title }}
      </label>
    </div>
  </div>
</template>
<script>
import insightsService from '@/services/insightsService';
export default {
  name: 'EditRelatedArticlesBlock',
  props: { block: { type: Object, required: true } },
  emits: ['update'],
  data() { return { availableArticles: [] }; },
  computed: {
    selectedCount() { return (this.block.article_ids || []).length; },
  },
  async mounted() {
    const res = await insightsService.list();
    this.availableArticles = res.data;
  },
  methods: {
    isSelected(id) { return (this.block.article_ids || []).includes(id); },
    toggle(id) {
      const ids = this.block.article_ids || [];
      const next = ids.includes(id) ? ids.filter(x => x !== id) : [...ids, id];
      this.$emit('update', { ...this.block, article_ids: next });
    },
  },
};
</script>
```

```vue
<!-- EditKeyTakeawaysBlock.vue -->
<template>
  <div class="space-y-2">
    <div v-for="(b, i) in block.bullets" :key="i" class="flex items-center gap-2">
      <input :value="b" @input="updateBullet(i, $event.target.value)" class="flex-1 text-sm px-3 py-2 border border-light-gray rounded" />
      <button type="button" @click="removeBullet(i)" class="text-sm text-raspberry-500">Remove</button>
    </div>
    <button type="button" @click="addBullet" class="text-sm font-semibold text-raspberry-500">+ Add takeaway</button>
  </div>
</template>
<script>
export default {
  name: 'EditKeyTakeawaysBlock',
  props: { block: { type: Object, required: true } },
  emits: ['update'],
  methods: {
    updateBullet(i, v) {
      const bullets = [...(this.block.bullets || [])];
      bullets[i] = v;
      this.$emit('update', { ...this.block, bullets });
    },
    addBullet() { this.$emit('update', { ...this.block, bullets: [...(this.block.bullets || []), ''] }); },
    removeBullet(i) {
      const bullets = [...(this.block.bullets || [])];
      bullets.splice(i, 1);
      this.$emit('update', { ...this.block, bullets });
    },
  },
};
</script>
```

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/Admin/Insights/blocks/
git commit -m "feat(insights): 11 admin block edit components"
```

---

### Task 36: `BlockPickerModal.vue`

**Files:**
- Create: `resources/js/components/Admin/Insights/BlockPickerModal.vue`

- [ ] **Step 1: Implement**

```vue
<template>
  <div v-if="isOpen" class="fixed inset-0 bg-horizon-500/50 flex items-center justify-center z-50" @click.self="$emit('close')">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full m-4 p-6">
      <h3 class="text-xl font-bold text-horizon-500 mb-4">Add a block</h3>
      <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
        <button
          v-for="option in blockOptions"
          :key="option.type"
          type="button"
          @click="pick(option.type)"
          class="p-4 bg-savannah-100 hover:bg-light-pink-100 rounded-lg text-left transition-colors"
        >
          <p class="font-semibold text-horizon-500 text-sm">{{ option.label }}</p>
          <p class="text-xs text-neutral-500 mt-1">{{ option.description }}</p>
        </button>
      </div>
      <button type="button" @click="$emit('close')" class="mt-4 text-sm text-neutral-500">Cancel</button>
    </div>
  </div>
</template>

<script>
const BLOCK_OPTIONS = [
  { type: 'heading', label: 'Heading', description: 'Section title (H2/H3/H4)', defaults: { level: 2, text: '' } },
  { type: 'paragraph', label: 'Paragraph', description: 'Rich text body content', defaults: { html: '' } },
  { type: 'list', label: 'List', description: 'Bulleted or numbered', defaults: { ordered: false, items: [''] } },
  { type: 'image', label: 'Image', description: 'Upload or embed', defaults: { path: '', alt: '', alignment: 'full' } },
  { type: 'pull_quote', label: 'Pull quote', description: 'Highlighted quote', defaults: { text: '' } },
  { type: 'callout', label: 'Callout', description: 'Info / tip / warning', defaults: { variant: 'info', html: '' } },
  { type: 'divider', label: 'Divider', description: 'Horizontal rule', defaults: {} },
  { type: 'cta_button', label: 'CTA button', description: 'Link button', defaults: { label: '', href: '', style: 'primary' } },
  { type: 'tax_year_stat', label: 'Tax year stat', description: 'Live value from TaxConfig', defaults: { stat_key: '', label: '' } },
  { type: 'related_articles', label: 'Related articles', description: 'Link 2-4 other posts', defaults: { article_ids: [] } },
  { type: 'key_takeaways', label: 'Key takeaways', description: 'Top-of-article bullets', defaults: { bullets: [''] } },
];

export default {
  name: 'BlockPickerModal',
  props: { isOpen: { type: Boolean, required: true } },
  emits: ['close', 'pick'],
  data() { return { blockOptions: BLOCK_OPTIONS }; },
  methods: {
    pick(type) {
      const option = BLOCK_OPTIONS.find(o => o.type === type);
      this.$emit('pick', { type, ...option.defaults });
    },
  },
};
</script>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/components/Admin/Insights/BlockPickerModal.vue
git commit -m "feat(insights): block picker modal with 11 options + defaults"
```

---

### Task 37: `BespokeArticleNotice.vue`

**Files:**
- Create: `resources/js/components/Admin/Insights/BespokeArticleNotice.vue`

- [ ] **Step 1: Implement**

```vue
<template>
  <aside class="p-6 bg-violet-50 border-l-4 border-violet-500 rounded-r-lg">
    <h4 class="text-sm font-bold text-violet-700 uppercase tracking-wide mb-2">Bespoke article</h4>
    <p class="text-sm text-horizon-500 leading-relaxed mb-2">
      This article is rendered by a bespoke Vue component (<code class="text-xs bg-white px-1.5 py-0.5 rounded">{{ component }}</code>).
      To edit the article's content, update the component file directly in
      <code class="text-xs bg-white px-1.5 py-0.5 rounded">resources/js/views/Public/insights/{{ component }}.vue</code>.
    </p>
    <p class="text-sm text-horizon-500">
      You can still edit the title, summary, tags, hero image, and featured status here.
    </p>
  </aside>
</template>

<script>
export default {
  name: 'BespokeArticleNotice',
  props: { component: { type: String, required: true } },
};
</script>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/components/Admin/Insights/BespokeArticleNotice.vue
git commit -m "feat(insights): notice shown in editor for bespoke articles"
```

---

### Task 38: `ArticleEditor.vue`

**Files:**
- Create: `resources/js/views/Admin/Insights/ArticleEditor.vue`

Split layout: left panel is the field form, right panel is the block canvas. Handles both create and edit (`/admin/insights/new` and `/admin/insights/:id/edit`).

- [ ] **Step 1: Implement**

```vue
<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto px-4 py-6">
      <!-- Toolbar -->
      <header class="flex items-center justify-between mb-6 pb-4 border-b border-light-gray">
        <div>
          <router-link to="/admin/insights" class="text-sm text-neutral-500 hover:text-horizon-500">← All articles</router-link>
          <h1 class="text-2xl font-black text-horizon-500 mt-1" style="letter-spacing:-0.02em;">
            {{ isNew ? 'New article' : 'Edit article' }}
          </h1>
        </div>
        <div class="flex items-center gap-3">
          <span v-if="form.status" :class="statusClass" class="text-xs font-semibold px-2 py-1 rounded uppercase">
            {{ form.status }}
          </span>
          <button type="button" @click="saveDraft" :disabled="saving" class="px-4 py-2 text-sm font-semibold border border-horizon-500 text-horizon-500 rounded hover:bg-horizon-50">
            {{ saving ? 'Saving…' : 'Save draft' }}
          </button>
          <button type="button" @click="preview" class="px-4 py-2 text-sm font-semibold border border-horizon-500 text-horizon-500 rounded hover:bg-horizon-50">
            Preview
          </button>
          <button v-if="!isNew && !form.is_bespoke" type="button" @click="saveAsTemplateModal = true" class="px-4 py-2 text-sm font-semibold border border-horizon-500 text-horizon-500 rounded hover:bg-horizon-50">
            Save as template
          </button>
          <button type="button" @click="publish" :disabled="saving" class="px-5 py-2 text-sm font-semibold bg-raspberry-500 text-white rounded hover:bg-raspberry-600">
            {{ form.status === 'published' ? 'Update' : 'Publish' }}
          </button>
        </div>
      </header>

      <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
        <!-- Field panel -->
        <section class="lg:col-span-2 space-y-4">
          <div>
            <label class="block text-xs font-semibold text-neutral-500 uppercase mb-1">Title</label>
            <input v-model="form.title" class="w-full text-lg font-bold text-horizon-500 px-3 py-2 border border-light-gray rounded" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-neutral-500 uppercase mb-1">Subtitle</label>
            <input v-model="form.subtitle" class="w-full text-sm px-3 py-2 border border-light-gray rounded" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-neutral-500 uppercase mb-1">Summary</label>
            <textarea v-model="form.summary" rows="3" class="w-full text-sm px-3 py-2 border border-light-gray rounded"></textarea>
          </div>
          <div>
            <label class="block text-xs font-semibold text-neutral-500 uppercase mb-1">Category</label>
            <select v-model="form.category" class="w-full text-sm px-3 py-2 border border-light-gray rounded">
              <option value="tax-changes">Tax changes</option>
              <option value="pensions">Pensions</option>
              <option value="savings-isa">Savings & ISA</option>
              <option value="estate-planning">Estate planning</option>
              <option value="platform-updates">Platform updates</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-neutral-500 uppercase mb-1">Tags (comma-separated)</label>
            <input :value="(form.tags || []).join(', ')" @input="form.tags = $event.target.value.split(',').map(s => s.trim()).filter(Boolean)" class="w-full text-sm px-3 py-2 border border-light-gray rounded" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-neutral-500 uppercase mb-1">Hero image</label>
            <div v-if="form.hero_image_card_path" class="relative mb-2">
              <img :src="`/storage/${form.hero_image_card_path}`" class="rounded w-full max-h-40 object-cover" />
              <button type="button" @click="clearHero" class="absolute top-2 right-2 px-2 py-1 text-xs bg-raspberry-500 text-white rounded">Replace</button>
            </div>
            <input v-else type="file" accept="image/jpeg,image/png,image/webp" @change="handleHeroUpload" />
          </div>
          <label class="flex items-center gap-2 text-sm text-horizon-500">
            <input type="checkbox" v-model="form.is_featured" />
            Featured on landing page
          </label>

          <!-- SEO overrides (collapsed) -->
          <details class="border border-light-gray rounded p-3">
            <summary class="cursor-pointer text-sm font-semibold text-horizon-500">Search & sharing (SEO)</summary>
            <div class="space-y-3 mt-3">
              <div>
                <label class="block text-xs text-neutral-500 mb-1">Meta title (defaults to article title)</label>
                <input v-model="form.meta_title" class="w-full text-sm px-3 py-2 border border-light-gray rounded" />
              </div>
              <div>
                <label class="block text-xs text-neutral-500 mb-1">Meta description (defaults to summary)</label>
                <textarea v-model="form.meta_description" rows="2" class="w-full text-sm px-3 py-2 border border-light-gray rounded"></textarea>
              </div>
              <div>
                <label class="block text-xs text-neutral-500 mb-1">Canonical URL (optional)</label>
                <input v-model="form.canonical_url" class="w-full text-sm px-3 py-2 border border-light-gray rounded" />
              </div>
            </div>
          </details>
        </section>

        <!-- Canvas -->
        <section class="lg:col-span-3 space-y-4">
          <BespokeArticleNotice v-if="form.is_bespoke" :component="form.bespoke_component" />

          <template v-else>
            <!-- Template picker (new articles only) -->
            <div v-if="isNew && !templatePicked" class="p-6 bg-savannah-100 rounded-lg">
              <h3 class="font-bold text-horizon-500 mb-3">Start with a template</h3>
              <div class="space-y-2">
                <button type="button" @click="startBlank" class="w-full p-3 text-left bg-white border border-light-gray rounded hover:bg-light-pink-100">
                  <p class="font-semibold text-horizon-500 text-sm">Blank</p>
                  <p class="text-xs text-neutral-500">Empty canvas, add blocks as you go</p>
                </button>
                <button v-for="t in templates" :key="t.id" type="button" @click="useTemplate(t)" class="w-full p-3 text-left bg-white border border-light-gray rounded hover:bg-light-pink-100">
                  <p class="font-semibold text-horizon-500 text-sm">{{ t.name }}</p>
                  <p class="text-xs text-neutral-500">{{ t.description }}</p>
                </button>
              </div>
            </div>

            <div v-else>
              <div v-for="(block, i) in form.body_blocks" :key="i" class="p-4 bg-white border border-light-gray rounded-lg mb-3">
                <header class="flex items-center justify-between mb-3 pb-2 border-b border-light-gray">
                  <span class="text-xs font-semibold text-neutral-500 uppercase tracking-wide">{{ block.type.replace('_', ' ') }}</span>
                  <div class="flex items-center gap-2 text-xs">
                    <button type="button" @click="moveBlock(i, -1)" :disabled="i === 0" class="text-neutral-500 hover:text-horizon-500">↑</button>
                    <button type="button" @click="moveBlock(i, 1)" :disabled="i === form.body_blocks.length - 1" class="text-neutral-500 hover:text-horizon-500">↓</button>
                    <button type="button" @click="duplicateBlock(i)" class="text-neutral-500 hover:text-horizon-500">Duplicate</button>
                    <button type="button" @click="removeBlock(i)" class="text-raspberry-500">Delete</button>
                  </div>
                </header>
                <component
                  :is="editorForType(block.type)"
                  :block="block"
                  :article-slug="form.slug || ''"
                  @update="updateBlock(i, $event)"
                />
              </div>

              <button type="button" @click="pickerOpen = true" class="w-full py-3 bg-horizon-50 hover:bg-horizon-100 border-2 border-dashed border-horizon-500 rounded-lg text-sm font-semibold text-horizon-500">
                + Add block
              </button>
            </div>
          </template>
        </section>
      </div>

      <BlockPickerModal :is-open="pickerOpen" @close="pickerOpen = false" @pick="addBlock" />

      <!-- Save-as-template modal -->
      <div v-if="saveAsTemplateModal" class="fixed inset-0 bg-horizon-500/50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
          <h3 class="text-lg font-bold text-horizon-500 mb-3">Save as template</h3>
          <input v-model="newTemplateName" placeholder="Template name" class="w-full text-sm px-3 py-2 border border-light-gray rounded mb-2" />
          <input v-model="newTemplateDesc" placeholder="Description (optional)" class="w-full text-sm px-3 py-2 border border-light-gray rounded mb-4" />
          <div class="flex justify-end gap-2">
            <button type="button" @click="saveAsTemplateModal = false" class="px-4 py-2 text-sm text-neutral-500">Cancel</button>
            <button type="button" @click="saveAsTemplate" class="px-4 py-2 text-sm bg-raspberry-500 text-white rounded">Save</button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { mapActions, mapGetters } from 'vuex';
import AppLayout from '@/layouts/AppLayout.vue';
import BespokeArticleNotice from '@/components/Admin/Insights/BespokeArticleNotice.vue';
import BlockPickerModal from '@/components/Admin/Insights/BlockPickerModal.vue';
import insightsService from '@/services/insightsService';

import EditHeadingBlock from '@/components/Admin/Insights/blocks/EditHeadingBlock.vue';
import EditParagraphBlock from '@/components/Admin/Insights/blocks/EditParagraphBlock.vue';
import EditListBlock from '@/components/Admin/Insights/blocks/EditListBlock.vue';
import EditImageBlock from '@/components/Admin/Insights/blocks/EditImageBlock.vue';
import EditPullQuoteBlock from '@/components/Admin/Insights/blocks/EditPullQuoteBlock.vue';
import EditCalloutBlock from '@/components/Admin/Insights/blocks/EditCalloutBlock.vue';
import EditDividerBlock from '@/components/Admin/Insights/blocks/EditDividerBlock.vue';
import EditCtaButtonBlock from '@/components/Admin/Insights/blocks/EditCtaButtonBlock.vue';
import EditTaxYearStatBlock from '@/components/Admin/Insights/blocks/EditTaxYearStatBlock.vue';
import EditRelatedArticlesBlock from '@/components/Admin/Insights/blocks/EditRelatedArticlesBlock.vue';
import EditKeyTakeawaysBlock from '@/components/Admin/Insights/blocks/EditKeyTakeawaysBlock.vue';

const EDITOR_MAP = {
  heading: EditHeadingBlock,
  paragraph: EditParagraphBlock,
  list: EditListBlock,
  image: EditImageBlock,
  pull_quote: EditPullQuoteBlock,
  callout: EditCalloutBlock,
  divider: EditDividerBlock,
  cta_button: EditCtaButtonBlock,
  tax_year_stat: EditTaxYearStatBlock,
  related_articles: EditRelatedArticlesBlock,
  key_takeaways: EditKeyTakeawaysBlock,
};

export default {
  name: 'ArticleEditor',
  components: { AppLayout, BespokeArticleNotice, BlockPickerModal },
  data() {
    return {
      form: {
        title: '',
        subtitle: '',
        summary: '',
        category: 'pensions',
        tags: [],
        hero_image_path: null,
        hero_image_card_path: null,
        hero_image_thumb_path: null,
        body_blocks: [],
        template_id: null,
        status: 'draft',
        is_featured: false,
        is_bespoke: false,
        bespoke_component: null,
        meta_title: '',
        meta_description: '',
        canonical_url: '',
        slug: null,
      },
      saving: false,
      pickerOpen: false,
      templatePicked: false,
      saveAsTemplateModal: false,
      newTemplateName: '',
      newTemplateDesc: '',
      articleId: null,
    };
  },
  computed: {
    ...mapGetters('insights', ['templates']),
    isNew() { return !this.articleId; },
    statusClass() {
      return {
        'bg-neutral-100 text-neutral-500': this.form.status === 'draft',
        'bg-spring-100 text-spring-700': this.form.status === 'published',
        'bg-light-gray text-neutral-500': this.form.status === 'archived',
      };
    },
  },
  async mounted() {
    await this.fetchTemplates();

    if (this.$route.params.id) {
      this.articleId = Number(this.$route.params.id);
      const res = await insightsService.adminGet(this.articleId);
      this.form = { ...this.form, ...res.data };
      this.templatePicked = true;
    }
  },
  methods: {
    ...mapActions('insights', ['fetchTemplates', 'saveAsTemplate']),
    editorForType(type) { return EDITOR_MAP[type] || null; },
    startBlank() {
      this.form.body_blocks = [];
      this.templatePicked = true;
    },
    useTemplate(t) {
      this.form.body_blocks = JSON.parse(JSON.stringify(t.body_blocks || []));
      this.form.template_id = t.id;
      this.templatePicked = true;
    },
    addBlock(block) {
      this.form.body_blocks.push(block);
      this.pickerOpen = false;
    },
    updateBlock(i, block) { this.form.body_blocks.splice(i, 1, block); },
    moveBlock(i, dir) {
      const j = i + dir;
      if (j < 0 || j >= this.form.body_blocks.length) return;
      const blocks = [...this.form.body_blocks];
      [blocks[i], blocks[j]] = [blocks[j], blocks[i]];
      this.form.body_blocks = blocks;
    },
    duplicateBlock(i) {
      const copy = JSON.parse(JSON.stringify(this.form.body_blocks[i]));
      this.form.body_blocks.splice(i + 1, 0, copy);
    },
    removeBlock(i) { this.form.body_blocks.splice(i, 1); },
    async handleHeroUpload(event) {
      const file = event.target.files[0];
      if (!file) return;
      const slug = this.form.slug || this.slugify(this.form.title) || 'draft';
      try {
        const res = await insightsService.uploadImage(file, slug);
        this.form.hero_image_path = res.data.path;
        this.form.hero_image_card_path = res.data.card_path;
        this.form.hero_image_thumb_path = res.data.thumb_path;
      } catch (e) {
        alert('Upload failed');
      }
    },
    clearHero() {
      this.form.hero_image_path = null;
      this.form.hero_image_card_path = null;
      this.form.hero_image_thumb_path = null;
    },
    slugify(text) {
      return (text || '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    },
    async saveDraft() {
      this.saving = true;
      try {
        if (this.isNew) {
          const res = await insightsService.create(this.form);
          this.articleId = res.data.id;
          this.$router.replace(`/admin/insights/${this.articleId}/edit`);
        } else {
          await insightsService.update(this.articleId, this.form);
        }
      } catch (e) {
        alert('Save failed: ' + (e.response?.data?.message || e.message));
      } finally {
        this.saving = false;
      }
    },
    async publish() {
      await this.saveDraft();
      if (this.articleId) {
        await insightsService.publish(this.articleId);
        this.form.status = 'published';
        alert('Published!');
      }
    },
    preview() {
      if (!this.form.slug) { alert('Save a draft first to generate a slug.'); return; }
      window.open(`/insights/${this.form.slug}?preview=true`, '_blank');
    },
    async saveAsTemplate() {
      if (!this.newTemplateName) return;
      await this.saveAsTemplate({
        articleId: this.articleId,
        name: this.newTemplateName,
        description: this.newTemplateDesc,
      });
      this.saveAsTemplateModal = false;
      this.newTemplateName = '';
      this.newTemplateDesc = '';
      alert('Template saved.');
    },
  },
};
</script>
```

Note: There's a naming collision — the Vuex action `saveAsTemplate` and the local method `saveAsTemplate`. Rename the local method to `submitSaveAsTemplate` in the button handler and method, and call `this.saveAsTemplate({...})` mapped from Vuex.

Rename inline — update the button: `@click="submitSaveAsTemplate"` and the method:
```js
async submitSaveAsTemplate() {
  if (!this.newTemplateName) return;
  await this.saveAsTemplate({ articleId: this.articleId, name: this.newTemplateName, description: this.newTemplateDesc });
  this.saveAsTemplateModal = false;
  this.newTemplateName = '';
  this.newTemplateDesc = '';
  alert('Template saved.');
},
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/views/Admin/Insights/ArticleEditor.vue
git commit -m "feat(insights): admin article editor with split layout + block canvas"
```

---

### Task 39: `TemplateListPage.vue`

**Files:**
- Create: `resources/js/views/Admin/Insights/TemplateListPage.vue`

- [ ] **Step 1: Implement**

```vue
<template>
  <AppLayout>
    <div class="max-w-4xl mx-auto px-4 py-8">
      <header class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-black text-horizon-500" style="letter-spacing:-0.02em;">Article templates</h1>
        <router-link to="/admin/insights" class="text-sm text-neutral-500 hover:text-horizon-500">← Articles</router-link>
      </header>

      <div class="card p-4">
        <table class="w-full">
          <thead class="bg-savannah-100 text-xs text-horizon-500 uppercase tracking-wide">
            <tr>
              <th class="px-4 py-3 text-left">Name</th>
              <th class="px-4 py-3 text-left">Description</th>
              <th class="px-4 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="t in templates" :key="t.id" class="border-t border-light-gray">
              <td class="px-4 py-3 font-semibold text-horizon-500">{{ t.name }}</td>
              <td class="px-4 py-3 text-sm text-neutral-500">{{ t.description }}</td>
              <td class="px-4 py-3 text-right space-x-3">
                <button type="button" @click="rename(t)" class="text-sm text-horizon-500 hover:underline">Rename</button>
                <button type="button" @click="remove(t)" class="text-sm text-raspberry-500 hover:underline">Delete</button>
              </td>
            </tr>
          </tbody>
        </table>
        <p v-if="!templates.length" class="p-6 text-center text-sm text-neutral-500">No templates yet. Create one by editing an article and clicking "Save as template".</p>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { mapActions, mapGetters } from 'vuex';
import AppLayout from '@/layouts/AppLayout.vue';

export default {
  name: 'TemplateListPage',
  components: { AppLayout },
  computed: { ...mapGetters('insights', ['templates']) },
  async mounted() { await this.fetchTemplates(); },
  methods: {
    ...mapActions('insights', ['fetchTemplates', 'renameTemplate', 'deleteTemplate']),
    async rename(t) {
      const name = prompt('New name:', t.name);
      if (!name) return;
      await this.renameTemplate({ id: t.id, name });
    },
    async remove(t) {
      if (!confirm(`Delete template "${t.name}"? Articles using it will keep their blocks but lose the template reference.`)) return;
      await this.deleteTemplate(t.id);
    },
  },
};
</script>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/views/Admin/Insights/TemplateListPage.vue
git commit -m "feat(insights): admin template list page"
```

---

### Task 40: Admin router entries

**Files:**
- Modify: `resources/js/router/index.js`

- [ ] **Step 1: Add lazy imports and routes**

Near the other admin imports:

```javascript
const InsightsArticleListPage = () => import('@/views/Admin/Insights/ArticleListPage.vue');
const InsightsArticleEditor = () => import('@/views/Admin/Insights/ArticleEditor.vue');
const InsightsTemplateListPage = () => import('@/views/Admin/Insights/TemplateListPage.vue');
```

Inside the admin routes group (find `path: '/admin'` and its children):

```javascript
{ path: '/admin/insights', name: 'AdminInsights', component: InsightsArticleListPage, meta: { requiresAuth: true, requiresAdmin: true } },
{ path: '/admin/insights/new', name: 'AdminInsightNew', component: InsightsArticleEditor, meta: { requiresAuth: true, requiresAdmin: true } },
{ path: '/admin/insights/:id/edit', name: 'AdminInsightEdit', component: InsightsArticleEditor, meta: { requiresAuth: true, requiresAdmin: true } },
{ path: '/admin/insights/templates', name: 'AdminInsightTemplates', component: InsightsTemplateListPage, meta: { requiresAuth: true, requiresAdmin: true } },
```

- [ ] **Step 2: Add a sidebar link in the admin navigation**

Find the admin sidebar component (likely `resources/js/components/Admin/Navigation.vue` or similar — grep for `/admin/users` to locate). Add:

```vue
<router-link to="/admin/insights" class="...">Insights</router-link>
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/router/index.js resources/js/components/Admin/
git commit -m "feat(insights): admin router entries + sidebar link"
```

---

### Task 41: Admin end-to-end browser test (manual checklist)

Per the project's browser-testing law, walk through the admin flow in Playwright:

- [ ] Log in as `chris@fynla.org` locally (fetch verification code from DB per CLAUDE.md instructions)
- [ ] Visit `/admin/insights` — list loads, shows 8 bespoke articles with "Bespoke" badges
- [ ] Click "New article" — template picker shows, pick "Blank"
- [ ] Fill in title, subtitle, summary, category
- [ ] Upload a hero image — verify preview appears
- [ ] Click "+ Add block" — modal shows 11 options
- [ ] Add a heading block, paragraph block, callout block
- [ ] Click "Save draft" — verify article created, URL updates to `/edit`
- [ ] Click "Preview" — new tab opens with the draft rendered
- [ ] Click "Publish" — status changes to "Published"
- [ ] Visit `/insights/:slug` in another tab — article renders with all blocks
- [ ] Visit `/insights` — new article appears in hub
- [ ] Visit `/` — new article does NOT appear in landing hero (not featured)
- [ ] Go back to the editor, toggle "Featured on landing page", save
- [ ] Visit `/` — article now appears in the big featured tile
- [ ] Click an existing bespoke article (e.g. StocksSharesIsa) — editor shows "Bespoke article" notice, block canvas is replaced, metadata fields are editable
- [ ] Create a second article, click "Save as template", name it "Standard"
- [ ] Create a third article, pick "Standard" template in picker — verify blocks are pre-populated

Record each step's outcome. Do not mark this task complete until every step above has been performed in Playwright.

---

### Task 41b: AdminPanel.vue tab integration

Without this task, admins can reach `/admin/insights` only by typing the URL. The AdminPanel tab navigation (the sidebar of the admin area) must show "Insights" alongside other admin sections.

**Files:**
- Modify: `resources/js/views/Admin/AdminPanel.vue` — add navItems entry + tab icon case + tab render switch.

- [ ] **Step 1: Inspect the AdminPanel tab structure**

```bash
grep -n 'navItems\|getTabIcon\|activeTab' resources/js/views/Admin/AdminPanel.vue | head -20
```

Identify the `navItems` array (computed property), the `getTabIcon(tab)` method, and the template block that renders `<router-view />` or a conditional component based on `activeTab`.

- [ ] **Step 2: Add an "Insights" entry**

Add to the `navItems` array (use the same shape as other entries — typically `{ id: 'insights', label: 'Insights', path: '/admin/insights' }`). Add a case in `getTabIcon()` for `'insights'` returning an appropriate icon (use the existing icon set — a document or pencil icon is suitable).

- [ ] **Step 3: Verify the link navigates correctly**

With dev server running, log in as admin, visit `/admin`, confirm "Insights" appears in the sidebar and clicking it navigates to `/admin/insights`.

- [ ] **Step 4: Commit**

```bash
git add resources/js/views/Admin/AdminPanel.vue
git commit -m "feat(insights): add Insights tab to AdminPanel sidebar"
```

---

### Task 41c: Feature flag `VITE_INSIGHTS_CMS_ENABLED`

Gates the public frontend changes so that Phases 1-3 (backend) can ship to production safely before the frontend is activated. This reduces deploy-window risk: if something breaks in the hub/landing refactor, toggling the flag reverts to the pre-CMS state without a code rollback.

**Files:**
- Modify: `deploy/fynla-org/build.sh` — set `VITE_INSIGHTS_CMS_ENABLED=false` initially.
- Modify: `deploy/csjones-fynla/build.sh` — set `VITE_INSIGHTS_CMS_ENABLED=true`.
- Modify: `resources/js/router/index.js` — gate the `/insights/:slug` catch-all route.
- Modify: `resources/js/views/Public/insights/InsightsHubPage.vue` — fall back to hardcoded array when flag is false.
- Modify: `resources/js/views/Public/LandingPage.vue` — fall back to old 3-card section when flag is false.

- [ ] **Step 1: Add the env variable to both build scripts**

In `deploy/fynla-org/build.sh`, locate the `VITE_*` export block and add:

```bash
export VITE_INSIGHTS_CMS_ENABLED=false
```

(Flip to `true` only after Phases 1-3 are deployed, migrations run, seeder run, and public API manually verified.)

In `deploy/csjones-fynla/build.sh`:

```bash
export VITE_INSIGHTS_CMS_ENABLED=true
```

(Dev is always on so the feature is testable.)

- [ ] **Step 2: Gate the catch-all route in `router/index.js`**

```javascript
const insightsCmsEnabled = import.meta.env.VITE_INSIGHTS_CMS_ENABLED === 'true';

// ... inside the routes array, conditionally include:
...(insightsCmsEnabled
  ? [{ path: '/insights/:slug', name: 'InsightArticle', component: InsightArticlePage, meta: { public: true } }]
  : []
),
```

- [ ] **Step 3: Fall back in `InsightsHubPage.vue`**

If `VITE_INSIGHTS_CMS_ENABLED !== 'true'`, skip the Vuex fetch and fall back to the hardcoded `articles` array (keep the array in the file, protected by the flag, until the flag is permanently on). Once production flips to true and stays stable for a release cycle, the fallback array can be removed in a follow-up commit.

```javascript
computed: {
  insightsCmsEnabled() {
    return import.meta.env.VITE_INSIGHTS_CMS_ENABLED === 'true';
  },
  // ... existing computed ...
},

async mounted() {
  if (this.insightsCmsEnabled) {
    await this.fetchList();
  }
  // else: the hardcoded `articles` in data() is used directly
  this.loading = false;
},
```

- [ ] **Step 4: Same fallback in `LandingPage.vue`**

Similar gate: if the flag is false, render the legacy 3-card section (keep the old template and the hardcoded array behind `v-if="!insightsCmsEnabled"`, the new 2/3+1/3 layout behind `v-else`).

- [ ] **Step 5: Test both flag states**

With the dev server running, temporarily set `VITE_INSIGHTS_CMS_ENABLED=false` in `.env` and restart Vite. Verify the hub and landing render the legacy layout from the hardcoded array. Then set it back to `true` and verify the new DB-driven layouts.

- [ ] **Step 6: Commit**

```bash
git add deploy/fynla-org/build.sh deploy/csjones-fynla/build.sh resources/js/router/index.js resources/js/views/Public/insights/InsightsHubPage.vue resources/js/views/Public/LandingPage.vue
git commit -m "feat(insights): VITE_INSIGHTS_CMS_ENABLED flag for phased production rollout"
```

---

## Phase 6 — Polish & Integration

### Task 42: Architecture tests

**Files:**
- Create: `tests/Architecture/InsightsArchitectureTest.php`

- [ ] **Step 1: Write tests**

```php
<?php

declare(strict_types=1);

// Architecture test: catch-all /insights/:slug route must come after all named insight routes.
it('has /insights/:slug catch-all declared after named insight routes', function () {
    $router = file_get_contents(base_path('resources/js/router/index.js'));

    // Find all "Insight" route declarations and their order
    preg_match_all('/name:\s*\'(Insight[A-Za-z]*)\'/', $router, $matches);
    $names = $matches[1];

    // `InsightArticle` (catch-all) must be the last Insight* named route
    $lastIndex = count($names) - 1;
    expect($names[$lastIndex])->toBe('InsightArticle');
});

it('has no hardcoded tax years in insights code', function () {
    $paths = [
        base_path('app/Services/Insights'),
        base_path('app/Http/Controllers/Api/Admin'),
        base_path('app/Http/Controllers/Api/Public/InsightController.php'),
        base_path('resources/js/components/Insights'),
        base_path('resources/js/views/Public/insights'),
    ];

    $badPatterns = [
        '/20\d\d\/\d\d/',            // "2025/26"
        '/£20,000(?![a-zA-Z0-9_])/', // ISA allowance (any change needs to come from TaxConfigService)
    ];

    $violations = [];
    foreach ($paths as $path) {
        if (!file_exists($path)) continue;
        $rii = is_dir($path)
            ? new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path))
            : [new \SplFileInfo($path)];

        foreach ($rii as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && in_array($file->getExtension(), ['php', 'vue', 'js'])) {
                $contents = file_get_contents($file->getPathname());
                foreach ($badPatterns as $pat) {
                    if (preg_match($pat, $contents)) {
                        $violations[] = "{$file->getPathname()}: matches {$pat}";
                    }
                }
            }
        }
    }

    expect($violations)->toBe([]);
});

it('all Insights services use constructor injection', function () {
    $files = glob(app_path('Services/Insights/*.php'));
    foreach ($files as $file) {
        $content = file_get_contents($file);
        // Services that declare a constructor must use type-hinted params
        if (preg_match('/public function __construct\s*\(\s*\)/', $content)) {
            // Empty constructor is fine
            continue;
        }
        if (preg_match('/public function __construct\s*\((.+?)\)/s', $content, $m)) {
            expect($m[1])->toMatch('/\\\\?[A-Z][a-zA-Z]+/');
        }
    }
});

// Web route ordering: /insights/{slug} blade middleware route must come BEFORE the /{any} SPA catch-all.
// Otherwise the SPA catch-all matches first and the SEO middleware never fires.
it('has /insights/{slug} web.php route declared before the SPA catch-all', function () {
    $web = file_get_contents(base_path('routes/web.php'));

    $insightPos = strpos($web, "'/insights/{slug}'");
    $catchAllPos = strpos($web, "'/{any}'");

    expect($insightPos)->not->toBeFalse()
        ->and($catchAllPos)->not->toBeFalse()
        ->and($insightPos)->toBeLessThan($catchAllPos);
});
```

- [ ] **Step 2: Run, commit**

```bash
./vendor/bin/pest tests/Architecture/InsightsArchitectureTest.php
git add tests/Architecture/InsightsArchitectureTest.php
git commit -m "test(insights): architecture guardrails for route ordering + tax hardcoding"
```

---

### Task 43: Full test suite run + fix regressions

- [ ] **Step 1: Run the entire Pest suite**

```bash
./vendor/bin/pest 2>&1 | tail -40
```

Expected: all new tests pass, no existing tests break. If any existing tests break, investigate — usually a factory collision, a shared seed, or a change in a resource structure.

- [ ] **Step 2: If any existing tests break, fix each one in a separate commit**

Do not batch regression fixes — one test fix per commit with a clear message explaining what changed and why.

- [ ] **Step 3: Run architecture + integration tests**

```bash
./vendor/bin/pest --testsuite=Architecture
./vendor/bin/pest --testsuite=Integration
```

Expected: all green.

---

### Task 44: Deploy notes

**Files:**
- Create: `deploy/notes/2026-04-17-insights-cms.md`

- [ ] **Step 1: Generate deploy notes from `git diff`** (per the project's deploy-guide rule — generate from diff, not memory)

```bash
git log main..HEAD --name-only --pretty=format: | sort -u | grep -v '^$' > /tmp/changed-files.txt
cat /tmp/changed-files.txt
```

- [ ] **Step 2: Write the deploy note**

```markdown
# Admin Insights CMS — Deploy Guide

**Date:** 17 April 2026
**Branch:** feature/csj/insights-cms
**Environments:** dev (csjones.co/fynla) → production (fynla.org)

## Database migrations

Three new tables. Run on each environment after uploading code:

```bash
php artisan migrate --force
```

Migrations added (apply in this order via timestamp):
- `2026_04_17_090001_create_insight_templates_table.php`
- `2026_04_17_090002_create_insight_articles_table.php`
- `2026_04_17_090003_create_insight_article_revisions_table.php`

## Seeder

Run once after migrate:

```bash
php artisan db:seed --class=ExistingInsightsMetadataSeeder --force
```

Idempotent — safe to re-run. Inserts/updates 9 rows for the bespoke articles.

## PHP files to upload

[Generated from `git diff main..HEAD --name-only` — fill in on commit]

## Frontend build

Build locally per environment:

- **Production (fynla.org):** `./deploy/fynla-org/build.sh`
- **Dev (csjones.co/fynla):** `./deploy/csjones-fynla/build.sh`

Upload `public/build/` contents via SiteGround File Manager.

## Cache clear

After upload:

```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan optimize
```

## Cron

Confirm scheduler is running on each environment (fynla.org cron already set up per session 56). The `PublishScheduledInsightsJob` runs every 5 minutes and doesn't require any separate configuration — it piggybacks on the existing schedule.

## Storage symlink

Confirm `public/storage` is a symlink to `storage/app/public` on each host:

```bash
ls -la public/storage
```

If not linked: `php artisan storage:link`.

## Smoke tests after deploy

1. Visit `https://{host}/insights` — hub page renders 8 bespoke articles.
2. Visit `https://{host}/` — landing page "Latest insights" section renders 2/3 + 1/3 layout.
3. Log in as an admin, visit `/admin/insights` — list loads.
4. Create a draft article with one heading and one paragraph block, save, preview, publish.
5. Visit the article's public URL — renders correctly.
6. Revert: toggle feature off, delete the test article.

## Rollback

```bash
php artisan migrate:rollback --step=3
```

(Drops the 3 new tables. Any data loss is on test articles — the bespoke article rows will be re-inserted by the seeder on next re-deploy.)
```

- [ ] **Step 3: Save the deploy guide in both locations** (per the standing rule)

```bash
cp deploy/notes/2026-04-17-insights-cms.md /Users/CSJ/Desktop/fynlaBrain/April/April17Updates/deployInsightsCms.md
```

- [ ] **Step 4: Commit**

```bash
git add deploy/notes/2026-04-17-insights-cms.md
git commit -m "docs(insights): deploy guide for CMS rollout"
```

---

## Plan self-review (post-amendment)

**Amendment summary (applied 17 April 2026 after codebase audit):**
- Article count corrected from 9 to 8 throughout spec and plan.
- Admin middleware changed from `admin` alias to `permission:admin.access` on all admin routes. Controllers also add constructor-level `$this->middleware('permission:admin.access')`.
- Sitemap integration (old Task 23) deferred — no `SitemapController` exists in Fynla today.
- Revision writes now owned exclusively by `InsightArticleObserver`; removed from `InsightArticleService::update()`.
- Backend HTML sanitisation uses PHP `strip_tags` with allowlist; HTMLPurifier dependency dropped.
- `intervention/image` install step added as Task 10 Step 0 (was previously assumed installed).
- DOMPurify install converted from "verify if missing" to explicit install step.
- New Task 16b: `SanitizeInput` middleware update to preserve `body_blocks` HTML.
- Cache busting switched to generation counter (`insights.list_version` increment) to invalidate all paginated pages atomically.
- `InsightsSeoMetaInjector` middleware now pushes into a `@stack('head')` placeholder added to `app.blade.php` (was previously assumed to inject into Blade conditions that didn't exist).
- New Task 33b: Tailwind safelist for `bg-horizon-50`.
- New Task 41b: AdminPanel.vue tab navigation entry.
- New Task 41c: `VITE_INSIGHTS_CMS_ENABLED` feature flag for phased production rollout.
- Architecture test added for `routes/web.php` route ordering.
- Design guide version updated from v1.3.0 to v1.4.0.
- Spec's explanation of `PreviewWriteInterceptor` ordering corrected.

**Spec coverage check:**
- Scope & migration (hybrid) → Tasks 8, 29, 30 ✓
- Structured blocks format → Tasks 15, 26, 27 ✓
- Template semantics (copy-paste) + save-from-article → Tasks 2, 11, 19, 38 ✓
- 10 block types (catalogue minus Table) → Tasks 15, 26, 35 ✓
- Landing page hero 2/3 + 1/3 → Task 31 ✓
- Article schema (17 fields) → Task 1, 4 ✓
- Featuring rules (one at a time, auto-unfeature) → Task 9 (`setFeatured`), 18 ✓
- Publishing workflow (draft/published/scheduled/archived) → Tasks 9, 14, 18 ✓
- Scheduled publish cron → Task 14 ✓
- Bespoke articles metadata migration → Task 8 ✓
- Image upload + resize (WebP) → Tasks 10, 20, 35 ✓
- SEO (meta, OG, Twitter, JSON-LD) → Tasks 12, 22 ✓
- Sitemap inclusion → DEFERRED (Task 23 skipped — no existing `SitemapController`) — documented in spec's Sitemap section
- Admin auth via `permission:admin.access` → Tasks 18-20 routes + controller constructor checks ✓
- Audit via `Auditable` trait → Model setup in Task 4, 5 ✓
- Revisions → Task 3, 6, 13 ✓
- Route precedence (named before catch-all) → Task 29, 42 ✓
- Cache bust on feature change → Task 13 ✓
- Admin preview drafts → Task 21 (controller logic), Task 38 (Preview button) ✓
- Tax-year stat block → Task 26 (step 9) ✓

All spec sections covered.

**Placeholder scan:** Reviewed — no TBD/TODO/fill-in phrases. One flag: in Task 38's template, the method `saveAsTemplate` collides with the Vuex action of the same name; called out inline with a rename to `submitSaveAsTemplate`.

**Type consistency:** Method names used consistently — `fetchList`, `fetchFeatured`, `fetchBySlug`, `setFeatured`, `unsetFeatured`, `resyncFromTemplate`. Model class paths consistent (`App\Models\Insights\*`). Block types consistent across validator, renderer, and editor components.

**Scope check:** The plan covers one coherent feature. Large but cohesive — splitting would create artificial boundaries (e.g. "backend plan" and "frontend plan" that can't be shipped independently).

---

## Execution handoff

Plan complete and saved to `docs/superpowers/plans/2026-04-17-admin-insights-cms.md`. Two execution options:

**1. Subagent-Driven (recommended)** — I dispatch a fresh subagent per task, review between tasks, fast iteration. Best for a plan this size because each task is small enough to be one subagent dispatch, and the review-between-tasks catches drift early.

**2. Inline Execution** — Execute tasks in this session using the executing-plans skill, with checkpoints at phase boundaries (every 5-10 tasks).

Given this plan has 44 tasks across 6 phases and touches ~80+ files, subagent-driven is more reliable — but note the user memory rule: _"If using subagents, MUST check their work rigorously. Prefer inline execution for sequential work."_ Your call.

**Which approach?**

