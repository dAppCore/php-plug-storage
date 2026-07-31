<?php

declare(strict_types=1);

namespace Core\Plug\Storage\Bunny;

use Bunny\Storage\Client;
use Bunny\Storage\Region;
use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Response;
use Core\Plug\Storage\Contract\Browseable;
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

    protected readonly string $apiKey;

    protected readonly string $storageZone;

    protected readonly string $region;

    protected ?Client $client = null;

    public function __construct(
        ?string $apiKey = null,
        ?string $storageZone = null,
        ?string $region = null,
        string $zone = 'public'
    ) {
        $this->apiKey = $apiKey ?? config("cdn.bunny.{$zone}.api_key", '');
        $this->storageZone = $storageZone ?? config("cdn.bunny.{$zone}.storage_zone", '');
        $this->region = $region ?? config("cdn.bunny.{$zone}.region", Region::FALKENSTEIN);
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
     *
     * Uses the client's own exists() (a lightweight metadata request), not a
     * full download — the previous implementation fetched the whole file just
     * to throw the bytes away, which is a real cost against a video or a
     * multi-GB archive for what should be a single HEAD-style check.
     */
    public function exists(string $path): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            return $this->client()->exists($path);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get file size in bytes.
     *
     * Uses info() (metadata only) rather than downloading the file to measure
     * strlen() on its contents, for the same reason as exists() above.
     */
    public function size(string $path): ?int
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            return $this->client()->info($path)->getSize();
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
