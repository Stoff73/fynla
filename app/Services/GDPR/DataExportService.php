<?php

declare(strict_types=1);

namespace App\Services\GDPR;

use App\Models\AuditLog;
use App\Models\DataExport;
use App\Models\User;
use App\Services\Audit\AuditService;
use Illuminate\Support\Facades\Storage;

class DataExportService
{
    public function __construct(
        private readonly AuditService $auditService
    ) {}

    /**
     * Request a data export for a user
     */
    public function requestExport(User $user, string $format = DataExport::FORMAT_JSON): DataExport
    {
        // Check for existing pending/processing export
        $existingExport = DataExport::where('user_id', $user->id)
            ->whereIn('status', [DataExport::STATUS_PENDING, DataExport::STATUS_PROCESSING])
            ->first();

        if ($existingExport) {
            return $existingExport;
        }

        $export = DataExport::createRequest($user->id, $format);

        // Audit log
        $this->auditService->logGDPR(AuditLog::ACTION_EXPORT_REQUESTED, $user->id);

        return $export;
    }

    /**
     * Process a pending export request
     */
    public function processExport(DataExport $export): void
    {
        $export->markProcessing();

        try {
            $user = $export->user;
            $data = $this->gatherUserData($user);

            $filename = 'exports/user_'.$user->id.'_'.now()->format('Y-m-d_His').'.'.$export->format;

            if ($export->format === DataExport::FORMAT_JSON) {
                $content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            } else {
                $content = $this->convertToCsv($data);
            }

            Storage::put($filename, $content);
            $fileSize = Storage::size($filename);

            $export->markCompleted($filename, $fileSize);

            // Audit log
            $this->auditService->logGDPR(AuditLog::ACTION_EXPORT_COMPLETED, $user->id, [
                'export_id' => $export->id,
                'format' => $export->format,
            ]);
        } catch (\Exception $e) {
            $export->markFailed();
            throw $e;
        }
    }

    /**
     * Get the export file for download
     */
    public function getExportFile(DataExport $export): ?string
    {
        if (! $export->isDownloadable()) {
            return null;
        }

        $export->markDownloaded();

        return Storage::path($export->file_path);
    }

    /**
     * Gather all user data for export
     */
    private function gatherUserData(User $user): array
    {
        return [
            'export_date' => now()->toIso8601String(),
            'user' => $this->exportUserProfile($user),
            'family_members' => $this->exportFamilyMembers($user),
            'properties' => $this->exportProperties($user),
            'mortgages' => $this->exportMortgages($user),
            'savings_accounts' => $this->exportSavingsAccounts($user),
            'investment_accounts' => $this->exportInvestmentAccounts($user),
            'pensions' => [
                'dc_pensions' => $this->exportDCPensions($user),
                'db_pensions' => $this->exportDBPensions($user),
                'state_pension' => $this->exportStatePension($user),
            ],
            'protection_policies' => [
                'life' => $this->exportLifePolicies($user),
                'critical_illness' => $this->exportCriticalIllnessPolicies($user),
                'income_protection' => $this->exportIncomeProtectionPolicies($user),
            ],
            'business_interests' => $this->exportBusinessInterests($user),
            'chattels' => $this->exportChattels($user),
            'goals' => $this->exportGoals($user),
            'consents' => $this->exportConsents($user),
            'audit_logs' => $this->exportAuditLogs($user),
        ];
    }

    private function exportUserProfile(User $user): array
    {
        return [
            'id' => $user->id,
            'email' => $user->email,
            'first_name' => $user->first_name,
            'middle_name' => $user->middle_name,
            'surname' => $user->surname,
            'date_of_birth' => $user->date_of_birth?->toDateString(),
            'gender' => $user->gender,
            'country' => $user->country,
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }

    private function exportFamilyMembers(User $user): array
    {
        return $user->familyMembers()->get()->map(fn ($m) => $m->toArray())->toArray();
    }

    private function exportProperties(User $user): array
    {
        return $user->properties()->get()->map(fn ($p) => $p->toArray())->toArray();
    }

    private function exportMortgages(User $user): array
    {
        return $user->mortgages()->get()->map(fn ($m) => $m->toArray())->toArray();
    }

    private function exportSavingsAccounts(User $user): array
    {
        return $user->savingsAccounts()->get()->map(fn ($s) => $s->toArray())->toArray();
    }

    private function exportInvestmentAccounts(User $user): array
    {
        return $user->investmentAccounts()->with('holdings')->get()->map(fn ($i) => $i->toArray())->toArray();
    }

    private function exportDCPensions(User $user): array
    {
        return $user->dcPensions()->get()->map(fn ($p) => $p->toArray())->toArray();
    }

    private function exportDBPensions(User $user): array
    {
        return $user->dbPensions()->get()->map(fn ($p) => $p->toArray())->toArray();
    }

    private function exportStatePension(User $user): ?array
    {
        return $user->statePension?->toArray();
    }

    private function exportLifePolicies(User $user): array
    {
        return $user->lifeInsurancePolicies()->get()->map(fn ($p) => $p->toArray())->toArray();
    }

    private function exportCriticalIllnessPolicies(User $user): array
    {
        return $user->criticalIllnessPolicies()->get()->map(fn ($p) => $p->toArray())->toArray();
    }

    private function exportIncomeProtectionPolicies(User $user): array
    {
        return $user->incomeProtectionPolicies()->get()->map(fn ($p) => $p->toArray())->toArray();
    }

    private function exportBusinessInterests(User $user): array
    {
        return $user->businessInterests()->get()->map(fn ($b) => $b->toArray())->toArray();
    }

    private function exportChattels(User $user): array
    {
        return $user->chattels()->get()->map(fn ($c) => $c->toArray())->toArray();
    }

    private function exportGoals(User $user): array
    {
        return $user->goals()->withTrashed()->get()->map(fn ($g) => $g->toArray())->toArray();
    }

    private function exportConsents(User $user): array
    {
        return $user->consents()->get()->map(fn ($c) => $c->toArray())->toArray();
    }

    private function exportAuditLogs(User $user): array
    {
        return AuditLog::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($l) => $l->toArray())
            ->toArray();
    }

    private function convertToCsv(array $data): string
    {
        // Flatten the nested structure for CSV export
        $lines = [];
        $lines[] = 'Category,Field,Value';

        foreach ($data as $category => $items) {
            if (is_array($items)) {
                $this->flattenForCsv($lines, $category, $items);
            } else {
                $lines[] = $this->csvLine($category, 'value', $items);
            }
        }

        return implode("\n", $lines);
    }

    private function flattenForCsv(array &$lines, string $prefix, array $data): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $this->flattenForCsv($lines, $prefix.'.'.$key, $value);
            } else {
                $lines[] = $this->csvLine($prefix, (string) $key, $value);
            }
        }
    }

    private function csvLine(string $category, string $field, $value): string
    {
        $escapedValue = str_replace('"', '""', (string) $value);

        return '"'.$category.'","'.$field.'","'.$escapedValue.'"';
    }

    /**
     * Clean up expired exports
     */
    public function cleanupExpiredExports(): int
    {
        $expiredExports = DataExport::expired()->get();
        $count = 0;

        foreach ($expiredExports as $export) {
            if ($export->file_path && Storage::exists($export->file_path)) {
                Storage::delete($export->file_path);
            }
            $export->markExpired();
            $count++;
        }

        return $count;
    }
}
