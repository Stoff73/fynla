<?php

declare(strict_types=1);

use App\Agents\BaseAgent;
use Illuminate\Support\Facades\Cache;

/**
 * Concrete implementation of BaseAgent for testing protected methods.
 */
class TestableAgent extends BaseAgent
{
    public function analyze(int $userId): array
    {
        return ['userId' => $userId];
    }

    public function generateRecommendations(array $analysisData): array
    {
        return ['recommendations' => []];
    }

    public function buildScenarios(int $userId, array $parameters): array
    {
        return ['scenarios' => []];
    }

    // Expose protected methods for testing
    public function publicGetUserCacheKey(int $userId, string $suffix): string
    {
        return $this->getUserCacheKey($userId, $suffix);
    }

    public function publicFormatCurrency(float $amount): string
    {
        return $this->formatCurrency($amount);
    }

    public function publicFormatPercentage(float $value, int $decimals = 2): string
    {
        return $this->formatPercentage($value, $decimals);
    }

    public function publicResponse(bool $success, string $message, array $data = []): array
    {
        return $this->response($success, $message, $data);
    }

    public function publicRoundToPenny(float $value): float
    {
        return $this->roundToPenny($value);
    }
}

beforeEach(function () {
    $this->agent = new TestableAgent;
});

describe('getUserCacheKey', function () {
    it('generates cache key with agent name, user id, and suffix', function () {
        $key = $this->agent->publicGetUserCacheKey(123, 'analysis');

        expect($key)->toBe('v1_testableagent_123_analysis');
    });

    it('uses lowercase agent name', function () {
        $key = $this->agent->publicGetUserCacheKey(1, 'test');

        expect($key)->toStartWith('v1_testableagent_');
    });

    it('includes different suffixes correctly', function () {
        $analysisKey = $this->agent->publicGetUserCacheKey(1, 'analysis');
        $recommendationsKey = $this->agent->publicGetUserCacheKey(1, 'recommendations');

        expect($analysisKey)->toBe('v1_testableagent_1_analysis');
        expect($recommendationsKey)->toBe('v1_testableagent_1_recommendations');
    });
});

describe('clearUserCache', function () {
    it('clears cache for default suffixes', function () {
        Cache::shouldReceive('forget')
            ->once()
            ->with('v1_testableagent_1_analysis');
        Cache::shouldReceive('forget')
            ->once()
            ->with('v1_testableagent_1_recommendations');
        Cache::shouldReceive('forget')
            ->once()
            ->with('v1_testableagent_1_scenarios');

        $this->agent->clearUserCache(1);
    });

    it('clears cache for custom suffixes', function () {
        Cache::shouldReceive('forget')
            ->once()
            ->with('v1_testableagent_1_custom1');
        Cache::shouldReceive('forget')
            ->once()
            ->with('v1_testableagent_1_custom2');

        $this->agent->clearUserCache(1, ['custom1', 'custom2']);
    });
});

describe('invalidateUserCache', function () {
    it('clears all default cache keys for user', function () {
        // Pre-populate cache with values
        Cache::put('v1_testableagent_1_analysis', 'test_value');
        Cache::put('v1_testableagent_1_recommendations', 'test_value');
        Cache::put('v1_testableagent_1_scenarios', 'test_value');
        Cache::put('v1_testableagent_1_summary', 'test_value');
        Cache::put('v1_testableagent_1_projection', 'test_value');
        Cache::put('v1_testableagent_analysis_1', 'test_value');

        $this->agent->invalidateUserCache(1);

        // Verify cache was cleared
        expect(Cache::has('v1_testableagent_1_analysis'))->toBeFalse();
        expect(Cache::has('v1_testableagent_1_recommendations'))->toBeFalse();
        expect(Cache::has('v1_testableagent_1_scenarios'))->toBeFalse();
        expect(Cache::has('v1_testableagent_1_summary'))->toBeFalse();
        expect(Cache::has('v1_testableagent_1_projection'))->toBeFalse();
        expect(Cache::has('v1_testableagent_analysis_1'))->toBeFalse();
    });

    it('clears additional keys when provided', function () {
        Cache::put('custom_key_1', 'test_value');
        Cache::put('custom_key_2', 'test_value');

        $this->agent->invalidateUserCache(1, ['custom_key_1', 'custom_key_2']);

        expect(Cache::has('custom_key_1'))->toBeFalse();
        expect(Cache::has('custom_key_2'))->toBeFalse();
    });
});

