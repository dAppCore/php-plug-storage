<?php

declare(strict_types=1);

namespace Core\Plug\Storage\Bunny;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Response;
use Core\Plug\Storage\Contract\Browseable;
use Bunny\Storage\Client;
use Illuminate\Support\Facades\Log;

/**
 * Bunny Storage browsing operations.
 *
 * Supports:
 * - List files in directory
 * - Check file existence
 * - Get file size
 * - Public and private storage zones
 */
class Browse implements Browseable
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
     * List files in a path.
     */
    public function list(string $path = '/'): Response
    {
        if (! $this->isConfigured()) {
            return $this->error('Bunny Storage not configured');
        }

        try {
            $files = $this->client()->listFiles($path);

            return $this->ok([
                'path' => $path,
                'files' => $files,
                'count' => count($files),
            ]);
        } catch (\Exception $e) {
            Log::error('Bunny Storage: List failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage());
        }
    }

    /**
     * Check if a file exists.
     */
    public function exists(string $path): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            // Try to get the file contents - if it fails, file doesn't exist
            $this->client()->getContents($path);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get file size in bytes.
     */
    public function size(string $path): ?int
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $contents = $this->client()->getContents($path);

            return strlen($contents);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Create a browser for the public storage zone.
     */
    public static function public(): self
    {
        return new self(zone: 'public');
    }

    /**
     * Create a browser for the private storage zone.
     */
    public static function private(): self
    {
        return new self(zone: 'private');
    }
}
