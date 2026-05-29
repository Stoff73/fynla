<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class DebugScrape extends Command
{
    protected $signature = 'debug:scrape {url} {--show-body : Print the first 1500 chars of the response} {--inertia : Try Inertia headers}';
    protected $description = 'Diagnose what the scraper actually receives from a URL';

    public function handle(): int
    {
        $url = $this->argument('url');
        $headers = [
            'User-Agent' => 'Mozilla/5.0 (compatible; FynlaPipeline/1.0)',
            'Accept' => 'text/html,application/xhtml+xml',
            'Accept-Language' => 'en-GB,en;q=0.9',
        ];

        if ($this->option('inertia')) {
            $headers['X-Inertia'] = 'true';
            $headers['X-Requested-With'] = 'XMLHttpRequest';
            $headers['Accept'] = 'application/json';
            $this->info("Using Inertia headers");
        }

        $this->info("Fetching: {$url}");

        $r = Http::timeout(30)->withHeaders($headers)->get($url);

        $this->info("HTTP Status: {$r->status()}");
        $this->info("Body length: " . strlen($r->body()));
        $this->info("Content-Type: " . $r->header('Content-Type'));

        $body = $r->body();

        if ($this->option('inertia')) {
            // Try parse as JSON
            $json = json_decode($body, true);
            if ($json) {
                $this->info("✓ Valid JSON returned");
                $this->info("Top-level keys: " . implode(', ', array_keys($json)));
                if (isset($json['props'])) {
                    $this->info("props keys: " . implode(', ', array_keys($json['props'])));
                }
                $this->newLine();
                $this->info("--- First 2000 chars of JSON ---");
                $this->line(substr(json_encode($json, JSON_PRETTY_PRINT), 0, 2000));
            } else {
                $this->error("Inertia headers didn't return JSON. Got: " . substr($body, 0, 500));
            }
            return self::SUCCESS;
        }

        $this->info("Has <body>: " . (stripos($body, '<body') !== false ? 'yes' : 'no'));
        $this->info("Has <article>: " . (stripos($body, '<article') !== false ? 'yes' : 'no'));
        $this->info("Has <main>: " . (stripos($body, '<main') !== false ? 'yes' : 'no'));

        if ($this->option('show-body')) {
            $this->newLine();
            $this->info("--- First 1500 chars ---");
            $this->line(substr($body, 0, 1500));
        }

        return self::SUCCESS;
    }
}
