<?php

declare(strict_types=1);

namespace App\Services\Savings\MarketRates;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Reads the best-buy rate for each benchmark product from MoneySavingExpert's
 * savings pages (the best-savings guide and the best-cash-ISA guide).
 *
 * The pages are server-rendered articles. Fixed-term best buys are tables under
 * "Top one-year fixed savings" style headings with a "Rate (AER)" column and the
 * top pick in the first data row. Notice accounts share the "shorter fixes &
 * notice accounts" table, after a single-cell "Top notice accounts" row.
 * Easy-access picks are card layouts inside a table: the column headed
 * "Top 'normal' savings accounts" (or the "Top for new money" card for ISAs)
 * carries "Provider , 4.61%" text.
 *
 * Rates come back as decimals (4.88% -> 0.0488), the unit savings_market_rates
 * stores. Anything the page does not show is simply absent from the result;
 * nothing is guessed.
 */
final class MoneySavingExpertRatesParser
{
    /** heading fragment (normalised) => rate_key */
    private const TABLE_HEADINGS = [
        'one-year fixed savings' => 'fixed_1_year',
        'two-year fixed savings' => 'fixed_2_year',
        'three-year fixed savings' => 'fixed_3_year',
        'one-year fixed isas' => 'fixed_1_year_isa',
        'two-year fixed isas' => 'fixed_2_year_isa',
        'three-year fixed isas' => 'fixed_3_year_isa',
    ];

    /**
     * @return array<string, array{rate: float, provider: string}>
     */
    public function parse(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $doc = new DOMDocument;
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        $xpath = new DOMXPath($doc);

        $found = [];
        $pendingKey = null;
        $pendingNotice = false;

        foreach ($xpath->query('//h2|//h3|//h4|//table') ?: [] as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            if ($node->nodeName !== 'table') {
                $heading = $this->norm($node->textContent);
                $pendingKey = null;
                $pendingNotice = str_contains($heading, 'notice accounts');
                foreach (self::TABLE_HEADINGS as $needle => $key) {
                    if (str_contains($heading, $needle)) {
                        $pendingKey = $key;
                        break;
                    }
                }

                continue;
            }

            $rows = $this->rows($node);

            if ($pendingKey !== null) {
                if (! isset($found[$pendingKey]) && ($hit = $this->firstRateRow($rows)) !== null) {
                    $found[$pendingKey] = $hit;
                }
                $pendingKey = null;
            }

            if ($pendingNotice) {
                if (! isset($found['notice']) && ($hit = $this->noticeRow($rows)) !== null) {
                    $found['notice'] = $hit;
                }
                $pendingNotice = false;
            }

            $this->cards($rows, $found);
        }

        return $found;
    }

    /** @return list<list<DOMElement>> */
    private function rows(DOMElement $table): array
    {
        $rows = [];
        foreach ($table->getElementsByTagName('tr') as $tr) {
            $cells = [];
            foreach ($tr->childNodes as $cell) {
                if ($cell instanceof DOMElement && in_array($cell->nodeName, ['td', 'th'], true)) {
                    $cells[] = $cell;
                }
            }
            if ($cells !== []) {
                $rows[] = $cells;
            }
        }

        return $rows;
    }

    /**
     * The top pick of a best-buy table: the first row whose rate column reads as a
     * percentage. The rate column is the one headed "AER"; column 1 otherwise.
     *
     * @param  list<list<DOMElement>>  $rows
     * @return array{rate: float, provider: string}|null
     */
    private function firstRateRow(array $rows): ?array
    {
        $rateCol = 1;
        foreach ($rows[0] ?? [] as $i => $cell) {
            if (str_contains($this->norm($cell->textContent), 'aer')) {
                $rateCol = $i;
                break;
            }
        }

        foreach ($rows as $cells) {
            if (count($cells) < 2 || ! isset($cells[$rateCol])) {
                continue;
            }
            $rate = $this->leadingPercent($cells[$rateCol]->textContent);
            if ($rate !== null) {
                return ['rate' => $rate, 'provider' => $this->text($cells[0]->textContent)];
            }
        }

        return null;
    }

    /**
     * @param  list<list<DOMElement>>  $rows
     * @return array{rate: float, provider: string}|null
     */
    private function noticeRow(array $rows): ?array
    {
        $afterMarker = false;
        foreach ($rows as $cells) {
            if (! $afterMarker) {
                $afterMarker = count($cells) === 1 && str_contains($this->norm($cells[0]->textContent), 'top notice accounts');

                continue;
            }
            if (count($cells) >= 2 && ($rate = $this->leadingPercent($cells[1]->textContent)) !== null) {
                return ['rate' => $rate, 'provider' => $this->text($cells[0]->textContent)];
            }
        }

        return null;
    }

    /**
     * Easy-access picks live in card layouts, not rate tables.
     *
     * @param  list<list<DOMElement>>  $rows
     * @param  array<string, array{rate: float, provider: string}>  $found
     */
    private function cards(array $rows, array &$found): void
    {
        $easyCol = null;
        foreach ($rows as $cells) {
            foreach ($cells as $i => $cell) {
                $t = $this->norm($cell->textContent);
                if ($easyCol === null && str_contains($t, "top 'normal' savings accounts")) {
                    $easyCol = $i;
                }
                if (! isset($found['easy_access_isa'])
                    && (str_starts_with($t, 'top cash isa for new money') || str_starts_with($t, 'top for new money'))
                    && ($hit = $this->providerRate($cell)) !== null) {
                    $found['easy_access_isa'] = $hit;
                }
            }
            if ($easyCol !== null && ! isset($found['easy_access']) && isset($cells[$easyCol])) {
                $t = $this->norm($cells[$easyCol]->textContent);
                if (! str_contains($t, "top 'normal'") && ($hit = $this->providerRate($cells[$easyCol])) !== null) {
                    $found['easy_access'] = $hit;
                }
            }
        }
    }

    /**
     * "Provider , 4.61%" inside a card: the smallest element whose own text is
     * that pattern, so a label such as "5% on small amounts" above it is not
     * mistaken for the provider.
     *
     * @return array{rate: float, provider: string}|null
     */
    private function providerRate(DOMElement $cell): ?array
    {
        $candidates = [];
        foreach ($cell->getElementsByTagName('*') as $el) {
            $text = $this->text($el->textContent);
            if (mb_strlen($text) > 120) {
                continue;
            }
            if (preg_match('/^(?<provider>[^,]{2,80}?)\s*,\s*(?<rate>\d{1,2}(?:\.\d{1,2})?)\s*%/u', $text, $m) === 1) {
                $candidates[] = [mb_strlen($text), $m];
            }
        }
        if ($candidates === []) {
            return null;
        }
        usort($candidates, fn ($a, $b) => $a[0] <=> $b[0]);
        $m = $candidates[0][1];
        // "Trading 212 - newbies only" / "- for new customers only" qualifiers are not part of the name.
        $provider = preg_replace('/\s*[-–]\s*(newbies|for new customers)\s+only.*$/iu', '', $m['provider']) ?? $m['provider'];

        return ['rate' => round(((float) $m['rate']) / 100, 4), 'provider' => rtrim($this->text($provider), '* ')];
    }

    private function leadingPercent(string $text): ?float
    {
        return preg_match('/^\s*(\d{1,2}(?:\.\d{1,2})?)\s*%/u', $this->text($text), $m) === 1
            ? round(((float) $m[1]) / 100, 4)
            : null;
    }

    private function text(string $raw): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $raw));
    }

    private function norm(string $raw): string
    {
        return mb_strtolower(str_replace(['’', '‘'], "'", $this->text($raw)));
    }
}
