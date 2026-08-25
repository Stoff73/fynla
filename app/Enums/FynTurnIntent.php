<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Structured intent of an assistant Fyn turn, persisted as
 * ai_messages.metadata.turn_intent by the emitter that knows what it is
 * emitting — never inferred from content. Read-side consumers branch on
 * this when present and fall back to content heuristics only for legacy
 * rows. Spec:
 * docs/superpowers/specs/2026-07-22-structured-turn-intent-and-gate-facts.md
 */
enum FynTurnIntent: string
{
    case StepPrompt = 'step_prompt';
    case CaptureClarification = 'capture_clarification';
    case InterruptionAnswer = 'interruption_answer';
    case InterruptionOffer = 'interruption_offer';
    case DeferredPromise = 'deferred_promise';
    case DeferredRaise = 'deferred_raise';
    case ResumeGreeting = 'resume_greeting';
    case VerifyPrompt = 'verify_prompt';
    case VerifyAck = 'verify_ack';
    case Celebration = 'celebration';
    case TerminalNote = 'terminal_note';
    case AdviceAnswer = 'advice_answer';
    case CaptureAck = 'capture_ack';
}
