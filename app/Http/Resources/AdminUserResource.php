<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'surname' => $this->surname,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
            'is_preview_user' => $this->is_preview_user,
            'is_admin' => $this->is_admin,
            'is_advisor' => $this->is_advisor,
            'date_of_birth' => $this->date_of_birth,
            'marital_status' => $this->marital_status,
            'life_stage' => $this->life_stage,
            'onboarding_completed' => $this->onboarding_completed,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'role' => $this->when($this->relationLoaded('role'), $this->role),
            'subscription' => $this->when($this->relationLoaded('subscription'), $this->subscription),
            'spouse' => $this->when($this->relationLoaded('spouse'), function () {
                return $this->spouse ? [
                    'id' => $this->spouse->id,
                    'first_name' => $this->spouse->first_name,
                    'surname' => $this->spouse->surname,
                    'email' => $this->spouse->email,
                ] : null;
            }),
        ];
    }
}
