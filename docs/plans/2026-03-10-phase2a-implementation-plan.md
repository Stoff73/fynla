# Phase 2a: Backend Infrastructure Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build all backend API endpoints, database tables, services, and frontend state management needed for the Capacitor mobile app — without requiring native tooling.

**Architecture:** New DB tables (device_tokens, notification_preferences) with models/factories. New API endpoints for token refresh, device registration, notification preferences, and social sharing. Push notification service via FCM. Vuex persistence for offline-capable state. All following existing Controller → Agent → Service patterns.

**Tech Stack:** Laravel 10, Sanctum, FCM (laravel-notification-channels/fcm), Pest, Vuex + vuex-persistedstate

**Design doc:** `docs/plans/2026-03-10-phase2a-design.md`

---

## Task 1: Database Migrations

**Files:**
- Create: `database/migrations/2026_03_10_200001_create_device_tokens_table.php`
- Create: `database/migrations/2026_03_10_200002_create_notification_preferences_table.php`
- Create: `database/migrations/2026_03_10_200003_add_device_id_to_user_sessions_table.php`

**Step 1: Create device_tokens migration**

```php
<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('device_tokens')) {
            return;
        }

        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_token', 500);
            $table->string('device_id', 255);
            $table->enum('platform', ['ios', 'android']);
            $table->string('device_name', 255)->nullable();
            $table->string('app_version', 20)->nullable();
            $table->string('os_version', 50)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'device_id']);
            $table->index('device_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
```

**Step 2: Create notification_preferences migration**

```php
<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('notification_preferences')) {
            return;
        }

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('policy_renewals')->default(true);
            $table->boolean('goal_milestones')->default(true);
            $table->boolean('contribution_reminders')->default(true);
            $table->boolean('market_updates')->default(false);
            $table->boolean('fyn_daily_insight')->default(true);
            $table->boolean('security_alerts')->default(true);
            $table->boolean('payment_alerts')->default(true);
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
```

**Step 3: Create user_sessions device_id migration**

```php
<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('user_sessions', 'device_id')) {
            return;
        }

        Schema::table('user_sessions', function (Blueprint $table) {
            $table->string('device_id', 255)->nullable()->after('device_name');
        });
    }

    public function down(): void
    {
        Schema::table('user_sessions', function (Blueprint $table) {
            $table->dropColumn('device_id');
        });
    }
};
```

**Step 4: Run migrations**

Run: `php artisan migrate`
Expected: 3 migrations run successfully

**Step 5: Reseed database**

Run: `php artisan db:seed`
Expected: All seeders complete successfully

**Step 6: Commit**

```bash
git add database/migrations/2026_03_10_20000*
git commit -m "feat: add device_tokens, notification_preferences tables and user_sessions device_id (2a-01)"
```

---

## Task 2: Models + Factories

**Files:**
- Create: `app/Models/DeviceToken.php`
- Create: `app/Models/NotificationPreference.php`
- Modify: `app/Models/UserSession.php` (add device_id to fillable)
- Create: `database/factories/DeviceTokenFactory.php`
- Create: `database/factories/NotificationPreferenceFactory.php`
- Create: `tests/Unit/Models/DeviceTokenTest.php`
- Create: `tests/Unit/Models/NotificationPreferenceTest.php`

**Step 1: Write DeviceToken model tests**

```php
<?php
declare(strict_types=1);

use App\Models\DeviceToken;
use App\Models\User;

describe('DeviceToken', function () {
    it('belongs to a user', function () {
        $user = User::factory()->create();
        $token = DeviceToken::factory()->create(['user_id' => $user->id]);

        expect($token->user->id)->toBe($user->id);
    });

    it('scopes to a specific user', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        DeviceToken::factory()->create(['user_id' => $user1->id]);
        DeviceToken::factory()->create(['user_id' => $user2->id]);

        expect(DeviceToken::forUser($user1->id)->count())->toBe(1);
    });

    it('scopes to a specific platform', function () {
        $user = User::factory()->create();
        DeviceToken::factory()->ios()->create(['user_id' => $user->id]);
        DeviceToken::factory()->android()->create(['user_id' => $user->id, 'device_id' => 'android-1']);

        expect(DeviceToken::forPlatform('ios')->count())->toBe(1);
    });

    it('casts last_used_at as datetime', function () {
        $user = User::factory()->create();
        $token = DeviceToken::factory()->create([
            'user_id' => $user->id,
            'last_used_at' => '2026-03-10 12:00:00',
        ]);

        expect($token->last_used_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    });

    it('enforces unique user_id + device_id constraint', function () {
        $user = User::factory()->create();
        DeviceToken::factory()->create(['user_id' => $user->id, 'device_id' => 'device-1']);

        expect(fn () => DeviceToken::factory()->create([
            'user_id' => $user->id,
            'device_id' => 'device-1',
        ]))->toThrow(\Illuminate\Database\QueryException::class);
    });
});
```

**Step 2: Write NotificationPreference model tests**

