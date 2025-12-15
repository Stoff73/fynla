<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\SafeErrorResponse;
use App\Models\User;
use App\Services\Admin\DatabaseMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    use SafeErrorResponse;

    public function __construct(private DatabaseMetricsService $databaseMetrics) {}

    /**
     * Get admin dashboard statistics
     */
    public function dashboard(): JsonResponse
    {
        try {
            $stats = [
                'total_users' => User::count(),
                'admin_users' => User::where('is_admin', true)->count(),
                'linked_spouses' => User::whereNotNull('spouse_id')->count() / 2, // Divided by 2 since it's bidirectional
                'recent_users' => User::latest()->take(5)->get(['id', 'name', 'email', 'created_at']),
                'database_size' => $this->databaseMetrics->getDatabaseSize(),
                'last_backup' => $this->getLastBackupTime(),
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
     * Get all users with pagination
     */
    public function getUsers(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'per_page' => 'sometimes|integer|min:1|max:100',
                'search' => 'sometimes|string|max:100',
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

            $query = User::with('spouse:id,name,email');

            if ($search) {
                $search = substr($search, 0, 100); // Extra safety: truncate to max 100 chars
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
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
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).+$/',
            ],
            'is_admin' => 'boolean',
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
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'is_admin' => $request->is_admin ?? false,
            ]);

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
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,'.$id,
            'password' => [
                'sometimes',
                'string',
                'min:8',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).+$/',
            ],
            'is_admin' => 'sometimes|boolean',
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
            if ($request->has('name')) {
                $user->name = $request->name;
            }
            if ($request->has('email')) {
                $user->email = $request->email;
            }
            if ($request->has('password')) {
                $user->password = Hash::make($request->password);
            }
            if ($request->has('is_admin')) {
                $user->is_admin = $request->is_admin;
            }

            $user->save();

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
        if ($user->is_admin && User::where('is_admin', true)->count() === 1) {
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
