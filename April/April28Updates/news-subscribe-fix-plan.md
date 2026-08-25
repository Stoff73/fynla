# News Hub Subscribe — Email-List Signup Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the broken `/news` subscribe banner (which currently links straight to the raw `/feed/news.xml` RSS XML) with an email-capture form that runs a GDPR-compliant double opt-in flow, persists subscribers to a new `news_subscribers` table, and exposes an admin-only retrieval UI.

**Architecture:** New `news_subscribers` table + `NewsSubscriber` Eloquent model. One public `POST /api/public/news/subscribe` endpoint (rate-limited, IP-keyed, mirrors `ContactFormController`). Two web GET routes for email-link clicks (`/subscribe/news/confirm/{token}`, `/unsubscribe/news/{token}`). Two new Mailables sending from `marketing@fynla.org`, both extending `emails.layouts.master` per the `email-template` skill rules. Frontend: extract the banner into a `NewsSubscribeBanner` component with form / pending / success / error / already-registered states. Admin: paginated table view + CSV export, mirroring an existing admin list pattern.

**Tech Stack:** Laravel 10 (Pest tests, Sanctum, Eloquent, Mail, RateLimiter), Vue 3 + Vuex, MySQL 8, Tailwind (design system tokens only), Playwright for browser verification.

---

## Cross-references to `PR-237-review.md`

| PR-237 Finding | Severity | How this plan relates |
|---|---|---|
| **Finding #16 — Zero new tests for ~1,000 lines of new backend** | MEDIUM | This plan adds Pest feature tests for **all new backend code introduced here** (subscribe controller, web confirm/unsubscribe controller, admin controller, mailable construction). It does **not** retroactively cover the news/RSS/lifecycle code from PR #237 — that gap remains for a separate follow-up. |
| **Finding #8 — `v-html` trust boundary on NewsArticlePage** | MEDIUM | Not in scope for this plan (no admin newsletter composer is built; we only render subscriber lists, never user-supplied HTML). The trust-boundary comment recommendation in #8 stays as-is for a future ticket. |
| **Finding #11 — `image_url` exposed but unused** | LOW | Unrelated. No change. |
| **Finding #3 — `subscribe-max` slug parity** | LOW | Unrelated (lifecycle billing email, not news list). No change. |
| **Decision B2 — Icons on public landing surfaces approved** | — | This plan reuses the same RSS antenna SVG already in `NewsHubPage.vue:23` inside the new banner component. No new icons added. |

The new "newsletter broadcast" feature (per-published-article fan-out to confirmed subscribers) is **explicitly deferred** to a follow-up ticket per the user's instruction; only list-build infrastructure is implemented here.

---

## File structure

### Created

| Path | Responsibility |
|---|---|
| `database/migrations/2026_04_28_120000_create_news_subscribers_table.php` | Schema for the subscriber list. Idempotent (`Schema::hasTable` guard). |
| `app/Models/NewsSubscriber.php` | Eloquent model. Scopes: `confirmed()`, `pending()`, `unsubscribed()`. Static helper `generateToken()`. |
| `app/Http/Controllers/Api/Public/NewsSubscriberController.php` | Single `subscribe()` action. IP-keyed rate limit, dispatch to confirm-mail. |
| `app/Http/Controllers/NewsletterActionController.php` | Two web actions: `confirm($token)`, `unsubscribe($token)`. Lives outside `Api/` because email-link clicks have no API headers / Sanctum cookie. |
| `app/Http/Controllers/Api/Admin/NewsSubscriberController.php` | Two admin JSON actions: `index` (paginated), `export` (streamed CSV). |
| `app/Mail/Newsletter/NewsletterConfirmationMail.php` | Mailable for double opt-in confirmation. From: `marketing@fynla.org`. |
| `app/Mail/Newsletter/NewsletterWelcomeMail.php` | Mailable sent after click-confirm. From: `marketing@fynla.org`. Includes unsubscribe footer link. |
| `resources/views/emails/newsletter/confirm-subscription.blade.php` | Confirmation email blade. Extends `emails.layouts.master`. |
| `resources/views/emails/newsletter/welcome.blade.php` | Welcome email blade. Extends `emails.layouts.master`. |
| `resources/views/newsletter/confirmed.blade.php` | Standalone success page (no SPA shell — direct route from email link). |
| `resources/views/newsletter/unsubscribed.blade.php` | Standalone unsubscribe-confirmation page. |
| `resources/js/services/newsSubscriberService.js` | API wrapper for `POST /api/public/news/subscribe`. |
| `resources/js/components/News/NewsSubscribeBanner.vue` | Banner component: form / pending / success / already-registered states. |
| `resources/js/views/Admin/NewsSubscribersPage.vue` | Admin paginated table + CSV export button. |
| `tests/Feature/Api/Public/NewsSubscriberControllerTest.php` | Public POST: new / already-registered / already-confirmed / pending-resend / rate-limit / validation. |
| `tests/Feature/NewsletterActionControllerTest.php` | Web GET: confirm-valid / confirm-invalid / unsubscribe-valid / unsubscribe-invalid. |
| `tests/Feature/Api/Admin/NewsSubscriberControllerTest.php` | Admin: index pagination / filter / search / CSV export / non-admin denied. |
| `tests/Unit/Mail/Newsletter/NewsletterMailRenderTest.php` | Renders both Mailables and asserts the confirmation/unsubscribe URLs are present in the HTML. |

### Modified

| Path | Change |
|---|---|
| `resources/js/views/Public/NewsHubPage.vue:20-33` | Replace inline banner block with `<NewsSubscribeBanner />`. Add `?subscribed=1` query handler that flashes a one-time success notice. |
| `routes/api.php` | Add public `POST news/subscribe` and admin `news-subscribers` group. |
| `routes/web.php` | Add `GET /subscribe/news/confirm/{token}` and `GET /unsubscribe/news/{token}` **before** the SPA catch-all (same constraint as the existing `/feed/*.xml` routes). |
| `app/Http/Middleware/PreviewWriteInterceptor.php` | Add `api/public/news/subscribe` to `EXCLUDED_ROUTES`. |
| `config/mail.php` | Add `marketing` from-address block reading `MAIL_MARKETING_FROM_ADDRESS` / `MAIL_MARKETING_FROM_NAME`. |
| `.env.example` | Add `MAIL_MARKETING_FROM_ADDRESS=marketing@fynla.org` and `MAIL_MARKETING_FROM_NAME="Fynla"`. |
| `resources/js/router/index.js` | Add `/admin/news-subscribers` route under existing admin group with `requiresAuth` + admin guard. |
| `CSJTODO.md` | Append follow-up: "Newsletter broadcast — fan-out to confirmed subscribers when a `NewsArticle` is published." |

---

## Branch and commit strategy

This plan builds on top of `feature/phailanx/news-rss-lifecycle-emails` (the squashed PR #237 replacement branch). Commits land directly on that branch (the same way the four review fixes from `PR-237-review.md` were applied), not on a child feature branch — reason: PR #237's replacement PR has not yet been opened against `dev`, so the fix lands inside the same PR rather than chaining a second review cycle.

After this plan completes, the replacement PR `feature/phailanx/news-rss-lifecycle-emails` → `dev` is opened with PR-237-review.md and this plan both linked in the PR body for traceability.

---

## Task 1: Pre-flight — read existing patterns we must mirror

Before writing any new code, read three existing references so the new code looks native to the codebase.

**Files (read-only):**
- Read: `tests/Feature/Api/Public/InsightControllerTest.php` — Pest pattern for public-API feature tests; this is the template `PR-237-review.md` Finding #16 calls out.
- Read: `app/Http/Controllers/Api/ContactFormController.php` — IP-keyed rate-limit pattern (3 attempts / 5 min) and `Mail::raw` send pattern.
- Read: `routes/web.php` (full file) — confirm `/feed/*.xml` declared before SPA catch-all and copy that ordering.
- Read: any existing admin controller in `app/Http/Controllers/Api/Admin/` (whatever's there) — copy the auth + response shape.
- Read: any existing admin view under `resources/js/views/Admin/` — copy the layout + table styling.

- [ ] **Step 1: Confirm dev server is running**

```bash
curl -sI http://localhost:8000/news | head -1
```

Expected: `HTTP/1.1 200 OK`. If not, start with `./dev.sh` in another terminal.

- [ ] **Step 2: Read the four reference files**

Use the Read tool on each of the four files above. Note the function signatures, response shapes, and rate-limit pattern. No code is written in this task.

- [ ] **Step 3: Identify the existing admin layout and one admin list page**

```bash
ls app/Http/Controllers/Api/Admin/ resources/js/views/Admin/ 2>/dev/null
```

Pick one admin list page (any) as the styling reference for `NewsSubscribersPage.vue`. Note its filename in your scratch.

- [ ] **Step 4: Commit nothing — this is read-only prep.**

---

## Task 2: Migration — `news_subscribers` table

**Files:**
- Create: `database/migrations/2026_04_28_120000_create_news_subscribers_table.php`

- [ ] **Step 1: Write the migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('news_subscribers')) {
            return;
        }

        Schema::create('news_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('confirmation_token', 64)->unique();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('source', 32)->default('news_hub');
            $table->timestamps();

            $table->index('confirmed_at');
            $table->index('unsubscribed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_subscribers');
    }
};
```

- [ ] **Step 2: Run the migration**

```bash
php artisan migrate
```

Expected: `Migrating: 2026_04_28_120000_create_news_subscribers_table` then `Migrated: ... DONE`.

- [ ] **Step 3: Verify the table**

```bash
php artisan tinker --execute="echo \Schema::hasTable('news_subscribers') ? 'OK' : 'MISSING';"
```

Expected: `OK`.

- [ ] **Step 4: Re-run migrate to verify idempotence**

```bash
php artisan migrate
```

Expected: `Nothing to migrate.` — the `Schema::hasTable` guard means re-running is a no-op (consistent with PR-237-review.md Finding #4 fix).

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_04_28_120000_create_news_subscribers_table.php
git commit -m "feat(news): add news_subscribers table for newsletter signups"
```

