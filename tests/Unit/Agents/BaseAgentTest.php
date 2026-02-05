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

    public function publicFormatCurrency(float $amount, int $decimals = 2): string
    {
        return $this->formatCurrency($amount, $decimals);
    }

    public function publicFormatPercentage(float $value, int $decimals = 2): string
    {
        return $this->formatPercentage($value, $decimals);
    }

    public function publicCalculatePercentageChange(float $oldValue, float $newValue): float
    {
        return $this->calculatePercentageChange($oldValue, $newValue);
    }

    public function publicCalculateCompoundGrowth(float $principal, float $rate, int $years): float
    {
        return $this->calculateCompoundGrowth($principal, $rate, $years);
    }

    public function publicCalculatePresentValue(float $futureValue, float $discountRate, int $years): float
    {
        return $this->calculatePresentValue($futureValue, $discountRate, $years);
    }

    public function publicResponse(bool $success, string $message, array $data = []): array
    {
        return $this->response($success, $message, $data);
    }

    public function publicGetCurrentTaxYear(): string
    {
        return $this->getCurrentTaxYear();
    }

    public function publicValidateRequired(array $data, array $required): void
    {
        $this->validateRequired($data, $required);
    }

    public function publicCalculateAge(\DateTimeInterface|string $dateOfBirth): int
    {
        return $this->calculateAge($dateOfBirth);
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

        expect($key)->toBe('testableagent_123_analysis');
    });

    it('uses lowercase agent name', function () {
        $key = $this->agent->publicGetUserCacheKey(1, 'test');

        expect($key)->toStartWith('testableagent_');
    });

    it('includes different suffixes correctly', function () {
        $analysisKey = $this->agent->publicGetUserCacheKey(1, 'analysis');
        $recommendationsKey = $this->agent->publicGetUserCacheKey(1, 'recommendations');

        expect($analysisKey)->toBe('testableagent_1_analysis');
        expect($recommendationsKey)->toBe('testableagent_1_recommendations');
    });
});

describe('clearUserCache', function () {
    it('clears cache for default suffixes', function () {
        Cache::shouldReceive('forget')
            ->once()
            ->with('testableagent_1_analysis');
        Cache::shouldReceive('forget')
            ->once()
            ->with('testableagent_1_recommendations');
        Cache::shouldReceive('forget')
            ->once()
            ->with('testableagent_1_scenarios');

        $this->agent->clearUserCache(1);
    });

    it('clears cache for custom suffixes', function () {
        Cache::shouldReceive('forget')
            ->once()
            ->with('testableagent_1_custom1');
        Cache::shouldReceive('forget')
            ->once()
            ->with('testableagent_1_custom2');

        $this->agent->clearUserCache(1, ['custom1', 'custom2']);
    });
});

describe('invalidateUserCache', function () {
    it('clears all default cache keys for user', function () {
        // Pre-populate cache with values
        Cache::put('testableagent_1_analysis', 'test_value');
        Cache::put('testableagent_1_recommendations', 'test_value');
        Cache::put('testableagent_1_scenarios', 'test_value');
        Cache::put('testableagent_1_summary', 'test_value');
        Cache::put('testableagent_1_projection', 'test_value');
        Cache::put('testableagent_analysis_1', 'test_value');

        $this->agent->invalidateUserCache(1);

        // Verify cache was cleared
        expect(Cache::has('testableagent_1_analysis'))->toBeFalse();
        expect(Cache::has('testableagent_1_recommendations'))->toBeFalse();
        expect(Cache::has('testableagent_1_scenarios'))->toBeFalse();
        expect(Cache::has('testableagent_1_summary'))->toBeFalse();
        expect(Cache::has('testableagent_1_projection'))->toBeFalse();
        expect(Cache::has('testableagent_analysis_1'))->toBeFalse();
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
        Cache::put('testableagent_1_analysis', 'test_value');
        Cache::put('testableagent_2_analysis', 'test_value');
        Cache::put('testableagent_3_analysis', 'test_value');

        $this->agent->invalidateCacheForUsers([1, 2, 3]);

        expect(Cache::has('testableagent_1_analysis'))->toBeFalse();
        expect(Cache::has('testableagent_2_analysis'))->toBeFalse();
        expect(Cache::has('testableagent_3_analysis'))->toBeFalse();
    });

    it('skips null user ids', function () {
        Cache::put('testableagent_1_analysis', 'test_value');
        Cache::put('testableagent_3_analysis', 'test_value');

        // Should not throw an exception when null is in the array
        $this->agent->invalidateCacheForUsers([1, null, 3]);

        expect(Cache::has('testableagent_1_analysis'))->toBeFalse();
        expect(Cache::has('testableagent_3_analysis'))->toBeFalse();
    });
});

