<?php

declare(strict_types=1);

namespace App\Http\Requests\AI;

use App\Services\Onboarding\CaptureForms;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Validator;

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
            'message' => ['required_without:form', 'nullable', 'string', 'max:2000'],
            'current_route' => ['nullable', 'string', 'max:255'],
            // A structured capture-form answer (CaptureForms). Shape only —
            // business rules live in the store the director writes through.
            'form' => ['sometimes', 'array'],
            'form.name' => ['required_with:form', 'string', 'in:'.implode(',', CaptureForms::names())],
            // Not `required`: an allow_empty form saved with nothing chosen posts
            // `answers: {}` and that IS the answer (Laura, 2026-09-18: the
            // investments form). `required` treats an empty array as missing;
            // the key's presence is checked in withValidator instead.
            'form.answers' => ['array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $form = $this->input('form');
            if (! is_array($form) || $validator->errors()->has('form.name')) {
                return;
            }
            if (! array_key_exists('answers', $form)) {
                $validator->errors()->add('form.answers', 'The form answers are required.');

                return;
            }
            $schema = CaptureForms::schema((string) ($form['name'] ?? ''));
            if ($schema === null) {
                return;
            }
            $knownKinds = array_column($schema['kinds'], 'key');
            if (! empty($schema['lead_fields'])) {
                $knownKinds[] = CaptureForms::LEAD;
            }
            foreach (array_keys((array) ($form['answers'] ?? [])) as $kind) {
                if (! in_array($kind, $knownKinds, true)) {
                    $validator->errors()->add('form.answers.'.$kind, 'Unknown kind.');
                }
            }
            $answers = (array) ($form['answers'] ?? []);
            $rules = array_filter(
                CaptureForms::rules((string) $form['name']),
                static fn (string $key): bool => array_key_exists(strtok($key, '.'), $answers),
                ARRAY_FILTER_USE_KEY,
            );
            $nested = ValidatorFacade::make($answers, $rules);
            foreach ($nested->errors()->toArray() as $key => $messages) {
                foreach ($messages as $message) {
                    $validator->errors()->add('form.answers.'.$key, $message);
                }
            }
        });
    }
}