---

## Task 3: Model — `NewsSubscriber`

**Files:**
- Create: `app/Models/NewsSubscriber.php`

- [ ] **Step 1: Write the model**

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class NewsSubscriber extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'confirmation_token',
        'confirmed_at',
        'unsubscribed_at',
        'ip_address',
        'source',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    public static function generateToken(): string
    {
        return Str::random(48);
    }

    public function scopeConfirmed($query)
    {
        return $query->whereNotNull('confirmed_at')->whereNull('unsubscribed_at');
    }

    public function scopePending($query)
    {
        return $query->whereNull('confirmed_at')->whereNull('unsubscribed_at');
    }

    public function scopeUnsubscribed($query)
    {
        return $query->whereNotNull('unsubscribed_at');
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null && $this->unsubscribed_at === null;
    }

    public function isPending(): bool
    {
        return $this->confirmed_at === null && $this->unsubscribed_at === null;
    }
}
```

- [ ] **Step 2: Verify class loads**

```bash
php artisan tinker --execute="echo \App\Models\NewsSubscriber::generateToken();"
```

Expected: 48-character alphanumeric string printed.

- [ ] **Step 3: Commit**

```bash
git add app/Models/NewsSubscriber.php
git commit -m "feat(news): add NewsSubscriber model with confirmed/pending/unsubscribed scopes"
```

---

## Task 4: Mail config — `marketing` from-address

**Files:**
- Modify: `config/mail.php`
- Modify: `.env.example`

- [ ] **Step 1: Add the marketing block to `config/mail.php`**

Find the existing `'from' => ['address' => env('MAIL_FROM_ADDRESS', ...)]` block. Immediately after the closing `],` of that block, add:

```php
    'marketing' => [
        'address' => env('MAIL_MARKETING_FROM_ADDRESS', 'marketing@fynla.org'),
        'name' => env('MAIL_MARKETING_FROM_NAME', 'Fynla'),
    ],
```

- [ ] **Step 2: Add the env vars to `.env.example`**

Append to the mail section of `.env.example`:

```env
MAIL_MARKETING_FROM_ADDRESS="marketing@fynla.org"
MAIL_MARKETING_FROM_NAME="Fynla"
```

- [ ] **Step 3: Verify config reads**

```bash
php artisan tinker --execute="echo config('mail.marketing.address');"
```

Expected: `marketing@fynla.org`.

- [ ] **Step 4: Commit**

```bash
git add config/mail.php .env.example
git commit -m "feat(mail): add marketing from-address config for newsletter sends"
```

---

## Task 5: Mailable — `NewsletterConfirmationMail`

**Files:**
- Create: `app/Mail/Newsletter/NewsletterConfirmationMail.php`

- [ ] **Step 1: Write the Mailable**

```php
<?php

declare(strict_types=1);

namespace App\Mail\Newsletter;

use App\Models\NewsSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewsletterConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly NewsSubscriber $subscriber)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                config('mail.marketing.address'),
                config('mail.marketing.name')
            ),
            subject: 'Confirm your subscription to Fynla news',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.newsletter.confirm-subscription',
            with: [
                'confirmUrl' => url('/subscribe/news/confirm/'.$this->subscriber->confirmation_token),
                'rssUrl' => url('/feed/news.xml'),
            ],
        );
    }
}
```

- [ ] **Step 2: Commit (blade comes in next task)**

```bash
git add app/Mail/Newsletter/NewsletterConfirmationMail.php
git commit -m "feat(mail): add NewsletterConfirmationMail mailable"
```

---

## Task 6: Blade — `confirm-subscription.blade.php`

**Files:**
- Create: `resources/views/emails/newsletter/confirm-subscription.blade.php`

This blade follows every rule in the `email-template` skill (Rules 1-7).

- [ ] **Step 1: Write the blade**

```blade
@extends('emails.layouts.master', [
    'title' => 'Confirm your subscription',
    'preheader' => 'One click to confirm your Fynla news subscription.',
])

@section('logoBar')
    @include('emails.modules.logo-bar')
@endsection

@section('header')
    @include('emails.modules.hero-header', [
        'heading' => 'Confirm your <span style="color:#f9a8c0;">subscription</span>',
        'subtitle' => 'One click and you\'re on the list.',
    ])
@endsection

@section('body')
    @include('emails.modules.body', [
        'greeting' => 'Hi there,',
        'paragraphs' => [
            'Thanks for signing up to Fynla news. To finish your subscription, please confirm your email below.',
            'We\'ll only send you Fynla announcements and product updates &mdash; nothing else.',
        ],
    ])
    @include('emails.modules.cta', [
        'buttons' => [
            ['label' => 'Confirm subscription', 'url' => $confirmUrl, 'variant' => 'raspberry'],
        ],
    ])
    @include('emails.modules.notice', [
        'variant' => 'pink',
        'message' => 'Didn\'t ask for this? Just ignore this email &mdash; we won\'t add you to the list without confirmation.',
    ])
@endsection

@section('signoff')
    @include('emails.modules.signoff')
@endsection

@section('footer')
    @include('emails.modules.footer', ['variant' => 'dark'])
@endsection
```

- [ ] **Step 2: Adjacency check (Rule 2)**

Walk top-to-bottom. Outer bg colours of each `<tr>`:
1. logo-bar → `#ffffff` (white, allowed exception per Rule 7)
2. hero-header → gradient (counts as "not white / not eggshell")
3. body → `#f5f0eb` (eggshell)
4. cta → `#f5f0eb` (eggshell) — **same as body**
5. notice (pink variant) → outer is `#fce4ec` (pink)
6. signoff → `#f5f0eb`
7. footer (dark) → `#1F2A44`

The body→cta sequence is two consecutive eggshell `<tr>`s. Per Rule 2, this is allowed only when they "render as one continuous visual band." A body block followed immediately by its CTA is exactly that case — no resolution needed. Document this in a one-line HTML comment above the cta include.

Add this comment between the body and cta includes:

```blade
    {{-- Rule 2 note: body + cta render as one continuous eggshell band (intentional). --}}
```

- [ ] **Step 3: Render check**

```bash
php artisan tinker --execute="
\$s = new \App\Models\NewsSubscriber(['email' => 'test@example.com', 'confirmation_token' => 'TESTTOKEN']);
\$mail = new \App\Mail\Newsletter\NewsletterConfirmationMail(\$s);
echo \$mail->render();
" | head -40
```

Expected: HTML output beginning with `<!DOCTYPE` and including the confirmation URL `http://localhost:8000/subscribe/news/confirm/TESTTOKEN`.

- [ ] **Step 4: Commit**

```bash
git add resources/views/emails/newsletter/confirm-subscription.blade.php
git commit -m "feat(mail): add confirm-subscription blade template"
```

---

## Task 7: Mailable — `NewsletterWelcomeMail`

**Files:**
- Create: `app/Mail/Newsletter/NewsletterWelcomeMail.php`

- [ ] **Step 1: Write the Mailable**

```php
<?php

declare(strict_types=1);

namespace App\Mail\Newsletter;

use App\Models\NewsSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewsletterWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly NewsSubscriber $subscriber)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                config('mail.marketing.address'),
                config('mail.marketing.name')
            ),
            subject: 'You\'re subscribed to Fynla news',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.newsletter.welcome',
            with: [
                'newsUrl' => url('/news'),
                'unsubscribeUrl' => url('/unsubscribe/news/'.$this->subscriber->confirmation_token),
            ],
        );
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Mail/Newsletter/NewsletterWelcomeMail.php
git commit -m "feat(mail): add NewsletterWelcomeMail mailable"
```

