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

    /**
     * List files matching the given mime type inside a folder. Used by
     * pipeline:detect-new-videos.
     *
     * @return list<array{id:string,name:string,mimeType:string,webViewLink?:string,size?:int}>
     */
    public function listFiles(string $folderId, ?string $mimeType = null, int $pageSize = 100): array
    {
        $token = $this->oauth->accessToken();

        $query = "'{$folderId}' in parents and trashed=false";
        if ($mimeType !== null) {
            $query .= " and mimeType='{$mimeType}'";
        }

        $response = Http::withToken($token)
            ->timeout(30)
            ->get(self::API_ROOT.'/files', [
                'q' => $query,
                'fields' => 'files(id,name,mimeType,webViewLink,size,modifiedTime)',
                'pageSize' => $pageSize,
                'orderBy' => 'modifiedTime desc',
                'supportsAllDrives' => 'true',
                'includeItemsFromAllDrives' => 'true',
            ]);

        if (! $response->successful()) {
            Log::channel('pipeline')->error('Drive listFiles failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
                'folder_id' => $folderId,
            ]);
            throw new RuntimeException('Google Drive list failed: HTTP '.$response->status());
        }

        return array_values($response->json('files', []));
    }

    /**
     * Find the first sub-folder with the given name inside a parent folder.
     * Returns null if not found. Used to auto-discover the "Videos" folder
     * under Marketing Automation without hard-coding another env var.
     */
    public function findSubfolder(string $parentFolderId, string $name): ?string
    {
        $token = $this->oauth->accessToken();

        $q = "'{$parentFolderId}' in parents and mimeType='application/vnd.google-apps.folder' "
            ."and name='".addslashes($name)."' and trashed=false";

        $response = Http::withToken($token)
            ->timeout(30)
            ->get(self::API_ROOT.'/files', [
                'q' => $q,
                'fields' => 'files(id,name)',
                'pageSize' => 5,
                'supportsAllDrives' => 'true',
                'includeItemsFromAllDrives' => 'true',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Google Drive findSubfolder failed: HTTP '.$response->status());
        }

        $files = $response->json('files', []);

        return $files[0]['id'] ?? null;
    }

    /**
     * A cursor marking "now" in the Drive change stream. Store it, then feed it
     * to listChanges() later to get everything that changed since.
     */
    public function getStartPageToken(): string
    {
        $response = Http::withToken($this->oauth->accessToken())
            ->timeout(30)
            ->get(self::API_ROOT.'/changes/startPageToken', [
                'supportsAllDrives' => 'true',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Drive getStartPageToken failed: HTTP '.$response->status());
        }

        return (string) $response->json('startPageToken');
    }

    /**
     * Register a push-notification channel: Google POSTs $address whenever
     * anything in the Drive changes. $token is echoed back in every ping's
     * X-Goog-Channel-Token header so the receiver can verify authenticity.
     *
     * @return array{id:string,resourceId:string,expiration:?int}
     */
    public function watchChanges(string $channelId, string $pageToken, string $address, string $token, int $ttlSeconds = 604800): array
    {
        $response = Http::withToken($this->oauth->accessToken())
            ->timeout(30)
            ->post(self::API_ROOT.'/changes/watch?'.http_build_query([
                'pageToken' => $pageToken,
                'supportsAllDrives' => 'true',
                'includeItemsFromAllDrives' => 'true',
            ]), [
                'id' => $channelId,
                'type' => 'web_hook',
                'address' => $address,
                'token' => $token,
                'expiration' => (string) ((time() + $ttlSeconds) * 1000),
            ]);

        if (! $response->successful()) {
            Log::channel('pipeline')->error('Drive watchChanges failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('Drive watchChanges failed: HTTP '.$response->status());
        }

        return [
            'id' => (string) $response->json('id'),
            'resourceId' => (string) $response->json('resourceId'),
            'expiration' => $response->json('expiration') !== null ? (int) $response->json('expiration') : null,
        ];
    }

    /**
     * List everything that changed since $pageToken. Each change includes the
     * file's id/name/mimeType/parents so callers can route it.
     *
     * @return array{changes:list<array<string,mixed>>,newStartPageToken:?string,nextPageToken:?string}
     */
    public function listChanges(string $pageToken): array
    {
        $response = Http::withToken($this->oauth->accessToken())
            ->timeout(30)
            ->get(self::API_ROOT.'/changes', [
                'pageToken' => $pageToken,
                'fields' => 'newStartPageToken,nextPageToken,changes(fileId,removed,file(id,name,mimeType,parents,trashed))',
                'pageSize' => 100,
                'supportsAllDrives' => 'true',
                'includeItemsFromAllDrives' => 'true',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Drive listChanges failed: HTTP '.$response->status());
        }

        return [
            'changes' => array_values($response->json('changes', [])),
            'newStartPageToken' => $response->json('newStartPageToken'),
            'nextPageToken' => $response->json('nextPageToken'),
        ];
    }

    /**
     * Stop a previously-registered push channel.
     */
    public function stopChannel(string $channelId, string $resourceId): void
    {
        Http::withToken($this->oauth->accessToken())
            ->timeout(30)
            ->post(self::API_ROOT.'/channels/stop', [
                'id' => $channelId,
                'resourceId' => $resourceId,
            ]);
    }

    /**
     * Download a Drive file's binary content to a local path. Streams to
     * avoid loading multi-GB videos into memory.
     */
    public function downloadFile(string $fileId, string $localPath): void
    {
        $token = $this->oauth->accessToken();

        $dir = dirname($localPath);
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException("Failed to create directory for download: {$dir}");
        }

        $response = Http::withToken($token)
            ->timeout(600)
            ->withOptions([
                'sink' => $localPath,
            ])
            ->get(self::API_ROOT.'/files/'.$fileId.'?alt=media&supportsAllDrives=true');

        if (! $response->successful()) {
            @unlink($localPath);
            Log::channel('pipeline')->error('Drive download failed.', [
                'status' => $response->status(),
                'file_id' => $fileId,
                'local_path' => $localPath,
            ]);
            throw new RuntimeException('Google Drive download failed: HTTP '.$response->status());
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
