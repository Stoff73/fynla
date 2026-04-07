<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\User;
use App\Services\UserProfile\LetterToSpouseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LetterToSpouseController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly LetterToSpouseService $letterService
    ) {}

    /**
     * Check if user has a letter with user-entered content (not auto-generated)
     */
    public function exists(Request $request): JsonResponse
    {
        $user = $request->user();
        $letter = $user->letterToSpouse;

        // No letter record at all
        if (! $letter) {
            return response()->json([
                'success' => true,
                'has_content' => false,
            ]);
        }

        // Check if any user-editable fields have been filled in
        // These are fields that wouldn't be auto-populated
        $userEditableFields = [
            'executor_name',
            'executor_contact',
            'attorney_name',
            'attorney_contact',
            'financial_advisor_name',
            'financial_advisor_contact',
            'accountant_name',
            'accountant_contact',
            'password_manager_info',
            'estate_documents_location',
            'vehicles_info',
            'cryptocurrency_info',
            'funeral_service_details',
            'obituary_wishes',
            'additional_wishes',
        ];

        $hasUserContent = false;
        foreach ($userEditableFields as $field) {
            $value = $letter->$field;
            if ($value && is_string($value) && trim($value) !== '') {
                $hasUserContent = true;
                break;
            }
        }

        // Also check if funeral preference has been explicitly set
        if ($letter->funeral_preference && $letter->funeral_preference !== 'not_specified') {
            $hasUserContent = true;
        }

        return response()->json([
            'success' => true,
            'has_content' => $hasUserContent,
        ]);
    }

    /**
     * Get current user's letter
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $letter = $this->letterService->getOrCreateLetter($user);

        return response()->json([
            'success' => true,
            'data' => $letter,
        ]);
    }

    /**
     * Get spouse's letter (read-only for current user)
     */
    public function showSpouse(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->spouse_id) {
            return response()->json([
                'success' => false,
                'message' => 'No spouse linked to your account',
            ], 404);
        }

        $spouse = User::find($user->spouse_id);

        if (! $spouse) {
            return response()->json([
                'success' => false,
                'message' => 'Spouse not found',
            ], 404);
        }

        $letter = $this->letterService->getOrCreateLetter($spouse);

        return response()->json([
            'success' => true,
            'data' => $letter,
            'spouse_name' => $spouse->name,
            'read_only' => true,
        ]);
    }

    /**
     * Update current user's letter
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            // Part 1
            'immediate_actions' => 'nullable|string|max:10000',
            'executor_name' => 'nullable|string|max:255',
            'executor_contact' => 'nullable|string|max:255',
            'attorney_name' => 'nullable|string|max:255',
            'attorney_contact' => 'nullable|string|max:255',
            'financial_advisor_name' => 'nullable|string|max:255',
            'financial_advisor_contact' => 'nullable|string|max:255',
            'accountant_name' => 'nullable|string|max:255',
            'accountant_contact' => 'nullable|string|max:255',
            'immediate_funds_access' => 'nullable|string|max:10000',
            'employer_hr_contact' => 'nullable|string|max:255',
            'employer_benefits_info' => 'nullable|string|max:10000',
            // Part 2
            'password_manager_info' => 'nullable|string|max:10000',
            'phone_plan_info' => 'nullable|string|max:10000',
            'bank_accounts_info' => 'nullable|string|max:10000',
            'investment_accounts_info' => 'nullable|string|max:10000',
            'insurance_policies_info' => 'nullable|string|max:10000',
            'real_estate_info' => 'nullable|string|max:10000',
            'vehicles_info' => 'nullable|string|max:10000',
            'valuable_items_info' => 'nullable|string|max:10000',
            'cryptocurrency_info' => 'nullable|string|max:10000',
            'liabilities_info' => 'nullable|string|max:10000',
            'recurring_bills_info' => 'nullable|string|max:10000',
            // Part 3
            'estate_documents_location' => 'nullable|string|max:10000',
            'beneficiary_info' => 'nullable|string|max:10000',
            'children_education_plans' => 'nullable|string|max:10000',
            'financial_guidance' => 'nullable|string|max:10000',
            'social_security_info' => 'nullable|string|max:10000',
            // Part 4
            'funeral_preference' => 'nullable|in:burial,cremation,not_specified',
            'funeral_service_details' => 'nullable|string|max:10000',
            'obituary_wishes' => 'nullable|string|max:10000',
            'additional_wishes' => 'nullable|string|max:10000',
            // Additional boxes
            'additional_boxes' => 'nullable|array|max:10',
            'additional_boxes.*.title' => 'required|string|max:255',
            'additional_boxes.*.content' => 'required|string|max:10000',
        ]);

        $letter = $this->letterService->updateLetter($user, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Letter to spouse updated successfully',
            'data' => $letter,
        ]);
    }
}
