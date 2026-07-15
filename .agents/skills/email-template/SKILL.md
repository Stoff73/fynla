---
name: email-template
description: Assemble Fynla transactional emails from the master layout and reusable module partials, enforcing the email design rules. Use whenever creating, editing, converting, or auditing an email Blade — triggers on "build an email template", "create a new email", "update the welcome email", "audit this email template", or any task touching resources/views/emails/.
---

# Email Template — Fynla Master Layout & Modules

Assemble every Fynla transactional email from the master layout (`resources/views/emails/layouts/master.blade.php`) plus the reusable module partials (`resources/views/emails/modules/*.blade.php`). All new emails **must** extend `emails.layouts.master` and compose their body from `emails.modules.*`. The mockup reference is `public/mockups/email-master-template.html`.

Available modules: logo-bar, hero-header, gradient-header, body, code-box, notice, summary-table, stats-panel, numbered-steps, discount-panels, badge, bullet-list, counter, feature-grid, cta, divider, description-box, console-box, signoff (text only, no logo), footer.

Design rules enforced throughout: header colours fixed, no adjacent same-background sections, logo always links to homepage, summary tables are eggshell on solid-colour backgrounds, section titles are sentence-case horizon-blue H3s at 20px, hero subtitles stay on one line.

## Inviolable Rules

### Rule 1 — Header background colours are fixed

The hero header uses `linear-gradient(135deg, #1F2A44 0%, #e74c6f 100%)` with `#1F2A44` fallback. The simple gradient header uses `linear-gradient(135deg, #1F2A44 0%, #2d3a5c 100%)`. These colours are baked into `emails/modules/hero-header.blade.php` and `emails/modules/gradient-header.blade.php` and must not be overridden or parameterised.

**Why:** The header is Fynla's most recognisable visual anchor across email. Inconsistency between emails undermines recognition.

**How to apply:** Never add a `$bg`, `$gradient`, `$colour`, or similar variable to the header modules. Never override the header gradient with an inline style in a template. If a template requires a different brand treatment, stop and escalate — don't fork the header.

### Rule 2 — Two adjacent sections must not share the same solid background colour

Every `<tr>` inside the 600px container has an outer background. Adjacent rows must alternate.

**Why:** Without an alternation discipline, email clients render the template as one flat slab — module boundaries disappear and the email feels visually dead.

**How to apply:**
1. Track the outer bg colour of each section top to bottom. The common values in these modules are:
   - `#ffffff` (white) — default outer bg
   - `#f5f0eb` (eggshell)
   - `#fce4ec` / `#FAD6E0` (pink)
   - `#f0f9ff` / `#dbeafe` (light blue)
   - `#3b82f6`, `#e74c6f`, `#8b5cf6`, `#20B486` (solid colour)
   - `#1F2A44` (dark navy)
   - `#0F172A` (feature-grid strip)
2. If two consecutive sections resolve to the same colour, either:
   - Pass a different `$outerBg` / `$bg` prop to one of them, or
   - Insert `@include('emails.modules.divider', ['outerBg' => '#ffffff'])` between them.
3. Notice pills (`notice.blade.php`) carry two colours: the **outer `<td>`** and the **inner pill**. Rule 2 applies to the outer `<td>`, not the pill — two consecutive notices on a white outer bg are fine regardless of inner variant.

### Rule 3 — Fynla logo in the top-left always links to the homepage

Every Fynla logo in the email (logo-bar, sign-off, dark footer) is wrapped in `<a href="https://fynla.org">`. This is baked in — no module accepts an override.

**Why:** Users consistently click the logo expecting "home". A missing or wrong link breaks that expectation and wastes attention.

**How to apply:** Never open `logo-bar.blade.php`, `signoff.blade.php`, or the dark variant of `footer.blade.php` to change the anchor. If an email needs the logo to link somewhere else, stop — the request is wrong, not the rule.

### Rule 4 — Summary/detail tables sit on eggshell when their outer section is a solid colour

