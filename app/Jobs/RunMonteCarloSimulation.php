<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Constants\TaxDefaults;
use App\Services\Investment\MonteCarloSimulator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class RunMonteCarloSimulation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $timeout = 300; // 5 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(
        private string $jobId,
        private float $startValue,
        private float $monthlyContribution,
        private float $expectedReturn,
        private float $volatility,
        private int $years,
        private int $iterations = 1000,
        private ?float $goalAmount = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(MonteCarloSimulator $simulator): void
    {
        \Log::info("Monte Carlo job {$this->jobId} starting");

        // Mark job as running (preserve user_id from initial cache entry)
        $existing = Cache::get("monte_carlo_status_{$this->jobId}");
        $userId = is_array($existing) ? ($existing['user_id'] ?? null) : null;
        Cache::put("monte_carlo_status_{$this->jobId}", [
            'status' => 'running',
            'user_id' => $userId,
        ], TaxDefaults::CACHE_TTL_STANDARD);

        try {
            // Run simulation
            $results = $simulator->simulate(
                $this->startValue,
                $this->monthlyContribution,
                $this->expectedReturn,
                $this->volatility,
                $this->years,
                $this->iterations
            );

            \Log::info("Monte Carlo simulation completed for job {$this->jobId}");

            // Calculate goal probability if goal is provided
            if ($this->goalAmount) {
                $finalValues = array_map(fn ($r) => $r['final_value'], $results['year_by_year'][$this->years - 1]['percentiles']);
                $results['goal_probability'] = $simulator->calculateGoalProbability(
                    $finalValues,
                    $this->goalAmount
                );
            }

            // Store results in cache (24 hours for results, 1 hour for status)
            Cache::put("monte_carlo_results_{$this->jobId}", $results, TaxDefaults::CACHE_TTL_SIMULATION);
            Cache::put("monte_carlo_status_{$this->jobId}", [
                'status' => 'completed',
                'user_id' => $userId,
            ], TaxDefaults::CACHE_TTL_STANDARD);

            \Log::info("Monte Carlo job {$this->jobId} completed successfully, status cached");
        } catch (\Exception $e) {
            \Log::error("Monte Carlo job {$this->jobId} failed: ".$e->getMessage());

            // Mark job as failed
            Cache::put("monte_carlo_status_{$this->jobId}", [
                'status' => 'failed',
                'user_id' => $userId,
            ], TaxDefaults::CACHE_TTL_STANDARD);
            Cache::put("monte_carlo_error_{$this->jobId}", $e->getMessage(), TaxDefaults::CACHE_TTL_STANDARD);

            throw $e;
        }
    }
}
