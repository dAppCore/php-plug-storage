<?php

declare(strict_types=1);

namespace Core\Plug\Storage\Bunny;

use Core\Crypt\LthnHash;
use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Response;
use Bunny\Storage\Client;
use Illuminate\Support\Facades\Log;

/**
 * Bunny Storage vBucket operations for workspace-isolated paths.
 *
 * vBuckets provide workspace isolation on CDN:
 * - Each workspace gets a unique vBucket ID
 * - All paths are automatically scoped to the vBucket
 * - Prevents cross-workspace data access
 *
 * @example
 * $vbucket = new VBucket('example.com');
 * $vbucket->upload('/local/file.jpg', 'images/hero.jpg');
 * // Uploads to: /{vbucket_id}/images/hero.jpg
 */
class VBucket
{
    use BuildsResponse;

    protected string $domain;

    protected string $vBucketId;

    protected string $apiKey;

    protected string $storageZone;

    protected string $region;

    protected ?Client $client = null;

    public function __construct(
        string $domain,
        ?string $apiKey = null,
        ?string $storageZone = null,
        ?string $region = null,
        string $zone = 'public'
    ) {
        $this->domain = $domain;
        $this->vBucketId = LthnHash::vBucketId($domain);
        $this->apiKey = $apiKey ?? config("cdn.bunny.{$zone}.api_key", '');
        $this->storageZone = $storageZone ?? config("cdn.bunny.{$zone}.storage_zone", '');
        $this->region = $region ?? config("cdn.bunny.{$zone}.region", Client::STORAGE_ZONE_FS_EU);
    }

    /**
     * Check if the service is configured.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->apiKey) && ! empty($this->storageZone);
    }

    /**
     * Get the Bunny Storage client.
     */
    protected function client(): ?Client
    {
        if ($this->client === null && $this->isConfigured()) {
            $this->client = new Client($this->apiKey, $this->storageZone, $this->region);
        }

        return $this->client;
    }

    /**
     * Get the vBucket ID for this domain.
     */
    public function id(): string
    {
        return $this->vBucketId;
    }

    /**
     * Get the domain for this vBucket.
     */
    public function domain(): string
    {
        return $this->domain;
    }

    /**
     * Build a vBucket-scoped path.
     */
    public function path(string $path): string
    {
        return $this->vBucketId.'/'.ltrim($path, '/');
    }

    /**
     * Upload a file to vBucket-scoped path.
     */
    public function upload(string $localPath, string $remotePath): Response
    {
        if (! $this->isConfigured()) {
            return $this->error('Bunny Storage not configured');
        }

        if (! file_exists($localPath)) {
            return $this->error('Local file not found', ['local_path' => $localPath]);
        }

        $scopedPath = $this->path($remotePath);

        try {
            $this->client()->upload($localPath, $scopedPath);

            return $this->ok([
                'uploaded' => true,
                'domain' => $this->domain,
                'vbucket_id' => $this->vBucketId,
                'local_path' => $localPath,
                'remote_path' => $scopedPath,
                'size' => filesize($localPath),
            ]);
        } catch (\Exception $e) {
            Log::error('Bunny Storage vBucket: Upload failed', [
                'domain' => $this->domain,
                'local' => $localPath,
                'remote' => $scopedPath,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage());
        }
    }

    /**
     * Upload content directly to vBucket-scoped path.
     */
    public function putContents(string $remotePath, string $contents): Response
    {
        if (! $this->isConfigured()) {
            return $this->error('Bunny Storage not configured');
        }

        $scopedPath = $this->path($remotePath);

        try {
            $this->client()->putContents($scopedPath, $contents);

            return $this->ok([
                'uploaded' => true,
                'domain' => $this->domain,
                'vbucket_id' => $this->vBucketId,
                'remote_path' => $scopedPath,
                'size' => strlen($contents),
            ]);
        } catch (\Exception $e) {
            Log::error('Bunny Storage vBucket: putContents failed', [
                'domain' => $this->domain,
                'remote' => $scopedPath,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage());
        }
    }

    /**
     * Get file contents from vBucket-scoped path.
     */
    public function getContents(string $remotePath): Response
    {
        if (! $this->isConfigured()) {
            return $this->error('Bunny Storage not configured');
        }

        $scopedPath = $this->path($remotePath);

        try {
            $contents = $this->client()->getContents($scopedPath);

            return $this->ok([
                'contents' => $contents,
                'domain' => $this->domain,
                'vbucket_id' => $this->vBucketId,
                'remote_path' => $scopedPath,
                'size' => strlen($contents),
            ]);
        } catch (\Exception $e) {
            Log::error('Bunny Storage vBucket: getContents failed', [
                'domain' => $this->domain,
                'remote' => $scopedPath,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage());
        }
    }

    /**
     * Delete file from vBucket-scoped path.
     */
    public function delete(string $remotePath): Response
    {
        if (! $this->isConfigured()) {
            return $this->error('Bunny Storage not configured');
        }

        $scopedPath = $this->path($remotePath);

        try {
            $this->client()->delete($scopedPath);

            return $this->ok([
                'deleted' => true,
                'domain' => $this->domain,
                'vbucket_id' => $this->vBucketId,
                'path' => $scopedPath,
            ]);
        } catch (\Exception $e) {
            Log::error('Bunny Storage vBucket: Delete failed', [
                'domain' => $this->domain,
                'remote' => $scopedPath,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage());
        }
    }

    /**
     * List files in vBucket-scoped path.
     */
    public function list(string $path = ''): Response
    {
        if (! $this->isConfigured()) {
            return $this->error('Bunny Storage not configured');
        }

        $scopedPath = $this->path($path);

        try {
            $files = $this->client()->listFiles($scopedPath);

            return $this->ok([
                'path' => $scopedPath,
                'domain' => $this->domain,
                'vbucket_id' => $this->vBucketId,
                'files' => $files,
                'count' => count($files),
            ]);
        } catch (\Exception $e) {
            Log::error('Bunny Storage vBucket: List failed', [
                'domain' => $this->domain,
                'path' => $scopedPath,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage());
        }
    }

    /**
     * Check if file exists in vBucket-scoped path.
     */
    public function exists(string $path): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $scopedPath = $this->path($path);

        try {
            $this->client()->getContents($scopedPath);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Create a vBucket for a workspace (convenience method).
     */
    public static function forWorkspace(string $workspaceUuid, string $zone = 'public'): self
    {
        return new self($workspaceUuid, zone: $zone);
    }

    /**
     * Create a public zone vBucket.
     */
    public static function public(string $domain): self
    {
        return new self($domain, zone: 'public');
    }

    /**
     * Create a private zone vBucket.
     */
    public static function private(string $domain): self
    {
        return new self($domain, zone: 'private');
    }
}