```php
<?php
declare(strict_types=1);

use App\Models\NotificationPreference;
use App\Models\User;

describe('NotificationPreference', function () {
    it('belongs to a user', function () {
        $user = User::factory()->create();
        $prefs = NotificationPreference::factory()->create(['user_id' => $user->id]);

        expect($prefs->user->id)->toBe($user->id);
    });

    it('casts boolean preferences correctly', function () {
        $user = User::factory()->create();
        $prefs = NotificationPreference::factory()->create([
            'user_id' => $user->id,
            'policy_renewals' => true,
            'market_updates' => false,
        ]);

        expect($prefs->policy_renewals)->toBeTrue()
            ->and($prefs->market_updates)->toBeFalse();
    });

    it('gets or creates preferences for a user', function () {
        $user = User::factory()->create();

        // First call creates
        $prefs = NotificationPreference::getOrCreateForUser($user->id);
        expect($prefs)->toBeInstanceOf(NotificationPreference::class)
            ->and($prefs->policy_renewals)->toBeTrue()
            ->and($prefs->market_updates)->toBeFalse();

        // Second call returns existing
        $prefs2 = NotificationPreference::getOrCreateForUser($user->id);
        expect($prefs2->id)->toBe($prefs->id);
    });

    it('enforces unique user_id constraint', function () {
        $user = User::factory()->create();
        NotificationPreference::factory()->create(['user_id' => $user->id]);

        expect(fn () => NotificationPreference::factory()->create([
            'user_id' => $user->id,
        ]))->toThrow(\Illuminate\Database\QueryException::class);
    });
});
```

**Step 3: Run tests to verify they fail**

Run: `./vendor/bin/pest tests/Unit/Models/DeviceTokenTest.php tests/Unit/Models/NotificationPreferenceTest.php`
Expected: FAIL — classes don't exist yet

**Step 4: Create DeviceToken model**

```php
<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'device_token',
        'device_id',
        'platform',
        'device_name',
        'app_version',
        'os_version',
        'last_used_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForPlatform(Builder $query, string $platform): Builder
    {
        return $query->where('platform', $platform);
    }
}
```

**Step 5: Create NotificationPreference model**

```php
<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'policy_renewals',
        'goal_milestones',
        'contribution_reminders',
        'market_updates',
        'fyn_daily_insight',
        'security_alerts',
        'payment_alerts',
    ];

    protected $casts = [
        'policy_renewals' => 'boolean',
        'goal_milestones' => 'boolean',
        'contribution_reminders' => 'boolean',
        'market_updates' => 'boolean',
        'fyn_daily_insight' => 'boolean',
        'security_alerts' => 'boolean',
        'payment_alerts' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function getOrCreateForUser(int $userId): self
    {
        return self::firstOrCreate(
            ['user_id' => $userId],
            [
                'policy_renewals' => true,
                'goal_milestones' => true,
                'contribution_reminders' => true,
                'market_updates' => false,
                'fyn_daily_insight' => true,
                'security_alerts' => true,
                'payment_alerts' => true,
            ]
        );
    }
}
```

**Step 6: Create DeviceTokenFactory**

```php
<?php
declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeviceTokenFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'device_token' => fake()->sha256(),
            'device_id' => fake()->uuid(),
            'platform' => fake()->randomElement(['ios', 'android']),
            'device_name' => fake()->randomElement(['iPhone 15 Pro', 'Samsung Galaxy S24', 'Pixel 8']),
            'app_version' => '1.0.0',
            'os_version' => fake()->randomElement(['iOS 17.4', 'Android 14']),
            'last_used_at' => now(),
        ];
    }

    public function ios(): static
    {
        return $this->state(fn () => [
            'platform' => 'ios',
            'device_name' => 'iPhone 15 Pro',
            'os_version' => 'iOS 17.4',
        ]);
    }

    public function android(): static
    {
        return $this->state(fn () => [
            'platform' => 'android',
            'device_name' => 'Samsung Galaxy S24',
            'os_version' => 'Android 14',
        ]);
    }
}
```

**Step 7: Create NotificationPreferenceFactory**

```php
<?php
declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationPreferenceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'policy_renewals' => true,
            'goal_milestones' => true,
            'contribution_reminders' => true,
            'market_updates' => false,
            'fyn_daily_insight' => true,
            'security_alerts' => true,
            'payment_alerts' => true,
        ];
    }

    public function allEnabled(): static
    {
        return $this->state(fn () => [
            'market_updates' => true,
        ]);
    }

    public function allDisabled(): static
    {
        return $this->state(fn () => [
            'policy_renewals' => false,
            'goal_milestones' => false,
            'contribution_reminders' => false,
            'market_updates' => false,
            'fyn_daily_insight' => false,
            'security_alerts' => false,
            'payment_alerts' => false,
        ]);
    }
}
```

**Step 8: Update UserSession model fillable**

In `app/Models/UserSession.php`, add `'device_id'` to the `$fillable` array after `'device_name'`.

**Step 9: Run tests to verify they pass**

Run: `./vendor/bin/pest tests/Unit/Models/DeviceTokenTest.php tests/Unit/Models/NotificationPreferenceTest.php`
Expected: All pass

**Step 10: Commit**

```bash
git add app/Models/DeviceToken.php app/Models/NotificationPreference.php app/Models/UserSession.php database/factories/DeviceTokenFactory.php database/factories/NotificationPreferenceFactory.php tests/Unit/Models/DeviceTokenTest.php tests/Unit/Models/NotificationPreferenceTest.php
git commit -m "feat: add DeviceToken and NotificationPreference models with factories and tests (2a-02)"
```

