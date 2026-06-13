<?php

declare(strict_types=1);

namespace App\Services\AI\Actions;

use InvalidArgumentException;
use LogicException;

/**
 * Immutable typed action (CoALA Phase 5 item 3 — FR-M1, plan §"Action space").
 *
 * PHP has no sum types, so each variant is built through a named constructor
 * and carries its fields in a single readonly payload. Typed accessors guard
 * the variant: reading `surface()` off a non-ground action is a programming
 * error (LogicException), not a silent null — the dispatcher must never confuse
 * a retrieve for a ground.
 *
 * Variants (plan §"Action space"):
 *   reason   { prompt_template_id, working_memory_fields[] }
 *   retrieve { store: 'episodic' | 'semantic', query, filters }
 *   learn    { store, payload }
 *   ground   { surface, args }      // the external/write boundary
 *   no_action
 */
final class Action
{
    /** Retrieve targets are pinned to the two recall stores (plan line 299). */
    private const RETRIEVE_STORES = ['episodic', 'semantic'];

    /**
     * @param  array<string, mixed>  $payload
     */
    private function __construct(
        public readonly ActionType $type,
        public readonly array $payload,
    ) {}

    public static function reason(string $promptTemplateId, array $workingMemoryFields = []): self
    {
        if ($promptTemplateId === '') {
            throw new InvalidArgumentException('reason action requires a prompt_template_id');
        }

        return new self(ActionType::Reason, [
            'prompt_template_id' => $promptTemplateId,
            'working_memory_fields' => array_values($workingMemoryFields),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public static function retrieve(string $store, string $query, array $filters = []): self
    {
        if (! in_array($store, self::RETRIEVE_STORES, true)) {
            throw new InvalidArgumentException(
                'retrieve store must be one of ['.implode(', ', self::RETRIEVE_STORES)."], got '{$store}'"
            );
        }

        return new self(ActionType::Retrieve, [
            'store' => $store,
            'query' => $query,
            'filters' => $filters,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function learn(string $store, array $payload): self
    {
        if ($store === '') {
            throw new InvalidArgumentException('learn action requires a store');
        }

        return new self(ActionType::Learn, [
            'store' => $store,
            'payload' => $payload,
        ]);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public static function ground(string $surface, array $args = []): self
    {
        if ($surface === '') {
            throw new InvalidArgumentException('ground action requires a surface');
        }

        return new self(ActionType::Ground, [
            'surface' => $surface,
            'args' => $args,
        ]);
    }

    public static function noAction(): self
    {
        return new self(ActionType::NoAction, []);
    }

    public function isGround(): bool
    {
        return $this->type === ActionType::Ground;
    }

    public function isRetrieve(): bool
    {
        return $this->type === ActionType::Retrieve;
    }

    public function surface(): string
    {
        $this->assertType(ActionType::Ground, 'surface');

        return $this->payload['surface'];
    }

    /**
     * @return array<string, mixed>
     */
    public function args(): array
    {
        $this->assertType(ActionType::Ground, 'args');

        return $this->payload['args'];
    }

    public function store(): string
    {
        if ($this->type !== ActionType::Retrieve && $this->type !== ActionType::Learn) {
            throw new LogicException("store() is only valid for retrieve/learn actions, not {$this->type->value}");
        }

        return $this->payload['store'];
    }

    public function query(): string
    {
        $this->assertType(ActionType::Retrieve, 'query');

        return $this->payload['query'];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $this->assertType(ActionType::Retrieve, 'filters');

        return $this->payload['filters'];
    }

    public function promptTemplateId(): string
    {
        $this->assertType(ActionType::Reason, 'promptTemplateId');

        return $this->payload['prompt_template_id'];
    }

    /**
     * @return list<string>
     */
    public function workingMemoryFields(): array
    {
        $this->assertType(ActionType::Reason, 'workingMemoryFields');

        return $this->payload['working_memory_fields'];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $this->assertType(ActionType::Learn, 'payload');

        return $this->payload['payload'];
    }

    private function assertType(ActionType $expected, string $accessor): void
    {
        if ($this->type !== $expected) {
            throw new LogicException(
                "{$accessor}() is only valid for {$expected->value} actions, not {$this->type->value}"
            );
        }
    }
}