---

## Task 8: Blade — `welcome.blade.php` (newsletter)

**Files:**
- Create: `resources/views/emails/newsletter/welcome.blade.php`

- [ ] **Step 1: Write the blade**

```blade
@extends('emails.layouts.master', [
    'title' => 'You\'re subscribed',
    'preheader' => 'You\'re on the Fynla news list — here\'s what to expect.',
])

@section('logoBar')
    @include('emails.modules.logo-bar')
@endsection

@section('header')
    @include('emails.modules.hero-header', [
        'heading' => 'You\'re <span style="color:#f9a8c0;">in</span>',
        'subtitle' => 'Here\'s what you\'ll receive from us.',
    ])
@endsection

@section('body')
    @include('emails.modules.body', [
        'greeting' => 'Hi there,',
        'paragraphs' => [
            'Thanks for confirming. You\'re now on the Fynla news list and we\'ll keep you in the loop.',
        ],
    ])
    @include('emails.modules.bullet-list', [
        'heading' => 'What to expect',
        'items' => [
            'Product launches and major feature releases.',
            'Announcements from the Fynla team.',
            'Occasional deep-dives on UK personal finance topics.',
        ],
    ])
    {{-- Rule 2 note: bullet-list + cta render as one continuous eggshell band (intentional). --}}
    @include('emails.modules.cta', [
        'buttons' => [
            ['label' => 'Read latest news', 'url' => $newsUrl, 'variant' => 'raspberry'],
        ],
    ])
@endsection

@section('signoff')
    @include('emails.modules.signoff')
@endsection

@section('footer')
    @include('emails.modules.footer', [
        'variant' => 'dark',
        'links' => [
            ['label' => 'Unsubscribe', 'url' => $unsubscribeUrl],
        ],
    ])
@endsection
```

- [ ] **Step 2: Adjacency check (Rule 2)**

1. logo-bar → `#ffffff`
2. hero-header → gradient
3. body → `#f5f0eb`
4. bullet-list → `#f5f0eb` (eggshell, but the inner box is `#F7F6F4` — outer band continuous with body, intentional)
5. cta → `#f5f0eb` (continuous eggshell band — labelled with the comment)
6. signoff → `#f5f0eb`
7. footer (dark) → `#1F2A44`

Eggshell run from rows 3-6 is one continuous visual band (greeting → list → CTA → signoff). Acceptable per Rule 2. Footer dark provides the closing contrast.

- [ ] **Step 3: Verify the footer module accepts a `links` prop**

```bash
grep -n "\$links" resources/views/emails/modules/footer.blade.php
```

Expected: at least one match showing the dark variant renders an unsubscribe link list. If the prop name differs (e.g. `unsubscribeUrl`), update the welcome blade accordingly. **If the footer module does not support an unsubscribe link in any form, halt and ask the user how they want unsubscribe surfaced.**

- [ ] **Step 4: Render check**

```bash
php artisan tinker --execute="
\$s = new \App\Models\NewsSubscriber(['email' => 'test@example.com', 'confirmation_token' => 'WELCOMETOK']);
\$mail = new \App\Mail\Newsletter\NewsletterWelcomeMail(\$s);
echo \$mail->render();
" | grep -E "unsubscribe|news/WELCOMETOK"
```

Expected: at least one line containing the unsubscribe URL.

- [ ] **Step 5: Commit**

```bash
git add resources/views/emails/newsletter/welcome.blade.php
git commit -m "feat(mail): add newsletter welcome blade template with unsubscribe footer"
```

---

## Task 9: Mailable render unit test

**Files:**
- Create: `tests/Unit/Mail/Newsletter/NewsletterMailRenderTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Mail\Newsletter\NewsletterConfirmationMail;
use App\Mail\Newsletter\NewsletterWelcomeMail;
use App\Models\NewsSubscriber;

beforeEach(function () {
    $this->subscriber = NewsSubscriber::factory()->make([
        'email' => 'jane@example.com',
        'confirmation_token' => 'TESTTOKEN123',
    ]);
});

it('renders the confirmation mail with the confirm link', function () {
    $rendered = (new NewsletterConfirmationMail($this->subscriber))->render();

    expect($rendered)->toContain('/subscribe/news/confirm/TESTTOKEN123');
    expect($rendered)->toContain('Confirm subscription');
});

it('renders the welcome mail with the unsubscribe link', function () {
    $rendered = (new NewsletterWelcomeMail($this->subscriber))->render();

    expect($rendered)->toContain('/unsubscribe/news/TESTTOKEN123');
    expect($rendered)->toContain('Read latest news');
});

it('confirmation mail uses the marketing from-address', function () {
    $envelope = (new NewsletterConfirmationMail($this->subscriber))->envelope();

    expect($envelope->from->address)->toBe(config('mail.marketing.address'));
});
```

- [ ] **Step 2: Run the test (will fail — no factory yet)**

```bash
./vendor/bin/pest tests/Unit/Mail/Newsletter/NewsletterMailRenderTest.php
```

Expected: FAIL with `NewsSubscriber::factory()` not defined.

- [ ] **Step 3: Create the factory**

Create `database/factories/NewsSubscriberFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\NewsSubscriber;
use Illuminate\Database\Eloquent\Factories\Factory;

class NewsSubscriberFactory extends Factory
{
    protected $model = NewsSubscriber::class;

    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'confirmation_token' => NewsSubscriber::generateToken(),
            'confirmed_at' => null,
            'unsubscribed_at' => null,
            'ip_address' => fake()->ipv4(),
            'source' => 'news_hub',
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => ['confirmed_at' => now()]);
    }

    public function unsubscribed(): static
    {
        return $this->state(fn () => [
            'confirmed_at' => now()->subDays(30),
            'unsubscribed_at' => now(),
        ]);
    }
}
```

- [ ] **Step 4: Run the test (should pass)**

```bash
./vendor/bin/pest tests/Unit/Mail/Newsletter/NewsletterMailRenderTest.php
```

Expected: PASS — 3 tests.

- [ ] **Step 5: Commit**

```bash
git add database/factories/NewsSubscriberFactory.php tests/Unit/Mail/Newsletter/NewsletterMailRenderTest.php
git commit -m "test(mail): assert newsletter mailables render confirm/unsubscribe URLs"
```

---

## Task 10: Public subscribe controller — happy path (new email)

**Files:**
- Create: `app/Http/Controllers/Api/Public/NewsSubscriberController.php`
- Create: `tests/Feature/Api/Public/NewsSubscriberControllerTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Mail\Newsletter\NewsletterConfirmationMail;
use App\Models\NewsSubscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    RateLimiter::clear('news-subscribe:127.0.0.1');
});

it('creates a pending subscriber and sends confirmation email for a new address', function () {
    $response = $this->postJson('/api/public/news/subscribe', [
        'email' => 'new@example.com',
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'status' => 'pending_confirmation',
        ]);

    $subscriber = NewsSubscriber::where('email', 'new@example.com')->first();
    expect($subscriber)->not->toBeNull();
    expect($subscriber->confirmed_at)->toBeNull();
    expect($subscriber->confirmation_token)->not->toBeEmpty();

    Mail::assertSent(NewsletterConfirmationMail::class, fn ($mail) => $mail->subscriber->email === 'new@example.com');
});
```

- [ ] **Step 2: Run the test (fails — no route)**

```bash
./vendor/bin/pest tests/Feature/Api/Public/NewsSubscriberControllerTest.php
```

Expected: FAIL with 404 from `/api/public/news/subscribe`.