---

## Task 3: Auth Token Refresh Endpoint

**Files:**
- Create: `app/Http/Controllers/Api/V1/Auth/TokenRefreshController.php`
- Create: `tests/Feature/Mobile/TokenRefreshTest.php`
- Modify: `routes/api_v1.php` (add route)
- Modify: `app/Http/Middleware/PreviewWriteInterceptor.php` (add to EXCLUDED_ROUTES)

**Step 1: Write tests**

```php
<?php
declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

describe('Token Refresh API', function () {
    it('issues a new token and revokes the old one', function () {
        $user = User::factory()->create();
        $token = $user->createToken('test-token');
        $plainToken = $token->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $plainToken)
            ->postJson('/api/v1/auth/refresh-token');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['token', 'expires_at', 'token_age_days'],
            ])
            ->assertJson(['success' => true]);

        // Old token should be revoked
        expect(PersonalAccessToken::findToken($plainToken))->toBeNull();

        // New token should work
        $newToken = $response->json('data.token');
        $this->withHeader('Authorization', 'Bearer ' . $newToken)
            ->getJson('/api/v1/health')
            ->assertOk();
    });

    it('returns 401 for unauthenticated requests', function () {
        $this->postJson('/api/v1/auth/refresh-token')
            ->assertUnauthorized();
    });

    it('returns new token data with correct structure', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/auth/refresh-token');

        $response->assertOk();
        $data = $response->json('data');

        expect($data['token'])->toBeString()->not->toBeEmpty()
            ->and($data['expires_at'])->toBeString()
            ->and($data['token_age_days'])->toBe(0);
    });
});
```

**Step 2: Run tests to verify they fail**

Run: `./vendor/bin/pest tests/Feature/Mobile/TokenRefreshTest.php`
Expected: FAIL — route not found

**Step 3: Create TokenRefreshController**

```php
<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TokenRefreshController extends Controller
{
    use SanitizedErrorResponse;

    public function refresh(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $currentToken = $user->currentAccessToken();

            // Revoke the current token
            $currentToken->delete();

            // Create a new token with 30-day expiry
            $newToken = $user->createToken('mobile-token', ['*'], now()->addDays(30));

            return response()->json([
                'success' => true,
                'data' => [
                    'token' => $newToken->plainTextToken,
                    'expires_at' => now()->addDays(30)->toIso8601String(),
                    'token_age_days' => 0,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Refreshing auth token');
        }
    }
}
```

**Step 4: Add route to `routes/api_v1.php`**

Inside the `auth:sanctum` group, add:

```php
    // Auth token refresh
    Route::post('/auth/refresh-token', [\App\Http\Controllers\Api\V1\Auth\TokenRefreshController::class, 'refresh'])
        ->middleware('throttle:device-registration')
        ->name('api.v1.auth.refresh-token');
```

**Step 5: Add to PreviewWriteInterceptor EXCLUDED_ROUTES**

In `app/Http/Middleware/PreviewWriteInterceptor.php`, add `'api/v1/auth/refresh-token'` to the `EXCLUDED_ROUTES` array.

**Step 6: Run tests**

Run: `./vendor/bin/pest tests/Feature/Mobile/TokenRefreshTest.php`
Expected: All pass

**Step 7: Commit**

```bash
git add app/Http/Controllers/Api/V1/Auth/TokenRefreshController.php tests/Feature/Mobile/TokenRefreshTest.php routes/api_v1.php app/Http/Middleware/PreviewWriteInterceptor.php
git commit -m "feat: add auth token refresh endpoint for mobile (2a-03)"
```

---

## Task 4: Device Registration API

**Files:**
- Create: `app/Http/Controllers/Api/V1/Mobile/DeviceController.php`
- Create: `app/Http/Requests/V1/RegisterDeviceRequest.php`
- Create: `tests/Feature/Mobile/DeviceRegistrationTest.php`
- Modify: `routes/api_v1.php` (add routes)
- Modify: `app/Http/Middleware/PreviewWriteInterceptor.php` (add to EXCLUDED_ROUTES)

**Step 1: Write tests**