describe('formatCurrency', function () {
    it('formats positive amounts with pound sign', function () {
        expect($this->agent->publicFormatCurrency(1234.56))->toBe('£1,234.56');
    });

    it('formats large amounts with commas', function () {
        expect($this->agent->publicFormatCurrency(1234567.89))->toBe('£1,234,567.89');
    });

    it('formats zero correctly', function () {
        expect($this->agent->publicFormatCurrency(0))->toBe('£0.00');
    });

    it('respects custom decimal places', function () {
        expect($this->agent->publicFormatCurrency(1234.567, 0))->toBe('£1,235');
        expect($this->agent->publicFormatCurrency(1234.567, 3))->toBe('£1,234.567');
    });

    it('formats negative amounts', function () {
        expect($this->agent->publicFormatCurrency(-500.50))->toBe('£-500.50');
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

describe('calculatePercentageChange', function () {
    it('calculates positive percentage change', function () {
        $change = $this->agent->publicCalculatePercentageChange(100, 150);

        expect($change)->toBe(50.0);
    });

    it('calculates negative percentage change', function () {
        $change = $this->agent->publicCalculatePercentageChange(100, 75);

        expect($change)->toBe(-25.0);
    });

    it('returns zero when old value is zero', function () {
        $change = $this->agent->publicCalculatePercentageChange(0, 100);

        expect($change)->toBe(0.0);
    });

    it('returns zero when values are equal', function () {
        $change = $this->agent->publicCalculatePercentageChange(100, 100);

        expect($change)->toBe(0.0);
    });

    it('handles decimal values', function () {
        $change = $this->agent->publicCalculatePercentageChange(50.0, 75.0);

        expect($change)->toBe(50.0);
    });
});

describe('calculateCompoundGrowth', function () {
    it('calculates compound growth for one year', function () {
        $result = $this->agent->publicCalculateCompoundGrowth(1000, 0.05, 1);

        expect($result)->toBe(1050.0);
    });

    it('calculates compound growth for multiple years', function () {
        $result = $this->agent->publicCalculateCompoundGrowth(1000, 0.05, 2);

        expect($result)->toBe(1102.5);
    });

    it('returns principal when years is zero', function () {
        $result = $this->agent->publicCalculateCompoundGrowth(1000, 0.05, 0);

        expect($result)->toBe(1000.0);
    });

    it('returns principal when rate is zero', function () {
        $result = $this->agent->publicCalculateCompoundGrowth(1000, 0, 5);

        expect($result)->toBe(1000.0);
    });

    it('handles 10 year compound growth', function () {
        $result = $this->agent->publicCalculateCompoundGrowth(10000, 0.07, 10);

        // 10000 * (1.07)^10 = 19671.51...
        expect(round($result, 2))->toBe(19671.51);
    });
});

describe('calculatePresentValue', function () {
    it('calculates present value correctly', function () {
        $result = $this->agent->publicCalculatePresentValue(1050, 0.05, 1);

        expect($result)->toBe(1000.0);
    });

    it('calculates present value for multiple years', function () {
        $result = $this->agent->publicCalculatePresentValue(1102.5, 0.05, 2);

        expect($result)->toBe(1000.0);
    });

    it('returns future value when years is zero', function () {
        $result = $this->agent->publicCalculatePresentValue(1000, 0.05, 0);

        expect($result)->toBe(1000.0);
    });

    it('returns future value when discount rate is zero', function () {
        $result = $this->agent->publicCalculatePresentValue(1000, 0, 5);

        expect($result)->toBe(1000.0);
    });

    it('calculates present value for 10 years', function () {
        $result = $this->agent->publicCalculatePresentValue(19671.51, 0.07, 10);

        // Should be approximately 10000
        expect(round($result, 0))->toBe(10000.0);
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

describe('getCurrentTaxYear', function () {
    it('returns tax year in correct format', function () {
        $taxYear = $this->agent->publicGetCurrentTaxYear();

        // Should match format like "2024/25" or "2025/26"
        expect($taxYear)->toMatch('/^\d{4}\/\d{2}$/');
    });

    it('returns correct tax year for April onwards', function () {
        // Travel to June 2025
        $this->travelTo(Carbon\Carbon::create(2025, 6, 15));

        $taxYear = $this->agent->publicGetCurrentTaxYear();

        expect($taxYear)->toBe('2025/26');
    });

    it('returns previous tax year for January-March', function () {
        // Travel to February 2025
        $this->travelTo(Carbon\Carbon::create(2025, 2, 15));

        $taxYear = $this->agent->publicGetCurrentTaxYear();

        expect($taxYear)->toBe('2024/25');
    });

    it('returns previous tax year for early April before 6th', function () {
        // Travel to April 5, 2025
        $this->travelTo(Carbon\Carbon::create(2025, 4, 5));

        $taxYear = $this->agent->publicGetCurrentTaxYear();

        expect($taxYear)->toBe('2024/25');
    });

    it('returns new tax year on April 6th', function () {
        // Travel to April 6, 2025
        $this->travelTo(Carbon\Carbon::create(2025, 4, 6));

        $taxYear = $this->agent->publicGetCurrentTaxYear();

        expect($taxYear)->toBe('2025/26');
    });
});

describe('validateRequired', function () {
    it('passes when all required fields are present', function () {
        $data = ['field1' => 'value1', 'field2' => 'value2'];

        // Should not throw an exception
        $this->agent->publicValidateRequired($data, ['field1', 'field2']);

        expect(true)->toBeTrue();
    });

    it('throws exception when required field is missing', function () {
        $data = ['field1' => 'value1'];

        expect(fn () => $this->agent->publicValidateRequired($data, ['field1', 'field2']))
            ->toThrow(\InvalidArgumentException::class, 'Missing required parameter: field2');
    });

    it('throws exception with first missing field name', function () {
        $data = [];

        expect(fn () => $this->agent->publicValidateRequired($data, ['field1', 'field2']))
            ->toThrow(\InvalidArgumentException::class, 'Missing required parameter: field1');
    });

    it('passes with empty required array', function () {
        $data = ['field1' => 'value1'];

        $this->agent->publicValidateRequired($data, []);

        expect(true)->toBeTrue();
    });

    it('passes with empty data and empty required', function () {
        $this->agent->publicValidateRequired([], []);

        expect(true)->toBeTrue();
    });
});

describe('calculateAge', function () {
    it('calculates age from date string', function () {
        // Use a date relative to current date for testing
        $now = new DateTime;
        $yearsAgo = 35;
        $dob = (clone $now)->modify("-{$yearsAgo} years")->format('Y-m-d');

        $age = $this->agent->publicCalculateAge($dob);

        expect($age)->toBe($yearsAgo);
    });

    it('calculates age from DateTime object', function () {
        $now = new DateTime;
        $yearsAgo = 40;
        $dob = (clone $now)->modify("-{$yearsAgo} years");

        $age = $this->agent->publicCalculateAge($dob);

        expect($age)->toBe($yearsAgo);
    });

    it('calculates age correctly before birthday in current year', function () {
        $now = new DateTime;
        // Create a date that is in the future this year
        $futureMonth = ((int) $now->format('n')) + 3;
        if ($futureMonth > 12) {
            $futureMonth = $futureMonth - 12;
        }
        $year = (int) $now->format('Y') - 35;
        $dob = sprintf('%04d-%02d-15', $year, $futureMonth);

        $age = $this->agent->publicCalculateAge($dob);

        // Should be one year less since birthday hasn't happened yet
        expect($age)->toBe(34);
    });

    it('calculates age correctly on birthday', function () {
        $now = new DateTime;
        $yearsAgo = 25;
        // Exact same date, years ago
        $dob = (clone $now)->modify("-{$yearsAgo} years")->format('Y-m-d');

        $age = $this->agent->publicCalculateAge($dob);

        expect($age)->toBe($yearsAgo);
    });

    it('handles recent birth dates', function () {
        $now = new DateTime;
        // Exactly 1 year ago
        $dob = (clone $now)->modify('-1 year')->format('Y-m-d');

        $age = $this->agent->publicCalculateAge($dob);

        expect($age)->toBe(1);
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
