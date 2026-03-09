<?php

declare(strict_types=1);

namespace Core\Plug\Storage\Bunny;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Response;
use Core\Plug\Storage\Contract\Uploadable;
use Bunny\Storage\Client;
use Illuminate\Support\Facades\Log;

/**
 * Bunny Storage upload operations.
 *
 * Supports:
 * - File uploads from local path
 * - Direct content uploads
 * - Public and private storage zones
 */
class Upload implements Uploadable
{
    use BuildsResponse;

    protected string $apiKey;

    protected string $storageZone;

    protected string $region;

    protected ?Client $client = null;

    public function __construct(
        ?string $apiKey = null,
        ?string $storageZone = null,
        ?string $region = null,
        string $zone = 'public'
    ) {
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
     * Upload a file from local path.
     */
    public function file(string $localPath, string $remotePath): Response
    {
        if (! $this->isConfigured()) {
            return $this->error('Bunny Storage not configured');
        }

        if (! file_exists($localPath)) {
            return $this->error('Local file not found', ['local_path' => $localPath]);
        }

        try {
            $this->client()->upload($localPath, $remotePath);

            return $this->ok([
                'uploaded' => true,
                'local_path' => $localPath,
                'remote_path' => $remotePath,
                'size' => filesize($localPath),
            ]);
        } catch (\Exception $e) {
            Log::error('Bunny Storage: Upload failed', [
                'local' => $localPath,
                'remote' => $remotePath,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage());
        }
    }

    /**
     * Upload content directly.
     */
    public function contents(string $remotePath, string $contents): Response
    {
        if (! $this->isConfigured()) {
            return $this->error('Bunny Storage not configured');
        }

        try {
            $this->client()->putContents($remotePath, $contents);

            return $this->ok([
                'uploaded' => true,
                'remote_path' => $remotePath,
                'size' => strlen($contents),
            ]);
        } catch (\Exception $e) {
            Log::error('Bunny Storage: putContents failed', [
                'remote' => $remotePath,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage());
        }
    }

    /**
     * Create an uploader for the public storage zone.
     */
    public static function public(): self
    {
        return new self(zone: 'public');
    }

    /**
     * Create an uploader for the private storage zone.
     */
    public static function private(): self
    {
        return new self(zone: 'private');
    }
}