```php
<?php
declare(strict_types=1);

use App\Models\DeviceToken;
use App\Models\User;

describe('Device Registration API', function () {
    it('registers a new device token', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/mobile/devices', [
            'device_token' => 'fcm-token-abc123',
            'device_id' => 'device-uuid-1',
            'platform' => 'ios',
            'device_name' => 'iPhone 15 Pro',
            'app_version' => '1.0.0',
            'os_version' => 'iOS 17.4',
        ]);

        $response->assertCreated()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'device_id' => 'device-uuid-1',
            'platform' => 'ios',
        ]);
    });

    it('upserts existing device token', function () {
        $user = User::factory()->create();
        DeviceToken::factory()->create([
            'user_id' => $user->id,
            'device_id' => 'device-uuid-1',
            'device_token' => 'old-token',
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/mobile/devices', [
            'device_token' => 'new-token',
            'device_id' => 'device-uuid-1',
            'platform' => 'ios',
        ]);

        $response->assertOk();
        expect(DeviceToken::where('user_id', $user->id)->count())->toBe(1);
        expect(DeviceToken::where('user_id', $user->id)->first()->device_token)->toBe('new-token');
    });

    it('lists user devices', function () {
        $user = User::factory()->create();
        DeviceToken::factory()->count(2)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/v1/mobile/devices');

        $response->assertOk()
            ->assertJsonCount(2, 'data.devices');
    });

    it('revokes a device by device_id', function () {
        $user = User::factory()->create();
        DeviceToken::factory()->create([
            'user_id' => $user->id,
            'device_id' => 'device-to-delete',
        ]);

        $response = $this->actingAs($user)->deleteJson('/api/v1/mobile/devices/device-to-delete');

        $response->assertOk();
        $this->assertDatabaseMissing('device_tokens', [
            'user_id' => $user->id,
            'device_id' => 'device-to-delete',
        ]);
    });

    it('returns 401 for unauthenticated requests', function () {
        $this->postJson('/api/v1/mobile/devices', [
            'device_token' => 'token',
            'device_id' => 'id',
            'platform' => 'ios',
        ])->assertUnauthorized();
    });

    it('validates required fields', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/mobile/devices', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['device_token', 'device_id', 'platform']);
    });

    it('validates platform enum', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/mobile/devices', [
            'device_token' => 'token',
            'device_id' => 'id',
            'platform' => 'windows',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['platform']);
    });

    it('prevents access to other users devices', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        DeviceToken::factory()->create([
            'user_id' => $otherUser->id,
            'device_id' => 'other-device',
        ]);

        $this->actingAs($user)->deleteJson('/api/v1/mobile/devices/other-device')
            ->assertNotFound();
    });
});
```

**Step 2: Run tests to verify they fail**

Run: `./vendor/bin/pest tests/Feature/Mobile/DeviceRegistrationTest.php`
Expected: FAIL

**Step 3: Create RegisterDeviceRequest**

```php
<?php
declare(strict_types=1);

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_token' => ['required', 'string', 'max:500'],
            'device_id' => ['required', 'string', 'max:255'],
            'platform' => ['required', Rule::in(['ios', 'android'])],
            'device_name' => ['nullable', 'string', 'max:255'],
            'app_version' => ['nullable', 'string', 'max:20'],
            'os_version' => ['nullable', 'string', 'max:50'],
        ];
    }
}
```

**Step 4: Create DeviceController**

```php
<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\RegisterDeviceRequest;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    use SanitizedErrorResponse;

    public function store(RegisterDeviceRequest $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $existing = DeviceToken::where('user_id', $userId)
                ->where('device_id', $request->device_id)
                ->first();

            if ($existing) {
                $existing->update([
                    'device_token' => $request->device_token,
                    'platform' => $request->platform,
                    'device_name' => $request->device_name,
                    'app_version' => $request->app_version,
                    'os_version' => $request->os_version,
                    'last_used_at' => now(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Device token updated.',
                    'data' => ['device_id' => $existing->device_id],
                ]);
            }

            $token = DeviceToken::create([
                'user_id' => $userId,
                'device_token' => $request->device_token,
                'device_id' => $request->device_id,
                'platform' => $request->platform,
                'device_name' => $request->device_name,
                'app_version' => $request->app_version,
                'os_version' => $request->os_version,
                'last_used_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Device registered.',
                'data' => ['device_id' => $token->device_id],
            ], 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Registering device');
        }
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $devices = DeviceToken::forUser($request->user()->id)
                ->orderByDesc('last_used_at')
                ->get(['device_id', 'platform', 'device_name', 'app_version', 'os_version', 'last_used_at']);

            return response()->json([
                'success' => true,
                'data' => ['devices' => $devices],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Listing devices');
        }
    }

    public function destroy(Request $request, string $deviceId): JsonResponse
    {
        try {
            $deleted = DeviceToken::where('user_id', $request->user()->id)
                ->where('device_id', $deviceId)
                ->delete();

            if (! $deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Device not found.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Device revoked.',
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Revoking device');
        }
    }
}
```

**Step 5: Add routes to `routes/api_v1.php`**

Inside the `auth:sanctum` group, add:

```php
    // Device registration
    Route::post('/mobile/devices', [\App\Http\Controllers\Api\V1\Mobile\DeviceController::class, 'store'])
        ->middleware('throttle:device-registration')
        ->name('api.v1.mobile.devices.store');
    Route::get('/mobile/devices', [\App\Http\Controllers\Api\V1\Mobile\DeviceController::class, 'index'])
        ->name('api.v1.mobile.devices.index');
    Route::delete('/mobile/devices/{deviceId}', [\App\Http\Controllers\Api\V1\Mobile\DeviceController::class, 'destroy'])
        ->name('api.v1.mobile.devices.destroy');
```

**Step 6: Add to PreviewWriteInterceptor EXCLUDED_ROUTES**

Add `'api/v1/mobile/devices'` to the array.

**Step 7: Run tests**

Run: `./vendor/bin/pest tests/Feature/Mobile/DeviceRegistrationTest.php`
Expected: All pass

**Step 8: Commit**