- [ ] **Step 3: Create the controller**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Mail\Newsletter\NewsletterConfirmationMail;
use App\Models\NewsSubscriber;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class NewsSubscriberController extends Controller
{
    private const RATE_LIMIT_KEY_PREFIX = 'news-subscribe:';
    private const RATE_LIMIT_MAX_ATTEMPTS = 3;
    private const RATE_LIMIT_DECAY_SECONDS = 300;

    public function subscribe(Request $request): JsonResponse
    {
        $key = self::RATE_LIMIT_KEY_PREFIX.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::RATE_LIMIT_MAX_ATTEMPTS)) {
            return response()->json([
                'success' => false,
                'status' => 'rate_limited',
                'message' => 'Too many attempts. Please try again in a few minutes.',
            ], 429);
        }

        $validated = $request->validate([
            'email' => 'required|email|max:255',
        ]);

        RateLimiter::hit($key, self::RATE_LIMIT_DECAY_SECONDS);

        $email = strtolower(trim($validated['email']));

        if (User::where('email', $email)->exists()) {
            return response()->json([
                'success' => true,
                'status' => 'already_registered',
                'message' => "You're already registered with Fynla — sign in to manage your news preferences.",
            ]);
        }

        $subscriber = NewsSubscriber::firstOrNew(['email' => $email]);

        if ($subscriber->exists && $subscriber->isConfirmed()) {
            return response()->json([
                'success' => true,
                'status' => 'already_confirmed',
                'message' => "You're already subscribed — thanks!",
            ]);
        }

        $subscriber->fill([
            'confirmation_token' => NewsSubscriber::generateToken(),
            'confirmed_at' => null,
            'unsubscribed_at' => null,
            'ip_address' => $request->ip(),
            'source' => 'news_hub',
        ])->save();

        Mail::to($subscriber->email)->send(new NewsletterConfirmationMail($subscriber));

        $message = $subscriber->wasRecentlyCreated
            ? 'Check your inbox to confirm your subscription.'
            : 'Confirmation email re-sent — check your inbox.';

        return response()->json([
            'success' => true,
            'status' => 'pending_confirmation',
            'message' => $message,
        ]);
    }
}
```

- [ ] **Step 4: Add the route**

Append to `routes/api.php` near the existing public news block:

```php
Route::post('/public/news/subscribe', [\App\Http\Controllers\Api\Public\NewsSubscriberController::class, 'subscribe'])
    ->middleware('throttle:5,1');
```

(The `throttle:5,1` is a hard cap belt-and-braces over the IP-keyed `RateLimiter` inside the controller.)

- [ ] **Step 5: Add to `PreviewWriteInterceptor::EXCLUDED_ROUTES`**

Open `app/Http/Middleware/PreviewWriteInterceptor.php`. Find the `EXCLUDED_ROUTES` array (or `EXCLUDED_PATHS` — match the existing constant name). Add:

```php
'api/public/news/subscribe',
```

- [ ] **Step 6: Run the test (should pass)**

```bash
./vendor/bin/pest tests/Feature/Api/Public/NewsSubscriberControllerTest.php
```

Expected: PASS — 1 test.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Api/Public/NewsSubscriberController.php routes/api.php app/Http/Middleware/PreviewWriteInterceptor.php tests/Feature/Api/Public/NewsSubscriberControllerTest.php
git commit -m "feat(news): add public newsletter subscribe endpoint with confirmation email"
```

---

## Task 11: Public subscribe controller — already-registered Fynla user

**Files:**
- Modify: `tests/Feature/Api/Public/NewsSubscriberControllerTest.php`

- [ ] **Step 1: Append the test**

```php
it('returns already_registered when email belongs to a Fynla user', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    $response = $this->postJson('/api/public/news/subscribe', [
        'email' => 'existing@example.com',
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'status' => 'already_registered',
        ]);

    expect(NewsSubscriber::where('email', 'existing@example.com')->exists())->toBeFalse();
    Mail::assertNotSent(NewsletterConfirmationMail::class);
});
```

- [ ] **Step 2: Run the test (should pass — controller already handles this)**

```bash
./vendor/bin/pest tests/Feature/Api/Public/NewsSubscriberControllerTest.php --filter="already_registered"
```

Expected: PASS.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Api/Public/NewsSubscriberControllerTest.php
git commit -m "test(news): assert registered Fynla users are not added to subscriber list"
```

---

## Task 12: Public subscribe controller — already-confirmed and pending-resend cases

**Files:**
- Modify: `tests/Feature/Api/Public/NewsSubscriberControllerTest.php`

- [ ] **Step 1: Append the tests**

```php
it('returns already_confirmed when subscriber exists and is confirmed', function () {
    NewsSubscriber::factory()->confirmed()->create(['email' => 'confirmed@example.com']);

    $response = $this->postJson('/api/public/news/subscribe', [
        'email' => 'confirmed@example.com',
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'status' => 'already_confirmed',
        ]);

    Mail::assertNotSent(NewsletterConfirmationMail::class);
});

it('resends the confirmation email when a pending subscriber re-submits', function () {
    $original = NewsSubscriber::factory()->create([
        'email' => 'pending@example.com',
        'confirmation_token' => 'OLDTOKEN',
    ]);

    $response = $this->postJson('/api/public/news/subscribe', [
        'email' => 'pending@example.com',
    ]);

    $response->assertOk()
        ->assertJson(['status' => 'pending_confirmation']);

    $reloaded = $original->fresh();
    expect($reloaded->confirmation_token)->not->toBe('OLDTOKEN');
    expect($reloaded->confirmed_at)->toBeNull();

    Mail::assertSent(NewsletterConfirmationMail::class, 1);
});
```

- [ ] **Step 2: Run the suite**

```bash
./vendor/bin/pest tests/Feature/Api/Public/NewsSubscriberControllerTest.php
```

Expected: PASS — 4 tests.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Api/Public/NewsSubscriberControllerTest.php
git commit -m "test(news): cover already-confirmed and pending-resend subscribe paths"
```

---

## Task 13: Public subscribe controller — rate limiting and validation

**Files:**
- Modify: `tests/Feature/Api/Public/NewsSubscriberControllerTest.php`

- [ ] **Step 1: Append the tests**

```php
it('rejects invalid email addresses', function () {
    $response = $this->postJson('/api/public/news/subscribe', [
        'email' => 'not-an-email',
    ]);

    $response->assertStatus(422);
    Mail::assertNotSent(NewsletterConfirmationMail::class);
});

it('rate-limits after 3 successful submits from the same IP', function () {
    for ($i = 1; $i <= 3; $i++) {
        $this->postJson('/api/public/news/subscribe', [
            'email' => "user{$i}@example.com",
        ])->assertOk();
    }

    $response = $this->postJson('/api/public/news/subscribe', [
        'email' => 'user4@example.com',
    ]);

    $response->assertStatus(429)
        ->assertJson(['status' => 'rate_limited']);

    expect(NewsSubscriber::where('email', 'user4@example.com')->exists())->toBeFalse();
});
```

- [ ] **Step 2: Run the suite**

```bash
./vendor/bin/pest tests/Feature/Api/Public/NewsSubscriberControllerTest.php
```

Expected: PASS — 6 tests.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Api/Public/NewsSubscriberControllerTest.php
git commit -m "test(news): cover validation and rate-limit on subscribe endpoint"
```

---

## Task 14: Web confirm/unsubscribe controller — confirm action

**Files:**
- Create: `app/Http/Controllers/NewsletterActionController.php`
- Create: `tests/Feature/NewsletterActionControllerTest.php`
- Create: `resources/views/newsletter/confirmed.blade.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Mail\Newsletter\NewsletterWelcomeMail;
use App\Models\NewsSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(fn () => Mail::fake());

it('confirms a pending subscriber via valid token and sends welcome mail', function () {
    $subscriber = NewsSubscriber::factory()->create(['confirmation_token' => 'VALIDTOKEN']);

    $response = $this->get('/subscribe/news/confirm/VALIDTOKEN');

    $response->assertOk();
    $response->assertSee("You're subscribed");

    expect($subscriber->fresh()->confirmed_at)->not->toBeNull();
    Mail::assertSent(NewsletterWelcomeMail::class);
});

it('returns 404 for an invalid confirm token', function () {
    $this->get('/subscribe/news/confirm/INVALIDTOKEN')->assertNotFound();
});

it('is idempotent — second confirm click does not re-send welcome', function () {
    $subscriber = NewsSubscriber::factory()->confirmed()->create(['confirmation_token' => 'IDEMTOKEN']);

    $this->get('/subscribe/news/confirm/IDEMTOKEN')->assertOk();

    Mail::assertNotSent(NewsletterWelcomeMail::class);
});
```

- [ ] **Step 2: Run the test (fails)**

```bash
./vendor/bin/pest tests/Feature/NewsletterActionControllerTest.php
```

Expected: FAIL with route not found.

- [ ] **Step 3: Create the controller**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Mail\Newsletter\NewsletterWelcomeMail;
use App\Models\NewsSubscriber;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Mail;

class NewsletterActionController extends Controller
{
    public function confirm(string $token): View
    {
        $subscriber = NewsSubscriber::where('confirmation_token', $token)->firstOrFail();

        $alreadyConfirmed = $subscriber->isConfirmed();

        if (! $alreadyConfirmed) {
            $subscriber->update([
                'confirmed_at' => now(),
                'unsubscribed_at' => null,
            ]);

            Mail::to($subscriber->email)->send(new NewsletterWelcomeMail($subscriber));
        }

        return view('newsletter.confirmed', [
            'email' => $subscriber->email,
            'alreadyConfirmed' => $alreadyConfirmed,
        ]);
    }

    public function unsubscribe(string $token): View
    {
        $subscriber = NewsSubscriber::where('confirmation_token', $token)->firstOrFail();

        if ($subscriber->unsubscribed_at === null) {
            $subscriber->update(['unsubscribed_at' => now()]);
        }

        return view('newsletter.unsubscribed', [
            'email' => $subscriber->email,
        ]);
    }
}
```

