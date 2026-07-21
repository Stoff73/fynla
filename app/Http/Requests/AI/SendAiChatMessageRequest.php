<?php

declare(strict_types=1);

namespace App\Http\Requests\AI;

use Illuminate\Foundation\Http\FormRequest;

/**
 * M18 — extracted from inline AiChatController::sendMessage validation.
 * Centralises the message + current_route rules so the contract is
 * documented in one place; conversation ownership is still enforced via
 * AiConversation::forUser($user->id)->findOrFail($id) in the controller.
 */
final class SendAiChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:2000'],
            'current_route' => ['nullable', 'string', 'max:255'],
        ];
    }
}
