<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\News\NewsArticle;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class NewsArticleSeeder extends Seeder
{
    public function run(): void
    {
        // First article: launch announcement.
        // Idempotent — uses updateOrCreate by slug so re-running the seeder
        // refreshes content without duplicating rows.
        NewsArticle::updateOrCreate(
            ['slug' => 'launching-fynla'],
            [
                'title'   => 'Launching Fynla: a new personal finance platform built to change your financial future',
                'summary' => 'Fynla is the new UK personal finance platform from founders Brett Isenberg (FCA) and Chris Slater-Jones (DipPFS) — bringing professional-grade financial planning to everyone.',
                'body'    => $this->bodyHtml(),
                'image_url'        => null,
                'author_name'      => 'The Fynla Team',
                'status'           => 'published',
                'published_at'     => Carbon::create(2026, 4, 27, 9, 0, 0),
                'meta_title'       => 'Launching Fynla — a new UK personal finance platform | Fynla',
                'meta_description' => 'Fynla is the new UK personal finance platform from founders Brett Isenberg (FCA) and Chris Slater-Jones (DipPFS), bringing professional-grade financial planning to everyone.',
            ]
        );
    }

    /**
     * Returns the launch article body as raw HTML.
     *
     * SECURITY: This HTML is rendered with `v-html` on the public news article
     * page. It is currently SAFE because the only writer is this seeder
     * (CSJ-authored content). When an admin write path is added, the input
     * MUST be sanitised (e.g. via mews/purifier) or piped through a markdown
     * renderer before persisting — never trust raw HTML from user input.
     */
    private function bodyHtml(): string
    {
        return <<<'HTML'
<p class="lead">We're launching <strong>Fynla</strong>, a new UK personal finance platform built to give everyone access to the same professional-grade planning tools that have, until now, sat behind expensive advisory fees.</p>

<p>Whether you're a recent graduate weighing up student debt, a growing family balancing a mortgage, or a retiree making sense of new tax rules, Fynla helps you see all your finances in one place, understand what they mean, and make confident decisions about your future.</p>

<h2>Why we built Fynla</h2>

<p>Personal finance in the UK has long suffered from the same problem: the tools needed to plan a financial future properly were either locked behind professional gatekeepers or too complicated for a kitchen-table conversation. Most people were left guessing — about their pensions, their tax position, their retirement income, their inheritance plans.</p>

<p>Fynla is our answer. Professional-grade planning tools, made accessible to everyone — and grounded in over 40 years of combined experience at the heart of UK financial services.</p>

<h2>Who's behind Fynla</h2>

<p>Fynla was co-founded by <strong><a href="/about#chris-slater-jones">Chris Slater-Jones</a></strong> and <strong><a href="/about#brett-isenberg">Brett Isenberg, FCA</a></strong>.</p>

<p><strong>Chris Slater-Jones</strong> brings over twenty years of experience as a financial adviser, director and compliance lead across the UK and South Africa. His career spans the advisory front line at St. James's Place through to the boardrooms of capital markets consultancies. As a former FCA-registered adviser, Chris has helped thousands of families navigate fragmented finances — pensions in one place, ISAs in another, with no clear way to see how it all fits together. He leads Fynla's editorial work on financial planning, retirement, pensions, ISAs, protection and estate planning, and holds a PG Diploma in Financial Planning along with the DipPFS qualification from the Chartered Insurance Institute.</p>

<p><strong>Brett Isenberg</strong> is a Fellow of the Institute of Chartered Accountants in England and Wales (ICAEW) and a former Big 4 accountant with two decades of experience at the intersection of business and finance. He has founded and sold a high-growth marketing group and served as CFO and COO across EMEA for global organisations including Dentsu and Merkle. Brett leads Fynla's work on net worth, investments, savings strategy, tax planning and personal finance technology, applying the same financial rigour used by global enterprises to everyday personal financial planning.</p>

<h2>What Fynla does</h2>

<p>Fynla is a comprehensive UK financial planning platform that brings together pensions, investments, savings, property, protection and estate planning into a single unified dashboard. Our proprietary <strong>Fynla Brain&reg;</strong> handles the complex calculations, projections and scenario modelling so you don't have to.</p>

<p>From day one, Fynla covers the seven areas that matter most to UK households:</p>

<ul>
  <li><strong>Protection</strong> — life cover, income protection and critical illness, with a clear view of your gaps.</li>
  <li><strong>Savings</strong> — cash, ISAs and emergency funds, with allowance tracking through the tax year.</li>
  <li><strong>Planning</strong> — portfolio analysis, tax-efficiency reads and projections across every wrapper you hold.</li>
  <li><strong>Retirement</strong> — pension consolidation visibility, Annual Allowance checks and retirement income projections.</li>
  <li><strong>Estate planning</strong> — Inheritance Tax modelling, will preparation and lasting power of attorney guidance.</li>
  <li><strong>Goals &amp; life events</strong> — funding plans for the things you actually want to achieve.</li>
  <li><strong>Coordination</strong> — joint household planning so couples can model their finances together.</li>
</ul>

<h2>What you can do today</h2>

<p>Fynla is free to try with a seven-day trial — no credit card required to start. Your data is encrypted with AES-256 and stored in UK data centres, and Fyn, your AI financial companion, is built in to walk you through your dashboard step by step.</p>

<p>You don't have to be comfortable with spreadsheets or fluent in tax law. You just have to be ready to see your financial life clearly for the first time.</p>
HTML;
    }
}