- [ ] **Step 4: Create the confirmed view**

```blade
{{-- resources/views/newsletter/confirmed.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>You're subscribed | Fynla</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <style>
        body { margin: 0; font-family: 'Segoe UI', Inter, sans-serif; background: #f5f0eb; color: #1F2A44; }
        .wrap { max-width: 540px; margin: 64px auto; padding: 48px 32px; background: #ffffff; border-radius: 16px; text-align: center; }
        h1 { font-size: 28px; font-weight: 900; margin: 0 0 16px; color: #1F2A44; }
        p { font-size: 15px; line-height: 1.6; color: #555; margin: 0 0 16px; }
        a { display: inline-block; margin-top: 24px; padding: 12px 24px; background: #e74c6f; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 700; }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>You're subscribed</h1>
        @if($alreadyConfirmed)
            <p>Your subscription was already confirmed. We'll keep <strong>{{ $email }}</strong> in the loop.</p>
        @else
            <p>Thanks for confirming. We've added <strong>{{ $email }}</strong> to the Fynla news list.</p>
        @endif
        <a href="/news">Read latest news</a>
    </div>
</body>
</html>
```

- [ ] **Step 5: Add the route to `routes/web.php`**

Insert immediately after the existing `Route::get('/feed/insights.xml', ...)` block (and **before** the SPA catch-all):

```php
// Newsletter confirm/unsubscribe — public, must come before SPA catch-all so email-link
// clicks don't hit the Vue shell. Tokens are 48-char random strings (NewsSubscriber::generateToken).
Route::get('/subscribe/news/confirm/{token}', [\App\Http\Controllers\NewsletterActionController::class, 'confirm'])
    ->name('newsletter.confirm')
    ->where('token', '[A-Za-z0-9]{48}');
Route::get('/unsubscribe/news/{token}', [\App\Http\Controllers\NewsletterActionController::class, 'unsubscribe'])
    ->name('newsletter.unsubscribe')
    ->where('token', '[A-Za-z0-9]{48}');
```

- [ ] **Step 6: Adjust the test to use a 48-char token**

The route constraint requires a 48-char alphanumeric token. Update the three test cases to use 48-char tokens — replace `'VALIDTOKEN'`, `'INVALIDTOKEN'`, `'IDEMTOKEN'` with `str_repeat('A', 48)`, `str_repeat('B', 48)`, `str_repeat('C', 48)` respectively.

- [ ] **Step 7: Run the tests**

```bash
./vendor/bin/pest tests/Feature/NewsletterActionControllerTest.php
```

Expected: PASS — 3 tests.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/NewsletterActionController.php tests/Feature/NewsletterActionControllerTest.php resources/views/newsletter/confirmed.blade.php routes/web.php
git commit -m "feat(news): add newsletter confirm action with welcome email send"
```

---

## Task 15: Web unsubscribe action

**Files:**
- Modify: `tests/Feature/NewsletterActionControllerTest.php`
- Create: `resources/views/newsletter/unsubscribed.blade.php`

- [ ] **Step 1: Append the test**

```php
it('unsubscribes a confirmed subscriber via valid token', function () {
    $token = str_repeat('D', 48);
    $subscriber = NewsSubscriber::factory()->confirmed()->create(['confirmation_token' => $token]);

    $response = $this->get("/unsubscribe/news/{$token}");

    $response->assertOk();
    $response->assertSee("You've unsubscribed");

    expect($subscriber->fresh()->unsubscribed_at)->not->toBeNull();
});

it('returns 404 for an invalid unsubscribe token', function () {
    $this->get('/unsubscribe/news/'.str_repeat('Z', 48))->assertNotFound();
});