```bash
git add app/Http/Controllers/Api/V1/Mobile/DeviceController.php app/Http/Requests/V1/RegisterDeviceRequest.php tests/Feature/Mobile/DeviceRegistrationTest.php routes/api_v1.php app/Http/Middleware/PreviewWriteInterceptor.php
git commit -m "feat: add device registration API with upsert and revoke (2a-04)"
```

---

## Task 5: Notification Preferences API

**Files:**
- Create: `app/Http/Controllers/Api/V1/Mobile/NotificationPreferenceController.php`
- Create: `app/Http/Requests/V1/UpdateNotificationPreferencesRequest.php`
- Create: `tests/Feature/Mobile/NotificationPreferenceApiTest.php`
- Modify: `routes/api_v1.php`

**Step 1: Write tests**

```php
<?php
declare(strict_types=1);

use App\Models\NotificationPreference;
use App\Models\User;

describe('Notification Preferences API', function () {
    it('returns default preferences for new user', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/mobile/notifications/preferences');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'policy_renewals' => true,
                    'goal_milestones' => true,
                    'contribution_reminders' => true,
                    'market_updates' => false,
                    'fyn_daily_insight' => true,
                    'security_alerts' => true,
                    'payment_alerts' => true,
                ],
            ]);
    });

    it('updates specific preferences', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson('/api/v1/mobile/notifications/preferences', [
            'market_updates' => true,
            'fyn_daily_insight' => false,
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $prefs = NotificationPreference::where('user_id', $user->id)->first();
        expect($prefs->market_updates)->toBeTrue()
            ->and($prefs->fyn_daily_insight)->toBeFalse()
            ->and($prefs->policy_renewals)->toBeTrue(); // unchanged
    });

    it('returns 401 for unauthenticated requests', function () {
        $this->getJson('/api/v1/mobile/notifications/preferences')
            ->assertUnauthorized();
    });

    it('validates boolean types', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/v1/mobile/notifications/preferences', [
            'policy_renewals' => 'not-a-boolean',
        ])->assertUnprocessable();
    });
});
```

**Step 2: Run tests to verify they fail**

**Step 3: Create UpdateNotificationPreferencesRequest**

```php
<?php
declare(strict_types=1);

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'policy_renewals' => ['nullable', 'boolean'],
            'goal_milestones' => ['nullable', 'boolean'],
            'contribution_reminders' => ['nullable', 'boolean'],
            'market_updates' => ['nullable', 'boolean'],
            'fyn_daily_insight' => ['nullable', 'boolean'],
            'security_alerts' => ['nullable', 'boolean'],
            'payment_alerts' => ['nullable', 'boolean'],
        ];
    }
}
```

**Step 4: Create NotificationPreferenceController**

```php
<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\UpdateNotificationPreferencesRequest;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\NotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    use SanitizedErrorResponse;

    public function show(Request $request): JsonResponse
    {
        try {
            $prefs = NotificationPreference::getOrCreateForUser($request->user()->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'policy_renewals' => $prefs->policy_renewals,
                    'goal_milestones' => $prefs->goal_milestones,
                    'contribution_reminders' => $prefs->contribution_reminders,
                    'market_updates' => $prefs->market_updates,
                    'fyn_daily_insight' => $prefs->fyn_daily_insight,
                    'security_alerts' => $prefs->security_alerts,
                    'payment_alerts' => $prefs->payment_alerts,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Fetching notification preferences');
        }
    }

    public function update(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        try {
            $prefs = NotificationPreference::getOrCreateForUser($request->user()->id);
            $prefs->update($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Notification preferences updated.',
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Updating notification preferences');
        }
    }
}
```

**Step 5: Add routes**

Inside `auth:sanctum` group in `routes/api_v1.php`:

```php
    // Notification preferences
    Route::get('/mobile/notifications/preferences', [\App\Http\Controllers\Api\V1\Mobile\NotificationPreferenceController::class, 'show'])
        ->name('api.v1.mobile.notifications.preferences.show');
    Route::put('/mobile/notifications/preferences', [\App\Http\Controllers\Api\V1\Mobile\NotificationPreferenceController::class, 'update'])
        ->name('api.v1.mobile.notifications.preferences.update');
```

**Step 6: Run tests**

Run: `./vendor/bin/pest tests/Feature/Mobile/NotificationPreferenceApiTest.php`
Expected: All pass

**Step 7: Commit**

```bash
git add app/Http/Controllers/Api/V1/Mobile/NotificationPreferenceController.php app/Http/Requests/V1/UpdateNotificationPreferencesRequest.php tests/Feature/Mobile/NotificationPreferenceApiTest.php routes/api_v1.php
git commit -m "feat: add notification preferences API with auto-create defaults (2a-05)"
```

---

## Task 6: Push Notification Service + Notification Classes

**Files:**
- Create: `app/Services/Mobile/PushNotificationService.php`
- Create: `app/Notifications/PolicyRenewalNotification.php`
- Create: `app/Notifications/GoalMilestoneNotification.php`
- Create: `app/Notifications/ContributionReminderNotification.php`
- Create: `app/Notifications/SecurityAlertNotification.php`
- Create: `app/Notifications/SubscriptionExpiringNotification.php`
- Create: `app/Notifications/DailyInsightNotification.php`
- Create: `tests/Unit/Services/Mobile/PushNotificationServiceTest.php`
- Modify: `composer.json` (add FCM package)

