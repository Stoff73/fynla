<?php

declare(strict_types=1);

/**
 * Application-wide Architecture Tests
 *
 * These tests enforce architectural rules and coding standards across the entire FPS application.
 * They ensure consistency in controllers, agents, models, form requests, and API responses.
 */

// Test: All controllers extend ApiController
arch('all API controllers extend Controller')
    ->expect('App\Http\Controllers\Api')
    ->toExtend('App\Http\Controllers\Controller')
    ->ignoring('App\Http\Controllers\Api\Controller');

// Test: All agents extend BaseAgent
arch('all agents extend BaseAgent')
    ->expect('App\Agents')
    ->classes()
    ->toExtend('App\Agents\BaseAgent')
    ->ignoring('App\Agents\BaseAgent');

// Test: All models use proper traits
arch('all models extend Eloquent Model')
    ->expect('App\Models')
    ->toExtend('Illuminate\Database\Eloquent\Model')
    ->ignoring('App\Models\User'); // User extends Authenticatable

arch('models use HasFactory trait')
    ->expect('App\Models')
    ->toUse('Illuminate\Database\Eloquent\Factories\HasFactory');

// Test: All form requests follow naming convention and extend FormRequest
arch('all form requests extend FormRequest')
    ->expect('App\Http\Requests')
    ->toExtend('Illuminate\Foundation\Http\FormRequest');

arch('form request names end with Request')
    ->expect('App\Http\Requests')
    ->toHaveSuffix('Request');

// Test: All migrations follow naming convention
// Note: This test is skipped as Pest Architecture doesn't support file name pattern matching
// Migrations should be manually reviewed to follow Laravel's naming convention

// Test: Controllers should not have direct DB queries
// Exception: Some controllers use DB facade for complex queries (tax settings, IHT calculations)
arch('controllers do not use DB facade directly')
    ->expect('App\Http\Controllers')
    ->not->toUse('Illuminate\Support\Facades\DB')
    ->ignoring([
        'App\Http\Controllers\Api\Estate\IHTController',
        'App\Http\Controllers\Api\TaxSettingsController',
        'App\Http\Controllers\Api\Retirement\DCPensionHoldingsController',
        'App\Http\Controllers\Api\PreviewController', // Uses DB for complex persona queries
        'App\Http\Controllers\Api\WebhookController', // Uses DB for payment transaction handling
        'App\Http\Controllers\Api\FamilyMembersController', // Uses DB for relationship queries
        'App\Http\Controllers\Api\PaymentController', // Uses DB for payment processing
        'App\Http\Controllers\Api\RetirementController', // Uses DB for batch pension queries
        'App\Http\Controllers\Api\InvestmentController', // Uses DB for portfolio aggregation
    ]);

// Note: Controllers can use Eloquent models for simple CRUD operations
// Complex queries should be delegated to services/agents
// This test is informational and can be reviewed manually

// Test: Services and Agents use strict types
arch('all agents use strict types')
    ->expect('App\Agents')
    ->toUseStrictTypes();

arch('all services use strict types')
    ->expect('App\Services')
    ->toUseStrictTypes();

arch('all models use strict types')
    ->expect('App\Models')
    ->toUseStrictTypes();

arch('all controllers use strict types')
    ->expect('App\Http\Controllers')
    ->toUseStrictTypes();

// Test: No usage of deprecated functions
arch('code does not use deprecated functions')
    ->expect('App')
    ->not->toUse([
        'mysql_query',
        'mysql_connect',
        'ereg',
        'split',
        'create_function',
    ]);

// Test: Security - No usage of dangerous functions
// Exception: AdminController uses exec for legitimate admin operations (backups, etc.)
arch('code does not use dangerous functions')
    ->expect('App')
    ->not->toUse([
        'eval',
        'exec',
        'shell_exec',
        'system',
        'passthru',
    ])
    ->ignoring([
        'App\Http\Controllers\Api\AdminController',
    ]);

// Test: Models should not contain business logic
arch('models do not use external services')
    ->expect('App\Models')
    ->not->toUse([
        'Illuminate\Support\Facades\Cache',
        'Illuminate\Support\Facades\Queue',
        'Illuminate\Support\Facades\Mail',
    ]);

// Test: All API controllers are in the Api namespace
arch('API controllers are in Api namespace')
    ->expect('App\Http\Controllers\Api')
    ->toBeClasses();

// Test: All services are in appropriate module namespaces
// Note: Some services have interfaces for abstraction (e.g., FieldMapperInterface)
arch('services are organized by module')
    ->expect('App\Services')
    ->toBeClasses()
    ->ignoring([
        'App\Services\Documents\FieldMappers\FieldMapperInterface',
    ])
    ->and('App\Services\Protection')
    ->toBeClasses()
    ->and('App\Services\Savings')
    ->toBeClasses()
    ->and('App\Services\Investment')
    ->toBeClasses()
    ->and('App\Services\Retirement')
    ->toBeClasses()
    ->and('App\Services\Estate')
    ->toBeClasses()
    ->and('App\Services\Coordination')
    ->toBeClasses();

// Test: Naming conventions
// Note: Service naming conventions are informational
// Services should use descriptive suffixes like Calculator, Analyzer, etc.

// Test: All agents have required methods
arch('all agents have analyze method')
    ->expect('App\Agents')
    ->classes()
    ->toHaveMethod('analyze')
    ->ignoring('App\Agents\BaseAgent');

// Test: Test classes follow naming conventions
arch('test classes end with Test suffix')
    ->expect('Tests\Unit')
    ->toHaveSuffix('Test')
    ->and('Tests\Feature')
    ->toHaveSuffix('Test')
    ->and('Tests\Architecture')
    ->toHaveSuffix('Test');

// Test: No TODOs or FIXMEs in committed code (warning)
// Note: This is informational - TODOs are allowed but should be tracked
// Manual review recommended for TODO/FIXME comments