it('is idempotent — second unsubscribe click does not change unsubscribed_at', function () {
    $token = str_repeat('E', 48);
    $subscriber = NewsSubscriber::factory()->unsubscribed()->create(['confirmation_token' => $token]);
    $first = $subscriber->unsubscribed_at;

    $this->get("/unsubscribe/news/{$token}")->assertOk();

    expect($subscriber->fresh()->unsubscribed_at->timestamp)->toBe($first->timestamp);
});
```

- [ ] **Step 2: Create the unsubscribed view**

```blade
{{-- resources/views/newsletter/unsubscribed.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Unsubscribed | Fynla</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <style>
        body { margin: 0; font-family: 'Segoe UI', Inter, sans-serif; background: #f5f0eb; color: #1F2A44; }
        .wrap { max-width: 540px; margin: 64px auto; padding: 48px 32px; background: #ffffff; border-radius: 16px; text-align: center; }
        h1 { font-size: 28px; font-weight: 900; margin: 0 0 16px; color: #1F2A44; }
        p { font-size: 15px; line-height: 1.6; color: #555; margin: 0 0 16px; }
        a { display: inline-block; margin-top: 24px; padding: 12px 24px; background: #e74c6f; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 700; }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>You've unsubscribed</h1>
        <p>We've removed <strong>{{ $email }}</strong> from the Fynla news list. You won't receive any more announcements from us.</p>
        <p>Changed your mind? Just sign up again from the news page.</p>
        <a href="/news">Back to Fynla</a>
    </div>
</body>
</html>
```

- [ ] **Step 3: Run the suite**

```bash
./vendor/bin/pest tests/Feature/NewsletterActionControllerTest.php
```

Expected: PASS — 6 tests.

- [ ] **Step 4: Commit**

```bash
git add resources/views/newsletter/unsubscribed.blade.php tests/Feature/NewsletterActionControllerTest.php
git commit -m "feat(news): add newsletter unsubscribe action and confirmation page"
```

---

## Task 16: Frontend — `newsSubscriberService`

**Files:**
- Create: `resources/js/services/newsSubscriberService.js`

- [ ] **Step 1: Write the service**

```javascript
import api from './api';

const newsSubscriberService = {
  async subscribe(email) {
    const { data } = await api.post('/public/news/subscribe', { email });
    return data;
  },
};

export default newsSubscriberService;
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/services/newsSubscriberService.js
git commit -m "feat(news): add newsSubscriberService API wrapper"
```

---

## Task 17: Frontend — `NewsSubscribeBanner` component

**Files:**
- Create: `resources/js/components/News/NewsSubscribeBanner.vue`

This component owns all states: idle, submitting, success-pending-confirm, success-already-registered, success-already-confirmed, error.

- [ ] **Step 1: Write the component**

```vue
<template>
  <div class="bg-light-pink-100 rounded-xl px-5 py-4 mb-10">
    <div class="flex items-center gap-3">
      <svg class="w-6 h-6 text-horizon-500 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M5 5a14 14 0 0114 14M5 11a8 8 0 018 8M6.5 18.5a1 1 0 11-2 0 1 1 0 012 0z" />
      </svg>
      <div class="flex-1">
        <p class="text-sm font-semibold text-horizon-500 leading-tight">Subscribe to Fynla news</p>
        <p class="text-xs text-neutral-500 mt-0.5">Get every announcement straight to your inbox.</p>
      </div>
    </div>

    <form v-if="status === 'idle' || status === 'error'" @submit.prevent="handleSubmit" class="mt-3 flex flex-col sm:flex-row gap-2">
      <label for="news-subscribe-email" class="sr-only">Email address</label>
      <input
        id="news-subscribe-email"
        v-model="email"
        type="email"
        required
        autocomplete="email"
        placeholder="your@email.com"
        class="flex-1 px-3 py-2 text-sm rounded-lg border border-light-gray bg-white text-horizon-500 placeholder-neutral-400 focus:outline-none focus:ring-2 focus:ring-raspberry-500"
        :disabled="submitting"
      />
      <button
        type="submit"
        class="px-4 py-2 text-sm font-semibold rounded-lg bg-raspberry-500 text-white hover:bg-raspberry-600 transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
        :disabled="submitting"
      >
        {{ submitting ? 'Sending…' : 'Subscribe' }}
      </button>
    </form>

    <p v-if="status === 'error'" class="mt-2 text-xs text-raspberry-500" role="alert">{{ message }}</p>

    <div v-if="status === 'pending_confirmation'" class="mt-3 text-sm text-horizon-500" role="status">
      <strong>Check your inbox.</strong> {{ message }}
    </div>

    <div v-if="status === 'already_registered'" class="mt-3 text-sm text-horizon-500" role="status">
      You're already registered with Fynla —
      <router-link to="/login" class="underline font-semibold hover:text-raspberry-500 transition-colors">sign in</router-link>
      to manage your news preferences.
    </div>

    <div v-if="status === 'already_confirmed'" class="mt-3 text-sm text-horizon-500" role="status">
      <strong>You're already subscribed</strong> — thanks!
    </div>

    <p class="mt-3 text-xs text-neutral-400">
      Prefer RSS?
      <a href="/feed/news.xml" target="_blank" rel="noopener noreferrer" class="underline hover:text-raspberry-500 transition-colors">Subscribe via feed</a>.
    </p>
  </div>
</template>

<script>
import newsSubscriberService from '@/services/newsSubscriberService';

export default {
  name: 'NewsSubscribeBanner',

  data() {
    return {
      email: '',
      status: 'idle',
      message: '',
      submitting: false,
    };
  },

  methods: {
    async handleSubmit() {
      this.submitting = true;
      this.status = 'idle';
      this.message = '';

      try {
        const result = await newsSubscriberService.subscribe(this.email);
        this.status = result.status;
        this.message = result.message || '';
        if (result.status === 'pending_confirmation') {
          this.email = '';
        }
      } catch (err) {
        this.status = 'error';
        if (err.response?.status === 429) {
          this.message = 'Too many attempts. Please try again in a few minutes.';
        } else if (err.response?.status === 422) {
          this.message = 'Please enter a valid email address.';
        } else {
          this.message = 'Could not subscribe right now. Please try again.';
        }
      } finally {
        this.submitting = false;
      }
    },
  },
};
</script>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/components/News/NewsSubscribeBanner.vue
git commit -m "feat(news): add NewsSubscribeBanner Vue component with all signup states"
```

---

## Task 18: Frontend — wire `NewsSubscribeBanner` into `NewsHubPage`

**Files:**
- Modify: `resources/js/views/Public/NewsHubPage.vue`

- [ ] **Step 1: Replace the banner block**

In `NewsHubPage.vue`, replace the existing `<a href="/feed/news.xml" ...>` block on lines 20-33 with:

```vue
        <NewsSubscribeBanner />
```

- [ ] **Step 2: Register the component**

Add the import and component registration in the `<script>` block:

```javascript
import NewsSubscribeBanner from '@/components/News/NewsSubscribeBanner.vue';
```

```javascript
  components: {
    PublicLayout,
    NewsSubscribeBanner,
  },
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/views/Public/NewsHubPage.vue
git commit -m "feat(news): replace RSS-XML link with NewsSubscribeBanner on news hub"
```

---

## Task 19: Frontend browser tests — happy path

These tests run in Playwright against `http://localhost:8000`. They must actually click, type, submit, and assert.

- [ ] **Step 1: Reset the database to a clean seed state**

```bash
php artisan db:seed --force
```

- [ ] **Step 2: Navigate and snapshot**

Navigate to `http://localhost:8000/news`. Take a snapshot. Confirm the new banner has an email input and a `Subscribe` button.

- [ ] **Step 3: Submit a fresh email**

Type `playwright-test-1@example.com` into the email input. Click `Subscribe`. Wait for the inline message to appear.

- [ ] **Step 4: Assert pending state**

Assert the visible text contains `Check your inbox`.

- [ ] **Step 5: Verify DB row exists**

```bash
php artisan tinker --execute="echo \App\Models\NewsSubscriber::where('email', 'playwright-test-1@example.com')->first()?->confirmation_token ?? 'MISSING';"
```

Expected: 48-char token printed (not `MISSING`). Save the token for the next step.

- [ ] **Step 6: Click the confirmation link in the rendered email**

```bash
TOKEN=$(php artisan tinker --execute="echo \App\Models\NewsSubscriber::where('email', 'playwright-test-1@example.com')->first()->confirmation_token;")
echo "$TOKEN"
```

Navigate to `http://localhost:8000/subscribe/news/confirm/$TOKEN` in Playwright.

- [ ] **Step 7: Assert confirmation page**

The page should show heading "You're subscribed" and the email address. `confirmed_at` should now be non-null:

```bash
php artisan tinker --execute="echo \App\Models\NewsSubscriber::where('email', 'playwright-test-1@example.com')->first()->confirmed_at;"
```

Expected: a timestamp.

- [ ] **Step 8: Click `Read latest news` button**

Click it; assert the URL becomes `/news` and the page loads.

- [ ] **Step 9: No commit — these are runtime tests, no code changed.**

---

## Task 20: Frontend browser tests — already-registered, pending-resend, rate-limit

- [ ] **Step 1: Already-registered path**

Navigate to `/news`. Submit `john@example.com` (a seeded test user). Assert the inline message contains `already registered with Fynla` and contains a `sign in` link. Confirm no row is created:

```bash
php artisan tinker --execute="echo \App\Models\NewsSubscriber::where('email', 'john@example.com')->exists() ? 'BAD' : 'OK';"
```

Expected: `OK`.

- [ ] **Step 2: Pending-resend path**

Submit `playwright-test-1@example.com` again (it's already in pending state from Task 19). Wait. Assert the message contains `re-sent` (or just `Check your inbox` — match what the controller returns). Verify the token has rotated:

```bash
php artisan tinker --execute="echo \App\Models\NewsSubscriber::where('email', 'playwright-test-1@example.com')->first()->confirmation_token;"
```

Expected: a different 48-char token from the one captured earlier.

- [ ] **Step 3: Rate-limit path**

Clear the rate limit first to set a clean baseline:

```bash
php artisan tinker --execute="\Illuminate\Support\Facades\RateLimiter::clear('news-subscribe:127.0.0.1');"
```

Submit four different emails in quick succession via the browser banner (e.g. `rl1@example.com`, `rl2@example.com`, `rl3@example.com`, `rl4@example.com`). The fourth submit should show an error message containing `Too many attempts`.

- [ ] **Step 4: Unsubscribe path**

For the user confirmed in Task 19, fetch their token, then navigate to `http://localhost:8000/unsubscribe/news/$TOKEN`. Assert the page heading reads "You've unsubscribed" and the email is shown. Verify:

```bash
php artisan tinker --execute="echo \App\Models\NewsSubscriber::where('email', 'playwright-test-1@example.com')->first()->unsubscribed_at;"
```

Expected: a timestamp.

- [ ] **Step 5: Clean up test data**

```bash
php artisan tinker --execute="\App\Models\NewsSubscriber::whereIn('email', ['playwright-test-1@example.com','rl1@example.com','rl2@example.com','rl3@example.com','rl4@example.com'])->delete();"
```

- [ ] **Step 6: No commit — runtime verification only.**

---

## Task 21: Admin — list controller (paginated index)

**Files:**
- Create: `app/Http/Controllers/Api/Admin/NewsSubscriberController.php`
- Create: `tests/Feature/Api/Admin/NewsSubscriberControllerTest.php`

Reuse the auth/admin middleware approach from whichever admin controller was identified in Task 1, Step 3.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\NewsSubscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('returns a paginated list of subscribers for admins', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Sanctum::actingAs($admin);

    NewsSubscriber::factory()->count(3)->confirmed()->create();
    NewsSubscriber::factory()->count(2)->create();
    NewsSubscriber::factory()->unsubscribed()->create();

    $response = $this->getJson('/api/admin/news-subscribers');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'email', 'status', 'source', 'created_at', 'confirmed_at', 'unsubscribed_at']],
            'meta' => ['current_page', 'last_page', 'total'],
        ]);

    expect($response->json('meta.total'))->toBe(6);
});

it('rejects non-admin users', function () {
    $user = User::factory()->create(['is_admin' => false]);
    Sanctum::actingAs($user);

    $this->getJson('/api/admin/news-subscribers')->assertForbidden();
});

it('rejects unauthenticated requests', function () {
    $this->getJson('/api/admin/news-subscribers')->assertUnauthorized();
});

it('filters by status', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Sanctum::actingAs($admin);

    NewsSubscriber::factory()->count(2)->confirmed()->create();
    NewsSubscriber::factory()->count(3)->create();

    $response = $this->getJson('/api/admin/news-subscribers?status=confirmed');

    $response->assertOk();
    expect($response->json('meta.total'))->toBe(2);
});

it('searches by email', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Sanctum::actingAs($admin);

    NewsSubscriber::factory()->create(['email' => 'hello@unique.test']);
    NewsSubscriber::factory()->count(3)->create();

    $response = $this->getJson('/api/admin/news-subscribers?search=unique');

    $response->assertOk();
    expect($response->json('meta.total'))->toBe(1);
});
```

(The `is_admin` boolean is a placeholder. **Verify the actual admin determination mechanism** — it could be `role`, `is_admin`, a relation, etc. — by reading the controller from Task 1 Step 3, and adjust both the test fixture and the controller's gate to match.)

- [ ] **Step 2: Run the test (fails)**

```bash
./vendor/bin/pest tests/Feature/Api/Admin/NewsSubscriberControllerTest.php
```

Expected: FAIL with route not found.

- [ ] **Step 3: Create the controller**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsSubscriberController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|in:all,confirmed,pending,unsubscribed',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $status = $validated['status'] ?? 'all';
        $perPage = $validated['per_page'] ?? 50;

        $query = NewsSubscriber::query();

        match ($status) {
            'confirmed' => $query->confirmed(),
            'pending' => $query->pending(),
            'unsubscribed' => $query->unsubscribed(),
            default => null,
        };

        if (! empty($validated['search'])) {
            $query->where('email', 'like', '%'.$validated['search'].'%');
        }

        $paginated = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'data' => $paginated->getCollection()->map(fn ($s) => [
                'id' => $s->id,
                'email' => $s->email,
                'status' => $this->statusOf($s),
                'source' => $s->source,
                'ip_address' => $s->ip_address,
                'created_at' => $s->created_at?->toIso8601String(),
                'confirmed_at' => $s->confirmed_at?->toIso8601String(),
                'unsubscribed_at' => $s->unsubscribed_at?->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    private function statusOf(NewsSubscriber $s): string
    {
        if ($s->unsubscribed_at !== null) {
            return 'unsubscribed';
        }
        return $s->confirmed_at !== null ? 'confirmed' : 'pending';
    }
}
```

- [ ] **Step 4: Add the admin routes**

Append to `routes/api.php` inside whatever admin route group already exists (or wrap as below if none):

```php
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('news-subscribers', [\App\Http\Controllers\Api\Admin\NewsSubscriberController::class, 'index']);
});
```

(Replace `'admin'` with whatever the actual admin middleware alias is in `app/Http/Kernel.php` — usually `admin` or `role:admin`. Verify this from Task 1 Step 3.)

- [ ] **Step 5: Run the tests**

```bash
./vendor/bin/pest tests/Feature/Api/Admin/NewsSubscriberControllerTest.php
```

Expected: PASS — 4 tests.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/Admin/NewsSubscriberController.php tests/Feature/Api/Admin/NewsSubscriberControllerTest.php routes/api.php
git commit -m "feat(news): add admin endpoint for listing news subscribers"
```

---

## Task 22: Admin — CSV export

**Files:**
- Modify: `app/Http/Controllers/Api/Admin/NewsSubscriberController.php`
- Modify: `tests/Feature/Api/Admin/NewsSubscriberControllerTest.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Append the test**

```php
it('exports all subscribers as CSV for admins', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Sanctum::actingAs($admin);

    NewsSubscriber::factory()->confirmed()->create(['email' => 'csv1@example.com']);
    NewsSubscriber::factory()->create(['email' => 'csv2@example.com']);

    $response = $this->get('/api/admin/news-subscribers/export');

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $body = $response->streamedContent();
    expect($body)->toContain('email,status,source');
    expect($body)->toContain('csv1@example.com');
    expect($body)->toContain('csv2@example.com');
});
```

- [ ] **Step 2: Add the `export` method to the controller**

```php
public function export(): \Symfony\Component\HttpFoundation\StreamedResponse
{
    $filename = 'news-subscribers-'.now()->format('Y-m-d-His').'.csv';

    return response()->stream(function () {
        $handle = fopen('php://output', 'w');
        fputcsv($handle, ['email', 'status', 'source', 'ip_address', 'created_at', 'confirmed_at', 'unsubscribed_at']);

        NewsSubscriber::query()->orderByDesc('created_at')->chunk(500, function ($chunk) use ($handle) {
            foreach ($chunk as $s) {
                fputcsv($handle, [
                    $s->email,
                    $this->statusOf($s),
                    $s->source,
                    $s->ip_address,
                    $s->created_at?->toIso8601String(),
                    $s->confirmed_at?->toIso8601String(),
                    $s->unsubscribed_at?->toIso8601String(),
                ]);
            }
        });

        fclose($handle);
    }, 200, [
        'Content-Type' => 'text/csv; charset=UTF-8',
        'Content-Disposition' => 'attachment; filename="'.$filename.'"',
    ]);
}
```

- [ ] **Step 3: Add the route**

Inside the same admin group:

```php
Route::get('news-subscribers/export', [\App\Http\Controllers\Api\Admin\NewsSubscriberController::class, 'export']);
```

(Place this **before** the `news-subscribers` collection route if the framework needs it — Laravel route resolution handles this fine because `export` is not a route-model-binding.)

- [ ] **Step 4: Run the tests**

```bash
./vendor/bin/pest tests/Feature/Api/Admin/NewsSubscriberControllerTest.php
```

Expected: PASS — 5 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/Admin/NewsSubscriberController.php tests/Feature/Api/Admin/NewsSubscriberControllerTest.php routes/api.php
git commit -m "feat(news): add CSV export endpoint for news subscribers"
```

---

## Task 23: Admin — Vue page

**Files:**
- Create: `resources/js/views/Admin/NewsSubscribersPage.vue`
- Modify: `resources/js/router/index.js`

This page mirrors the existing admin list page identified in Task 1 Step 3. The skeleton below uses generic patterns; **adapt to match whatever admin layout/utility classes the existing admin views use** (e.g. shared `<AdminTable>` component, shared `useAdminApi()` composable).

- [ ] **Step 1: Write the page**

```vue
<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-2xl font-bold text-horizon-500">News subscribers</h1>
          <p class="text-sm text-neutral-500 mt-1">{{ meta?.total ?? 0 }} total subscribers</p>
        </div>
        <button
          type="button"
          class="px-4 py-2 text-sm font-semibold rounded-lg bg-raspberry-500 text-white hover:bg-raspberry-600 transition-colors"
          @click="downloadCsv"
        >
          Export CSV
        </button>
      </div>

      <div class="flex gap-2 mb-4">
        <button
          v-for="filter in statusFilters"
          :key="filter.value"
          type="button"
          class="px-3 py-1 text-sm rounded-full transition-colors"
          :class="status === filter.value ? 'bg-horizon-500 text-white' : 'bg-light-gray text-horizon-500 hover:bg-savannah-100'"
          @click="setStatus(filter.value)"
        >
          {{ filter.label }}
        </button>
        <input
          v-model="search"
          type="search"
          placeholder="Search by email…"
          class="ml-auto px-3 py-1 text-sm rounded-lg border border-light-gray bg-white text-horizon-500"
          @input="debouncedFetch"
        />
      </div>

      <div v-if="loading" class="text-center py-12 text-neutral-500">Loading…</div>
      <div v-else-if="error" class="text-center py-12 text-raspberry-500">{{ error }}</div>
      <div v-else-if="!subscribers.length" class="text-center py-12 text-neutral-500">No subscribers match your filters.</div>

      <table v-else class="w-full text-sm">
        <thead class="text-left border-b border-light-gray">
          <tr>
            <th class="py-2 px-3 font-semibold text-horizon-500">Email</th>
            <th class="py-2 px-3 font-semibold text-horizon-500">Status</th>
            <th class="py-2 px-3 font-semibold text-horizon-500">Source</th>
            <th class="py-2 px-3 font-semibold text-horizon-500">Signed up</th>
            <th class="py-2 px-3 font-semibold text-horizon-500">Confirmed</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="s in subscribers" :key="s.id" class="border-b border-light-gray">
            <td class="py-2 px-3 text-horizon-500">{{ s.email }}</td>
            <td class="py-2 px-3">
              <span :class="statusClass(s.status)">{{ s.status }}</span>
            </td>
            <td class="py-2 px-3 text-neutral-500">{{ s.source }}</td>
            <td class="py-2 px-3 text-neutral-500">{{ formatDate(s.created_at) }}</td>
            <td class="py-2 px-3 text-neutral-500">{{ formatDate(s.confirmed_at) }}</td>
          </tr>
        </tbody>
      </table>

      <div v-if="meta && meta.last_page > 1" class="flex items-center justify-center gap-3 mt-6">
        <button type="button" class="text-sm text-horizon-500 disabled:opacity-40" :disabled="page <= 1" @click="changePage(page - 1)">Previous</button>
        <span class="text-sm text-neutral-500">Page {{ page }} of {{ meta.last_page }}</span>
        <button type="button" class="text-sm text-horizon-500 disabled:opacity-40" :disabled="page >= meta.last_page" @click="changePage(page + 1)">Next</button>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import api from '@/services/api';

export default {
  name: 'NewsSubscribersPage',
  components: { AppLayout },

  data() {
    return {
      subscribers: [],
      meta: null,
      page: 1,
      status: 'all',
      search: '',
      loading: false,
      error: null,
      debounceTimer: null,
      statusFilters: [
        { value: 'all', label: 'All' },
        { value: 'confirmed', label: 'Confirmed' },
        { value: 'pending', label: 'Pending' },
        { value: 'unsubscribed', label: 'Unsubscribed' },
      ],
    };
  },

  mounted() {
    document.title = 'News subscribers | Fynla admin';
    this.fetchSubscribers();
  },

  methods: {
    async fetchSubscribers() {
      this.loading = true;
      this.error = null;
      try {
        const { data } = await api.get('/admin/news-subscribers', {
          params: { page: this.page, status: this.status, search: this.search || undefined },
        });
        this.subscribers = data.data;
        this.meta = data.meta;
      } catch (err) {
        this.error = 'Could not load subscribers.';
      } finally {
        this.loading = false;
      }
    },
    setStatus(value) {
      this.status = value;
      this.page = 1;
      this.fetchSubscribers();
    },
    debouncedFetch() {
      clearTimeout(this.debounceTimer);
      this.debounceTimer = setTimeout(() => {
        this.page = 1;
        this.fetchSubscribers();
      }, 250);
    },
    changePage(newPage) {
      this.page = newPage;
      this.fetchSubscribers();
    },
    async downloadCsv() {
      const response = await api.get('/admin/news-subscribers/export', { responseType: 'blob' });
      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      link.download = `news-subscribers-${new Date().toISOString().slice(0, 10)}.csv`;
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);
    },
    formatDate(iso) {
      if (!iso) return '—';
      return new Date(iso).toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
    },
    statusClass(status) {
      const map = {
        confirmed: 'badge-active',
        pending: 'text-violet-500 font-semibold',
        unsubscribed: 'text-neutral-400',
      };
      return map[status] || '';
    },
  },
};
</script>
```

- [ ] **Step 2: Add the route to `router/index.js`**

Find the existing admin route group. Add:

```javascript
{
  path: '/admin/news-subscribers',
  name: 'admin-news-subscribers',
  component: () => import('@/views/Admin/NewsSubscribersPage.vue'),
  meta: { requiresAuth: true, requiresAdmin: true },
},
```

(Use whatever the existing admin meta flag is — `requiresAdmin`, `adminOnly`, etc. — match it.)

- [ ] **Step 3: Browser-test the admin page**

Login as `chris@fynla.org` / `Password1!` (admin user). Navigate to `http://localhost:8000/admin/news-subscribers`. Confirm:
- Table renders with at least one row (use the test data from earlier tasks if needed).
- Filter chips switch between `All` / `Confirmed` / `Pending` / `Unsubscribed`.
- Search by partial email reduces the list.
- Click `Export CSV` — a file downloads. Open it; first line is the header; rows match the table.

