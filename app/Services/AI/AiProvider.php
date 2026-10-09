<?php

declare(strict_types=1);

namespace App\Services\AI;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * The one home for which AI provider Fyn runs on, and what each provider
 * needs: its config block, its chat model, and which wire format it speaks.
 *
 * Providers (chosen in the admin panel, `AdminController::setAiProvider`):
 *  - anthropic: Claude, Anthropic Messages format (`AiToolDefinitions`).
 *  - xai:       Grok, OpenAI Chat Completions format (`XaiToolDefinitions`).
 *  - openai:    GPT, OpenAI Chat Completions format: the same catalogue,
 *               client and request shape as xAI, with OpenAI's key, base URL
 *               and model (`config/services.php` `openai`).
 *
 * GPT-6 Luna runs on Chat Completions: it supports function calling there
 * only with `reasoning_effort: "none"`, which every Chat Completions call
 * with tools sends (https://developers.openai.com/api/docs/models/gpt-6-luna,
 * https://developers.openai.com/api/docs/guides/latest-model).
 */
final class AiProvider
{
    public const ANTHROPIC = 'anthropic';

    public const XAI = 'xai';

    public const OPENAI = 'openai';

    /** @var list<string> */
    public const ALL = [self::ANTHROPIC, self::XAI, self::OPENAI];

    /** Names shown in the admin panel. */
    private const NAMES = [
        self::ANTHROPIC => 'Anthropic Claude',
        self::XAI => 'xAI Grok',
        self::OPENAI => 'OpenAI GPT',
    ];

    /** Used when a provider's config block names no chat model. */
    private const DEFAULT_CHAT_MODELS = [
        self::ANTHROPIC => 'claude-haiku-4-5-20251001',
        self::XAI => 'grok-4.3',
        self::OPENAI => 'gpt-6-luna',
    ];

    /**
     * The active provider.
     *
     * Lookup order (first match wins):
     *  1. Versioned key `ai_provider:v{N}` where N is `ai_provider_version`
     *     (the admin toggle bumps the version, so an in-flight reader never
     *     sees a torn write; S0.11.4).
     *  2. Legacy unversioned `ai_provider` key (tests and evals write it).
     *  3. The `services.ai_provider` config default (env `AI_PROVIDER`).
     *
     * A chat loop resolves this ONCE at its entry and reuses the snapshot, so
     * a mid-stream toggle cannot swap the wire format inside one turn
     * (INV-2.9.4).
     */
    public static function active(): string
    {
        $default = (string) config('services.ai_provider', self::ANTHROPIC);
        $version = (int) Cache::get('ai_provider_version', 0);

        if ($version > 0) {
            return (string) Cache::get("ai_provider:v{$version}", $default);
        }

        return (string) Cache::get('ai_provider', $default);
    }

    /**
     * Make the provider active (the admin toggle) and return the new version.
     *
     * S0.11.4 — bump the version counter and write the new value under the
     * versioned key. In-flight chat loops captured the OLD version's value at
     * their entry, so they finish on their original provider; new requests
     * see the new provider atomically. The legacy unversioned key is written
     * too, for the evals and tests that read or write it directly.
     */
    public static function switchTo(string $provider): int
    {
        $version = (int) Cache::get('ai_provider_version', 0) + 1;

        Cache::forever("ai_provider:v{$version}", $provider);
        Cache::forever('ai_provider_version', $version);
        Cache::forever('ai_provider', $provider);

        return $version;
    }

    /**
     * Whether the provider speaks the OpenAI Chat Completions format (tool
     * catalogue `{type: function, function: {...}}`, `tool_calls`, `role:
     * tool` results). xAI and OpenAI do; Anthropic does not.
     */
    public static function speaksOpenAiFormat(string $provider): bool
    {
        return $provider === self::XAI || $provider === self::OPENAI;
    }

    /**
     * The tool-catalogue format for a provider: 'xai' (OpenAI function
     * calling, the corpus `*.xai.md` schemas) or 'anthropic'.
     */
    public static function toolFormat(string $provider): string
    {
        return self::speaksOpenAiFormat($provider) ? self::XAI : self::ANTHROPIC;
    }

    /**
     * The OpenAI-format provider that owns the connection: OpenAI when it is
     * the given provider, otherwise xAI (Anthropic has no OpenAI connection).
     */
    public static function openAiFormatConnection(string $provider): string
    {
        return $provider === self::OPENAI ? self::OPENAI : self::XAI;
    }

    /**
     * The OpenAI-format tool list as the provider accepts it.
     *
     * OpenAI's strict function calling needs `additionalProperties: false` on
     * every object and every property listed in `required`
     * (https://developers.openai.com/api/docs/guides/function-calling, "Strict
     * mode"); it rejects the whole request otherwise. xAI accepts the corpus's
     * strict tools with optional properties, so on OpenAI such a tool is sent
     * with `strict: false` (Chat Completions' default) and keeps its schema
     * and its optional fields. Every other tool stays strict. xAI is unchanged.
     *
     * @param  list<array<string, mixed>>  $tools
     * @return list<array<string, mixed>>
     */
    public static function toolsFor(string $provider, array $tools): array
    {
        if ($provider !== self::OPENAI) {
            return $tools;
        }

        return array_map(static function (array $tool): array {
            if (($tool['function']['strict'] ?? false) === true
                && ! self::meetsOpenAiStrictRules($tool['function']['parameters'] ?? [])) {
                $tool['function']['strict'] = false;
            }

            return $tool;
        }, $tools);
    }

    /**
     * Whether every object in a JSON schema closes its properties and
     * requires all of them (OpenAI's strict-mode rules).
     */
    private static function meetsOpenAiStrictRules(mixed $schema): bool
    {
        if (! is_array($schema)) {
            return true;
        }

        if (isset($schema['properties']) && is_array($schema['properties'])) {
            $required = (array) ($schema['required'] ?? []);

            if (($schema['additionalProperties'] ?? null) !== false
                || array_diff(array_keys($schema['properties']), $required) !== []) {
                return false;
            }
        }

        foreach ($schema as $child) {
            if (is_array($child) && ! self::meetsOpenAiStrictRules($child)) {
                return false;
            }
        }

        return true;
    }

    public static function name(string $provider): string
    {
        return self::NAMES[$provider] ?? $provider;
    }

    /** A value from the provider's `config/services.php` block. */
    public static function setting(string $provider, string $key, mixed $default = null): mixed
    {
        return config("services.{$provider}.{$key}", $default);
    }

    public static function isConfigured(string $provider): bool
    {
        return ! empty(self::setting($provider, 'api_key'));
    }

    public static function chatModel(string $provider): string
    {
        return (string) (self::setting($provider, 'chat_model') ?: self::defaultChatModel($provider));
    }

    public static function advancedChatModel(string $provider): string
    {
        return (string) (self::setting($provider, 'advanced_chat_model') ?: self::chatModel($provider));
    }

    public static function visionModel(string $provider): string
    {
        return (string) (self::setting($provider, 'vision_model') ?: self::chatModel($provider));
    }

    public static function defaultChatModel(string $provider): string
    {
        return self::DEFAULT_CHAT_MODELS[$provider] ?? self::DEFAULT_CHAT_MODELS[self::ANTHROPIC];
    }

    /**
     * The OpenAI-format connection Fyn's one-shot helpers (typed form fill,
     * conversation summary, fact synthesis, document extraction) use: OpenAI
     * while it is the active provider, otherwise xAI, as before OpenAI was
     * added.
     */
    public static function helperConnection(): string
    {
        return self::openAiFormatConnection(self::active());
    }

    /**
     * Request options a provider needs on every Chat Completions call.
     *
     * OpenAI: `store: false`, so OpenAI does not keep users' conversations
     * (CSJ 2026-10-08); `reasoning_effort: "none"`, which GPT-6 Luna needs to
     * call functions on Chat Completions (its default is "medium";
     * https://developers.openai.com/api/docs/models/gpt-6-luna). xAI: none.
     *
     * @return array<string, mixed>
     */
    public static function chatCompletionsOptions(string $provider): array
    {
        return $provider === self::OPENAI
            ? ['store' => false, 'reasoning_effort' => 'none']
            : [];
    }

    /**
     * One non-streamed Chat Completions request to an OpenAI-format
     * connection, with that provider's options. The body's `model` defaults
     * to the connection's vision model.
     *
     * @param  array<string, mixed>  $body
     */
    public static function postChatCompletion(string $connection, array $body, int $timeoutSeconds): Response
    {
        $body['model'] ??= self::visionModel($connection);

        return Http::withHeaders([
            'Authorization' => 'Bearer '.self::setting($connection, 'api_key'),
            'Content-Type' => 'application/json',
        ])
            ->timeout($timeoutSeconds)
            ->post(self::chatCompletionsUrl($connection), array_merge($body, self::chatCompletionsOptions($connection)));
    }

    /** The Chat Completions endpoint of an OpenAI-format provider. */
    public static function chatCompletionsUrl(string $provider): string
    {
        $connection = self::openAiFormatConnection($provider);

        return rtrim((string) self::setting($connection, 'base_url'), '/').'/chat/completions';
    }
}
