<?php

declare(strict_types=1);

use App\Models\ProposedProcedureAmendment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('lists pending amendments and approves one for an admin', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    $a = ProposedProcedureAmendment::create([
        'procedure_id' => 'recommendation-routing', 'problem_observed' => 'looped',
        'proposed_fix' => 'add guard', 'rationale' => 'x', 'failure_type' => 'reasoning_loop_exhaustion',
        'status' => 'pending',
    ]);

    $this->getJson('/api/admin/procedure-amendments')
        ->assertOk()
        ->assertJsonFragment(['procedure_id' => 'recommendation-routing']);

    $this->patchJson("/api/admin/procedure-amendments/{$a->id}", ['action' => 'approve'])
        ->assertOk();

    expect($a->fresh()->status)->toBe('approved')
        ->and($a->fresh()->reviewed_by)->toBe($admin->id);
});

it('rejects a pending amendment for an admin', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    $a = ProposedProcedureAmendment::create([
        'procedure_id' => 'recommendation-routing', 'problem_observed' => 'wrong branch',
        'proposed_fix' => 'fix branch', 'status' => 'pending',
    ]);

    $this->patchJson("/api/admin/procedure-amendments/{$a->id}", ['action' => 'reject'])
        ->assertOk();

    expect($a->fresh()->status)->toBe('rejected')
        ->and($a->fresh()->reviewed_by)->toBe($admin->id);
});

it('only lists pending amendments, not already-reviewed ones', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    ProposedProcedureAmendment::create([
        'procedure_id' => 'pending-proc', 'problem_observed' => 'x', 'proposed_fix' => 'y', 'status' => 'pending',
    ]);
    ProposedProcedureAmendment::create([
        'procedure_id' => 'approved-proc', 'problem_observed' => 'x', 'proposed_fix' => 'y', 'status' => 'approved',
    ]);

    $this->getJson('/api/admin/procedure-amendments')
        ->assertOk()
        ->assertJsonFragment(['procedure_id' => 'pending-proc'])
        ->assertJsonMissing(['procedure_id' => 'approved-proc']);
});

it('forbids a non-admin', function (): void {
    Sanctum::actingAs(User::factory()->create(['is_admin' => false, 'is_advisor' => false]));

    $this->getJson('/api/admin/procedure-amendments')->assertForbidden();
});

it('rejects an already-reviewed amendment with 422', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    $a = ProposedProcedureAmendment::create([
        'procedure_id' => 'p', 'problem_observed' => 'x', 'proposed_fix' => 'y', 'status' => 'approved',
    ]);

    $this->patchJson("/api/admin/procedure-amendments/{$a->id}", ['action' => 'approve'])
        ->assertStatus(422);
});

it('rejects an unknown action with 422', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    $a = ProposedProcedureAmendment::create([
        'procedure_id' => 'p', 'problem_observed' => 'x', 'proposed_fix' => 'y', 'status' => 'pending',
    ]);

    $this->patchJson("/api/admin/procedure-amendments/{$a->id}", ['action' => 'nonsense'])
        ->assertStatus(422);

    expect($a->fresh()->status)->toBe('pending');
});

it('approving an amendment writes NO procedural corpus file', function (): void {
    $corpus = (string) config('fyn.memory.procedural_path');
    $before = File::isDirectory($corpus) ? count(File::allFiles($corpus)) : 0;

    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    $a = ProposedProcedureAmendment::create([
        'procedure_id' => 'recommendation-routing', 'problem_observed' => 'looped',
        'proposed_fix' => 'add guard', 'status' => 'pending',
    ]);

    $this->patchJson("/api/admin/procedure-amendments/{$a->id}", ['action' => 'approve'])
        ->assertOk();

    $after = File::isDirectory($corpus) ? count(File::allFiles($corpus)) : 0;
    expect($after)->toBe($before); // approval never touches the corpus — no autonomous procedural edit
});