**Step 1: Install FCM package**

Run: `composer require laravel-notification-channels/fcm`

If the package is not available or incompatible with Laravel 10, create a lightweight FCM HTTP client service instead (the plan accounts for this — the PushNotificationService can use Guzzle directly to call the FCM v1 API).

**Step 2: Write PushNotificationService tests**

```php
<?php
declare(strict_types=1);

use App\Models\DeviceToken;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\Mobile\PushNotificationService;

beforeEach(function () {
    $this->service = app(PushNotificationService::class);
});

describe('PushNotificationService', function () {
    it('checks notification preferences before sending', function () {
        $user = User::factory()->create();
        NotificationPreference::factory()->create([
            'user_id' => $user->id,
            'policy_renewals' => false,
        ]);
        DeviceToken::factory()->create(['user_id' => $user->id]);

        $result = $this->service->shouldSend($user->id, 'policy_renewals');
        expect($result)->toBeFalse();
    });

    it('returns true when preference is enabled', function () {
        $user = User::factory()->create();
        NotificationPreference::factory()->create([
            'user_id' => $user->id,
            'policy_renewals' => true,
        ]);
        DeviceToken::factory()->create(['user_id' => $user->id]);

        $result = $this->service->shouldSend($user->id, 'policy_renewals');
        expect($result)->toBeTrue();
    });

    it('returns false when user has no device tokens', function () {
        $user = User::factory()->create();
        NotificationPreference::factory()->create(['user_id' => $user->id]);

        $result = $this->service->shouldSend($user->id, 'policy_renewals');
        expect($result)->toBeFalse();
    });

    it('returns user device tokens', function () {
        $user = User::factory()->create();
        DeviceToken::factory()->count(2)->create(['user_id' => $user->id]);

        $tokens = $this->service->getDeviceTokens($user->id);
        expect($tokens)->toHaveCount(2);
    });

    it('removes stale device tokens', function () {
        $user = User::factory()->create();
        $device = DeviceToken::factory()->create(['user_id' => $user->id]);

        $this->service->removeStaleToken($device->device_token);

        $this->assertDatabaseMissing('device_tokens', [
            'device_token' => $device->device_token,
        ]);
    });
});
```

**Step 3: Create PushNotificationService**

```php
<?php
declare(strict_types=1);

namespace App\Services\Mobile;

use App\Models\DeviceToken;
use App\Models\NotificationPreference;
use App\Traits\StructuredLogging;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    use StructuredLogging;

    public function shouldSend(int $userId, string $preferenceKey): bool
    {
        $hasDevices = DeviceToken::forUser($userId)->exists();
        if (! $hasDevices) {
            return false;
        }

        $prefs = NotificationPreference::getOrCreateForUser($userId);

        return (bool) ($prefs->{$preferenceKey} ?? false);
    }

    public function getDeviceTokens(int $userId): array
    {
        return DeviceToken::forUser($userId)
            ->pluck('device_token')
            ->toArray();
    }

    public function sendToUser(int $userId, string $title, string $body, array $data = []): void
    {
        $tokens = $this->getDeviceTokens($userId);

        foreach ($tokens as $token) {
            $this->sendToToken($token, $title, $body, $data);
        }
    }

    public function sendToToken(string $deviceToken, string $title, string $body, array $data = []): void
    {
        $serverKey = config('services.fcm.server_key');

        if (! $serverKey) {
            Log::warning('FCM server key not configured, skipping push notification');

            return;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'key=' . $serverKey,
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', [
                'to' => $deviceToken,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $data,
            ]);

            if ($response->failed()) {
                $this->handleFailedResponse($response, $deviceToken);
            }
        } catch (\Exception $e) {
            Log::error('FCM send failed', [
                'error' => $e->getMessage(),
                'device_token_prefix' => substr($deviceToken, 0, 10) . '...',
            ]);
        }
    }

    public function removeStaleToken(string $deviceToken): void
    {
        DeviceToken::where('device_token', $deviceToken)->delete();
    }

    private function handleFailedResponse($response, string $deviceToken): void
    {
        $body = $response->json();
        $error = $body['results'][0]['error'] ?? $body['error'] ?? 'unknown';

        if (in_array($error, ['NotRegistered', 'InvalidRegistration'])) {
            $this->removeStaleToken($deviceToken);
            Log::info('Removed stale FCM token', ['error' => $error]);
        } else {
            Log::warning('FCM send error', ['error' => $error]);
        }
    }
}
```

**Step 4: Add FCM config to `config/services.php`**

Add to the return array:

```php
'fcm' => [
    'server_key' => env('FCM_SERVER_KEY'),
    'project_id' => env('FCM_PROJECT_ID'),
],
```

**Step 5: Create 6 notification classes**

Each follows the same pattern. Here's one example — `PolicyRenewalNotification.php`:

