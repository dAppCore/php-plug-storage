<?php

declare(strict_types=1);

namespace Core\Plug\Storage\Bunny;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Response;
use Core\Plug\Storage\Contract\Downloadable;
use Bunny\Storage\Client;
use Illuminate\Support\Facades\Log;

/**
 * Bunny Storage download operations.
 *
 * Supports:
 * - Download file contents as string
 * - Download file to local path
 * - Public and private storage zones
 */
class Download implements Downloadable
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
     * Get file contents as string.
     */
    public function contents(string $remotePath): Response
    {
        if (! $this->isConfigured()) {
            return $this->error('Bunny Storage not configured');
        }

        try {
            $contents = $this->client()->getContents($remotePath);

            return $this->ok([
                'contents' => $contents,
                'remote_path' => $remotePath,
                'size' => strlen($contents),
            ]);
        } catch (\Exception $e) {
            Log::error('Bunny Storage: getContents failed', [
                'remote' => $remotePath,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage());
        }
    }

    /**
     * Download file to local path.
     */
    public function toFile(string $remotePath, string $localPath): Response
    {
        if (! $this->isConfigured()) {
            return $this->error('Bunny Storage not configured');
        }

        try {
            $contents = $this->client()->getContents($remotePath);

            $directory = dirname($localPath);
            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            file_put_contents($localPath, $contents);

            return $this->ok([
                'downloaded' => true,
                'remote_path' => $remotePath,
                'local_path' => $localPath,
                'size' => strlen($contents),
            ]);
        } catch (\Exception $e) {
            Log::error('Bunny Storage: Download to file failed', [
                'remote' => $remotePath,
                'local' => $localPath,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage());
        }
    }

    /**
     * Create a downloader for the public storage zone.
     */
    public static function public(): self
    {
        return new self(zone: 'public');
    }

    /**
     * Create a downloader for the private storage zone.
     */
    public static function private(): self
    {
        return new self(zone: 'private');
    }
}
