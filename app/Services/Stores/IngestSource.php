<?php

declare(strict_types=1);

namespace App\Services\Stores;

enum IngestSource: string
{
    case FORM = 'form';
    case FYN_AI = 'fyn_ai';
    case UPLOAD = 'upload';
    case SEEDER = 'seeder';
    case ADMIN = 'admin';
}
