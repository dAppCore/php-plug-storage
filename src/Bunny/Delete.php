<?php

declare(strict_types=1);

namespace Core\Plug\Storage\Bunny;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Response;
use Core\Plug\Storage\Contract\Deletable;
use Bunny\Storage\Client;
use Illuminate\Support\Facades\Log;

/**
 * Bunny Storage delete operations.
 *
 * Supports:
 * - Single file deletion
 * - Bulk file deletion
 * - Public and private storage zones
 */
class Delete implements Deletable
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
     * Delete a single file.
     */
    public function path(string $remotePath): Response
    {
        if (! $this->isConfigured()) {
            return $this->error('Bunny Storage not configured');
        }

        try {
            $this->client()->delete($remotePath);

            return $this->ok([
                'deleted' => true,
                'path' => $remotePath,
            ]);
        } catch (\Exception $e) {
            Log::error('Bunny Storage: Delete failed', [
                'remote' => $remotePath,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage());
        }
    }

    /**
     * Delete multiple files.
     *
     * @param  array<string>  $remotePaths
     */
    public function paths(array $remotePaths): Response
    {
        if (! $this->isConfigured()) {
            return $this->error('Bunny Storage not configured');
        }

        $failed = [];
        $deleted = [];

        foreach ($remotePaths as $path) {
            try {
                $this->client()->delete($path);
                $deleted[] = $path;
            } catch (\Exception $e) {
                $failed[] = $path;
                Log::error('Bunny Storage: Delete failed', [
                    'remote' => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (! empty($failed)) {
            return $this->error('Some files failed to delete', [
                'deleted' => $deleted,
                'failed' => $failed,
                'total' => count($remotePaths),
            ]);
        }

        return $this->ok([
            'deleted' => count($deleted),
            'paths' => $deleted,
        ]);
    }

    /**
     * Create a deleter for the public storage zone.
     */
    public static function public(): self
    {
        return new self(zone: 'public');
    }

    /**
     * Create a deleter for the private storage zone.
     */
    public static function private(): self
    {
        return new self(zone: 'private');
    }
}
