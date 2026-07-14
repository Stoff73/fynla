<?php

declare(strict_types=1);

namespace App\Services\Pipeline\Google;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Thin wrapper around Google Drive REST v3. Uploads a Markdown-formatted
 * string as a native Google Doc into a specific folder, returning the file
 * ID and shareable link.
 *
 * Uses OAuth (via GoogleOAuthClient) — access tokens are refreshed on demand.
 */
class GoogleDriveService
{
    private const API_ROOT = 'https://www.googleapis.com/drive/v3';

    private const UPLOAD_ROOT = 'https://www.googleapis.com/upload/drive/v3';

    public function __construct(private readonly GoogleOAuthClient $oauth) {}

    /**
     * Upload the given Markdown content as a Google Doc in the target folder.
     * Google performs the Markdown → Doc conversion server-side.
     *
     * @return array{id: string, webViewLink: string}
     */
    public function uploadMarkdownAsGoogleDoc(string $title, string $markdown, ?string $folderId = null): array
    {
        $folderId ??= $this->requireFolderId();
        $token = $this->oauth->accessToken();

        $metadata = [
            'name' => $title,
            'mimeType' => 'application/vnd.google-apps.document',
            'parents' => [$folderId],
        ];

        $boundary = 'fynla-'.bin2hex(random_bytes(8));
        $body = $this->multipartBody($metadata, $markdown, 'text/markdown', $boundary);

        $response = Http::withToken($token)
            ->withHeaders([
                'Content-Type' => 'multipart/related; boundary='.$boundary,
                'Content-Length' => (string) strlen($body),
            ])
            ->withBody($body, 'multipart/related; boundary='.$boundary)
            ->timeout(60)
            ->retry(2, 500, function ($e) {
                return $e instanceof \Illuminate\Http\Client\ConnectionException;
            })
            ->post(self::UPLOAD_ROOT.'/files?uploadType=multipart&supportsAllDrives=true&fields=id,name,webViewLink');

        if (! $response->successful()) {
            Log::channel('pipeline')->error('Drive upload failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
                'title' => $title,
            ]);
            throw new RuntimeException('Google Drive upload failed: HTTP '.$response->status().' — '.$response->body());
        }

        $payload = $response->json();

        return [
            'id' => $payload['id'],
            'webViewLink' => $payload['webViewLink'] ?? 'https://docs.google.com/document/d/'.$payload['id'].'/edit',
        ];
    }

    /**
     * Move a Drive file into the target folder (used to place a newly-created
     * spreadsheet inside Marketing Automation).
     */
    public function moveToFolder(string $fileId, string $folderId): void
    {
        $token = $this->oauth->accessToken();

        $current = Http::withToken($token)
            ->timeout(30)
            ->get(self::API_ROOT.'/files/'.$fileId, [
                'fields' => 'parents',
                'supportsAllDrives' => 'true',
            ]);

        if (! $current->successful()) {
            throw new RuntimeException('Failed to fetch Drive file parents: HTTP '.$current->status());
        }

        $existingParents = $current->json('parents', []);
        $removeParents = implode(',', array_filter($existingParents));

        $response = Http::withToken($token)
            ->timeout(30)
            ->patch(self::API_ROOT.'/files/'.$fileId.'?'.http_build_query([
                'addParents' => $folderId,
                'removeParents' => $removeParents,
                'supportsAllDrives' => 'true',
                'fields' => 'id,parents',
            ]));

        if (! $response->successful()) {
            Log::channel('pipeline')->error('Drive moveToFolder failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
                'file_id' => $fileId,
                'folder_id' => $folderId,
            ]);
            throw new RuntimeException('Google Drive move failed: HTTP '.$response->status());
        }
    }

    private function multipartBody(array $metadata, string $content, string $contentType, string $boundary): string
    {
        return
            "--{$boundary}\r\n".
            "Content-Type: application/json; charset=UTF-8\r\n\r\n".
            json_encode($metadata, JSON_UNESCAPED_SLASHES)."\r\n".
            "--{$boundary}\r\n".
            "Content-Type: {$contentType}; charset=UTF-8\r\n\r\n".
            $content."\r\n".
            "--{$boundary}--";
    }

    private function requireFolderId(): string
    {
        $folder = config('pipeline.google.drive_folder_id');
        if (! is_string($folder) || $folder === '') {
            throw new RuntimeException('PIPELINE_GOOGLE_DRIVE_FOLDER_ID is not set.');
        }

        return $folder;
    }
}
