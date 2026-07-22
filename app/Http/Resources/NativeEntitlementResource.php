<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Data\Billing\ResolvedEntitlement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class NativeEntitlementResource extends JsonResource
{
    /**
     * @param  array<string, mixed>  $capabilities
     * @param  array<string, int|null>  $limits
     */
    public function __construct(
        ResolvedEntitlement $entitlement,
        private readonly array $capabilities,
        private readonly array $limits,
    ) {
        parent::__construct($entitlement);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ResolvedEntitlement $entitlement */
        $entitlement = $this->resource;

        return [
            'tier' => $entitlement->tier,
            'provider' => $entitlement->provider,
            'status' => $entitlement->status,
            'renews' => $entitlement->renews,
            'current_period_end' => $entitlement->periodEndsAt?->toISOString(),
            'capabilities' => $this->capabilities,
            'limits' => $this->limits,
            'billing_management' => match ($entitlement->provider) {
                'apple' => 'apple',
                'revolut' => 'web',
                default => 'none',
            },
        ];
    }
}
