<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Persona Split
    |--------------------------------------------------------------------------
    | Master switch for the persona-split architecture. When false,
    | AiChatController routes through CoordinatingAgent::chat() exactly
    | as today. When true, post-onboarding turns route through
    | FynPersonaOrchestrator. Onboarding turns always go through
    | OnboardingChatDirector regardless of this flag.
    */
    'persona_split_enabled' => env('FYN_PERSONA_SPLIT', false),

    /*
    |--------------------------------------------------------------------------
    | Classifier Fast-Path
    |--------------------------------------------------------------------------
    | Separate kill switch for the rule-based classifier fast-path within
    | the orchestrator. Only meaningful when persona_split_enabled is true.
    | Disable to force every turn through advice Fyn first and let the
    | delegate_to_capture tool call drive persona transitions.
    */
    'classifier_fast_path_enabled' => env('FYN_CLASSIFIER_FAST_PATH', true),

    /*
    |--------------------------------------------------------------------------
    | Capture Mode Guardrails
    |--------------------------------------------------------------------------
    | Maximum user turns the orchestrator will allow in capturing state
    | before force-returning to advice with a fallback message. Prevents
    | capture lock-in when data-capture Fyn fails to emit capture_complete.
    */
    'capture_max_turns' => (int) env('FYN_CAPTURE_MAX_TURNS', 6),

    /*
    |--------------------------------------------------------------------------
    | Cancel Patterns
    |--------------------------------------------------------------------------
    | Regex patterns that flip the orchestrator back to advice when the user
    | types one of these at the start of a capturing-state message.
    | Evaluated before data-capture Fyn is invoked — no LLM call on cancel.
    */
    'cancel_patterns' => [
        '/^(stop|cancel|never\s*mind|forget\s*it|nah|skip)\b/i',
    ],
];