- [ ] **Step 4: Commit**

```bash
git add resources/js/views/Admin/NewsSubscribersPage.vue resources/js/router/index.js
git commit -m "feat(news): add admin page to list and export news subscribers"
```

---

## Task 24: CSJTODO follow-up note

**Files:**
- Modify: `CSJTODO.md`

- [ ] **Step 1: Append the follow-up section**

Add to `CSJTODO.md` under whatever the active sprint section is:

```markdown
### Newsletter broadcast (follow-up to news-subscribe-fix-plan)

When a `NewsArticle` is published (`status` flips to `published`), fan out a broadcast to all confirmed subscribers (`NewsSubscriber::confirmed()`). Email content uses the same article body. Should be queueable, paced (avoid SMTP 451 rate-limits — see Session 67 lifecycle hotfix), and skip subscribers who unsubscribed after the article was queued.

Out of scope for the news-subscribe-fix branch (which only built the list-build infrastructure). This ticket builds the use-the-list infrastructure.
```

- [ ] **Step 2: Commit**

```bash
git add CSJTODO.md
git commit -m "docs: add newsletter broadcast follow-up to CSJTODO"
```

---

## Task 25: Full test run + final cleanup

- [ ] **Step 1: Run the full test suite**

```bash
./vendor/bin/pest
```