When a summary table is placed inside a section whose outer background is light-pink, light-blue, raspberry, violet, solid-blue, success-green, or dark-navy, the table's inner wrapper bg is forced to eggshell `#F7F6F4` for legibility. When the outer section is white or eggshell, the table uses the default pink wrapper `#FAD6E0`.

**Why:** A raspberry or pink table wrapper on a pink outer section disappears. Eggshell always has enough contrast against the listed solid colours to stay legible.

**How to apply:** Always pass `$surroundBg` to `summary-table.blade.php` matching the outer `<td>` bg of the surrounding section. The module auto-selects eggshell vs pink based on that value — you don't need to calculate it, but you **do** need to pass it correctly.

### Rule 5 — Section titles are sentence-case horizon-blue H3s at 20px

Every in-body section heading (for example "Your account", "Plan your next steps", "Tips for your journey", "Unlock your discount", "What to do next") renders as:

```html
<h3 style="margin:0 0 8px;font-size:20px;font-weight:700;color:#1F2A44;">Section title</h3>
```

- **Sentence case** — only the first word is capitalised. Acronyms (except ISA, per rule 10 in AGENTS.md) are spelled out.
- **Horizon blue** — `#1F2A44` (Fynla's text/nav palette value). No other colour.
- **20px, weight 700.**
- **Not uppercase**, no letter-spacing tracking, not bold-all-caps eyebrow labels.

**Why:** Earlier drafts used an 11px uppercase eyebrow style ("YOUR ACCOUNT") that looked like dev-tool chrome and fought the body copy. The horizon-blue 20px H3 reads as a clear section break without competing with the H1 hero title.

**How to apply:** Never use `text-transform:uppercase` or `letter-spacing:1px` on a section heading. Never set heading colour to anything other than `#1F2A44`. Subtitles under a section heading stay as regular 14px body text on a separate paragraph — don't bold them.

### Rule 6 — Hero subtitles stay on one line

The subtitle under the H1 in `hero-header` and `gradient-header` must render on a single line at the standard 600px email width. With the Fyn character image occupying ~130px on the right, the available text column for the subtitle is roughly 440px — comfortably fitting 55–60 characters of 14px text.

**Why:** A wrapping subtitle competes visually with the large H1 and turns the hero into a block of text instead of a clean "title + tagline" unit. Consistency across emails also matters: every hero should read the same way.

**How to apply:** Keep every hero subtitle at or below 55 characters. Punch the message into the H1 (which may wrap onto two lines, using `<br/>` where deliberate); use the subtitle for a single short tagline only. Never force `white-space:nowrap` — the right fix is shorter copy, not clipping.

### Rule 7 — Outer page is white; internal "neutral" sections are eggshell (one exception: the logo bar)

The area **outside** the 600px email container (the `<body>` and any full-width wrapper table) is white `#ffffff`. **Inside** the container, any section that would otherwise be a generic white bg becomes eggshell `#f5f0eb`. Accent sections (pink `#fce4ec`, dark navy `#1F2A44`, gradient hero, `#0F172A` dark strips) keep their colours and provide contrast.

**The single exception: the logo bar `<tr>` at the top of every email is white `#ffffff`.** It's the only section inside the container allowed to be white — it creates a clean visual handshake between the outer white page and the eggshell content band below. The `logo-bar.blade.php` module hardcodes this and it must not be overridden.

**Why:** Inverting the old convention (eggshell outside / white inside) makes the 600px container read as a warmer, more defined object sitting on a clean page. It also eliminates the dead "blank white" feel that generic white internal sections used to have.

**How to apply:**
- `<body>` bg and any outer wrapper `<table>` bg: `#ffffff`.
- Inner 600px container `<table>` bg: `#f5f0eb` (eggshell fallback so unpainted gaps don't flash white).
- Any `<tr>` / `<td>` that used to have `background:#ffffff` for the section's bg: change to `#f5f0eb`.
- Module defaults (`$outerBg` on every module) default to `#f5f0eb`.
- **Rule 2 still applies.** If the flip creates two consecutive eggshell `<tr>`s that represent distinct sections, resolve by: (a) merging them into one logical `<tr>` when the content belongs together, (b) swapping one for a different accent bg (pink or dark), or (c) inserting a non-eggshell intermezzo (stats panels, discount panels, or a gradient header are natural breaks). **Consecutive same-bg `<tr>`s are acceptable when they render as one continuous visual band** — e.g., a section heading `<tr>` directly above its content `<tr>`. What's disallowed is two separately-conceived sections meeting at the same bg colour.

## Master Layout

```blade
@extends('emails.layouts.master', ['title' => 'Your Fynla Invoice', 'preheader' => 'Your invoice INV-2026-0033 for £269.99'])

@section('logoBar')
    @include('emails.modules.logo-bar')
@endsection

@section('header')
    @include('emails.modules.hero-header', [
        'heading'  => 'Your <span style="color:#f9a8c0;">invoice</span>',
        'subtitle' => 'Receipt for your Pro subscription',
    ])
@endsection

@section('body')
    @include('emails.modules.body', [
        'greeting'   => 'Hi ' . ($user->first_name ?? 'there') . ',',
        'paragraphs' => ['Thank you for your payment. Your invoice is below.'],
    ])
    @include('emails.modules.summary-table', [
        'surroundBg' => '#ffffff',
        'rows' => [
            ['label' => 'Plan',           'value' => 'Pro (Yearly)'],
            ['label' => 'Amount',         'value' => '&pound;269.99'],
            ['label' => 'Billing period', 'value' => '15 Apr 2026 &ndash; 14 Apr 2027'],
            ['label' => 'Status',         'value' => 'Active', 'valueColor' => '#22c55e'],
        ],
    ])
    @include('emails.modules.cta', [
        'buttons' => [
            ['label' => 'View Invoice', 'url' => 'https://fynla.org/account/invoices/33', 'variant' => 'raspberry'],
        ],
    ])
@endsection

@section('signoff')
    @include('emails.modules.signoff')
@endsection

@section('footer')
    @include('emails.modules.footer', ['variant' => 'dark'])
@endsection
```

## Module Inventory

Each module is one `<tr>` (or occasionally a `<tr>` containing a nested table). All accept an `$outerBg` prop so you can tune colours for Rule 2 adjacency.

| Module | File | Purpose | Key props |
|--------|------|---------|-----------|
| Logo bar | `logo-bar.blade.php` | White bar with Fynla logo linked to homepage | (none) |
| Hero header | `hero-header.blade.php` | Gradient header with optional Fyn character | `heading`, `subtitle`, `showCharacter` |
| Gradient header | `gradient-header.blade.php` | Simple dark-navy gradient header | `heading`, `subtitle` |
| Body | `body.blade.php` | Greeting + paragraphs | `greeting`, `paragraphs`, `bg`, `padding` |
| Code box | `code-box.blade.php` | Verification / reset code pill (dark only — the blue variant was retired so reset and verification codes look identical) | `code`, `label`, `expiry` |
| Notice | `notice.blade.php` | Coloured notification pill | `message`, `variant` (info/solid-blue/pink/violet/neutral — green `success` and raspberry `raspberry` variants have been removed), `icon` |
| Summary table | `summary-table.blade.php` | Key/value details box (Rule 4) | `rows`, `surroundBg` |
| Stats panel | `stats-panel.blade.php` | Alternating horizon/light-blue two-column stat rows | `panels` (array of `title`, `number`, `sentence`) |
| Numbered steps | `numbered-steps.blade.php` | Large raspberry numbers + top-aligned title/description | `steps` (array of `title`, `description`) |
| Discount panels | `discount-panels.blade.php` | Progress bar with **two states**: zero-state renders bar + green "Get started" CTA only; non-zero state renders bar + clickable 2-column module grid with Harvey-ball status + max-discount footer | `progressPercent`, `modules`, `ctaLabel`, `ctaUrl` |
| Top tips | `top-tips.blade.php` | Three full-width rounded white cards on a coloured section bg; all cards share a min-height so desktop and mobile render identically | `tips` (array of 3 × `title`, `description`), `heading`, `outerBg`, `height` |
| Badge | `badge.blade.php` | Inline reference chip | `text` |
| Bullet list | `bullet-list.blade.php` | Bulleted list in eggshell box | `heading`, `items` |
| Counter | `counter.blade.php` | Large urgency number + caption | `number`, `label`, `subtext` |
| Feature grid | `feature-grid.blade.php` | 2×2 dark navy tiles — no icons, 17px titles, optional bottom CTA ("Check out features") | `features` (array of 4 × `title`, `subtitle`), `cta` (`label`, `url`) |
| CTA | `cta.blade.php` | Button(s), multiple stacked | `buttons` (array with `label`, `url`, `variant`) |
| Divider | `divider.blade.php` | Hairline separator | (none) |
| Description box | `description-box.blade.php` | Pre-formatted user-supplied text | `text` |
| Console box | `console-box.blade.php` | Monospace dark code block | `text`, `maxHeight` |
| Signoff | `signoff.blade.php` | "Kindest regards, The Fynla Team" — text only (no logo; logo lives in the top logo-bar and the dark footer) | `closing`, `team` |
| Footer | `footer.blade.php` | Dark (logo+links) or light (simple line) | `variant`, `links`, `year` |

## Stats Panel — Usage & Layout

The `stats-panel` module renders one or more full-bleed two-column panels with a horizon-blue cell on one side (title + large number) and a light-blue cell on the other (supporting sentence). Successive panels automatically alternate sides — the first is horizon-left, the second horizon-right, and so on — so no two same-colour cells sit directly above each other.

```blade
@include('emails.modules.stats-panel', [
    'panels' => [
        [
            'title'    => 'Pension annual allowance',
            'number'   => '&pound;60,000',
            'sentence' => '1 in 3 higher-rate taxpayers fail to claim the full pension tax relief they\'re entitled to.',
        ],
        [
            'title'    => 'Annual ISA allowance',
            'number'   => '&pound;20,000',
            'sentence' => 'The average unused ISA headroom in the UK is &pound;8,400 &mdash; tax-free growth left on the table every year.',
        ],
        [
            'title'    => 'Inheritance Tax rate',
            'number'   => '40%',
            'sentence' => 'Without planning, 40% of every pound above the combined nil-rate bands is paid in tax.',
        ],
    ],
])
```

**Layout rules for the panel:**
- Horizon cell = `#1F2A44` with title in `#f9a8c0` (pink, weight 700) and number in white (weight 900, 40px).
- Light-blue cell = `#dbeafe` with sentence in horizon blue `#1F2A44` (NOT a dark-blue — the supporting copy reads as calm horizon text, matching the rest of the email).
- Cells are **50% / 50%** — both sides equal width.
- Alternation is automatic — pass panels in narrative order and the module swaps sides.
- `$number` and `$sentence` render unescaped so you can pass HTML entities (`&pound;`, `&mdash;`) and light inline emphasis (`<strong>`). Escape user input upstream if any value is user-provided.

**When to use it instead of `notice` or `summary-table`:**
- Use `stats-panel` when the content is a list of standalone stats that each pair a single striking number with a one-sentence insight.
- Use `notice` for a short informational pill (no number-first hierarchy).
- Use `summary-table` for a list of label/value rows that belong to one subject (an account, an invoice, a subscription).

## Numbered Steps — Usage

The `numbered-steps` module renders a list of actions with large raspberry numerals on the left and top-aligned title + description on the right. Used for "Plan your next steps" and "Get started" flows.

```blade
@include('emails.modules.numbered-steps', [
    'steps' => [
        [
            'title'       => 'Add your pensions',
            'description' => 'So we can check you\'re using your Annual Allowance and reclaiming the tax relief you\'re owed.',
        ],
        [
            'title'       => 'Link your investments',
            'description' => 'To surface ISA headroom you\'re not using and a tax-efficiency read across all your wrappers.',
        ],
        [
            'title'       => 'Log your estate',
            'description' => 'Model an IHT-efficient plan before it matters &mdash; most households don\'t until it\'s too late.',
        ],
    ],
])
```

**Layout rules:**
- Number: 54px / weight 900 / `#e74c6f` / line-height 0.9 — tight leading makes the number's optical top match the title row.
- Title: 15px / weight 700 / horizon blue `#1F2A44` / line-height 1.2.
- Description: 13px / `#555` / line-height 1.6. HTML entities (`&mdash;`, `&pound;`) allowed; escape user input upstream.
- Number cell is a fixed 70px with 14px right padding so titles sit at a consistent margin.
- Both cells use `vertical-align: top` — Rule 2 for internal alignment: description text aligns with the top of the numeral, not its centre.
- Rows 1 to n−1 have 18px bottom margin. The final row has 28px so it breathes before whatever CTA follows.

## Discount Panels — Usage

The `discount-panels` module renders the full "Unlock your discount" section — a dark `#0F172A` strip containing a progress bar, a 2-column grid of module tiles (each a clickable deep-link with a Harvey-ball completion indicator), and a footer line summarising the max discount.

Canonical journey tiers: **25% = 5%**, **50% = 10%**, **75% = 15%**, **100% = 20%**. The module auto-computes `discountEarned` from `progressPercent` using this mapping — override with `discountEarned` only if a template needs to diverge.

### Two states

**Non-zero state** (`$progressPercent > 0` AND `$modules` is non-empty): render the progress bar + the 2×3 module grid with Harvey-ball status + the max-discount footer message. Tiles deep-link into the dashboard; no separate CTA (the tiles *are* the CTA).

```blade
@include('emails.modules.discount-panels', [
    'progressPercent' => 50,
    'nextTierText'    => 'Next tier: 75% → 15% discount',
    'modules' => [
        ['title' => 'Protection', 'subtitle' => 'Life, income, critical illness', 'url' => 'https://fynla.org/protection',          'status' => 'full'],
        ['title' => 'Savings',    'subtitle' => 'Cash, ISAs, emergency fund',     'url' => 'https://fynla.org/savings',             'status' => 'three-quarter'],
        ['title' => 'Investment', 'subtitle' => 'Portfolios & projections',       'url' => 'https://fynla.org/investment',          'status' => 'half'],
        ['title' => 'Retirement', 'subtitle' => 'Pensions & decumulation',        'url' => 'https://fynla.org/net-worth/retirement','status' => 'quarter'],
        ['title' => 'Estate',     'subtitle' => 'IHT, wills, LPAs',               'url' => 'https://fynla.org/estate',              'status' => 'empty'],
        ['title' => 'Goals',      'subtitle' => 'Life events & targets',          'url' => 'https://fynla.org/goals',               'status' => 'empty'],
    ],
])
```

**Zero state** (`$progressPercent === 0` OR `$modules` omitted): render the progress bar + details only, followed by a centred **green "Get started" CTA**. No tiles, no max-discount footer. Used on re-engagement emails where the user hasn't started any module.

```blade
@include('emails.modules.discount-panels', [
    'progressPercent' => 0,
    'nextTierText'    => 'Next tier: 25% → 5% discount',
    'ctaLabel'        => 'Get started',
    'ctaUrl'          => 'https://fynla.org/dashboard',
])
```

The zero-state CTA is **green `#20B486`** (the Fyn-action green, same treatment as the Get Started email's "Get started with Fyn" button) to distinguish it from the raspberry CTAs used elsewhere — a user at 0% is at the *beginning* of their journey, not mid-action.

**Status values → Harvey ball:** `full` ● / `three-quarter` ◕ / `half` ◑ / `quarter` ◔ / `empty` ○. Any non-empty status is rendered in spring green `#22c55e`; `empty` is rendered in slate `#64748b`.

**Deep-link URLs must be absolute** (`https://fynla.org/...`) per the master-layout rule on CTA links. The entire tile is a single `<a>` anchor with `display:block; text-decoration:none; color:#ffffff` so the whole card area is clickable in any mail client.

**Progress bar** uses a two-cell `<table>` (fill cell `#22c55e`, track cell `#1F2A44`) rather than nested `<div>`s so Outlook/Gmail render the fill width reliably. Pass any 0–100 integer as `progressPercent`.

## Top Tips — Usage

The `top-tips` module renders three full-width rounded white cards in a single row on a coloured section background. All three cards share the same min-height so they render at the same height both side-by-side on desktop and stacked on mobile — no client-dependent reflow produces uneven cards.

```blade
@include('emails.modules.top-tips', [
    'heading' => 'Top tips',
    'outerBg' => '#fce4ec',
    'tips' => [
        ['title' => 'Review quarterly',      'description' => 'Tax allowances change every year &mdash; a 10-minute check keeps you ahead.'],
        ['title' => 'Invite your partner',   'description' => 'Joint modelling unlocks household-level tax savings most single-user plans miss.'],
        ['title' => 'Keep details current',  'description' => 'Salary, pension contributions and property values feed every projection you see.'],
    ],
])
```

**Layout rules:**
- Exactly three tips. The module pads or trims `$tips` to three so the grid always balances.
- Outer section background defaults to light pink `#fce4ec`; pass any eligible section bg via `$outerBg`.
- Section heading renders as a Rule-5 H3 (20px / weight 700 / horizon `#1F2A44`). Pass `heading => null` to hide.
- Cards: white `#ffffff`, 12px border-radius, 16px inner padding, arranged as three 33.33% columns with an 8px horizontal gutter via `border-spacing: 8px 0`.
- **Equal height:** the outer `<td>` uses the legacy `height="150"` attribute (which Outlook/Gmail respect) and the inner `<div>` uses `min-height` with `box-sizing: border-box` (which browsers and modern clients respect). Override `height` if your longest tip description needs more room — always check the longest card renders without clipping.
- Title: 14px / weight 700 / horizon `#1F2A44`. Description: 12px / `#555` / line-height 1.5.
- Keep each card's description under ~90 characters so all three fit the default 150px card height.

## Workflow — Creating a New Email

1. **Start from a mockup.** The canonical reference is `public/mockups/email-master-template.html`. If the user has a new mockup, skim it and identify which modules each section maps to.
2. **Create the Blade file** as `resources/views/emails/{kebab-name}.blade.php`.
3. **Extend the master layout** with `@extends('emails.layouts.master', [...])` and populate the `logoBar`, `header`, `body`, `signoff`, `footer` sections.
4. **Compose the body** section by section. For each section, `@include` the right module with the data it needs.
5. **Run the adjacency check (Rule 2).** Write out the outer bg of each `<tr>` top to bottom and confirm no two adjacent rows share a colour. Insert dividers or flip a module's `$outerBg` to resolve collisions.
6. **Confirm Rule 3.** If you added a logo anywhere other than `logo-bar`, `signoff`, or `footer`, delete it — those three are the only places Fynla branding appears in an email.
7. **Confirm Rule 4.** For every `summary-table` include, eyeball the `$surroundBg` against the outer section it sits inside.
8. **Wire up the Mailable.** Add the view variables in the Mailable's `content()` method and pass through `$user`, `$code`, or whatever the template expects.

## Rule 2 Adjacency — Colour Palette Reference

| Token | Hex | Used by |
|-------|-----|---------|
| White | `#ffffff` | Default outer bg, logo bar |
| Eggshell light | `#f5f0eb` | Page bg, body section alternative |
| Eggshell table | `#F7F6F4` | Bullet list, badge, summary table (Rule 4), light footer |
| Pink light | `#fce4ec` | Notice pink variant |
| Pink table | `#FAD6E0` | Summary table default, description box |
| Blue light | `#f0f9ff` | Code box blue variant outer |
| Blue info | `#dbeafe` | Notice info variant |
| Blue solid | `#3b82f6` | Notice solid-blue variant, code box blue accent |
| Raspberry | `#e74c6f` | Notice raspberry variant, CTA primary |
| Violet | `#ede9fe` | Notice violet variant |
| Success | `#d1fae5` | Notice success variant |
| Dark navy | `#1F2A44` | Hero header fallback, code box dark, dark footer |
| Feature strip | `#0F172A` | Feature grid bg |

Two consecutive `<tr>` rows whose outer bgs match any cell in this table are a Rule 2 violation.

## Before Handing Off

Run through this checklist:

- [ ] Template extends `emails.layouts.master` and composes its body from `emails.modules.*` (no ad-hoc inline table rows).
- [ ] Rule 1 — no template overrides the gradient on `hero-header` or `gradient-header`.
- [ ] Rule 2 — walked top-to-bottom; no two adjacent `<tr>`s share an outer bg colour.
- [ ] Rule 3 — every Fynla logo in the email is wrapped in `<a href="https://fynla.org">`.
- [ ] Rule 4 — every `summary-table` include has a `$surroundBg` matching its outer section.
- [ ] Rule 5 — every section heading in the body is an `<h3>` at 20px / weight 700 / `#1F2A44`, sentence case, no uppercase or tracking.
- [ ] Rule 6 — every hero subtitle is ≤ 55 characters so it stays on one line at 600px width.
- [ ] Rule 7 — `<body>` bg is `#ffffff`; inner container bg is `#f5f0eb`; the only `#ffffff` section inside the container is the top logo bar (no other white internal sections). Adjacent `<tr>`s never share an eggshell bg unless they render as one continuous visual band.
- [ ] `$preheader` set on the master layout (one sentence, under 90 characters — this is what shows in inbox previews).
- [ ] All CTA URLs are absolute and point at `https://fynla.org/...` (not `/relative/paths`).
- [ ] Tested by rendering the view via a route or `Mail::fake()` + a Pest test, and eyeballed in a browser or mail preview tool.

## What NOT to Do

- Do **not** create a new module by copy-pasting inline markup into a template. Add a new partial under `resources/views/emails/modules/` and reuse it.
- Do **not** inline a gradient or hero background in an email Blade — use the header modules.
- Do **not** pass colour overrides to `hero-header` / `gradient-header` (Rule 1).
- Do **not** skip the adjacency check "because the email is short" (Rule 2).
- Do **not** point the top-left logo at a tracking URL, a campaign landing, or anywhere other than `https://fynla.org` (Rule 3).
- Do **not** place a pink-wrappered summary table on a pink outer section (Rule 4).
- Do **not** style section headings as 11px uppercase eyebrow labels (Rule 5) — they must be 20px sentence-case horizon-blue H3s.
- Do **not** write hero subtitles longer than one line (Rule 6) — shorten the copy, don't `white-space:nowrap` the wrap away.
- Do **not** add a Fynla logo back into the signoff module — the signoff is text-only. Logos live in `logo-bar` (top) and `footer` (dark variant only).
- Do **not** use green (`success`) or raspberry (`raspberry`) notice variants — they were removed because solid green and solid raspberry pill backgrounds read as clickable CTAs and visually compete with the real raspberry CTA buttons. For positive/affirmation copy use `info` or `neutral`; for alerts use `pink` or `violet`.
- Do **not** put coloured icon squares on feature-grid tiles — the grid is now title + subtitle only (17px / weight 700 white title, 11px muted subtitle). Keep tiles on `#1F2A44` with the `#0F172A` strip around them.
- Do **not** use `#ffffff` as an internal section bg (Rule 7) — use eggshell `#f5f0eb`. White is reserved for the outer page (outside the 600px container) **and** for the top logo bar only.
