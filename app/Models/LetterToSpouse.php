<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LetterToSpouse extends Model
{
    use HasFactory;

    protected $table = 'letters_to_spouse';

    protected $hidden = [
        'password_manager_info',
        'bank_accounts_info',
        'cryptocurrency_info',
        'investment_accounts_info',
    ];

    protected $fillable = [
        'user_id',
        // Part 1: What to do immediately
        'immediate_actions',
        'executor_name',
        'executor_contact',
        'attorney_name',
        'attorney_contact',
        'financial_advisor_name',
        'financial_advisor_contact',
        'accountant_name',
        'accountant_contact',
        'immediate_funds_access',
        'employer_hr_contact',
        'employer_benefits_info',
        // Part 2: Accessing and managing accounts
        'password_manager_info',
        'phone_plan_info',
        'bank_accounts_info',
        'investment_accounts_info',
        'insurance_policies_info',
        'real_estate_info',
        'vehicles_info',
        'valuable_items_info',
        'cryptocurrency_info',
        'liabilities_info',
        'recurring_bills_info',
        // Part 3: Long-term plans
        'estate_documents_location',
        'beneficiary_info',
        'children_education_plans',
        'financial_guidance',
        'social_security_info',
        // Part 4: Funeral and final wishes
        'funeral_preference',
        'funeral_service_details',
        'obituary_wishes',
        'additional_wishes',
        'additional_boxes',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'additional_boxes' => 'array',
    ];

    /**
     * Get the user that owns the letter
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
