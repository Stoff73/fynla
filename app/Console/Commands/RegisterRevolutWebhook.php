<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Payment\RevolutService;
use Illuminate\Console\Command;

class RegisterRevolutWebhook extends Command
{
    protected $signature = 'revolut:register-webhook
                            {url? : The webhook URL (defaults to APP_URL/api/webhooks/revolut)}
                            {--list : List currently registered webhooks}';

    protected $description = 'Register or list Revolut webhook endpoints';

    public function handle(RevolutService $revolutService): int
    {
        if ($this->option('list')) {
            return $this->listWebhooks($revolutService);
        }

        return $this->registerWebhook($revolutService);
    }

    private function registerWebhook(RevolutService $revolutService): int
    {
        $url = $this->argument('url') ?? config('app.url').'/api/webhooks/revolut';

        // Check for existing webhook with the same URL
        try {
            $existing = $revolutService->listWebhooks();
            foreach ($existing as $webhook) {
                if (($webhook['url'] ?? '') === $url) {
                    $this->warn("Webhook already registered for this URL (ID: {$webhook['id']}).");
                    $this->table(['Key', 'Value'], [
                        ['ID', $webhook['id']],
                        ['URL', $webhook['url']],
                        ['Events', implode(', ', $webhook['events'] ?? [])],
                    ]);

                    if (! $this->confirm('Register a duplicate anyway?', false)) {
                        return self::SUCCESS;
                    }
                }
            }
        } catch (\RuntimeException) {
            // If listing fails, proceed with registration anyway
        }

        $this->info("Registering webhook: {$url}");

        $events = [
            'ORDER_COMPLETED',
            'ORDER_PAYMENT_FAILED',
            'SUBSCRIPTION_INITIATED',
            'SUBSCRIPTION_FINISHED',
            'SUBSCRIPTION_CANCELLED',
            'SUBSCRIPTION_OVERDUE',
        ];

        try {
            $result = $revolutService->registerWebhook($url, $events);
            $this->info('Webhook registered successfully.');
            $this->table(['Key', 'Value'], [
                ['ID', $result['id'] ?? 'N/A'],
                ['URL', $result['url'] ?? $url],
                ['Events', implode(', ', $result['events'] ?? $events)],
            ]);

            return self::SUCCESS;
        } catch (\RuntimeException $e) {
            $this->error("Failed to register webhook: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    private function listWebhooks(RevolutService $revolutService): int
    {
        try {
            $webhooks = $revolutService->listWebhooks();

            if (empty($webhooks)) {
                $this->info('No webhooks registered.');

                return self::SUCCESS;
            }

            $rows = [];
            foreach ($webhooks as $webhook) {
                $rows[] = [
                    $webhook['id'] ?? 'N/A',
                    $webhook['url'] ?? 'N/A',
                    implode(', ', $webhook['events'] ?? []),
                ];
            }

            $this->table(['ID', 'URL', 'Events'], $rows);

            return self::SUCCESS;
        } catch (\RuntimeException $e) {
            $this->error("Failed to list webhooks: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