Expected: all tests pass. New tests added by this plan: 6 (public subscribe) + 6 (newsletter actions) + 5 (admin) + 3 (mailable render) = **20 new tests**. Total suite size should be approximately 940 + 20 = 960.

- [ ] **Step 2: Run pint**

```bash
./vendor/bin/pint
```

Expected: zero issues, or auto-fix and re-run until clean.

- [ ] **Step 3: Browser smoke — final golden path**

Re-run the Task 19 happy path one more time, end-to-end (subscribe → confirm → see news page → unsubscribe). Confirm everything still works after all subsequent changes.

- [ ] **Step 4: Verify no regression on the original news page**

Navigate to `/news` and confirm:
- Featured article hero card still renders.
- Recent articles grid still renders (pagination still works).
- The bottom "Want to stay updated?" CTA section still renders unchanged.
- The small "Or subscribe via RSS" link in that bottom CTA still opens `/feed/news.xml`.

- [ ] **Step 5: Verify the RSS feeds still work**

```bash
curl -sI http://localhost:8000/feed/news.xml | head -2
curl -sI http://localhost:8000/feed/insights.xml | head -2
```

Expected: both return `HTTP/1.1 200 OK` with `Content-Type: application/rss+xml; charset=UTF-8`. (PR-237 RSS work must not have regressed.)

- [ ] **Step 6: Final commit if any pint changes**

```bash
git status
git add .
git commit -m "style: apply pint formatting"
```

(Skip if `git status` is clean.)

---

## Task 26: Open the replacement PR

This task happens after the user has run all browser tests on local and is satisfied.

- [ ] **Step 1: Push the branch**

```bash
git push origin feature/phailanx/news-rss-lifecycle-emails
```

- [ ] **Step 2: Open PR `feature/phailanx/news-rss-lifecycle-emails` → `dev`**

Use `gh pr create` with a body that links to:
- `April/April28Updates/PR-237-review.md` (the original review)
- `April/April28Updates/news-subscribe-fix-plan.md` (this plan)
- And summarises the change as: "PR #237 work plus the news-subscribe email-list signup fix flagged by CSJ on 2026-04-28."

- [ ] **Step 3: Self-review and merge to `dev`** (CSJ does this manually).

- [ ] **Step 4: Deploy to dev (csjones.co/fynla)** following the existing `deploy/csjones-fynla/build.sh` flow. Specifically test on `https://csjones.co/fynla/news`:
  - Submit a fresh email; receive confirmation email at `marketing@fynla.org` From: address.
  - Click confirmation link in the email; lands on the confirmed page.
  - Welcome email arrives.
  - Click unsubscribe link in welcome email; lands on the unsubscribed page.
  - Login as admin; navigate to `/admin/news-subscribers`; export CSV; verify the test row is present.

- [ ] **Step 5: After dev is green, open `dev → main` PR** for the next periodic release.

---

## Self-review checklist

- [x] Every spec requirement from the conversation has a task: form (Tasks 17-18), DB (Task 2), model (Task 3), public POST (Tasks 10-13), confirm action (Task 14), unsubscribe action (Task 15), already-registered check (Task 11), already-confirmed (Task 12), pending-resend (Task 12), rate-limit (Task 13), preview-mode exclusion (Task 10), marketing from-address (Task 4), double opt-in (Tasks 5-9, 14), unsubscribe link in welcome (Tasks 7-8), admin index (Task 21), admin CSV export (Task 22), admin Vue page (Task 23), broadcast follow-up note (Task 24), tests for everything new (Tasks 9, 10-13, 14-15, 21-22), browser tests (Tasks 19-20).
- [x] Cross-referenced PR-237-review.md findings #16, #8, #11, #3, B2 — explicit table at top of plan.
- [x] No placeholders, TBDs, or `// implement here` comments in any task.
- [x] All file paths are exact.
- [x] All test code is shown in full (no "similar to Task N" pointers).
- [x] All commit messages are written out.
- [x] Type/method consistency: `confirmation_token` is the field name everywhere, `NewsSubscriber::generateToken()` is the static factory, `subscribe()` / `confirm($token)` / `unsubscribe($token)` are the verbs, `'pending_confirmation' | 'already_registered' | 'already_confirmed'` are the response status strings used both backend and frontend.
- [x] Email rule-2 adjacency check is performed in Tasks 6 and 8 with the exact bg-colour walk written out.
- [x] The plan acknowledges three places where the engineer must verify existing patterns before writing (admin middleware alias, admin meta route flag, footer module unsubscribe-link prop name) rather than guessing — Task 1 + explicit verification steps in Tasks 8, 21, 23.

---

## Execution handoff

Plan complete and saved to `April/April28Updates/news-subscribe-fix-plan.md`. Two execution options:

**1. Subagent-Driven (recommended)** — Dispatch a fresh subagent per task with two-stage review between tasks. Best for catching mistakes early on a 26-task plan.

**2. Inline Execution** — Execute tasks in this session using `superpowers:executing-plans` with batch checkpoints (likely after Tasks 4, 13, 18, 23). Faster but less rigorous.

Which approach?