```php
<?php
declare(strict_types=1);

namespace App\Notifications;

use App\Services\Mobile\PushNotificationService;
use Illuminate\Notifications\Notification;

class PolicyRenewalNotification extends Notification
{
    public function __construct(
        private readonly string $policyName,
        private readonly string $renewalDate,
    ) {}

    public function via(object $notifiable): array
    {
        $pushService = app(PushNotificationService::class);

        if ($pushService->shouldSend($notifiable->id, 'policy_renewals')) {
            return ['database'];
        }

        return [];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Policy Renewal Reminder',
            'body' => "Your {$this->policyName} renews on {$this->renewalDate}. Review your coverage to ensure it still meets your needs.",
            'type' => 'policy_renewal',
            'data' => [
                'policy_name' => $this->policyName,
                'renewal_date' => $this->renewalDate,
            ],
        ];
    }
}
```

Create the remaining 5 following the same pattern with appropriate preference keys: `goal_milestones`, `contribution_reminders`, `security_alerts`, `payment_alerts`, `fyn_daily_insight`.

**Step 6: Run tests**

Run: `./vendor/bin/pest tests/Unit/Services/Mobile/PushNotificationServiceTest.php`
Expected: All pass

**Step 7: Commit**

```bash
git add app/Services/Mobile/PushNotificationService.php app/Notifications/ config/services.php tests/Unit/Services/Mobile/PushNotificationServiceTest.php
git commit -m "feat: add PushNotificationService with FCM support and 6 notification classes (2a-06)"
```

---

## Task 7: Scheduled Notification Commands

**Files:**
- Create: `app/Console/Commands/SendDailyInsightNotifications.php`
- Create: `app/Console/Commands/SendPolicyRenewalReminders.php`
- Create: `tests/Unit/Commands/SendDailyInsightNotificationsTest.php`
- Create: `tests/Unit/Commands/SendPolicyRenewalRemindersTest.php`
- Modify: `app/Console/Kernel.php`

**Step 1: Write tests for both commands**

Test that the commands exist, run without errors, and respect notification preferences.

**Step 2: Create SendDailyInsightNotifications command**

```php
<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DeviceToken;
use App\Models\NotificationPreference;
use App\Services\Mobile\PushNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendDailyInsightNotifications extends Command
{
    protected $signature = 'notifications:daily-insight';

    protected $description = 'Send daily Fyn insight push notifications to opted-in users';

    public function handle(PushNotificationService $pushService): int
    {
        $userIds = NotificationPreference::where('fyn_daily_insight', true)
            ->pluck('user_id');

        $usersWithDevices = DeviceToken::whereIn('user_id', $userIds)
            ->distinct('user_id')
            ->pluck('user_id');

        $count = 0;

        foreach ($usersWithDevices as $userId) {
            try {
                $pushService->sendToUser(
                    $userId,
                    'Your Daily Financial Insight',
                    'Tap to see today\'s personalised tip from Fyn.',
                    ['type' => 'daily_insight', 'route' => '/dashboard']
                );
                $count++;
            } catch (\Exception $e) {
                Log::warning('Failed to send daily insight', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Sent daily insights to {$count} users.");

        return Command::SUCCESS;
    }
}
```

**Step 3: Create SendPolicyRenewalReminders command**

Similar pattern — queries protection policies with `renewal_date` within 30 days, sends to policy owner.

**Step 4: Register in Kernel.php**

Add to the `schedule()` method in `app/Console/Kernel.php`:

```php
$schedule->command('notifications:daily-insight')->dailyAt('08:00');
$schedule->command('notifications:policy-renewals')->dailyAt('09:00');
```

**Step 5: Run tests**

**Step 6: Commit**

```bash
git add app/Console/Commands/SendDailyInsightNotifications.php app/Console/Commands/SendPolicyRenewalReminders.php tests/Unit/Commands/ app/Console/Kernel.php
git commit -m "feat: add scheduled notification commands for daily insights and policy renewals (2a-07)"
```

---

## Task 8: Deep Link Configuration

**Files:**
- Create: `public/.well-known/apple-app-site-association`
- Create: `public/.well-known/assetlinks.json`

**Step 1: Create apple-app-site-association**

JSON file (no .json extension — Apple requirement). Contains supported paths for Universal Links.

**Step 2: Create assetlinks.json**

Android App Links config with placeholder SHA256 fingerprint.

**Step 3: Commit**

```bash
git add public/.well-known/
git commit -m "feat: add deep link configuration for iOS Universal Links and Android App Links (2a-08)"
```

---

## Task 9: CORS + Middleware Updates

**Files:**
- Modify: `config/cors.php` (add Capacitor origins)
- Modify: `app/Http/Middleware/SecurityHeaders.php` (Capacitor in CSP)

**Step 1: Add Capacitor origins to CORS**

Add `'capacitor://localhost'` and `'http://localhost'` to the `allowed_origins` array.

**Step 2: Add Capacitor to CSP connect-src**

In SecurityHeaders, add Capacitor origins to the `connect-src` directive.

**Step 3: Run existing tests to verify no regression**

Run: `./vendor/bin/pest tests/Feature/`
Expected: All pass

**Step 4: Commit**

```bash
git add config/cors.php app/Http/Middleware/SecurityHeaders.php
git commit -m "feat: add Capacitor origins to CORS and CSP for mobile app (2a-09)"
```

---

## Task 10: Vuex Persistence + Platform Detection + Mobile Dashboard Store

