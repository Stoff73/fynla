<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProposedProcedureAmendment extends Model
{
    protected $fillable = [
        'procedure_id', 'problem_observed', 'proposed_fix', 'rationale',
        'failure_type', 'conversation_id', 'status', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];
}