describe('invalidateCacheForUsers', function () {
    it('invalidates cache for multiple users', function () {
        // Pre-populate cache for multiple users
        Cache::put('v1_testableagent_1_analysis', 'test_value');
        Cache::put('v1_testableagent_2_analysis', 'test_value');
        Cache::put('v1_testableagent_3_analysis', 'test_value');

        $this->agent->invalidateCacheForUsers([1, 2, 3]);

        expect(Cache::has('v1_testableagent_1_analysis'))->toBeFalse();
        expect(Cache::has('v1_testableagent_2_analysis'))->toBeFalse();
        expect(Cache::has('v1_testableagent_3_analysis'))->toBeFalse();
    });

    it('skips null user ids', function () {
        Cache::put('v1_testableagent_1_analysis', 'test_value');
        Cache::put('v1_testableagent_3_analysis', 'test_value');

        // Should not throw an exception when null is in the array
        $this->agent->invalidateCacheForUsers([1, null, 3]);

        expect(Cache::has('v1_testableagent_1_analysis'))->toBeFalse();
        expect(Cache::has('v1_testableagent_3_analysis'))->toBeFalse();
    });
});

describe('formatCurrency', function () {
    it('formats positive amounts with pound sign', function () {
        expect($this->agent->publicFormatCurrency(1234.56))->toBe('£1,235');
    });

    it('formats large amounts with commas', function () {
        expect($this->agent->publicFormatCurrency(1234567.89))->toBe('£1,234,568');
    });

    it('formats zero correctly', function () {
        expect($this->agent->publicFormatCurrency(0))->toBe('£0');
    });

    it('formats negative amounts', function () {
        expect($this->agent->publicFormatCurrency(-500.50))->toBe('£-501');
    });
});

describe('formatPercentage', function () {
    it('formats percentage with percent sign', function () {
        expect($this->agent->publicFormatPercentage(5.5))->toBe('5.50%');
    });

    it('formats zero percentage', function () {
        expect($this->agent->publicFormatPercentage(0))->toBe('0.00%');
    });

    it('formats 100 percent', function () {
        expect($this->agent->publicFormatPercentage(100))->toBe('100.00%');
    });

    it('respects custom decimal places', function () {
        expect($this->agent->publicFormatPercentage(5.555, 1))->toBe('5.6%');
        expect($this->agent->publicFormatPercentage(5.555, 3))->toBe('5.555%');
    });

    it('formats negative percentages', function () {
        expect($this->agent->publicFormatPercentage(-3.5))->toBe('-3.50%');
    });
});

describe('response', function () {
    it('returns success response with correct structure', function () {
        $response = $this->agent->publicResponse(true, 'Success message', ['key' => 'value']);

        expect($response)->toHaveKeys(['success', 'message', 'data', 'timestamp']);
        expect($response['success'])->toBeTrue();
        expect($response['message'])->toBe('Success message');
        expect($response['data'])->toBe(['key' => 'value']);
    });

    it('returns failure response', function () {
        $response = $this->agent->publicResponse(false, 'Error message');

        expect($response['success'])->toBeFalse();
        expect($response['message'])->toBe('Error message');
        expect($response['data'])->toBe([]);
    });

    it('includes ISO 8601 timestamp', function () {
        $response = $this->agent->publicResponse(true, 'Test');

        expect($response['timestamp'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/');
    });

    it('includes empty data array by default', function () {
        $response = $this->agent->publicResponse(true, 'Test');

        expect($response['data'])->toBe([]);
    });
});

describe('roundToPenny', function () {
    it('rounds to two decimal places', function () {
        expect($this->agent->publicRoundToPenny(123.456))->toBe(123.46);
    });

    it('rounds down when third decimal is less than 5', function () {
        expect($this->agent->publicRoundToPenny(123.454))->toBe(123.45);
    });

    it('rounds up when third decimal is 5 or more', function () {
        expect($this->agent->publicRoundToPenny(123.455))->toBe(123.46);
    });

    it('handles already rounded values', function () {
        expect($this->agent->publicRoundToPenny(123.45))->toBe(123.45);
    });

    it('handles whole numbers', function () {
        expect($this->agent->publicRoundToPenny(100.0))->toBe(100.0);
    });

    it('handles negative values', function () {
        expect($this->agent->publicRoundToPenny(-123.456))->toBe(-123.46);
    });

    it('handles zero', function () {
        expect($this->agent->publicRoundToPenny(0))->toBe(0.0);
    });

    it('handles very small values', function () {
        expect($this->agent->publicRoundToPenny(0.001))->toBe(0.0);
        expect($this->agent->publicRoundToPenny(0.005))->toBe(0.01);
    });
});