**Files:**
- Create: `resources/js/utils/platform.js`
- Create: `resources/js/store/modules/mobileDashboard.js`
- Modify: `resources/js/store/index.js` (add persistence + new module)
- Modify: `package.json` (add vuex-persistedstate)

**Step 1: Install vuex-persistedstate**

Run: `npm install vuex-persistedstate`

**Step 2: Create platform.js utility**

```javascript
export const platform = {
    isNative: () => typeof window !== 'undefined' && typeof window.Capacitor !== 'undefined' && window.Capacitor.isNativePlatform(),
    isIOS: () => platform.isNative() && window.Capacitor.getPlatform() === 'ios',
    isAndroid: () => platform.isNative() && window.Capacitor.getPlatform() === 'android',
    isWeb: () => !platform.isNative(),
    isMobileViewport: () => typeof window !== 'undefined' && window.innerWidth < 768,
};
```

**Step 3: Create mobileDashboard Vuex module**

Standard Vuex module consuming `/api/v1/mobile/dashboard` with state for summary, netWorth, modules, alerts, insight, loading, error, lastFetched. Actions use British English (`fetchDashboard`, `refreshDashboard`, `clearCache`).

**Step 4: Add persistence plugin and mobileDashboard to store/index.js**

Import `createPersistedState` and `mobileDashboard`, add to plugins and modules.

**Step 5: Commit**

```bash
git add resources/js/utils/platform.js resources/js/store/modules/mobileDashboard.js resources/js/store/index.js package.json package-lock.json
git commit -m "feat: add Vuex persistence, platform detection, and mobileDashboard store (2a-10)"
```

---

## Task 11: Social Share Backend

**Files:**
- Create: `app/Http/Controllers/Api/V1/Mobile/ShareController.php`
- Create: `app/Services/Mobile/ShareContentGenerator.php`
- Create: `app/Http/Requests/V1/ShareContentRequest.php`
- Create: `tests/Feature/Mobile/ShareContentTest.php`
- Create: `tests/Unit/Services/Mobile/ShareContentGeneratorTest.php`
- Modify: `routes/api_v1.php`

**Step 1: Write tests**

Test that share payloads are generated correctly, contain no PII (no monetary values, no names), and handle invalid types.

**Step 2: Create ShareContentGenerator service**

Generates sanitised share text per type. **Critical:** Never include monetary values, account balances, portfolio figures, or names of joint owners/beneficiaries.

**Step 3: Create ShareContentRequest and ShareController**

**Step 4: Add route**

Inside `auth:sanctum` group:
```php
Route::get('/mobile/share/{type}/{id?}', [ShareController::class, 'show'])
    ->name('api.v1.mobile.share');
```

**Step 5: Run tests**

**Step 6: Commit**

```bash
git add app/Http/Controllers/Api/V1/Mobile/ShareController.php app/Services/Mobile/ShareContentGenerator.php app/Http/Requests/V1/ShareContentRequest.php tests/Feature/Mobile/ShareContentTest.php tests/Unit/Services/Mobile/ShareContentGeneratorTest.php routes/api_v1.php
git commit -m "feat: add social share backend with PII-safe content generation (2a-11)"
```

---

## Task 12: Integration Testing + Code Review + Deploy Notes

**Step 1: Run full test suite**

Run: `./vendor/bin/pest`
Expected: All tests pass

**Step 2: Run mobile-specific tests**

Run: `./vendor/bin/pest tests/Feature/Mobile/ tests/Unit/Services/Mobile/ tests/Unit/Models/ tests/Unit/Commands/`

**Step 3: Code review via subagent**

Review all Phase 2a changes for security, conventions, test coverage.

**Step 4: Reseed database**

Run: `php artisan db:seed`

**Step 5: Generate deploy notes**

Create `docs/plans/phase2a-deploy-notes.md` listing all files to upload.

**Step 6: Commit**

```bash
git add docs/plans/phase2a-deploy-notes.md
git commit -m "docs: add Phase 2a deploy notes and mark phase complete"
```

---

## Environment Variables (Production)

Add to `.env` when deploying:

```
FCM_SERVER_KEY=your_firebase_server_key
FCM_PROJECT_ID=your_firebase_project_id
```

---

## Summary

| Task | Files Created | Files Modified | Tests |
|------|--------------|----------------|-------|
| 2a-01 Migrations | 3 | 0 | 0 |
| 2a-02 Models + Factories | 6 | 1 | 2 |
| 2a-03 Token Refresh | 2 | 2 | 1 |
| 2a-04 Device Registration | 3 | 2 | 1 |
| 2a-05 Notification Prefs | 3 | 1 | 1 |
| 2a-06 Push Service | 8 | 1 | 1 |
| 2a-07 Scheduled Commands | 2 | 1 | 2 |
| 2a-08 Deep Links | 2 | 0 | 0 |
| 2a-09 CORS + Middleware | 0 | 2 | 0 |
| 2a-10 Vuex + Platform | 3 | 2 | 0 |
| 2a-11 Social Share | 5 | 1 | 2 |
| 2a-12 Integration | 1 | 0 | 0 |
| **Total** | **~38** | **~13** | **~10** |
