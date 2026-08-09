<?php

declare(strict_types=1);

namespace App\Services\Pipeline\Content;

use App\Services\Pipeline\Google\GoogleOAuthClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Exports a native Google Doc as a .docx file so WordDocxIngestor can
 * parse it with a single code path. Called by the ingest command when
 * it sees a file with mimeType 'application/vnd.google-apps.document'
 * in the Marketing Automation ▸ Articles folder.
 */
class GoogleDocExporter
{
    private const EXPORT_MIME = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    private const API_ROOT = 'https://www.googleapis.com/drive/v3';

    public function __construct(private readonly GoogleOAuthClient $oauth) {}

    /**
     * Export the given Google Doc to a local .docx file. Returns the
     * local path on success.
     */
    public function exportToDocx(string $fileId, string $localPath): string
    {
        $token = $this->oauth->accessToken();

        $dir = dirname($localPath);
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException("Failed to create export dir: {$dir}");
        }

        $response = Http::withToken($token)
            ->timeout(120)
            ->withOptions(['sink' => $localPath])
            ->get(self::API_ROOT.'/files/'.$fileId.'/export', [
                'mimeType' => self::EXPORT_MIME,
            ]);

        if (! $response->successful()) {
            @unlink($localPath);
            Log::channel('pipeline')->error('Google Doc export failed.', [
                'file_id' => $fileId,
                'status' => $response->status(),
            ]);
            throw new RuntimeException('Google Doc export failed: HTTP '.$response->status());
        }

        return $localPath;
    }
}
