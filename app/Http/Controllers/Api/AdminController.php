<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Admin\DatabaseMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(private DatabaseMetricsService $databaseMetrics)
    {
        // Defence-in-depth: explicit admin check in addition to route middleware
        $this->middleware(function ($request, $next) {
            if (! $request->user()?->is_admin) {
                abort(403, 'Admin access required');
            }

            return $next($request);
        });
    }

    /**
     * Get admin dashboard statistics
     */
    public function dashboard(): JsonResponse
    {
        try {
            $realUsers = User::where('is_preview_user', false);
            $adminRoleId = Role::findByName(Role::ROLE_ADMIN)?->id;
            $stats = [
                'total_users' => (clone $realUsers)->count(),
                'admin_users' => $adminRoleId ? (clone $realUsers)->where('role_id', $adminRoleId)->count() : 0,
                'linked_spouses' => (clone $realUsers)->whereNotNull('spouse_id')->count() / 2,
                'recent_users' => (clone $realUsers)->latest()->take(5)->get(['id', 'first_name', 'surname', 'email', 'created_at']),
                'database_size' => $this->databaseMetrics->getDatabaseSize(),
                'last_backup' => $this->getLastBackupTime(),
                'table_statistics' => $this->databaseMetrics->getTableStatistics(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return $this->safeErrorResponse('Failed to load dashboard', $e);
        }
    }

    /**
     * Get subscription and trial statistics for admin dashboard.
     */
    public function getSubscriptionStats(): JsonResponse
    {
        try {
            $stats = [
                'trialing' => Subscription::where('status', 'trialing')->count(),
                'active' => Subscription::where('status', 'active')->count(),
                'expired' => Subscription::where('status', 'expired')->count(),
                'cancelled' => Subscription::where('status', 'cancelled')->count(),
                'total_revenue' => Payment::where('status', 'completed')->sum('amount'),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return $this->safeErrorResponse('Failed to load subscription stats', $e);
        }
    }

    /**
     * Get all users with pagination
     */
    public function getUsers(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'per_page' => 'sometimes|integer|min:1|max:100',
                'search' => 'sometimes|nullable|string|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $perPage = min((int) $request->query('per_page', 15), 100);
            $search = $request->query('search');

            $query = User::with(['role', 'spouse:id,first_name,surname,email', 'subscription', 'subscription.payments'])
                ->where('is_preview_user', false);

            if ($search) {
                $search = substr($search, 0, 100); // Extra safety: truncate to max 100 chars
                // Escape LIKE wildcards to prevent unintended matches
                $search = str_replace(['%', '_'], ['\\%', '\\_'], $search);
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('surname', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $users = $query->latest()->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $users,
            ]);
        } catch (\Exception $e) {
            return $this->safeErrorResponse('Failed to fetch users', $e);
        }
    }

    /**
     * Create a new user account
     */
    public function createUser(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'surname' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).+$/',
            ],
            'role_id' => 'sometimes|exists:roles,id',
        ], [
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $role = $request->role_id
                ? Role::find($request->role_id)
                : Role::findByName(Role::ROLE_USER);

            $user = new User();
            $user->first_name = $request->first_name;
            $user->surname = $request->surname;
            $user->name = trim($request->first_name.' '.$request->surname);
            $user->email = $request->email;
            $user->password = Hash::make($request->password);
            $user->role_id = $role?->id;
            $user->is_admin = $role?->name === Role::ROLE_ADMIN;
            $user->save();

            $user->load('role');

            return response()->json([
                'success' => true,
                'message' => 'User created successfully',
                'data' => $user,
            ], 201);
        } catch (\Exception $e) {
            return $this->safeErrorResponse('Failed to create user', $e);
        }
    }

    /**
     * Update user details
     */
    public function updateUser(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|string|max:255',
            'surname' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,'.$id,
            'password' => [
                'sometimes',
                'string',
                'min:8',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).+$/',
            ],
            'role_id' => 'sometimes|exists:roles,id',
        ], [
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            if ($request->has('first_name')) {
                $user->first_name = $request->first_name;
            }
            if ($request->has('surname')) {
                $user->surname = $request->surname;
            }
            if ($request->has('first_name') || $request->has('surname')) {
                $user->name = trim(($user->first_name ?? '').' '.($user->surname ?? ''));
            }
            if ($request->has('email')) {
                $user->email = $request->email;
            }
            if ($request->has('password')) {
                $user->password = Hash::make($request->password);
            }
            if ($request->has('role_id')) {
                $role = Role::find($request->role_id);
                $user->role_id = $role?->id;
                $user->is_admin = $role?->name === Role::ROLE_ADMIN;
            }

            $user->save();
            $user->load('role');

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
                'data' => $user,
            ]);
        } catch (\Exception $e) {
            return $this->safeErrorResponse('Failed to update user', $e);
        }
    }

    /**
     * Delete a user account
     */
    public function deleteUser(int $id): JsonResponse
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        // Prevent deleting the last admin
        $adminRoleId = Role::findByName(Role::ROLE_ADMIN)?->id;
        if ($adminRoleId && $user->role_id === $adminRoleId && User::where('role_id', $adminRoleId)->count() === 1) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete the last admin user',
            ], 422);
        }

        try {
            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully',
            ]);
        } catch (\Exception $e) {
            return $this->safeErrorResponse('Failed to delete user', $e);
        }
    }

    /**
     * Get available roles
     */
    public function getRoles(): JsonResponse
    {
        try {
            $roles = Role::orderBy('level')->get(['id', 'name', 'display_name', 'level']);

            return response()->json([
                'success' => true,
                'data' => $roles,
            ]);
        } catch (\Exception $e) {
            return $this->safeErrorResponse('Failed to fetch roles', $e);
        }
    }

    /**
     * Create database backup
     */
    public function createBackup(): JsonResponse
    {
        try {
            $filename = 'backup_'.date('Y-m-d_H-i-s').'.sql';
            $path = storage_path('app/backups');

            // Create backups directory if it doesn't exist
            if (! file_exists($path)) {
                mkdir($path, 0750, true); // Owner + group only (more secure)
            }

            $fullPath = $path.'/'.$filename;

            // Get database credentials
            $database = config('database.connections.'.config('database.default'));

            // Create temporary my.cnf file with credentials (secure method)
            $configFile = storage_path('app/backups/.my.cnf.'.uniqid());
            $configContent = sprintf(
                "[client]\nhost=%s\nuser=%s\npassword=%s\n",
                $database['host'],
                $database['username'],
                $database['password']
            );
            file_put_contents($configFile, $configContent);
            chmod($configFile, 0600); // Secure permissions - owner read/write only

            // Create mysqldump command using config file (password not visible in process list)
            $command = sprintf(
                'mysqldump --defaults-extra-file=%s %s > %s',
                escapeshellarg($configFile),
                escapeshellarg($database['database']),
                escapeshellarg($fullPath)
            );

            // Execute backup
            exec($command, $output, $returnCode);

            // Clean up temporary config file immediately
            if (file_exists($configFile)) {
                unlink($configFile);
            }

            if ($returnCode !== 0) {
                throw new \Exception('Backup command failed with code: '.$returnCode);
            }

            // Get file size
            $fileSize = filesize($fullPath);

            return response()->json([
                'success' => true,
                'message' => 'Database backup created successfully',
                'data' => [
                    'filename' => $filename,
                    'path' => $fullPath,
                    'size' => $this->databaseMetrics->formatBytes($fileSize),
                    'created_at' => date('Y-m-d H:i:s'),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->safeErrorResponse('Failed to create backup', $e);
        }
    }

    /**
     * List all backups
     */
    public function listBackups(): JsonResponse
    {
        try {
            $path = storage_path('app/backups');

            if (! file_exists($path)) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                ]);
            }

            $files = array_diff(scandir($path), ['.', '..']);
            $backups = [];

            foreach ($files as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
                    $fullPath = $path.'/'.$file;
                    $backups[] = [
                        'filename' => $file,
                        'size' => $this->databaseMetrics->formatBytes(filesize($fullPath)),
                        'created_at' => date('Y-m-d H:i:s', filemtime($fullPath)),
                        'path' => $fullPath,
                    ];
                }
            }

            // Sort by creation time, newest first
            usort($backups, function ($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });

            return response()->json([
                'success' => true,
                'data' => $backups,
            ]);
        } catch (\Exception $e) {
            return $this->safeErrorResponse('Failed to list backups', $e);
        }
    }

    /**
     * Restore database from backup
     */
    public function restoreBackup(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'filename' => ['required', 'string', 'regex:/^backup_[\d\-_]+\.sql$/'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $filename = basename($request->filename); // Prevent path traversal
            $backupsDir = storage_path('app/backups');
            $path = $backupsDir.'/'.$filename;

            // Security: Verify the resolved path is within the backups directory
            $realPath = realpath($path);
            $realBackupsDir = realpath($backupsDir);
            if ($realPath === false || $realBackupsDir === false || ! str_starts_with($realPath, $realBackupsDir)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid backup file path',
                ], 403);
            }

            if (! file_exists($path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Backup file not found',
                ], 404);
            }

            // Get database credentials
            $database = config('database.connections.'.config('database.default'));

            // Create temporary my.cnf file with credentials (secure method - same as backup)
            $configFile = storage_path('app/backups/.my.cnf.'.uniqid());
            $configContent = sprintf(
                "[client]\nhost=%s\nuser=%s\npassword=%s\n",
                $database['host'],
                $database['username'],
                $database['password']
            );
            file_put_contents($configFile, $configContent);
            chmod($configFile, 0600); // Secure permissions - owner read/write only

            // Create mysql restore command using config file (password not visible in process list)
            $command = sprintf(
                'mysql --defaults-extra-file=%s %s < %s',
                escapeshellarg($configFile),
                escapeshellarg($database['database']),
                escapeshellarg($path)
            );

            // Execute restore
            exec($command, $output, $returnCode);

            // Clean up temporary config file immediately
            if (file_exists($configFile)) {
                unlink($configFile);
            }

            if ($returnCode !== 0) {
                throw new \Exception('Restore command failed with code: '.$returnCode);
            }

            // Clear caches after restore
            Artisan::call('cache:clear');
            Artisan::call('config:clear');

            return response()->json([
                'success' => true,
                'message' => 'Database restored successfully',
            ]);
        } catch (\Exception $e) {
            return $this->safeErrorResponse('Failed to restore backup', $e);
        }
    }

    /**
     * Delete a backup file
     */
    public function deleteBackup(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'filename' => ['required', 'string', 'regex:/^backup_[\d\-_]+\.sql$/'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $filename = basename($request->filename); // Prevent path traversal
            $backupsDir = storage_path('app/backups');
            $path = $backupsDir.'/'.$filename;

            // Security: Verify the resolved path is within the backups directory
            $realPath = realpath($path);
            $realBackupsDir = realpath($backupsDir);
            if ($realPath === false || $realBackupsDir === false || ! str_starts_with($realPath, $realBackupsDir)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid backup file path',
                ], 403);
            }

            if (! file_exists($path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Backup file not found',
                ], 404);
            }

            unlink($path);

            return response()->json([
                'success' => true,
                'message' => 'Backup deleted successfully',
            ]);
        } catch (\Exception $e) {
            return $this->safeErrorResponse('Failed to delete backup', $e);
        }
    }

    /**
     * Helper: Get last backup time
     */
    private function getLastBackupTime(): ?string
    {
        try {
            $path = storage_path('app/backups');

            if (! file_exists($path)) {
                return null;
            }

            $files = array_diff(scandir($path), ['.', '..']);
            $latestTime = 0;

            foreach ($files as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
                    $time = filemtime($path.'/'.$file);
                    if ($time > $latestTime) {
                        $latestTime = $time;
                    }
                }
            }

            return $latestTime > 0 ? date('Y-m-d H:i:s', $latestTime) : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
